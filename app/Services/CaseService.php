<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Helpers\CacheHelper;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\CaseStatusUpdated;
use App\Notifications\CaseUpdated;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaseService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly PhilippineAddressService $addressService,
        private readonly CaseEventRecorder $eventRecorder,
        private readonly CaseNumberGenerator $caseNumbers,
        private readonly ClientProfileService $clientProfiles,
    ) {}

    public function createCase(array $data, string $userId): CaseFile
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->createCaseInternal($data, $userId);
            } catch (QueryException $e) {
                // Retry on unique constraint violation (case_number or tracker_number collision)
                if ($e->getCode() === '23505' && $attempt < $maxAttempts) {
                    continue;
                }

                throw $e;
            }
        }

        // Should never reach here
        throw new \RuntimeException('Failed to create case after '.$maxAttempts.' attempts.');
    }

    private function createCaseInternal(array $data, string $userId): CaseFile
    {
        return DB::transaction(function () use ($data, $userId) {
            $categoryMutation = $this->resolveCategoryMutation($data);
            $categoryIds = $categoryMutation['ids'] ?? [];
            $this->lockActiveCategories($categoryIds);

            $filer = User::with('client')->findOrFail($userId);
            $client = $this->resolveFilingClient($data, $filer);

            $case = CaseFile::create([
                'case_number' => $this->generateCaseNumber(),
                'tracker_number' => $this->generateTrackerNumber(),
                'vulnerability_indicator' => $data['vulnerability_indicator'] ?? null,
                'nok_vulnerability_indicator' => $data['nok_vulnerability_indicator'] ?? null,
                'summary' => $data['summary'] ?? null,
                'status' => CaseFile::STATUS_PENDING_REVIEW,
                'consent_given_at' => ! empty($data['consent']) ? now() : null,
                'user_id' => $userId,
                'client_id' => $client->id,
                'category_id' => $this->primaryCategoryId($categoryIds, null, $categoryMutation['legacy'] ?? false),
                'case_issue_id' => $data['case_issue_id'] ?? null,
            ]);
            $this->syncCaseCategories($case, $categoryIds);

            $this->clientProfiles->syncAddress($client, $data['address'] ?? null);
            $this->clientProfiles->syncEmployment($client, $data['employment'] ?? null);
            $this->clientProfiles->syncNextOfKin($client, $data['next_of_kin'] ?? null);

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category', 'categories', 'caseIssue']);
        });
    }

    /**
     * Resolve which client a new case belongs to. OFW filers always file for
     * their own linked client — any selected_client_id/new_ofw they send is
     * ignored so one OFW cannot file against another's record (IDOR).
     * NEXT_OF_KIN filers may pick an OFW linked to them via ofw_client_id or
     * inline-create one (linked back below). Staff keep the full picker.
     */
    private function resolveFilingClient(array $data, User $filer): Client
    {
        // NOK filers share users.role=OFW (role lands on clients.type), so
        // exclude them from the OFW self-file shortcut — they file via
        // selected_client_id/new_ofw below.
        if ($filer->isOfw() && ! $filer->isNextOfKin()) {
            if ($filer->client) {
                return $filer->client;
            }

            throw ValidationException::withMessages([
                'client' => 'No client to file for. Select a client or provide new OFW details.',
            ]);
        }

        if (! empty($data['selected_client_id'])) {
            $picked = Client::findOrFail($data['selected_client_id']);

            if ($filer->isNextOfKin()) {
                abort_unless(
                    $filer->client && $picked->ofw_client_id === $filer->client->id && $picked->type === 'OFW',
                    403,
                    'You may only file for an OFW linked to your account.'
                );
            }

            return $picked;
        }

        if (! empty($data['new_ofw'])) {
            $client = $this->clientProfiles->createClient($data['new_ofw'], null, 'OFW');

            // Inline OFW filed by a NEXT_OF_KIN filer: link back to the filer's
            // own client so the relationship survives without a user row.
            if ($filer->isNextOfKin() && $filer->client) {
                $client->ofw_client_id = $filer->client->id;
                $client->save();
            }

            return $client;
        }

        if ($filer->client) {
            return $filer->client;
        }

        throw ValidationException::withMessages([
            'client' => 'No client to file for. Select a client or provide new OFW details.',
        ]);
    }

    /**
     * OFW clients linked to a NEXT_OF_KIN filer's own client via ofw_client_id.
     * Feeds the scoped NOK dropdown — no broad PII search needed.
     *
     * @return Collection<int, Client>
     */
    public function linkedOfwClients(User $filer)
    {
        if (! $filer->client) {
            return collect();
        }

        return Client::where('is_deleted', false)
            ->where('type', 'OFW')
            ->where('ofw_client_id', $filer->client->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'email']);
    }

    /**
     * Cases awaiting CM review: account-first filings not yet approved.
     */
    public function getReviewQueue(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CaseFile::with('client', 'category', 'categories')
            ->where('status', CaseFile::STATUS_PENDING_REVIEW)
            ->where('is_deleted', false);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'ilike', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"]);
                    });
            });
        }

        return $query->orderBy('created_at', 'asc')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function getReviewQueueCount(): int
    {
        return CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)
            ->where('is_deleted', false)
            ->count();
    }

    /**
     * Approve a pending filing: stamp the reviewer and open the case.
     */
    public function approveCase(string $id, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $userId) {
            $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)
                ->lockForUpdate()
                ->findOrFail($id);

            $case->update([
                'status' => 'OPEN',
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            $this->eventRecorder->caseOpened($case, $userId);

            AuditLog::create([
                'action' => AuditAction::PUBLISH->value,
                'module' => AuditModule::CASE->value,
                'entity_id' => $case->id,
                'description' => 'Case '.$case->case_number.' approved'.(! empty($case->summary) ? ' — '.$case->summary : ''),
                'old_value' => ['status' => CaseFile::STATUS_PENDING_REVIEW],
                'new_value' => ['status' => 'OPEN'],
                'user_id' => $userId,
                'timestamp' => now(),
            ]);

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category', 'categories', 'caseIssue']);
        });
    }

    /**
     * Reject a pending filing: soft-delete with a reason. Kept on CaseService
     * (not a generic delete) so the review outcome stays auditable.
     */
    public function rejectCase(string $id, string $reason, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $reason, $userId) {
            $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)
                ->findOrFail($id);

            $case->deletion_reason = $reason;
            $case->deleted_by = $userId;
            $case->save();
            $case->delete();

            AuditLog::create([
                'action' => AuditAction::DELETE->value,
                'module' => AuditModule::CASE->value,
                'entity_id' => $case->id,
                'description' => "Filing {$case->case_number} rejected — {$reason}",
                'old_value' => ['status' => CaseFile::STATUS_PENDING_REVIEW],
                'new_value' => ['is_deleted' => true, 'deletion_reason' => $reason],
                'user_id' => $userId,
                'timestamp' => now(),
            ]);

            return $case;
        });
    }

    private function resolveCategoryMutation(array $data): ?array
    {
        $hasScalar = array_key_exists('category_id', $data);
        $hasArray = array_key_exists('category_ids', $data);

        if ($hasScalar && $hasArray) {
            throw ValidationException::withMessages(['category_ids' => 'Use either category_id or category_ids, not both.']);
        }

        if (! $hasScalar && ! $hasArray) {
            return null;
        }

        $ids = $hasArray ? ($data['category_ids'] ?? []) : (($data['category_id'] ?? null) ? [$data['category_id']] : []);
        $ids = array_values(array_unique(array_filter($ids)));

        return ['ids' => $ids, 'legacy' => $hasScalar];
    }

    private function lockActiveCategories(array $ids): void
    {
        $categories = CaseCategory::whereIn('id', $ids)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')->lockForUpdate()->get();
        if ($categories->count() !== count($ids)) {
            throw ValidationException::withMessages(['category_ids' => 'All selected categories must be active.']);
        }
    }

    private function syncCaseCategories(CaseFile $case, array $categoryIds): void
    {
        $changes = $case->categories()->sync($categoryIds);

        if ($case->status !== 'DRAFT' && array_filter($changes, fn ($items) => ! empty($items))) {
            $this->invalidateCategoryCaches($case);
        }
    }

    private function invalidateCategoryCaches(CaseFile $case): void
    {
        Cache::forget('stats:cases');
        Cache::forget('dashboard:cm_cases_by_category');
        Cache::forget('dashboard:admin_cases_by_category');
        ReportsService::invalidateAll();
    }

    private function recordCategoryMutation(
        CaseFile $case,
        array $oldCategoryIds,
        array $newCategoryIds,
        string $userId,
        string $action = AuditAction::UPDATE->value,
        bool $force = false,
    ): void {
        $oldCategoryIds = $this->sortedCategoryIds($oldCategoryIds);
        $newCategoryIds = $this->sortedCategoryIds($newCategoryIds);

        if (! $force && $oldCategoryIds === $newCategoryIds) {
            return;
        }

        AuditLog::create([
            'action' => $action,
            'module' => AuditModule::CASE->value,
            'entity_id' => $case->id,
            'description' => 'Case category assignments changed.',
            'old_value' => ['category_ids' => $oldCategoryIds],
            'new_value' => ['category_ids' => $newCategoryIds],
            'user_id' => $userId,
            'timestamp' => now(),
        ]);
    }

    private function sortedCategoryIds(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter($categoryIds)));
        sort($categoryIds, SORT_STRING);

        return $categoryIds;
    }

    private function primaryCategoryId(array $ids, ?string $current, bool $legacy): ?string
    {
        if (empty($ids)) {
            return null;
        }
        if ($legacy) {
            return $ids[0];
        }
        if ($current !== null && in_array($current, $ids, true)) {
            return $current;
        }

        return CaseCategory::whereIn('id', $ids)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')->value('id');
    }

    private function revalidatePublishedCategories(CaseFile $case): void
    {
        $ids = $case->categories()->pluck('case_categories.id')->all();
        if (empty($ids)) {
            throw ValidationException::withMessages(['category_ids' => 'At least one active category is required to publish.']);
        }
        $this->lockActiveCategories($ids);
        if (! in_array($case->category_id, $ids, true)) {
            $case->category_id = $this->primaryCategoryId($ids, $case->category_id, false);
            $case->save();
        }
    }

    public function getCases(array $filters = [], string $sort = 'created_at', string $direction = 'desc', int $perPage = 15)
    {
        $sortMap = [
            'case_number' => 'case_number',
            'tracker_number' => 'tracker_number',
            'status' => 'status',
            'created_at' => 'created_at',
        ];
        $sortColumn = $sortMap[$sort] ?? 'created_at';

        $query = CaseFile::with(['client', 'user', 'category', 'categories', 'caseIssue', 'referrals.agency', 'referrals.milestones']);

        $query->where('status', '!=', 'DRAFT');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereNotIn('status', ['ARCHIVED']);
        }

        // Role-based scoping — restrict which cases the current user can see
        /** @var User|null $user */
        $user = auth()->user();
        if ($user && $user->role !== UserRole::ADMIN->value && $user->role !== UserRole::CASE_MANAGER->value) {
            $query->where(function ($q) use ($user) {
                if ($user->role === UserRole::AGENCY->value) {
                    if ($user->agcy_id) {
                        $q->whereHas('referrals', function ($rq) use ($user) {
                            $rq->where('agcy_id', $user->agcy_id);
                        });
                    } else {
                        $q->whereRaw('1=0'); // No agency assigned — see nothing
                    }
                }
            });
        } elseif ($user && in_array($user->role, [UserRole::ADMIN->value, UserRole::CASE_MANAGER->value]) && ! empty($filters['user_id'])) {
            // ADMIN and CASE_MANAGER can filter by user_id
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['vulnerability_indicator'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('vulnerability_indicator', 'LIKE', "%{$filters['vulnerability_indicator']}%")
                    ->orWhere('nok_vulnerability_indicator', 'LIKE', "%{$filters['vulnerability_indicator']}%");
            });
        }

        if (! empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereIn('category_id', $filters['category_ids'])
                    ->orWhereHas('categories', function ($categoryQuery) use ($filters) {
                        $categoryQuery->whereIn('case_categories.id', $filters['category_ids']);
                    });
            });
        } elseif (! empty($filters['category_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id'])
                    ->orWhereHas('categories', function ($categoryQuery) use ($filters) {
                        $categoryQuery->where('case_categories.id', $filters['category_id']);
                    });
            });
        }

        if (! empty($filters['case_issue_id'])) {
            $query->where('case_issue_id', $filters['case_issue_id']);
        }

        if (! empty($filters['agcy_id'])) {
            $query->whereHas('referrals', function ($q) use ($filters) {
                $q->where('agcy_id', $filters['agcy_id']);
            });
        }

        if (! empty($filters['age_min_days']) && is_numeric($filters['age_min_days'])) {
            $query->where('created_at', '<=', now()->subDays((int) $filters['age_min_days']));
        }

        if (($filters['referral_state'] ?? null) === 'none') {
            $query->whereDoesntHave('referrals', function ($q) {
                $q->where('is_deleted', false);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'ilike', "%{$search}%")
                    ->orWhere('tracker_number', 'ilike', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('middle_name', 'ilike', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"])
                            ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) ILIKE ?", ["%{$search}%"]);
                    });
            });
        }

        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';
        $query->orderBy($sortColumn, $direction)->orderBy('id', $direction);

        return $query->paginate(min(max($perPage, 10), 100));
    }

    public function getCase(string $id): CaseFile
    {
        return CaseFile::with([
            'client.addresses',
            'client.employments',
            'client.nextOfKin',
            'referrals.milestones',
            'referrals.agency',
            'referrals.attachments.user',
            'user',
            'category',
            'categories',
            'caseIssue',
            'documents' => fn ($q) => $q->where('is_deleted', false),
        ])->findOrFail($id);
    }

    public function updateCase(string $id, array $data, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $case = CaseFile::lockForUpdate()->findOrFail($id);
            $categoryMutation = $this->resolveCategoryMutation($data);
            $categoryIds = $categoryMutation['ids'] ?? [];
            $oldCategoryIds = $case->categories()->pluck('case_categories.id')->all();
            $newStatus = $data['status'] ?? $case->status;
            if ($categoryMutation !== null && empty($categoryIds) && $newStatus !== 'DRAFT') {
                throw ValidationException::withMessages(['category_ids' => 'At least one category is required.']);
            }
            if ($categoryMutation !== null) {
                $this->lockActiveCategories($categoryIds);
            }
            $old = $case->toArray();
            $oldStatus = $case->status;

            if ($newStatus !== $case->status && in_array($newStatus, ['CLOSED', 'ARCHIVED'], true)) {
                $canClose = $this->canClose($id);
                if (! $canClose['can_close']) {
                    abort(422, $canClose['reason']);
                }
            }

            $updateData = [
                'status' => $newStatus,
                'vulnerability_indicator' => $data['vulnerability_indicator'] ?? null,
                'nok_vulnerability_indicator' => $data['nok_vulnerability_indicator'] ?? null,
                'summary' => $data['summary'] ?? null,
                'category_id' => $categoryMutation === null ? $case->category_id : $this->primaryCategoryId($categoryIds, $case->category_id, $categoryMutation['legacy']),
                'case_issue_id' => $data['case_issue_id'] ?? $case->case_issue_id,
            ];

            if ($newStatus === 'CLOSED' && $case->status !== 'CLOSED') {
                $updateData['closed_at'] = now();
            } elseif ($newStatus === 'ARCHIVED' && $case->status !== 'CLOSED') {
                $updateData['closed_at'] = $case->closed_at ?? now();
            } elseif ($newStatus === 'OPEN' && $case->status === 'CLOSED') {
                $updateData['closed_at'] = null;
            }

            $case->update($updateData);

            if ($categoryMutation !== null) {
                $this->syncCaseCategories($case, $categoryIds);
                $this->recordCategoryMutation($case, $oldCategoryIds, $categoryIds, $userId);
            }

            // Audit logging is handled by AuditObserver::updated() — no manual log needed.

            if ($case->wasChanged('status')) {
                // Case edits can close or reopen a case just like toggleCaseStatus —
                // record the same client-facing events. (Archive transitions are
                // administrative and intentionally not client-visible.)
                if ($case->status === 'CLOSED') {
                    $this->eventRecorder->caseClosed($case, $userId);
                } elseif ($case->status === 'OPEN' && $oldStatus === 'CLOSED') {
                    $this->eventRecorder->caseReopened($case, $userId);
                }
            }

            $updateCase = $case;
            $updateOld = $old;
            $updateUserId = $userId;
            $updateOldStatus = $oldStatus;
            $updateStatusChanged = $case->wasChanged('status');
            DB::afterCommit(function () use ($updateCase, $updateOld, $updateUserId, $updateOldStatus, $updateStatusChanged) {
                try {
                    if ($updateStatusChanged) {
                        $this->dispatchStatusChangeNotification($updateCase, $updateOldStatus ?? 'UNKNOWN', $updateCase->status, $updateUserId);
                    }
                    $this->dispatchCaseUpdateNotification($updateCase, $updateOld, $updateUserId);
                } catch (\Throwable $e) {
                    report($e, ['context' => 'Failed to send case update notification']);
                }
            });

            return $case->load([
                'client.addresses',
                'client.employments',
                'client.nextOfKin',
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
                'category',
                'categories',
                'caseIssue',
            ]);
        });
    }

    public function archiveCase(string $id, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id) {
            $case = CaseFile::findOrFail($id);

            abort_unless($case->status === 'CLOSED', 422, 'Only closed cases can be archived.');

            $canClose = $this->canClose($id);
            abort_unless($canClose['can_close'], 422, $canClose['reason']);

            $old = $case->toArray();

            $case->update([
                'status' => 'ARCHIVED',
            ]);

            // Audit logging is handled by AuditObserver::updated() — no manual log needed.

            return $case->load([
                'client.addresses',
                'client.employments',
                'client.nextOfKin',
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
                'category',
                'categories',
                'caseIssue',
            ]);
        });
    }

    public function unarchiveCase(string $id, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id) {
            $case = CaseFile::findOrFail($id);

            abort_unless($case->status === 'ARCHIVED', 422, 'Only archived cases can be restored.');

            $old = $case->toArray();

            // Restore to the previous (pre-archive) state. Cases can only be
            // archived from CLOSED, so unarchiving returns them to CLOSED and
            // preserves the original closed_at timestamp (CASE-032).
            $case->update([
                'status' => 'CLOSED',
                'closed_at' => $case->closed_at ?? now(),
            ]);

            // Audit logging is handled by AuditObserver::updated() — no manual log needed.

            return $case->load([
                'client.addresses',
                'client.employments',
                'client.nextOfKin',
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
                'category',
                'categories',
                'caseIssue',
            ]);
        });
    }

    public function canClose(string $id): array
    {
        $case = CaseFile::findOrFail($id);

        $pendingReferrals = $case->referrals()
            ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
            ->count();

        if ($pendingReferrals > 0) {
            return [
                'can_close' => false,
                'reason' => "Cannot close case: {$pendingReferrals} referral(s) still pending or in progress.",
            ];
        }

        return [
            'can_close' => true,
            'reason' => null,
        ];
    }

    public function toggleCaseStatus(string $id, string $userId): CaseFile
    {
        $case = DB::transaction(function () use ($id, $userId) {
            $case = CaseFile::findOrFail($id);
            $old = $case->toArray();

            // The toggle only flips OPEN ↔ CLOSED. Drafts are published through
            // the dedicated publish flow, and archived cases are restored via
            // unarchive — silently "reopening" either here would skip those paths.
            if (! in_array($case->status, ['OPEN', 'CLOSED'], true)) {
                abort(422, 'Only open or closed cases can be reopened or closed.');
            }

            if ($case->status === 'OPEN') {
                $canClose = $this->canClose($id);
                if (! $canClose['can_close']) {
                    abort(422, $canClose['reason']);
                }
            }

            $oldStatus = $case->status;
            $case->update([
                'status' => $case->status === 'OPEN' ? 'CLOSED' : 'OPEN',
            ]);

            if ($case->wasChanged('status') && $case->status === 'CLOSED') {
                $case->update(['closed_at' => now()]);
            }

            if ($case->status === 'CLOSED') {
                $this->eventRecorder->caseClosed($case, $userId);
            } else {
                $this->eventRecorder->caseReopened($case, $userId);
            }

            // Audit logging is handled by AuditObserver::updated() — no manual log needed.

            DB::afterCommit(function () use ($case, $oldStatus, $userId) {
                try {
                    $this->dispatchStatusChangeNotification($case, $oldStatus, $case->status, $userId);
                } catch (\Throwable $e) {
                    report($e, ['context' => 'Failed to send status change notification']);
                }
            });

            return $case->load([
                'client.addresses',
                'client.employments',
                'client.nextOfKin',
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
                'category',
                'categories',
                'caseIssue',
            ]);
        });

        return $case;
    }

    private function dispatchCaseUpdateNotification(CaseFile $case, array $oldData, string $userId): void
    {
        $user = User::find($userId);
        $updatedBy = $user?->name ?? 'Unknown';
        $case->loadMissing('client.nextOfKin');

        $changes = [];
        $meaningfulFields = ['vulnerability_indicator', 'nok_vulnerability_indicator', 'summary'];

        foreach ($meaningfulFields as $field) {
            $oldVal = $oldData[$field] ?? null;
            $newVal = $case->$field ?? null;

            if ($oldVal !== $newVal) {
                $changes[$field] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        if (empty($changes)) {
            return;
        }

        $caseManager = User::find($case->user_id);
        $notifyUsers = $caseManager ? [$caseManager] : [];

        $clientEmail = $this->resolveClientNotificationEmail($case) ?? '';

        // Staff notification keeps the actor and field-level diff (internal).
        $this->notificationService->notifyUsers($notifyUsers, new CaseUpdated($case, $updatedBy, $changes));

        // The client-visible record is shipped on the public tracking payload —
        // it must carry no staff names and no field-level change data.
        $this->notificationService->notifyOfw(
            $case,
            $clientEmail,
            'case_updated',
            'Case Updated',
            "The details of case #{$case->case_number} have been updated by the Bayanihan team.",
            [],
            route('cases.show', $case->id),
        );
    }

    private function dispatchStatusChangeNotification(CaseFile $case, string $oldStatus, string $newStatus, string $userId): void
    {
        $user = User::find($userId);
        $updatedBy = $user?->name ?? 'Unknown';
        $case->loadMissing('client.nextOfKin');

        $caseManager = User::find($case->user_id);
        $notifyUsers = $caseManager ? [$caseManager] : [];

        $clientEmail = $this->resolveClientNotificationEmail($case) ?? '';

        $this->notificationService->notifyAll(
            $case,
            $notifyUsers,
            $clientEmail,
            new CaseStatusUpdated($case, $oldStatus, $newStatus, $updatedBy),
            'case_status_updated',
            "Your case is now {$this->ofwCaseStatusLabel($newStatus)}",
            $this->ofwCaseStatusMessage($case, $newStatus),
            ['old_status' => $oldStatus, 'new_status' => $newStatus],
            route('cases.show', $case->id),
        );
    }

    /**
     * Client-facing label for a case status. Plain language only —
     * internal status codes never reach the client.
     */
    private function ofwCaseStatusLabel(string $status): string
    {
        return match (strtoupper($status)) {
            'OPEN' => 'in progress',
            'CLOSED' => 'resolved',
            'ARCHIVED' => 'archived',
            default => 'updated',
        };
    }

    /**
     * Client-facing body for a case status change. Plain language only —
     * internal status codes never reach the client.
     */
    private function ofwCaseStatusMessage(CaseFile $case, string $status): string
    {
        $caseNumber = $case->case_number ?? '';

        return match (strtoupper($status)) {
            'OPEN' => "Your case {$caseNumber} is now in progress. Your case manager is coordinating the help you need.",
            'CLOSED' => "Your case {$caseNumber} has been resolved. Thank you for trusting the Bayanihan team.",
            'ARCHIVED' => "Your case {$caseNumber} has been filed for safekeeping. Contact us if you need help again.",
            default => "There is an update on your case {$caseNumber}. Open your case to read the details.",
        };
    }

    private function resolveClientNotificationEmail(CaseFile $case): ?string
    {
        return $case->client?->email ?? $case->user?->email;
    }

    /**
     * Both identifiers come from CaseNumberGenerator: allocation is one atomic
     * statement against case_number_counters, so there is no read-modify-write
     * to protect, and a hard-purged case can no longer hand its number to a
     * later one.
     */
    private function generateCaseNumber(): string
    {
        return $this->caseNumbers->nextCaseNumber();
    }

    private function generateTrackerNumber(): string
    {
        return $this->caseNumbers->nextTrackerNumber();
    }

    public function getCaseStats(?User $user = null): array
    {
        // AGENCY users see only their agency's cases; ADMIN and CASE_MANAGER see all.
        // Cache key is role+ID for AGENCY (each agency sees different data), global for others.
        $isScoped = $user && $user->role === UserRole::AGENCY->value;
        $cacheKey = $isScoped ? 'stats:cases:AGENCY:'.$user->id : 'stats:cases';

        return CacheHelper::safeRemember($cacheKey, 120, function () use ($user) {
            // Build the scoped case query — same role logic as getCases()
            // Qualify is_deleted to avoid ambiguity when joining case_categories (which also has is_deleted via SoftDeleteFlag)
            $caseQuery = CaseFile::where('cases.is_deleted', false);

            if ($user && $user->role === UserRole::AGENCY->value) {
                if ($user->agcy_id) {
                    $caseQuery->whereHas('referrals', function ($rq) use ($user) {
                        $rq->where('agcy_id', $user->agcy_id);
                    });
                } else {
                    $caseQuery->whereRaw('1=0');
                }
            }

            // Aggregate counts via PostgreSQL FILTER clauses. Client-type
            // counts now read clients.type through the case's client, since
            // cases no longer carry client_type.
            $counts = (clone $caseQuery)->selectRaw("
                    COUNT(*) FILTER (WHERE status NOT IN ('DRAFT','ARCHIVED')) AS active,
                    COUNT(*) FILTER (WHERE status = 'OPEN') AS open,
                    COUNT(*) FILTER (WHERE status = 'CLOSED') AS closed,
                    COUNT(*) FILTER (WHERE status = 'ARCHIVED') AS archived,
                    COUNT(*) FILTER (WHERE status = 'PENDING_REVIEW') AS pending_review
                ")->first();

            // Total referrals — ADMIN and CASE_MANAGER see all; AGENCY sees own only
            $totalReferrals = match (true) {
                ! $user || $user->role === UserRole::ADMIN->value || $user->role === UserRole::CASE_MANAGER->value => Referral::count(),
                $user->role === UserRole::AGENCY->value => $user->agcy_id ? Referral::where('agcy_id', $user->agcy_id)->count() : 0,
                default => Referral::count(),
            };

            // Category breakdown — scoped
            $categoryBreakdown = (clone $caseQuery)
                ->join('case_category', 'cases.id', '=', 'case_category.case_id')
                ->join('case_categories as pivot_categories', 'case_category.case_category_id', '=', 'pivot_categories.id')
                ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
                ->where('cases.is_deleted', false)
                ->select('pivot_categories.id', 'pivot_categories.name', 'pivot_categories.color', DB::raw('COUNT(DISTINCT cases.id) as count'))
                ->groupBy('pivot_categories.id', 'pivot_categories.name', 'pivot_categories.color')
                ->orderBy('count', 'desc')
                ->get();

            return [
                'total_cases' => (int) $counts->active,
                'open_cases' => (int) $counts->open,
                'closed_cases' => (int) $counts->closed,
                'archived_cases' => (int) $counts->archived,
                'pending_review_cases' => (int) $counts->pending_review,
                'total_referrals' => $totalReferrals,
                'category_breakdown' => $categoryBreakdown,
            ];
        });
    }

    /**
     * Client directory stats (Task 2.2).
     *
     * Eloquent/query-builder replacement for the two DB::selectOne raw queries
     * previously in ClientController::getClientStats. Same result shape:
     * total/ofw/nok clients, per-marker vulnerability counts, open-case
     * client count, and total referrals. All constraints are bound parameters.
     */
    public function getClientDirectoryStats(?User $user): array
    {
        if ($user?->isAgency() && ! $user->agcy_id) {
            return [
                'total_clients' => 0,
                'ofw_clients' => 0,
                'nok_clients' => 0,
                'vulnerability_counts' => ['PWD' => 0, 'Senior Citizen' => 0, 'Solo Parent' => 0, 'Indigenous Person' => 0],
                'clients_with_open_cases' => 0,
                'total_referrals' => 0,
            ];
        }

        $agencyId = ($user?->isAgency() && $user->agcy_id) ? $user->agcy_id : null;

        $totalClients = $user?->isAdmin()
            ? Client::where('is_deleted', false)
                // Same rule as ClientController@index: at least one live
                // non-DRAFT case, or the tile counts clients the listing hides.
                ->whereHas('caseFiles', function ($q) {
                    $q->where('is_deleted', false)
                        ->whereNull('cases.deleted_at')
                        ->where('status', '!=', 'DRAFT');
                })
                ->count()
            : $this->countDistinctClientsWithCases($agencyId, function ($q) {
                // A live draft alone does not establish a client either (the
                // caseFiles whereHas already rules out caseless clients).
                $q->where('cases.status', '!=', 'DRAFT');
            });

        $ofwClients = $this->countDistinctClientsWithCases($agencyId, function ($q) {
            $q->withVisibleStatus()->whereHas('client', fn ($c) => $c->where('type', 'OFW'));
        });

        $nokClients = $this->countDistinctClientsWithCases($agencyId, function ($q) {
            $q->withVisibleStatus()->whereHas('client', fn ($c) => $c->where('type', 'NEXT_OF_KIN'));
        });

        $vulnerabilityCounts = [];
        foreach (['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'] as $marker) {
            $vulnerabilityCounts[$marker] = $this->countDistinctClientsWithCases($agencyId, function ($q) use ($marker) {
                $q->withVisibleStatus()->withVulnerabilityMarker($marker);
            });
        }

        $clientsWithOpenCases = $this->countDistinctClientsWithCases($agencyId, function ($q) {
            $q->withOpenStatus();
        });

        $totalReferrals = Referral::where('is_deleted', false)
            ->whereHas('caseFile', function ($q) {
                $q->live();
            })
            ->when($agencyId, function ($q) use ($agencyId) {
                $q->where('agcy_id', $agencyId);
            })
            ->count();

        return [
            'total_clients' => (int) $totalClients,
            'ofw_clients' => (int) $ofwClients,
            'nok_clients' => (int) $nokClients,
            'vulnerability_counts' => [
                'PWD' => (int) $vulnerabilityCounts['PWD'],
                'Senior Citizen' => (int) $vulnerabilityCounts['Senior Citizen'],
                'Solo Parent' => (int) $vulnerabilityCounts['Solo Parent'],
                'Indigenous Person' => (int) $vulnerabilityCounts['Indigenous Person'],
            ],
            'clients_with_open_cases' => (int) $clientsWithOpenCases,
            'total_referrals' => (int) $totalReferrals,
        ];
    }

    /**
     * Distinct clients having at least one live case matching the given
     * constraints (plus agency scope). Equivalent to
     * COUNT(DISTINCT CASE WHEN ... THEN cl.id END) over the clients/cases join.
     */
    private function countDistinctClientsWithCases(?string $agencyId, callable $constraints, ?callable $clientConstraints = null): int
    {
        $clients = Client::where('clients.is_deleted', false);

        if ($clientConstraints !== null) {
            $clientConstraints($clients);
        }

        return $clients
            ->whereHas('caseFiles', function ($q) use ($agencyId, $constraints) {
                $q->live()->forAgency($agencyId);
                $constraints($q);
            })
            ->count();
    }

    // -------------------------------------------------------------------------
    // Soft-delete (Trash) methods
    // -------------------------------------------------------------------------

    /**
     * Soft-delete an archived case with a mandatory reason.
     *
     * Only ARCHIVED cases may be deleted. The case and all cascaded child
     * records (referrals → comments, attachments; documents) are soft-deleted
     * via the CascadeSoftDeletes trait on the model. The deletion reason is
     * stored on the case row and in the audit log.
     *
     * @throws HttpResponseException 422 if not ARCHIVED
     */
    public function deleteArchivedCase(CaseFile $case, string $reason, string $userId): void
    {
        abort_unless($case->status === 'ARCHIVED', 422, 'Only archived cases can be deleted.');

        DB::transaction(function () use ($case, $reason, $userId) {
            // Store reason directly on the row before soft-delete so it
            // persists even if only the trashed record is inspected.
            $case->deletion_reason = $reason;
            $case->saveQuietly();

            // Trigger soft-delete cascade (CascadeSoftDeletes fires on the
            // SoftDeletes-powered delete() call).
            $case->delete();

            // Explicit audit entry with the deletion reason for COA compliance.
            // AuditObserver::deleted() also fires, but that captures field diffs;
            // this entry captures the human-provided reason.
            AuditLog::create([
                'action' => AuditAction::DELETE->value,
                'module' => AuditModule::CASE->value,
                'entity_id' => $case->id,
                'description' => "Case {$case->case_number} deleted — {$reason}",
                'old_value' => ['status' => $case->status, 'case_number' => $case->case_number],
                'new_value' => ['is_deleted' => true, 'deletion_reason' => $reason],
                'user_id' => $userId,
                'timestamp' => now(),
            ]);
        });
    }

    /**
     * Restore a soft-deleted case back to ARCHIVED status.
     *
     * Cascaded restore is triggered by the CascadeSoftDeletes trait on restore().
     */
    public function restoreTrashedCase(CaseFile $case, string $userId): CaseFile
    {
        return DB::transaction(function () use ($case, $userId) {
            // Restore case (and cascaded referrals/documents via CascadeSoftDeletes)
            $case->restore();

            // Ensure case returns to ARCHIVED, not some other status
            $case->update(['status' => 'ARCHIVED', 'deletion_reason' => null]);

            AuditLog::create([
                'action' => AuditAction::RESTORE->value,
                'module' => AuditModule::CASE->value,
                'entity_id' => $case->id,
                'description' => "Case {$case->case_number} restored from trash",
                'old_value' => ['is_deleted' => true],
                'new_value' => ['is_deleted' => false, 'status' => 'ARCHIVED'],
                'user_id' => $userId,
                'timestamp' => now(),
            ]);

            return $case->load([
                'client.addresses',
                'client.employments',
                'client.nextOfKin',
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
                'category',
                'categories',
                'caseIssue',
            ]);
        });
    }

    /**
     * Paginated list of soft-deleted cases for the trash view.
     *
     * CASE_MANAGER role sees only their own trashed cases.
     * ADMIN sees all.
     */
    public function getTrashedCases(array $filters = [], ?User $user = null): LengthAwarePaginator
    {
        $query = CaseFile::onlyTrashed()
            ->with(['client', 'user'])
            ->orderBy('deleted_at', 'desc');

        // Role-based ownership filter
        if ($user && $user->isCaseManager()) {
            $query->where('user_id', $user->id);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'ilike', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"]);
                    });
            });
        }

        $perPage = isset($filters['per_page']) ? min(max((int) $filters['per_page'], 10), 100) : 15;

        return $query->paginate($perPage);
    }

    /**
     * Permanently delete soft-deleted cases older than the retention period.
     *
     * Called by the PurgeTrashedCases artisan command. Returns the count of
     * purged cases.
     */
    public function purgeTrashedCases(int $retentionDays): int
    {
        $cutoff = now()->subDays($retentionDays);

        $cases = CaseFile::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->get();

        $count = $cases->count();

        if ($count === 0) {
            return 0;
        }

        DB::transaction(function () use ($cases, $cutoff, $count) {
            $ids = $cases->pluck('id')->all();

            // case_events has no soft-delete flag and its FK is RESTRICT, so it cannot go through
            // $cascadeSoftDeletes (CaseEvent is append-only; its deleting hook throws).
            // Limit: event rows go without per-row audit, and referrals with request access-links can still hit RESTRICT.
            DB::table('case_events')->whereIn('case_id', $ids)->delete();

            foreach ($cases as $case) {
                // CascadeSoftDeletes handles child force-deletes when forceDelete() is called.
                $case->forceDelete();
            }

            // Audit the purge batch
            AuditLog::create([
                'action' => AuditAction::PURGE->value,
                'module' => AuditModule::CASE->value,
                'entity_id' => null,
                'description' => "Auto-purged {$count} case(s) with deleted_at before {$cutoff->toDateString()}",
                'old_value' => ['purged_count' => $count, 'cutoff_date' => $cutoff->toDateString()],
                'new_value' => null,
                'user_id' => null,
                'timestamp' => now(),
            ]);
        });

        return $count;
    }
}
