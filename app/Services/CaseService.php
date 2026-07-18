<?php

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\CaseStatusUpdated;
use App\Notifications\CaseUpdated;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaseService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ReferralService $referralService,
        private readonly CaseEventRecorder $eventRecorder,
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

            $createData = [
                'case_number' => $this->generateCaseNumber(),
                'tracker_number' => $this->generateTrackerNumber(),
                'client_type' => $data['client_type'],
                'vulnerability_indicator' => $data['vulnerability_indicator'] ?? null,
                'summary' => $data['summary'] ?? null,
                'status' => 'OPEN',
                'consent_given_at' => ! empty($data['consent']) ? now() : null,
                'user_id' => $userId,
                'category_id' => $this->primaryCategoryId($categoryIds, null, $categoryMutation['legacy'] ?? false),
                'case_issue_id' => $data['case_issue_id'] ?? null,
            ];

            $case = CaseFile::create($createData);
            $this->syncCaseCategories($case, $categoryIds);

            if (! empty($data['selected_client_id'])) {
                $client = $this->activeClientOrFail($data['selected_client_id']);
                $this->assertSelectedNokBelongsToClient($client, $data['selected_nok_id'] ?? null);
                $case->client_id = $client->id;
                $case->save();
            }

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category', 'categories', 'caseIssue']);
        });
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if ($value instanceof \DateTimeInterface) {
            return false;
        }

        return trim((string) $value) === '';
    }

    private function resolveClientNotificationEmail(CaseFile $case): ?string
    {
        if ($case->client_id) {
            return app(CaseRecipientResolver::class)->resolve($case);
        }

        if ($case->client_type === 'NEXT_OF_KIN') {
            return null;
        }

        return $case->client?->email ?? null;
    }

    private function activeClientOrFail(?string $clientId): ?Client
    {
        if (empty($clientId)) {
            return null;
        }

        $client = Client::whereKey($clientId)
            ->where('is_deleted', false)
            ->first();

        if (! $client) {
            throw ValidationException::withMessages([
                'selected_client_id' => 'The selected client is no longer active.',
            ]);
        }

        return $client;
    }

    private function assertSelectedNokBelongsToClient(?Client $client, ?string $selectedNokId): void
    {
        if (! $client || empty($selectedNokId)) {
            return;
        }

        $belongsToClient = $client->nextOfKin()
            ->where('is_deleted', false)
            ->whereKey($selectedNokId)
            ->exists();

        if (! $belongsToClient) {
            throw ValidationException::withMessages([
                'selected_nok_id' => 'The selected next of kin does not belong to the selected active client.',
            ]);
        }
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

        if (array_filter($changes, fn ($items) => ! empty($items))) {
            $this->invalidateCategoryCaches($case);
        }
    }

    private function invalidateCategoryCaches(CaseFile $case): void
    {
        Cache::forget('stats:cases');
        Cache::forget('dashboard:cm_cases_by_category');
        Cache::forget('dashboard:admin_cases_by_category');
        Cache::forget('tracking:data:'.$case->id);
        ReportsService::invalidateAll();
    }

    private function recordCategoryMutation(
        CaseFile $case,
        array $oldCategoryIds,
        array $newCategoryIds,
        string $userId,
        string $action = 'UPDATE',
        bool $force = false,
    ): void {
        $oldCategoryIds = $this->sortedCategoryIds($oldCategoryIds);
        $newCategoryIds = $this->sortedCategoryIds($newCategoryIds);

        if (! $force && $oldCategoryIds === $newCategoryIds) {
            return;
        }

        AuditLog::create([
            'action' => $action,
            'module' => 'case',
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

    public function getCases(array $filters = [], string $sort = 'created_at', string $direction = 'desc', int $perPage = 15)
    {
        $sortMap = [
            'case_number' => 'case_number',
            'tracker_number' => 'tracker_number',
            'client_type' => 'client_type',
            'status' => 'status',
            'created_at' => 'created_at',
        ];
        $sortColumn = $sortMap[$sort] ?? 'created_at';

        $query = CaseFile::with(['client', 'user', 'category', 'categories', 'caseIssue', 'referrals.agency', 'referrals.milestones']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereNotIn('status', ['ARCHIVED']);
        }

        // Role-based scoping — restrict which cases the current user can see
        $user = auth()->user();
        if ($user && $user->role !== 'ADMIN') {
            $query->where(function ($q) use ($user) {
                if ($user->role === 'CASE_MANAGER') {
                    $q->where('user_id', $user->id);
                } elseif ($user->role === 'AGENCY') {
                    if ($user->agcy_id) {
                        $q->whereHas('referrals', function ($rq) use ($user) {
                            $rq->where('agcy_id', $user->agcy_id);
                        });
                    } else {
                        $q->whereRaw('1=0'); // No agency assigned — see nothing
                    }
                }
            });
        } elseif ($user && $user->role === 'ADMIN' && ! empty($filters['user_id'])) {
            // ADMIN can still filter by user_id
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['client_type'])) {
            $query->where('client_type', $filters['client_type']);
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
                $q->where('case_number', 'like', "%{$search}%")
                    ->orWhere('tracker_number', 'like', "%{$search}%")
                    ->orWhere('client_type', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('middle_initial', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                            ->orWhereRaw("CONCAT(first_name, ' ', middle_initial, ' ', last_name) LIKE ?", ["%{$search}%"]);
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
            'referrals.complianceRequirements',
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
            if ($categoryMutation !== null && empty($categoryIds)) {
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
                'client_type' => $data['client_type'],
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
                } catch (\Throwable) {
                    report('Failed to send case update notification');
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

            $case->update([
                'status' => 'OPEN',
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
                } catch (\Throwable) {
                    report('Failed to send status change notification');
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
        $meaningfulFields = ['client_type', 'vulnerability_indicator', 'nok_vulnerability_indicator', 'summary'];

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
            new CaseStatusUpdated($case, $oldStatus, $newStatus),
            'case_status_updated',
            'Case Status Updated',
            "Case #{$case->case_number} status changed from {$oldStatus} to {$newStatus}",
            ['old_status' => $oldStatus, 'new_status' => $newStatus],
            route('cases.show', $case->id),
        );
    }

    private function generateCaseNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "CASE-{$date}-";

        // Use PostgreSQL advisory lock to serialize case number generation for today
        // This prevents duplicate case numbers under concurrent requests
        $lockKey = crc32("case_number_{$date}");
        DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

        $maxCaseNumber = CaseFile::where('case_number', 'like', "{$prefix}%")
            ->max('case_number');

        if ($maxCaseNumber) {
            $lastNumber = (int) substr($maxCaseNumber, -4);

            return $prefix.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        }

        return "{$prefix}0001";
    }

    private function generateTrackerNumber(): string
    {
        return 'OWBAP-'.strtoupper(Str::random(7));
    }

    public function getCaseStats(): array
    {
        return CacheHelper::safeRemember('stats:cases', 120, function () {
            $counts = DB::selectOne("
                SELECT
                    COUNT(*) FILTER (WHERE status NOT IN ('ARCHIVED')) AS active,
                    COUNT(*) FILTER (WHERE status = 'OPEN') AS open,
                    COUNT(*) FILTER (WHERE status = 'CLOSED') AS closed,
                    COUNT(*) FILTER (WHERE status = 'ARCHIVED') AS archived,
                    COUNT(*) FILTER (WHERE client_type = 'OFW' AND status NOT IN ('ARCHIVED')) AS ofw,
                    COUNT(*) FILTER (WHERE client_type = 'NEXT_OF_KIN' AND status NOT IN ('ARCHIVED')) AS nok
                FROM cases
                WHERE is_deleted = false
            ");

            $totalReferrals = Referral::count();

            $categoryBreakdown = CaseFile::join('case_category', 'cases.id', '=', 'case_category.case_id')
                ->join('case_categories as pivot_categories', 'case_category.case_category_id', '=', 'pivot_categories.id')
                ->whereNotIn('cases.status', ['ARCHIVED'])
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
                'ofw_cases' => (int) $counts->ofw,
                'nok_cases' => (int) $counts->nok,
                'total_referrals' => $totalReferrals,
                'category_breakdown' => $categoryBreakdown,
            ];
        });
    }

    /**
     * Normalize a free-text position/title to Title Case for consistent storage.
     */
    private function normalizePosition(?string $position): ?string
    {
        if (empty(trim($position ?? ''))) {
            return null;
        }

        return ucwords(strtolower(trim($position)));
    }
}
