<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Helpers\CacheHelper;
use App\Http\Requests\UpdateCaseRequest;
use App\Models\AuditLog;
use App\Models\CaseDocument;
use App\Models\CaseFile;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CaseService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly PhilippineAddressService $addressService,
        private readonly CaseEventRecorder $eventRecorder,
        private readonly CaseNumberGenerator $caseNumbers,
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
        $paths = [];
        try {
            return DB::transaction(function () use ($data, $userId, &$paths) {
                $filer = User::findOrFail($userId);
                abort_unless($filer->isOfw() || $filer->isCaseManager() || $filer->isAdmin(), 403);
                abort_if(($data['client_type'] ?? 'OFW') === 'NEXT_OF_KIN' && ! $filer->isCaseManager(), 403);
                if (($data['client_type'] ?? 'OFW') === 'NEXT_OF_KIN') {
                    Validator::make($data, [
                        'nok_name' => ['required', 'string', 'max:255'],
                        'nok_relationship' => ['required', 'string', 'max:255'],
                        'nok_contact_number' => ['required', 'string', 'max:255'],
                        'nok_email' => ['required', 'email', 'max:255'],
                    ])->validate();
                }
                $allowed = array_diff(CaseFile::INTAKE_FIELDS, ['ofw_user_id', 'proposed_assessment_by', 'proposed_assessment_at', 'confirmed_assessments', 'confirmed_assessment_other', 'confirmed_assessment_by', 'confirmed_assessment_at', 'consent_accepted_by', 'requested_document_slots', 'document_request_reason', 'document_requested_by', 'document_requested_at', 'reviewed_document_ids', 'assigned_case_manager_id', 'last_correction_reason']);
                $attributes = Arr::only($data, [...$allowed, 'client_type', 'summary', 'vulnerability_indicator', 'nok_vulnerability_indicator']);
                foreach (['region', 'province', 'city_municipality', 'barangay'] as $part) {
                    $field = 'address_'.$part;
                    $value = $attributes[$field.'_code'] ?? $attributes[$field] ?? null;
                    if ($value !== null) {
                        $attributes[$field] = $this->addressService->resolve($value);
                    }
                }
                if ($filer->isOfw()) {
                    $attributes['intake_date'] = now(config('app.operating_timezone', 'Asia/Manila'))->toDateString();
                    $attributes['client_type'] = 'OFW';
                    $attributes['email'] = $filer->email;
                    foreach (['nok_name', 'nok_relationship', 'nok_email', 'nok_contact_number', 'nok_vulnerability_indicator', 'staff_summary', 'missing_document_reasons'] as $field) {
                        unset($attributes[$field]);
                    }

                    // The filer is the OFW: keep their account identity in step
                    // with the snapshot they just confirmed. Case rows stay
                    // authoritative and are never rewritten from here.
                    $filer->fill([
                        'first_name' => $data['first_name'] ?? null,
                        'middle_name' => $data['middle_name'] ?? null,
                        'last_name' => $data['last_name'] ?? null,
                        'suffix' => $data['suffix'] ?? null,
                        'date_of_birth' => $data['date_of_birth'] ?? null,
                        'sex' => $data['sex'] ?? null,
                        'contact_number' => $data['contact_number'] ?? null,
                    ])->save();
                    if (empty($data['consent'])) {
                        throw ValidationException::withMessages(['consent' => 'Consent is required.']);
                    }
                    $attributes['consent_method'] = 'ONLINE';
                    $attributes['consent_evidence'] = null;
                } else {
                    if (empty($data['consent'])) {
                        throw ValidationException::withMessages(['consent' => 'Consent is required.']);
                    }
                    $attributes['consent_method'] = 'STAFF_ATTESTED';
                    $attributes['consent_evidence'] = null;
                }
                Validator::make($data, [
                    'return_reason' => ['required', Rule::in(IntakeDocumentService::RETURN_REASONS)],
                    'consent_text_version' => ['required', Rule::in([IntakeDocumentService::CONSENT_VERSION])],
                    'proposed_assessments' => ['nullable', 'array', 'max:8'],
                    'proposed_assessments.*' => ['required', 'distinct', Rule::in(CaseFile::ASSESSMENTS)],
                    'proposed_assessment_other' => [Rule::requiredIf(in_array('OTHER', $data['proposed_assessments'] ?? [], true)), 'nullable', 'string', 'max:2000'],
                ])->validate();
                $missing = IntakeDocumentService::missingSlots(array_keys($data['documents'] ?? []), $data['return_reason'] ?? '');
                if ($filer->isOfw()) {
                    foreach ($missing as $slot) {
                        throw ValidationException::withMessages(['documents.'.$slot => 'Required document missing.']);
                    }
                }
                $linkedOfwId = $filer->isOfw() ? $userId : User::query()
                    ->where('email', $attributes['email'] ?? null)
                    ->where('role', UserRole::OFW->value)
                    ->where('is_active', true)
                    ->value('id');
                $case = CaseFile::create(array_merge($attributes, [
                    'case_number' => $this->generateCaseNumber(), 'tracker_number' => $this->generateTrackerNumber(),
                    'status' => $filer->isOfw() ? CaseFile::STATUS_PENDING_REVIEW : 'OPEN', 'user_id' => $userId,
                    'ofw_user_id' => $linkedOfwId,
                    'assigned_case_manager_id' => $filer->isCaseManager() ? $userId : null,
                    'consent_given_at' => now(), 'consent_accepted_by' => $userId,
                    'consent_text_version' => IntakeDocumentService::CONSENT_VERSION,
                    'proposed_assessment_by' => ! empty($attributes['proposed_assessments']) ? $userId : null,
                    'proposed_assessment_at' => ! empty($attributes['proposed_assessments']) ? now() : null,
                    'document_requirements' => IntakeDocumentService::buildRequirements(
                        array_keys($data['documents'] ?? []),
                        (array) ($data['complied_onsite_slots'] ?? []),
                        $data['return_reason'] ?? '',
                    ),
                ]));
                app(IntakeDocumentService::class)->store($case, $data['documents'] ?? [], $userId, $paths);
                if (! $filer->isOfw()) {
                    $this->eventRecorder->caseOpened($case, $userId);
                }

                return $case;
            });
        } catch (\Throwable $exception) {
            app(IntakeDocumentService::class)->cleanup($paths);
            throw $exception;
        }
    }

    /**
     * Cases awaiting CM review: account-first filings not yet approved.
     */
    public function getReviewQueue(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CaseFile::query()
            ->where('status', CaseFile::STATUS_PENDING_REVIEW)
            ->where('is_deleted', false);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'ilike', "%{$search}%")
                    ->orWhere('cases.first_name', 'ilike', "%{$search}%")
                    ->orWhere('cases.last_name', 'ilike', "%{$search}%")
                    ->orWhere('cases.middle_name', 'ilike', "%{$search}%");
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
     * Requires confirmed assessment and reviewed document compliance.
     */
    public function approveCase(string $id, string $userId, array $review = []): CaseFile
    {
        return DB::transaction(function () use ($id, $userId, $review) {
            $actor = User::findOrFail($userId);
            abort_unless($actor->isAdmin() || $actor->isCaseManager(), 403);
            $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)->lockForUpdate()->findOrFail($id);
            $validated = Validator::make($review, [
                'confirmed_assessments' => ['required', 'array', 'min:1'],
                'confirmed_assessments.*' => ['required', 'distinct', Rule::in(CaseFile::ASSESSMENTS)],
                'confirmed_assessment_other' => [Rule::requiredIf(in_array('OTHER', $review['confirmed_assessments'] ?? [], true)), 'nullable', 'string', 'max:2000'],
                'staff_summary' => ['nullable', 'string', 'max:5000'],
                'reviewed_document_ids' => ['required', 'array'],
                'category_ids' => ['required', 'array', 'min:1'],
                'category_ids.*' => ['required', 'distinct', Rule::exists('case_categories', 'id')->where('is_active', true)],
            ])->validate();
            if (! $actor->isCaseManager() && ! $case->assigned_case_manager_id) {
                throw ValidationException::withMessages(['assigned_case_manager_id' => 'An unassigned case must first be approved by a case manager.']);
            }
            app(IntakeDocumentService::class)->assertCompliant($case, $validated['reviewed_document_ids']);
            $case->categories()->sync($validated['category_ids']);
            unset($validated['category_ids']);
            $case->update(array_merge($validated, [
                'status' => 'OPEN', 'reviewed_by' => $userId, 'reviewed_at' => now(),
                'confirmed_assessment_by' => $userId, 'confirmed_assessment_at' => now(),
                'assigned_case_manager_id' => $actor->isCaseManager() ? $userId : $case->assigned_case_manager_id,
                'missing_document_reasons' => null,
                'document_requirements' => null,
            ]));
            $this->eventRecorder->caseOpened($case, $userId);
            AuditLogger::approve()->byId($userId)->forEntity($case, $case->case_number)
                ->module(AuditModule::CASE)->context(['status' => CaseFile::STATUS_PENDING_REVIEW], ['status' => 'OPEN'])->write();

            return $case;
        });
    }

    /** Reject a pending filing without removing it from manager case listings. */
    public function rejectCase(string $id, string $reason, string $userId, array $slots = []): CaseFile
    {
        return DB::transaction(function () use ($id, $reason, $userId, $slots) {
            // Same lock as approveCase: the two transitions must serialize. Without
            // it a concurrent approve can commit status=OPEN between this read and
            // the soft-delete below, and the rejection would then land on an
            // already-published case (categories detached, "rejected" audit entry).
            $case = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)
                ->where('is_deleted', false)
                ->lockForUpdate()
                ->findOrFail($id);

            $case->status = 'REJECTED';
            $case->deletion_reason = $reason;
            $case->deleted_by = null;
            $case->saveQuietly();

            $case->loadMissing('ofwUser');
            $clientEmail = $this->resolveClientNotificationEmail($case);
            if ($clientEmail) {
                $slotLabels = collect($slots)->map(fn ($slot) => IntakeDocumentService::REQUIREMENT_LABELS[$slot] ?? $slot)->all();
                $message = $slots === []
                    ? "Your assistance request {$case->case_number} was declined. {$reason} Submit a new request at ".route('ofw.intake')
                    : "Your assistance request {$case->case_number} was declined. Missing requirements: ".implode(', ', $slotLabels).". {$reason} Submit a new request at ".route('ofw.intake');
                $this->notificationService->notifyOfw(
                    $case,
                    $clientEmail,
                    'case_rejected',
                    "Case {$case->case_number} was declined",
                    $message,
                    ['status' => 'REJECTED'],
                    route('ofw.intake'),
                );
            }

            AuditLogger::reject()
                ->byId($userId)
                ->forEntity($case, $case->case_number)
                ->module(AuditModule::CASE)
                ->context(['status' => CaseFile::STATUS_PENDING_REVIEW], ['status' => 'REJECTED', 'deletion_reason' => $reason, 'requested_slots' => $slots])
                ->write();

            return $case;
        });
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
        'client_type' => ['columns' => ['cases.client_type']],
        'client_name' => ['columns' => ['cases.last_name', 'cases.first_name']],
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

        $query = CaseFile::with(['user', 'reviewer:id,name,role', 'categories:id,name', 'referrals.category', 'referrals.agency', 'referrals.milestones']);

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
        $query->where('cases.is_deleted', false)
            ->whereNotIn('cases.status', [CaseFile::STATUS_PENDING_REVIEW]);

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
            $query->whereIn('cases.client_type', $clientTypes);
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

        $categoryIds = MultiSelectFilter::ids($filters['category_ids'] ?? $filters['category_id'] ?? null);
        if ($categoryIds !== []) {
            $query->whereHas('categories', fn ($categories) => $categories->whereIn('case_categories.id', $categoryIds));
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
                    ->orWhere('cases.first_name', 'ilike', "%{$search}%")
                    ->orWhere('cases.last_name', 'ilike', "%{$search}%")
                    ->orWhere('cases.middle_name', 'ilike', "%{$search}%");
            });
        }

        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';

        foreach ($sortSpec['columns'] as $sortColumn) {
            $query->orderBy($sortColumn, $direction);
        }

        $query->orderBy('cases.id', $direction);

        return $query->paginate(min(max($perPage, 10), 100));
    }

    public function caseListPayload(CaseFile $case): array
    {
        return array_merge($case->toArray(), $case->only(['first_name', 'middle_name', 'last_name', 'suffix', 'employment_country', 'employment_status', 'intake_date', 'assigned_case_manager_id']));
    }

    public function getCase(string $id): CaseFile
    {
        return CaseFile::with([
            'referrals.milestones',
            // Read by TrackingService::buildTrackingData() for the case page
            // timeline and agency steps; without these each referral costs
            // two extra queries on every render.
            'referrals.services',
            'referrals.milestones.user',
            'referrals.agency',
            'referrals.attachments.user',
            'user',
            'reviewer:id,name,role',
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
            $actor = User::findOrFail($userId);
            abort_unless($actor->isAdmin() || ($actor->isCaseManager() && $case->assigned_case_manager_id === $userId), 403);
            if (empty(trim($data['correction_reason'] ?? ''))) {
                throw ValidationException::withMessages(['correction_reason' => 'A correction reason is required.']);
            }
            $attributes = Arr::only($data, UpdateCaseRequest::EDITABLE_FIELDS);
            $old = $case->toArray();
            $case->fill($attributes);
            $fields = array_keys($case->getDirty());
            if ($fields) {
                $case->last_correction_reason = $data['correction_reason'];
                $case->saveQuietly();
                AuditLogger::log(AuditAction::UPDATE)->byId($userId)->forEntity($case, $case->case_number)
                    ->module(AuditModule::CASE)->context(null, ['corrected_fields' => $fields, 'reason_recorded' => true])
                    ->description('Factual intake correction; reason retained privately.')->write();
                DB::afterCommit(function () use ($case, $old, $userId) {
                    try {
                        $this->dispatchCaseUpdateNotification($case, $old, $userId);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
            }

            return $case;
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
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
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
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
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
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
            ]);
        });

        return $case;
    }

    private function dispatchCaseUpdateNotification(CaseFile $case, array $oldData, string $userId): void
    {
        $user = User::find($userId);
        $updatedBy = $user?->name ?? 'Unknown';
        $case->loadMissing('ofwUser');

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

        $caseManager = User::find($case->assigned_case_manager_id);
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
            'Request Updated',
            "The details of request #{$case->case_number} have been updated by the Bayanihan team.",
            [],
            route('cases.show', $case->id),
        );
    }

    private function dispatchStatusChangeNotification(CaseFile $case, string $oldStatus, string $newStatus, string $userId): void
    {
        $user = User::find($userId);
        $updatedBy = $user?->name ?? 'Unknown';
        $case->loadMissing('ofwUser');

        $caseManager = User::find($case->assigned_case_manager_id);
        $notifyUsers = $caseManager ? [$caseManager] : [];

        $clientEmail = $this->resolveClientNotificationEmail($case) ?? '';

        [$statusLabel, $statusMessage] = $this->ofwCaseStatusCopy($case, $newStatus);

        $this->notificationService->notifyAll(
            $case,
            $notifyUsers,
            $clientEmail,
            new CaseStatusUpdated($case, $oldStatus, $newStatus, $updatedBy),
            'case_status_updated',
            "Your request is now {$statusLabel}",
            $statusMessage,
            ['old_status' => $oldStatus, 'new_status' => $newStatus],
            route('cases.show', $case->id),
        );
    }

    /**
     * Client-facing label and body for a case status change.
     *
     * One match, both halves: the label and the sentence always describe the
     * same status. They were two matches over the same four arms, so adding a
     * status meant editing both and it was easy to add it to one only.
     * Plain language only, internal status codes never reach the client.
     *
     * @return array{0: string, 1: string} [label, message]
     */
    private function ofwCaseStatusCopy(CaseFile $case, string $status): array
    {
        $caseNumber = $case->case_number ?? '';

        return match (strtoupper($status)) {
            'OPEN' => [
                'in progress',
                "Your request {$caseNumber} is now in progress. Your case manager is coordinating the help you need.",
            ],
            'CLOSED' => [
                'resolved',
                "Your request {$caseNumber} has been resolved. Thank you for trusting the Bayanihan team.",
            ],
            'ARCHIVED' => [
                'archived',
                "Your request {$caseNumber} has been filed for safekeeping. Contact us if you need help again.",
            ],
            default => [
                'updated',
                "There is an update on your request {$caseNumber}. Open your request to read the details.",
            ],
        };
    }

    private function resolveClientNotificationEmail(CaseFile $case): ?string
    {
        return $case->email;
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

            // Type counts use each case's saved classification, not account profile state.
            $counts = (clone $caseQuery)

                ->selectRaw("
                    COUNT(*) FILTER (WHERE cases.status NOT IN ('DRAFT','ARCHIVED','PENDING_REVIEW')) AS active,
                    COUNT(*) FILTER (WHERE cases.status = 'OPEN') AS open,
                    COUNT(*) FILTER (WHERE cases.status = 'CLOSED') AS closed,
                    COUNT(*) FILTER (WHERE cases.status = 'ARCHIVED') AS archived,
                    COUNT(*) FILTER (WHERE cases.status = 'REJECTED') AS declined,
                    COUNT(*) FILTER (WHERE cases.status = 'PENDING_REVIEW') AS pending_review,
                    COUNT(*) FILTER (WHERE cases.status NOT IN ('DRAFT','ARCHIVED','PENDING_REVIEW') AND cases.client_type = 'OFW') AS ofw_cases,
                    COUNT(*) FILTER (WHERE cases.status NOT IN ('DRAFT','ARCHIVED','PENDING_REVIEW') AND cases.client_type = 'NEXT_OF_KIN') AS nok_cases
                ")->first();

            // Total referrals — ADMIN and CASE_MANAGER see all; AGENCY sees own only
            $totalReferrals = match (true) {
                ! $user || $user->role === UserRole::ADMIN->value || $user->role === UserRole::CASE_MANAGER->value => Referral::count(),
                $user->role === UserRole::AGENCY->value => $user->agcy_id ? Referral::where('agcy_id', $user->agcy_id)->count() : 0,
                default => Referral::count(),
            };

            // Category breakdown — scoped
            $categoryBreakdown = (clone $caseQuery)
                ->join('case_file_case_category as classified_cases', 'cases.id', '=', 'classified_cases.case_id')
                ->join('case_categories as pivot_categories', 'classified_cases.case_category_id', '=', 'pivot_categories.id')
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
                'declined_cases' => (int) $counts->declined,
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
     * previously in the retired client-directory controller. Same result shape:
     * total/ofw/nok clients, per-marker vulnerability counts, open-case
     * client count, and total referrals. All constraints are bound parameters.
     */
    public function getClientDirectoryStats(?User $user): array
    {
        $query = CaseFile::query()->live()->withVisibleStatus();
        if ($user?->isAgency()) {
            $user->agcy_id ? $query->forAgency($user->agcy_id) : $query->whereRaw('1=0');
        }
        $vulnerabilities = [];
        foreach (['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'] as $marker) {
            $vulnerabilities[$marker] = (clone $query)->withVulnerabilityMarker($marker)->count();
        }

        return [
            'total_cases' => (clone $query)->count(),
            'ofw_cases' => (clone $query)->where('client_type', 'OFW')->count(),
            'nok_cases' => (clone $query)->where('client_type', 'NEXT_OF_KIN')->count(),
            'linked_accounts' => (clone $query)->distinct()->count('ofw_user_id'),
            'vulnerability_counts' => $vulnerabilities,
            'open_cases' => (clone $query)->withOpenStatus()->count(),
            'closed_cases' => (clone $query)->where('status', 'CLOSED')->count(),
            'total_referrals' => Referral::whereHas('caseFile', fn ($cases) => $cases->whereIn('cases.id', (clone $query)->select('cases.id')))
                ->when($user?->isAgency(), fn ($q) => $q->where('agcy_id', $user->agcy_id))->count(),
        ];
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
     * Deletion is the destructive half of the trash pair, so it uses the same
     * ownership scope as restore: a CASE_MANAGER may trash only a case they
     * own, ADMIN keeps global access. The actor is taken as a User (not a bare
     * id) because the check is the only thing standing between one manager's
     * closed case and another manager's mistake.
     *
     * @throws HttpResponseException 422 if not ARCHIVED, 403 if not the owner's
     */
    public function deleteArchivedCase(CaseFile $case, string $reason, User $actor): void
    {
        abort_unless($case->status === 'ARCHIVED', 422, 'Only archived cases can be deleted.');

        abort_unless(
            $actor->isAdmin() || $case->user_id === $actor->id,
            403,
            'You may only delete a case you own.',
        );

        $userId = $actor->id;

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
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
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
                ->with('user')
                ->orderBy('deleted_at', 'desc'),
            $user
        );

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'ilike', "%{$search}%")
                    ->orWhere('cases.first_name', 'ilike', "%{$search}%")
                    ->orWhere('cases.last_name', 'ilike', "%{$search}%")
                    ->orWhere('cases.middle_name', 'ilike', "%{$search}%");
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
                    // Read the ids and paths before the transaction: once
                    // forceDelete() runs, CascadeSoftDeletes and the ON DELETE
                    // CASCADE constraints remove the rows that hold them. A purge
                    // that is later rolled back must not have already emptied the
                    // bucket. The ids are collected once and reused for both the
                    // file paths and the activity-event cleanup, which previously
                    // ran the same four queries twice per case.
                    $ids = $this->purgeableEntityIds($case);
                    $filePaths = $this->purgeableFilePaths($case, $ids);

                    try {
                        DB::transaction(function () use ($case, $ids) {
                            // case_events has no soft-delete flag and its FK is RESTRICT, so it cannot go through
                            // $cascadeSoftDeletes (CaseEvent is append-only; its deleting hook throws).
                            // Limit: event rows go without per-row audit.
                            DB::table('case_events')->where('case_id', $case->id)->delete();

                            $activityEntityIds = collect([$case->id])
                                ->merge($ids['referralIds'])
                                ->merge($ids['milestoneIds'])
                                ->merge($ids['attachmentIds'])
                                ->merge($ids['commentIds'])
                                ->merge($ids['requestIds'])
                                ->merge($ids['requestItemIds'])
                                ->merge($ids['accessLinkIds'])
                                ->merge($ids['messageIds'])
                                ->merge($ids['messageAttachmentIds']);

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
     * @param  array{referralIds: Collection, requestIds: Collection, messageIds: Collection}  $ids
     * @return list<string>
     */
    private function purgeableFilePaths(CaseFile $case, array $ids): array
    {
        $paths = CaseDocument::withTrashed()
            ->where('case_id', $case->id)
            ->pluck('file_path')
            ->all();

        $referralIds = $ids['referralIds'];

        if ($referralIds->isEmpty()) {
            return $this->uniquePaths($paths);
        }

        $paths = array_merge($paths, ReferralAttachment::withTrashed()
            ->whereIn('referral_id', $referralIds)
            ->pluck('file_path')
            ->all());

        $requestIds = $ids['requestIds'];

        if ($requestIds->isNotEmpty()) {
            $messageIds = $ids['messageIds'];

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
     * Every entity id a force-purged case takes with it, read once.
     *
     * The purge needs these ids twice: to find the stored files (documents,
     * referral attachments, client-message attachments) and to sweep
     * activity_events, whose rows are not reached by any cascade. Both used to
     * walk the referral -> request -> message chain themselves, running the same
     * queries twice per case inside a chunked batch. All reads use withTrashed()
     * because a cascade may already have trashed a child before the case reached
     * its retention date.
     *
     * @return array<string, Collection<int, string>>
     */
    private function purgeableEntityIds(CaseFile $case): array
    {
        $referralIds = Referral::withTrashed()->where('case_id', $case->id)->pluck('id');
        $requestIds = ReferralClientRequest::withTrashed()->whereIn('referral_id', $referralIds)->pluck('id');
        $messageIds = ReferralClientMessage::withTrashed()->whereIn('request_id', $requestIds)->pluck('id');

        return [
            'referralIds' => $referralIds,
            'milestoneIds' => Milestone::withTrashed()->whereIn('refr_id', $referralIds)->pluck('id'),
            'attachmentIds' => ReferralAttachment::withTrashed()->whereIn('referral_id', $referralIds)->pluck('id'),
            'commentIds' => ReferralComment::withTrashed()->whereIn('refr_id', $referralIds)->pluck('id'),
            'requestIds' => $requestIds,
            'requestItemIds' => ReferralClientRequestItem::withTrashed()->whereIn('request_id', $requestIds)->pluck('id'),
            'accessLinkIds' => ReferralClientAccessLink::whereIn('request_id', $requestIds)->pluck('id'),
            'messageIds' => $messageIds,
            'messageAttachmentIds' => ReferralClientMessageAttachment::withTrashed()->whereIn('message_id', $messageIds)->pluck('id'),
        ];
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
