<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Helpers\CacheHelper;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientMessageAttachment;
use App\Models\ReferralClientRequest;
use App\Models\ReferralClientRequestItem;
use App\Models\ReferralComment;
use App\Models\User;
use App\Notifications\CaseStatusUpdated;
use App\Notifications\CaseUpdated;
use App\Support\MultiSelectFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     *
     * Optionally sync category_ids and case_issue_id before flipping to OPEN.
     * For backward compat: staff-created cases already have categories so
     * these may be omitted and the existing categories will be revalidated.
     */
    public function approveCase(string $id, string $userId, array $categoryIds = [], ?string $caseIssueId = null): CaseFile
    {
        return DB::transaction(function () use ($id, $userId, $categoryIds, $caseIssueId) {
            $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)
                ->lockForUpdate()
                ->findOrFail($id);

            // If category_ids were provided, sync them before revalidation
            if (! empty($categoryIds)) {
                $this->lockActiveCategories($categoryIds);
                $this->syncCaseCategories($case, $categoryIds);
            }

            $this->revalidatePublishedCategories($case);

            $updateData = [
                'status' => 'OPEN',
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ];

            // Override case_issue_id if provided at approve time
            if (! is_null($caseIssueId)) {
                $updateData['case_issue_id'] = $caseIssueId;
            }

            $case->update($updateData);

            $this->eventRecorder->caseOpened($case, $userId);

            AuditLogger::approve()
                ->byId($userId)
                ->forEntity($case, $case->case_number)
                ->module(AuditModule::CASE)
                ->context(['status' => CaseFile::STATUS_PENDING_REVIEW], ['status' => 'OPEN'])
                ->description('Case '.$case->case_number.' approved'.(! empty($case->summary) ? ' — '.$case->summary : ''))
                ->write();

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
            // Same lock as approveCase: the two transitions must serialize. Without
            // it a concurrent approve can commit status=OPEN between this read and
            // the soft-delete below, and the rejection would then land on an
            // already-published case (categories detached, "rejected" audit entry).
            $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)
                ->lockForUpdate()
                ->findOrFail($id);

            $case->status = 'REJECTED';
            $case->deletion_reason = $reason;
            $case->deleted_by = null;
            $case->saveQuietly();

            $case->loadMissing('client', 'user');
            $clientEmail = $this->resolveClientNotificationEmail($case);
            if ($clientEmail) {
                $this->notificationService->notifyOfw(
                    $case,
                    $clientEmail,
                    'case_rejected',
                    "Case {$case->case_number} was not approved",
                    "Your case request was not approved. Reason: {$reason}",
                    ['reason' => $reason, 'status' => 'REJECTED'],
                    route('ofw.case.show', $case->id),
                );
            }

            AuditLogger::reject()
                ->byId($userId)
                ->forEntity($case, $case->case_number)
                ->module(AuditModule::CASE)
                ->context(['status' => CaseFile::STATUS_PENDING_REVIEW], ['is_deleted' => true, 'deletion_reason' => $reason])
                ->write();

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

        if (array_filter($changes, fn ($items) => ! empty($items))) {
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

        AuditLogger::log(AuditAction::from($action))
            ->byId($userId)
            ->forEntity($case, $case->case_number)
            ->module(AuditModule::CASE)
            ->context(['category_ids' => $oldCategoryIds], ['category_ids' => $newCategoryIds])
            ->description('Case category assignments changed.')
            ->write();
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

    /**
     * Sort keys the case index accepts, mapped to the ORDER BY columns that
     * implement them plus, for keys that read a related table, the join needed
     * to reach it.
     *
     * Single source of truth for both sides of the contract: CaseController
     * builds its request `in:` rule from these keys and getCases() resolves
     * them here, so a key can never be accepted by validation without also
     * being sortable.
     *
     * `age` is deliberately absent — clients.date_of_birth is an encrypted
     * column (Client's EncryptedDate cast), so it holds ciphertext and cannot
     * be ordered in SQL. The column is not offered as sortable in the UI.
     *
     * @var array<string, array{columns: array<int, string>, join?: string, foreign_key?: string}>
     */
    public const SORT_KEYS = [
        'case_number' => ['columns' => ['cases.case_number']],
        'tracker_number' => ['columns' => ['cases.tracker_number']],
        'status' => ['columns' => ['cases.status']],
        'created_at' => ['columns' => ['cases.created_at']],
        'client_type' => ['columns' => ['clients.type'], 'join' => 'clients', 'foreign_key' => 'client_id'],
        'client_name' => ['columns' => ['clients.last_name', 'clients.first_name'], 'join' => 'clients', 'foreign_key' => 'client_id'],
        'author' => ['columns' => ['users.name'], 'join' => 'users', 'foreign_key' => 'user_id'],
    ];

    /**
     * Values the case index accepts for `client_type`. Lives on clients.type;
     * the same pair the registration flow enforces (OfwRegistrationController).
     */
    public const CLIENT_TYPE_FILTERS = ['OFW', 'NEXT_OF_KIN'];

    public function getCases(array $filters = [], string $sort = 'created_at', string $direction = 'desc', int $perPage = 15)
    {
        $sortSpec = self::SORT_KEYS[$sort] ?? self::SORT_KEYS['created_at'];

        $query = CaseFile::with(['client', 'user', 'reviewer:id,name,role', 'category', 'categories', 'caseIssue', 'referrals.agency', 'referrals.milestones']);

        // A sort that reads a related table needs it joined. The join exists
        // only to ORDER BY, so it is added only for that sort and always with
        // select('cases.*') — the hydrated columns stay exactly what the
        // un-joined query produced.
        if (isset($sortSpec['join'])) {
            $query->leftJoin($sortSpec['join'], "{$sortSpec['join']}.id", '=', "cases.{$sortSpec['foreign_key']}")
                ->select('cases.*');
        }

        // Every column below is qualified with `cases.`: a sort key that reads
        // clients/users joins that table in, and several of these names
        // (status, user_id, created_at, case_number) exist on more than one
        // side of such a join.
        $query->whereNotIn('cases.status', [CaseFile::STATUS_PENDING_REVIEW]);

        // Status and client type accept either the legacy scalar form or the
        // filter panel's array form.
        $statuses = MultiSelectFilter::ids($filters['status'] ?? null);
        if ($statuses !== []) {
            $query->whereIn('cases.status', $statuses);
        } else {
            $query->whereNotIn('cases.status', ['ARCHIVED']);
        }

        $clientTypes = MultiSelectFilter::ids($filters['client_type'] ?? null);
        if ($clientTypes !== []) {
            $query->ofClientType($clientTypes);
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
        } elseif ($user && in_array($user->role, [UserRole::ADMIN->value, UserRole::CASE_MANAGER->value])) {
            // ADMIN and CASE_MANAGER can filter by author
            $userIds = MultiSelectFilter::ids($filters['user_id'] ?? null);
            if ($userIds !== []) {
                $query->whereIn('cases.user_id', $userIds);
            }
        }

        $vulnerabilities = MultiSelectFilter::ids($filters['vulnerability_indicator'] ?? null);
        if ($vulnerabilities !== []) {
            $query->where(function ($outer) use ($vulnerabilities) {
                foreach ($vulnerabilities as $indicator) {
                    $outer->orWhere(function ($q) use ($indicator) {
                        $q->where('cases.vulnerability_indicator', 'LIKE', "%{$indicator}%")
                            ->orWhere('cases.nok_vulnerability_indicator', 'LIKE', "%{$indicator}%");
                    });
                }
            });
        }

        if (! empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereIn('cases.category_id', $filters['category_ids'])
                    ->orWhereHas('categories', function ($categoryQuery) use ($filters) {
                        $categoryQuery->whereIn('case_categories.id', $filters['category_ids']);
                    });
            });
        } elseif (! empty($filters['category_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('cases.category_id', $filters['category_id'])
                    ->orWhereHas('categories', function ($categoryQuery) use ($filters) {
                        $categoryQuery->where('case_categories.id', $filters['category_id']);
                    });
            });
        }

        $issueIds = MultiSelectFilter::ids($filters['case_issue_id'] ?? null);
        if ($issueIds !== []) {
            $query->whereIn('cases.case_issue_id', $issueIds);
        }

        $agencyIds = MultiSelectFilter::ids($filters['agcy_id'] ?? null);
        if ($agencyIds !== []) {
            $query->whereHas('referrals', function ($q) use ($agencyIds) {
                $q->whereIn('agcy_id', $agencyIds);
            });
        }

        if (! empty($filters['age_min_days']) && is_numeric($filters['age_min_days'])) {
            $query->where('cases.created_at', '<=', now()->subDays((int) $filters['age_min_days']));
        }

        if (($filters['referral_state'] ?? null) === 'none') {
            $query->whereDoesntHave('referrals', function ($q) {
                $q->where('referrals.is_deleted', false);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('cases.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('cases.created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('cases.case_number', 'ilike', "%{$search}%")
                    ->orWhere('cases.tracker_number', 'ilike', "%{$search}%")
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

        foreach ($sortSpec['columns'] as $sortColumn) {
            $query->orderBy($sortColumn, $direction);
        }

        $query->orderBy('cases.id', $direction);

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
            'reviewer:id,name,role',
            'category',
            'categories',
            'caseIssue',
            'documents' => fn ($q) => $q->where('is_deleted', false),
        ])->findOrFail($id);
    }

    /**
     * Case lifecycle transitions with the actor who performed each one.
     *
     * Reads the append-only audit log rather than inventing a second history.
     * UPDATE rows are included only for CLOSED → OPEN (reopen): every other
     * status transition already has its own purposeful row (CREATE, APPROVE,
     * REJECT, CLOSE, ARCHIVE, UNARCHIVE).
     *
     * @return array<int, array{action: string, label: string, actor: string, role: string|null, at: string|null}>
     */
    public function getCaseLifecycle(CaseFile $case): array
    {
        return AuditLog::with('user:id,name,role')
            ->where('entity_id', $case->id)
            ->whereIn('module', AuditModule::CASE->aliases())
            ->whereIn('action', ['CREATE', 'APPROVE', 'REJECT', 'CLOSE', 'ARCHIVE', 'UNARCHIVE', 'UPDATE'])
            ->orderBy('timestamp')
            ->orderBy('id')
            ->get()
            ->filter(fn (AuditLog $log) => $log->action !== 'UPDATE'
                || (($log->old_value['status'] ?? null) === 'CLOSED'
                    && ($log->new_value['status'] ?? null) === 'OPEN'))
            ->map(function (AuditLog $log) {
                $role = $log->user
                    ? UserRole::tryFrom($log->user->role)?->label()
                    : null;

                return [
                    'action' => $log->action,
                    'label' => match ($log->action) {
                        'CREATE' => 'Case created',
                        'APPROVE' => 'Approved',
                        'REJECT' => 'Rejected',
                        'CLOSE' => 'Closed',
                        'ARCHIVE' => 'Archived',
                        'UNARCHIVE' => 'Restored from archive',
                        'UPDATE' => 'Reopened',
                    },
                    'actor' => $log->user?->name ?? 'System',
                    'role' => $role,
                    'at' => $log->timestamp?->toISOString(),
                ];
            })
            ->values()
            ->all();
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

            if ($newStatus !== $case->status) {
                $this->assertStatusTransitionAllowed($case->status, $newStatus);

                if (in_array($newStatus, ['CLOSED', 'ARCHIVED'], true)) {
                    $canClose = $this->canClose($id);
                    if (! $canClose['can_close']) {
                        abort(422, $canClose['reason']);
                    }
                }
            }

            $updateData = ['status' => $newStatus];

            // Partial edits: every field is optional, so only the keys actually
            // present in the payload are written. Writing them unconditionally
            // meant a status-only save (e.g. `{status: 'CLOSED'}`) nulled the
            // case's vulnerability markers, summary and issue.
            foreach (['vulnerability_indicator', 'nok_vulnerability_indicator', 'summary', 'case_issue_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            $updateData['category_id'] = $categoryMutation === null ? $case->category_id : $this->primaryCategoryId($categoryIds, $case->category_id, $categoryMutation['legacy']);

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

            // Field-level audit logging is handled by AuditObserver::updated();
            // the close decision below is written as its own purposeful entry.

            if ($case->wasChanged('status')) {
                // Case edits can close or reopen a case just like toggleCaseStatus —
                // record the same client-facing events. (Archive transitions are
                // administrative and intentionally not client-visible.)
                if ($case->status === 'CLOSED') {
                    $this->eventRecorder->caseClosed($case, $userId);

                    AuditLogger::close()
                        ->byId($userId)
                        ->forEntity($case, $case->case_number)
                        ->module(AuditModule::CASE)
                        ->context(['status' => $oldStatus], ['status' => 'CLOSED'])
                        ->write();
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

    /**
     * Transitions the edit endpoint is not allowed to perform.
     *
     * The edit form carries a status field, but two status changes belong to
     * dedicated flows and must not be reachable by patching the case:
     *  - PENDING_REVIEW → OPEN is publication (approveCase stamps reviewed_by /
     *    reviewed_at, revalidates categories and writes the PUBLISH audit entry);
     *  - ARCHIVED is only reachable from CLOSED (archiveCase's rule).
     *
     * OPEN ↔ CLOSED stays available here — it is the same transition
     * toggleCaseStatus performs and records the same client-facing events.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpResponseException 422 on a forbidden transition
     */
    private function assertStatusTransitionAllowed(string $from, string $to): void
    {
        if ($to === 'OPEN' && $from === CaseFile::STATUS_PENDING_REVIEW) {
            abort(422, 'Pending cases must be published through the review queue.');
        }

        if ($to === 'ARCHIVED' && $from !== 'CLOSED') {
            abort(422, 'Only closed cases can be archived.');
        }
    }

    public function archiveCase(string $id, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $userId) {
            // Serialize the transition: a concurrent close/reopen must not slip
            // between this read and the status write.
            $case = CaseFile::lockForUpdate()->findOrFail($id);

            abort_unless($case->status === 'CLOSED', 422, 'Only closed cases can be archived.');

            $canClose = $this->canClose($id);
            abort_unless($canClose['can_close'], 422, $canClose['reason']);

            $old = $case->toArray();

            $case->update([
                'status' => 'ARCHIVED',
            ]);

            AuditLogger::log(AuditAction::ARCHIVE)
                ->byId($userId)
                ->forEntity($case, $case->case_number)
                ->module(AuditModule::CASE)
                ->context(['status' => 'CLOSED'], ['status' => 'ARCHIVED'])
                ->write();

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
        return DB::transaction(function () use ($id, $userId) {
            $case = CaseFile::lockForUpdate()->findOrFail($id);

            abort_unless($case->status === 'ARCHIVED', 422, 'Only archived cases can be restored.');

            $old = $case->toArray();

            // Restore to the previous (pre-archive) state. Cases can only be
            // archived from CLOSED, so unarchiving returns them to CLOSED and
            // preserves the original closed_at timestamp (CASE-032).
            $case->update([
                'status' => 'CLOSED',
                'closed_at' => $case->closed_at ?? now(),
            ]);

            AuditLogger::log(AuditAction::UNARCHIVE)
                ->byId($userId)
                ->forEntity($case, $case->case_number)
                ->module(AuditModule::CASE)
                ->context(['status' => 'ARCHIVED'], ['status' => 'CLOSED'])
                ->write();

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
            $case = CaseFile::lockForUpdate()->findOrFail($id);
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
            $reopening = $case->status === 'CLOSED';

            $case->update([
                'status' => $reopening ? 'OPEN' : 'CLOSED',
                // Reopening clears the closure date; closing stamps it.
                'closed_at' => $reopening ? null : now(),
            ]);

            if ($case->status === 'CLOSED') {
                $this->eventRecorder->caseClosed($case, $userId);

                AuditLogger::close()
                    ->byId($userId)
                    ->forEntity($case, $case->case_number)
                    ->module(AuditModule::CASE)
                    ->context(['status' => $oldStatus], ['status' => 'CLOSED'])
                    ->write();
            } else {
                $this->eventRecorder->caseReopened($case, $userId);
            }

            // Reopening is captured by AuditObserver::updated(); the close
            // decision above is written as its own purposeful entry.

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
            // The client-type tile uses the same population as total_cases
            // (everything outside DRAFT/ARCHIVED), matching the ofw/nok tiles
            // getClientDirectoryStats() already reports.
            $counts = (clone $caseQuery)
                ->leftJoin('clients as stat_clients', 'stat_clients.id', '=', 'cases.client_id')
                ->selectRaw("
                    COUNT(*) FILTER (WHERE cases.status NOT IN ('DRAFT','ARCHIVED','PENDING_REVIEW')) AS active,
                    COUNT(*) FILTER (WHERE cases.status = 'OPEN') AS open,
                    COUNT(*) FILTER (WHERE cases.status = 'CLOSED') AS closed,
                    COUNT(*) FILTER (WHERE cases.status = 'ARCHIVED') AS archived,
                    COUNT(*) FILTER (WHERE cases.status = 'PENDING_REVIEW') AS pending_review,
                    COUNT(*) FILTER (WHERE cases.status NOT IN ('DRAFT','ARCHIVED','PENDING_REVIEW') AND stat_clients.type = 'OFW') AS ofw_cases,
                    COUNT(*) FILTER (WHERE cases.status NOT IN ('DRAFT','ARCHIVED','PENDING_REVIEW') AND stat_clients.type = 'NEXT_OF_KIN') AS nok_cases
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
                'ofw_cases' => (int) $counts->ofw_cases,
                'nok_cases' => (int) $counts->nok_cases,
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

        $totalClients = $user?->isAgency()
            ? $this->countDistinctClientsWithCases($agencyId, fn ($q) => $q->withVisibleStatus())
            : Client::where('clients.is_deleted', false)->count();

        $typeCounts = Client::query()
            ->where('clients.is_deleted', false)
            ->when($user?->isAgency(), function ($query) use ($agencyId) {
                $query->whereHas('caseFiles', function ($cases) use ($agencyId) {
                    $cases->live()->forAgency($agencyId);
                });
            })
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $ofwClients = (int) ($typeCounts['OFW'] ?? 0);
        $nokClients = (int) ($typeCounts['NEXT_OF_KIN'] ?? 0);

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
            AuditLogger::log(AuditAction::DELETE)
                ->byId($userId)
                ->forEntity($case, $case->case_number)
                ->module(AuditModule::CASE)
                ->context(['status' => $case->status, 'case_number' => $case->case_number], ['is_deleted' => true, 'deletion_reason' => $reason])
                ->description("Case {$case->case_number} deleted — {$reason}")
                ->write();
        });
    }

    /**
     * Restore a soft-deleted case back to ARCHIVED status.
     *
     * Resolved, ownership-scoped and row-locked in one statement inside the
     * transaction: the caller hands over a bare id, so a case that is not the
     * actor's (CASE_MANAGER) and a case that purgeTrashedCases() has already
     * force-deleted both surface as a 404 instead of being restored, or being
     * reported as restored when nothing happened.
     *
     * Cascaded restore is triggered by the CascadeSoftDeletes trait on restore().
     *
     * @throws ModelNotFoundException 404 when the trashed case is gone or not the actor's
     */
    public function restoreTrashedCase(string $id, User $actor): CaseFile
    {
        return DB::transaction(function () use ($id, $actor) {
            $case = $this->scopeToTrashOwner(CaseFile::onlyTrashed()->whereKey($id), $actor)
                ->lockForUpdate()
                ->firstOrFail();

            // Restore case (and cascaded referrals/documents via CascadeSoftDeletes)
            if (! $case->restore()) {
                abort(404, 'Case not found in trash.');
            }

            // Ensure case returns to ARCHIVED, not some other status.
            // save() fires AuditObserver::updated() which logs the status change.
            $case->update(['status' => 'ARCHIVED', 'deletion_reason' => null]);

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
     * Trash visibility: a CASE_MANAGER only ever reaches their own trashed
     * cases, ADMIN keeps global access. Single source of truth for the trash
     * listing and the restore lookup — a case outside the scope is simply not
     * found, so its existence is not disclosed to a non-owner.
     */
    private function scopeToTrashOwner(Builder $query, ?User $user): Builder
    {
        if ($user && $user->isCaseManager()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    /**
     * Paginated list of soft-deleted cases for the trash view.
     *
     * CASE_MANAGER role sees only their own trashed cases.
     * ADMIN sees all.
     */
    public function getTrashedCases(array $filters = [], ?User $user = null): LengthAwarePaginator
    {
        $query = $this->scopeToTrashOwner(
            CaseFile::onlyTrashed()
                ->with(['client', 'user'])
                ->orderBy('deleted_at', 'desc'),
            $user
        );

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
     * PostgreSQL SQLSTATEs raised when a delete is refused by a foreign key:
     * restrict_violation (23001) for an explicit ON DELETE RESTRICT and
     * foreign_key_violation (23503) for the non-RESTRICT actions. Either one
     * means the row still has children the cascade did not reach.
     */
    private const FK_BLOCKING_SQLSTATES = ['23001', '23503'];

    /**
     * Cases hydrated per pagination round in purgeTrashedCases().
     *
     * Each case forces its own potentially large delete cascade, so a round of
     * 200 held that many cascade trees in one transaction batch. 50 keeps a
     * round's lock footprint and memory small while still amortising the
     * pagination query.
     */
    private const PURGE_CHUNK_SIZE = 50;

    /**
     * Permanently delete soft-deleted cases older than the retention period.
     *
     * Called by the PurgeTrashedCases artisan command. Every case is purged in
     * its own transaction, so a case that still has rows behind a RESTRICT
     * foreign key — in practice a referral whose client-request access-links
     * were never cleaned up — is logged and skipped instead of aborting the
     * whole batch. One such case used to roll the transaction back on every
     * scheduled run, so the batch permanently purged nothing.
     *
     * Returns the number of cases actually purged. Skipped cases are written
     * to the application log and counted in the batch audit entry, so a run
     * that matched cases but purged none is still visible.
     *
     * The eligible set is walked with chunkById() rather than loaded in one
     * collection: a first rollout or a long-misconfigured retention window can
     * leave an unbounded backlog of trashed cases, and hydrating all of them at
     * once is what turns a scheduled cleanup into an out-of-memory kill.
     *
     * A retention period below one day is rejected before anything is computed:
     * 0 or a negative value would flip the cutoff to "now" (or into the
     * future) and purge every trashed case regardless of the configured
     * policy. The method returns 0 (nothing purged) and logs the abort.
     *
     * The stored files behind a purged case are deleted here rather than left
     * to storage:cleanup-orphans. That sweep keys on case_documents and
     * referral_attachments rows, and forceDelete() takes exactly those rows
     * away — so a purge that lands before a successful sweep (storage outage, a
     * failed try) would leave the objects behind with nothing left pointing at
     * them, permanently.
     */
    public function purgeTrashedCases(int $retentionDays): int
    {
        if ($retentionDays < 1) {
            Log::warning('cases:purge-trashed — retention below 1 day aborted the purge before any case was touched.', [
                'retention_days' => $retentionDays,
            ]);

            return 0;
        }

        $cutoff = now()->subDays($retentionDays);

        $purgedCount = 0;
        $purgedFileCount = 0;
        $skippedCaseIds = [];

        // chunkById walks the eligible set in id order and re-runs the query for
        // each page, so the purge never hydrates the whole eligible set at once
        // (a first-rollout backlog or a long-forgotten retention setting can
        // leave tens of thousands of trashed cases). forceDelete() removes the
        // rows being paged, which is safe here because pagination keys off the
        // last id seen rather than an offset.
        CaseFile::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->chunkById(self::PURGE_CHUNK_SIZE, function ($cases) use (&$purgedCount, &$purgedFileCount, &$skippedCaseIds, $retentionDays, $cutoff) {
                foreach ($cases as $case) {
                    // Read the paths before the transaction: once forceDelete() runs,
                    // CascadeSoftDeletes and the ON DELETE CASCADE constraints remove
                    // the rows that hold them. A purge that is later rolled back must
                    // not have already emptied the bucket.
                    $filePaths = $this->purgeableFilePaths($case);

                    try {
                        DB::transaction(function () use ($case) {
                            // case_events has no soft-delete flag and its FK is RESTRICT, so it cannot go through
                            // $cascadeSoftDeletes (CaseEvent is append-only; its deleting hook throws).
                            // Limit: event rows go without per-row audit.
                            DB::table('case_events')->where('case_id', $case->id)->delete();

                            $referralIds = Referral::withTrashed()->where('case_id', $case->id)->pluck('id');
                            $activityEntityIds = $referralIds->prepend($case->id);
                            $activityEntityIds = $activityEntityIds
                                ->merge(Milestone::withTrashed()->whereIn('refr_id', $referralIds)->pluck('id'))
                                ->merge(ReferralAttachment::withTrashed()->whereIn('referral_id', $referralIds)->pluck('id'))
                                ->merge(ReferralComment::withTrashed()->whereIn('refr_id', $referralIds)->pluck('id'));
                            $requestIds = ReferralClientRequest::withTrashed()->whereIn('referral_id', $referralIds)->pluck('id');
                            $activityEntityIds = $activityEntityIds
                                ->merge($requestIds)
                                ->merge(ReferralClientRequestItem::withTrashed()->whereIn('request_id', $requestIds)->pluck('id'))
                                ->merge(ReferralClientAccessLink::whereIn('request_id', $requestIds)->pluck('id'));
                            $messageIds = ReferralClientMessage::withTrashed()->whereIn('request_id', $requestIds)->pluck('id');
                            $activityEntityIds = $activityEntityIds
                                ->merge($messageIds)
                                ->merge(ReferralClientMessageAttachment::withTrashed()->whereIn('message_id', $messageIds)->pluck('id'));
                            DB::table('activity_events')->whereIn('entity_id', $activityEntityIds->unique())->delete();

                            // CascadeSoftDeletes handles child force-deletes when forceDelete() is called.
                            $case->forceDelete();
                        });

                        $purgedCount++;

                        // Outside the transaction on purpose: a storage failure here
                        // must not roll back a purge that has already committed, and
                        // must not abort the rest of the batch.
                        $purgedFileCount += $this->deletePurgedFiles($case, $filePaths);
                    } catch (QueryException $e) {
                        // Only a blocking foreign key is survivable; anything else is a
                        // real failure and must keep bubbling up.
                        if (! in_array($e->getCode(), self::FK_BLOCKING_SQLSTATES, true)) {
                            throw $e;
                        }

                        $skippedCaseIds[] = $case->id;

                        Log::warning('cases:purge-trashed — skipped case held by a RESTRICT foreign key.', [
                            'case_id' => $case->id,
                            'case_number' => $case->case_number,
                            'retention_days' => $retentionDays,
                            'cutoff' => $cutoff->toDateString(),
                            'sqlstate' => $e->getCode(),
                        ]);
                    }
                }
            });

        if ($purgedCount === 0 && $skippedCaseIds === []) {
            return 0;
        }

        // Audit the purge batch, including what the run could not purge.
        $skippedCount = count($skippedCaseIds);

        AuditLogger::log(AuditAction::PURGE)
            ->byId(null)
            ->entity(null)
            ->module(AuditModule::CASE)
            ->context([
                'purged_count' => $purgedCount,
                'purged_file_count' => $purgedFileCount,
                'skipped_count' => $skippedCount,
                'skipped_case_ids' => $skippedCaseIds,
                'cutoff_date' => $cutoff->toDateString(),
            ], null)
            ->description("Auto-purged {$purgedCount} case(s) with deleted_at before {$cutoff->toDateString()}"
                .($skippedCount > 0 ? "; skipped {$skippedCount} held by a RESTRICT foreign key" : ''))
            ->write();

        return $purgedCount;
    }

    /**
     * Every stored file a force-purged case takes with it.
     *
     * Three chains, all read with withTrashed() because a cascade may already
     * have trashed the row before the case reached its retention date:
     *
     *   case_documents.case_id
     *   referrals.case_id -> referral_attachments.referral_id
     *   referrals -> client_requests -> messages -> attachments
     *
     * The last one is the citizen-uploaded path (passport and residence scans
     * arriving through a public tracking link); it is included because the
     * rows cascade away with the referral just like the first two.
     *
     * @return list<string>
     */
    private function purgeableFilePaths(CaseFile $case): array
    {
        $paths = CaseDocument::withTrashed()
            ->where('case_id', $case->id)
            ->pluck('file_path')
            ->all();

        $referralIds = Referral::withTrashed()
            ->where('case_id', $case->id)
            ->pluck('id');

        if ($referralIds->isEmpty()) {
            return $this->uniquePaths($paths);
        }

        $paths = array_merge($paths, ReferralAttachment::withTrashed()
            ->whereIn('referral_id', $referralIds)
            ->pluck('file_path')
            ->all());

        $requestIds = ReferralClientRequest::withTrashed()
            ->whereIn('referral_id', $referralIds)
            ->pluck('id');

        if ($requestIds->isNotEmpty()) {
            $messageIds = ReferralClientMessage::withTrashed()
                ->whereIn('request_id', $requestIds)
                ->pluck('id');

            if ($messageIds->isNotEmpty()) {
                $paths = array_merge($paths, ReferralClientMessageAttachment::withTrashed()
                    ->whereIn('message_id', $messageIds)
                    ->pluck('file_path')
                    ->all());
            }
        }

        return $this->uniquePaths($paths);
    }

    /**
     * Drop blank and duplicate paths so a shared object is deleted once.
     *
     * @param  array<int, mixed>  $paths
     * @return list<string>
     */
    private function uniquePaths(array $paths): array
    {
        return array_values(array_unique(array_filter($paths, fn ($path) => is_string($path) && $path !== '')));
    }

    /**
     * Delete the objects a purged case owned. Returns how many went away.
     *
     * Nothing in here may throw: the purge has already committed, and a bucket
     * that is briefly unavailable must cost one leaked object and a log line,
     * not the whole scheduled run.
     */
    private function deletePurgedFiles(CaseFile $case, array $filePaths): int
    {
        if ($filePaths === []) {
            return 0;
        }

        $storage = app(StorageService::class);
        $deleted = 0;

        foreach ($filePaths as $path) {
            try {
                $succeeded = $storage->delete($path);
            } catch (\Throwable $e) {
                Log::warning('cases:purge-trashed — storage threw while deleting a purged file.', [
                    'case_id' => $case->id,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }

            if ($succeeded) {
                $deleted++;

                continue;
            }

            Log::warning('cases:purge-trashed — could not delete a file behind a purged case.', [
                'case_id' => $case->id,
                'case_number' => $case->case_number,
                'path' => $path,
            ]);
        }

        return $deleted;
    }
}
