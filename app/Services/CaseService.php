<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Helpers\CacheHelper;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\NextOfKin;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\CaseStatusUpdated;
use App\Notifications\CaseUpdated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        private readonly PhilippineAddressService $addressService,
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
                'status' => 'DRAFT',
                'consent_given_at' => ! empty($data['consent']) ? now() : null,
                'user_id' => $userId,
                'category_id' => $this->primaryCategoryId($categoryIds, null, $categoryMutation['legacy'] ?? false),
                'case_issue_id' => $data['case_issue_id'] ?? null,
            ];

            $createData['draft_client_data'] = [
                'first_name' => $data['client']['first_name'] ?? '',
                'last_name' => $data['client']['last_name'] ?? '',
                'middle_initial' => $data['client']['middle_initial'] ?? null,
                'suffix' => $data['client']['suffix'] ?? null,
                'date_of_birth' => $data['client']['date_of_birth'] ?? null,
                'sex' => $data['client']['sex'] ?? null,
                'email' => $data['client']['email'] ?? null,
                'contact_number' => $data['client']['contact_number'] ?? null,
                'client_type' => $data['client_type'] ?? null,
                'selected_client_id' => $data['selected_client_id'] ?? null,
                'selected_nok_index' => $data['selected_nok_index'] ?? null,
                'selected_nok_id' => $this->selectedNokIdFromData($data),
                'address' => $data['address'] ?? null,
                'employment' => $this->normalizeDraftEmployment($data['employment'] ?? null),
                'next_of_kin' => $data['next_of_kin'] ?? null,
                'consent' => $data['consent'] ?? false,
            ];

            $case = CaseFile::create($createData);
            $this->syncCaseCategories($case, $categoryIds);

            $isExistingClient = ! empty($data['selected_client_id']);

            if ($isExistingClient) {
                $client = Client::findOrFail($data['selected_client_id']);

                if (! empty($data['address'])) {
                    $address = $client->addresses()->first();
                    $resolvedAddress = $this->resolveAddressNames($data['address']);
                    if ($address) {
                        $address->update($resolvedAddress);
                    } else {
                        $client->addresses()->create($resolvedAddress);
                    }
                }

                if (! empty($data['employment'])) {
                    $employment = $client->employments()->first();
                    if ($employment) {
                        $employment->update([
                            'employer_name' => $data['employment']['employer_name'] ?? null,
                            'position' => $data['employment']['position'] ?? null,
                            'country' => $data['employment']['country'] ?? null,
                            'start_date' => $data['employment']['start_date'] ?? null,
                            'end_date' => ! empty($data['employment']['is_present']) ? null : ($data['employment']['end_date'] ?? null),
                            'last_country' => $data['employment']['last_country'] ?? null,
                            'last_position' => $this->normalizePosition($data['employment']['last_position'] ?? null),
                            'date_of_arrival' => $data['employment']['date_of_arrival'] ?? null,
                        ]);
                    } else {
                        $client->employments()->create([
                            'employer_name' => $data['employment']['employer_name'] ?? null,
                            'position' => $data['employment']['position'] ?? null,
                            'country' => $data['employment']['country'] ?? null,
                            'start_date' => $data['employment']['start_date'] ?? null,
                            'end_date' => ! empty($data['employment']['is_present']) ? null : ($data['employment']['end_date'] ?? null),
                            'last_country' => $data['employment']['last_country'] ?? null,
                            'last_position' => $this->normalizePosition($data['employment']['last_position'] ?? null),
                            'date_of_arrival' => $data['employment']['date_of_arrival'] ?? null,
                        ]);
                    }
                }

                if (array_key_exists('next_of_kin', $data) && is_array($data['next_of_kin'])) {
                    $nokList = $data['next_of_kin'];

                    // Handle old single-object format (backward compat): update first NOK or create
                    if (isset($nokList['first_name'])) {
                        $nok = $client->nextOfKin()->first();
                        $normalized = $this->normalizeNokData($nokList);
                        if ($nok) {
                            $nok->update($normalized);
                        } else {
                            $client->nextOfKin()->create($normalized);
                        }
                    } else {
                        // Multi-NOK array format: run sync algorithm
                        $existingIds = $client->nextOfKin()->pluck('id')->toArray();
                        $incomingIds = array_filter(array_column($nokList, 'id'));
                        $idsToDelete = array_diff($existingIds, $incomingIds);

                        if (! empty($idsToDelete)) {
                            $client->nextOfKin()->whereIn('id', $idsToDelete)->each(fn ($n) => $n->delete());
                        }

                        foreach ($nokList as $nokData) {
                            $normalized = $this->normalizeNokData($nokData);
                            if (! empty($nokData['id'])) {
                                $nok = $client->nextOfKin()->find($nokData['id']);
                                if ($nok) {
                                    $nok->update($normalized);
                                }
                            } else {
                                $client->nextOfKin()->create($normalized);
                            }
                        }
                    }

                    $this->ensureSinglePrimary($client->id);
                }
            }

            if (isset($client)) {
                $case->client_id = $client->id;
                $case->save();
            }

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category', 'categories', 'caseIssue']);
        });
    }

    public function deleteDraft(string $id, string $userId): void
    {
        $case = CaseFile::where('status', 'DRAFT')->findOrFail($id);

        if ($case->user_id !== $userId) {
            throw new AuthorizationException('You do not own this draft.');
        }

        DB::transaction(function () use ($case) {
            CaseFile::withoutEvents(function () use ($case) {
                $client = $case->client;

                // Release the FK before deleting (must be done first due to onDelete restrict)
                $case->client_id = null;
                $case->save();

                // If the client is orphaned (no other cases reference it via client_id), delete it fully
                if ($client && ! $client->caseFiles()->exists()) {
                    // Must delete child records first (FK onDelete restrict on all)
                    $client->addresses()->each(fn ($a) => $a->forceDelete());
                    $client->employments()->each(fn ($e) => $e->forceDelete());
                    $client->nextOfKin()->each(fn ($n) => $n->forceDelete());

                    $client->forceDelete();
                }

                $case->forceDelete();
            });
        });
    }

    public function updateDraft(string $id, array $data, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $case = CaseFile::where('status', 'DRAFT')->lockForUpdate()->findOrFail($id);

            if ($case->user_id !== $userId) {
                throw new AuthorizationException('You do not own this draft.');
            }

            $categoryMutation = $this->resolveCategoryMutation($data);
            $categoryIds = $categoryMutation['ids'] ?? [];
            if ($categoryMutation !== null) {
                $this->lockActiveCategories($categoryIds);
            }

            // Update CaseFile fields without triggering audit/notification events
            CaseFile::withoutEvents(function () use ($case, $data, $categoryMutation, $categoryIds) {
                $updateData = [
                    'client_type' => $data['client_type'] ?? $case->client_type,
                    'vulnerability_indicator' => $data['vulnerability_indicator'] ?? $case->vulnerability_indicator,
                    'nok_vulnerability_indicator' => $data['nok_vulnerability_indicator'] ?? $case->nok_vulnerability_indicator,
                    'summary' => $data['summary'] ?? $case->summary,
                    'category_id' => $categoryMutation === null ? $case->category_id : $this->primaryCategoryId($categoryIds, $case->category_id, $categoryMutation['legacy']),
                    'case_issue_id' => $data['case_issue_id'] ?? $case->case_issue_id,
                ];

                $draftClientData = $case->draft_client_data ?? [];

                // Update draft_client_data when new client data is provided (no selected_client_id)
                if (! empty($data['client'])) {
                    $current = $draftClientData;
                    $draftClientData = array_merge($current, [
                        'first_name' => $data['client']['first_name'] ?? $current['first_name'] ?? '',
                        'last_name' => $data['client']['last_name'] ?? $current['last_name'] ?? '',
                        'middle_initial' => $data['client']['middle_initial'] ?? $current['middle_initial'] ?? null,
                        'suffix' => $data['client']['suffix'] ?? $current['suffix'] ?? null,
                        'email' => $data['client']['email'] ?? $current['email'] ?? null,
                        'contact_number' => $data['client']['contact_number'] ?? $current['contact_number'] ?? null,
                        'sex' => $data['client']['sex'] ?? $current['sex'] ?? null,
                        'date_of_birth' => $data['client']['date_of_birth'] ?? $current['date_of_birth'] ?? null,
                    ]);
                }

                if (! empty($data['selected_client_id'])) {
                    $updateData['client_id'] = $data['selected_client_id'];
                    $draftClientData['selected_client_id'] = $data['selected_client_id'];
                }

                if (array_key_exists('selected_nok_index', $data)) {
                    $draftClientData['selected_nok_index'] = $data['selected_nok_index'];
                    $draftClientData['selected_nok_id'] = $this->selectedNokIdFromData($data);
                }

                // Store address/employment/next_of_kin in draft_client_data for new-client drafts
                if (empty($data['selected_client_id']) && empty($case->client_id)) {
                    if (! empty($data['address'])) {
                        $draftClientData['address'] = $data['address'];
                    }
                    if (! empty($data['employment'])) {
                        $draftClientData['employment'] = $this->normalizeDraftEmployment($data['employment']);
                    }
                    if (! empty($data['next_of_kin'])) {
                        $draftClientData['next_of_kin'] = $data['next_of_kin'];
                    }
                    if (isset($data['nok_vulnerability_indicator'])) {
                        $draftClientData['nok_vulnerability_indicator'] = $data['nok_vulnerability_indicator'];
                    }
                    if (isset($data['consent'])) {
                        $draftClientData['consent'] = $data['consent'];
                    }
                }

                $updateData['draft_client_data'] = $draftClientData;

                $case->update($updateData);

                if ($categoryMutation !== null) {
                    $this->syncCaseCategories($case, $categoryIds);
                }
            });

            // Update linked client record when selected_client_id is provided (existing client mode)
            // NOTE: This runs outside withoutEvents so that UsesUuid auto-generates IDs for new records
            if (! empty($data['selected_client_id'])) {
                $client = Client::findOrFail($data['selected_client_id']);

                if (! empty($data['address'])) {
                    $address = $client->addresses()->first();
                    $resolvedAddress = $this->resolveAddressNames($data['address']);
                    if ($address) {
                        $address->update($resolvedAddress);
                    } else {
                        $client->addresses()->create($resolvedAddress);
                    }
                }

                if (! empty($data['employment'])) {
                    $employment = $client->employments()->first();
                    if ($employment) {
                        $employment->update([
                            'employer_name' => $data['employment']['employer_name'] ?? null,
                            'position' => $data['employment']['position'] ?? null,
                            'country' => $data['employment']['country'] ?? null,
                            'start_date' => $data['employment']['start_date'] ?? null,
                            'end_date' => ! empty($data['employment']['is_present']) ? null : ($data['employment']['end_date'] ?? null),
                            'last_country' => $data['employment']['last_country'] ?? null,
                            'last_position' => $this->normalizePosition($data['employment']['last_position'] ?? null),
                            'date_of_arrival' => $data['employment']['date_of_arrival'] ?? null,
                        ]);
                    } else {
                        $client->employments()->create([
                            'employer_name' => $data['employment']['employer_name'] ?? null,
                            'position' => $data['employment']['position'] ?? null,
                            'country' => $data['employment']['country'] ?? null,
                            'start_date' => $data['employment']['start_date'] ?? null,
                            'end_date' => ! empty($data['employment']['is_present']) ? null : ($data['employment']['end_date'] ?? null),
                            'last_country' => $data['employment']['last_country'] ?? null,
                            'last_position' => $this->normalizePosition($data['employment']['last_position'] ?? null),
                            'date_of_arrival' => $data['employment']['date_of_arrival'] ?? null,
                        ]);
                    }
                }

                if (array_key_exists('next_of_kin', $data) && is_array($data['next_of_kin'])) {
                    $nokList = $data['next_of_kin'];

                    // Handle old single-object format (backward compat): update first NOK or create
                    if (isset($nokList['first_name'])) {
                        $nok = $client->nextOfKin()->first();
                        $normalized = $this->normalizeNokData($nokList);
                        if ($nok) {
                            $nok->update($normalized);
                        } else {
                            $client->nextOfKin()->create($normalized);
                        }
                    } else {
                        // Multi-NOK array format: run sync algorithm
                        $existingIds = $client->nextOfKin()->pluck('id')->toArray();
                        $incomingIds = array_filter(array_column($nokList, 'id'));
                        $idsToDelete = array_diff($existingIds, $incomingIds);

                        if (! empty($idsToDelete)) {
                            $client->nextOfKin()->whereIn('id', $idsToDelete)->each(fn ($n) => $n->delete());
                        }

                        foreach ($nokList as $nokData) {
                            $normalized = $this->normalizeNokData($nokData);
                            if (! empty($nokData['id'])) {
                                $nok = $client->nextOfKin()->find($nokData['id']);
                                if ($nok) {
                                    $nok->update($normalized);
                                }
                            } else {
                                $client->nextOfKin()->create($normalized);
                            }
                        }
                    }

                    $this->ensureSinglePrimary($client->id);
                }
            }

            // No AuditLog creation — draft updates are transient
            // No notifications — draft updates are internal
            // No case_number/tracker_number regeneration — keep existing values

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category', 'categories', 'caseIssue']);
        });
    }

    public function getUserDrafts(string $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CaseFile::with('client', 'category', 'categories')
            ->where('status', 'DRAFT')
            ->where('user_id', $userId);

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

        if (! empty($filters['date_from'])) {
            $query->where('updated_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('updated_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('updated_at', 'desc')
            ->paginate($perPage);
    }

    public function publishDraft(string $id, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $userId) {
            $case = CaseFile::where('status', 'DRAFT')->lockForUpdate()->findOrFail($id);

            if ($case->user_id !== $userId) {
                throw new AuthorizationException('You do not own this draft.');
            }

            $case->loadMissing(['client.addresses', 'client.nextOfKin']);
            $oldCategoryIds = $case->categories()->pluck('case_categories.id')->all();
            $this->revalidatePublishedCategories($case);
            $this->recordCategoryMutation(
                $case,
                $oldCategoryIds,
                $case->categories()->pluck('case_categories.id')->all(),
                $userId,
                'PUBLISH',
                true,
            );
            $this->assertDraftCompleteForPublishing($case);

            // Capture the draft state before publishing (for audit diff)
            $old = $case->toArray();

            // Create client from draft_client_data if no client_id exists (new-client draft)
            if (empty($case->client_id) && ! empty($case->draft_client_data)) {
                $draftData = $case->draft_client_data;

                $client = Client::create([
                    'first_name' => $draftData['first_name'] ?? '',
                    'last_name' => $draftData['last_name'] ?? '',
                    'middle_initial' => $draftData['middle_initial'] ?? null,
                    'suffix' => $draftData['suffix'] ?? null,
                    'date_of_birth' => $draftData['date_of_birth'] ?? null,
                    'sex' => ! empty($draftData['sex']) ? strtoupper($draftData['sex']) : null,
                    'email' => $draftData['email'] ?? null,
                    'contact_number' => $draftData['contact_number'] ?? null,
                ]);

                if (! empty($draftData['address'])) {
                    $resolvedAddress = $this->resolveAddressNames($draftData['address']);
                    ClientAddress::create(array_merge(
                        ['client_id' => $client->id],
                        $resolvedAddress,
                    ));
                }

                if (! empty($draftData['employment'])) {
                    ClientEmployment::create([
                        'client_id' => $client->id,
                        'employer_name' => $draftData['employment']['employer_name'] ?? null,
                        'position' => $draftData['employment']['position'] ?? null,
                        'country' => $draftData['employment']['country'] ?? null,
                        'start_date' => $draftData['employment']['start_date'] ?? null,
                        'end_date' => ! empty($draftData['employment']['is_present']) ? null : ($draftData['employment']['end_date'] ?? null),
                        'last_country' => $draftData['employment']['last_country'] ?? null,
                        'last_position' => $this->normalizePosition($draftData['employment']['last_position'] ?? null),
                        'date_of_arrival' => $draftData['employment']['date_of_arrival'] ?? null,
                    ]);
                }

                if (! empty($draftData['next_of_kin'])) {
                    $nokRecords = $draftData['next_of_kin'];

                    // Handle old single-object format (backward compat)
                    if (isset($nokRecords['first_name'])) {
                        $nokRecords = [$nokRecords];
                    }

                    foreach ($nokRecords as $nokData) {
                        if (! empty($nokData['first_name'])) {
                            NextOfKin::create(array_merge(
                                ['client_id' => $client->id],
                                $this->normalizeNokData($nokData),
                            ));
                        }
                    }

                    $this->ensureSinglePrimary($client->id);
                }

                $case->client_id = $client->id;
                $case->consent_given_at = ! empty($draftData['consent']) ? now() : null;
                $case->save();
            }

            $case->update([
                'status' => 'OPEN',
            ]);

            $this->eventRecorder->caseOpened($case, $userId);

            $description = 'Case '.$case->case_number.' published';
            if (! empty($case->summary)) {
                $description .= ' — '.$case->summary;
            }

            // Audit logging is handled by AuditObserver::updated() — no manual log needed.

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category', 'categories', 'caseIssue']);
        });
    }

    private function assertDraftCompleteForPublishing(CaseFile $case): void
    {
        $missing = [];
        $draftData = $case->draft_client_data ?? [];
        $client = $case->client;
        $address = $client?->addresses->first();

        $clientType = $case->client_type ?: ($draftData['client_type'] ?? null);
        if (! in_array($clientType, ['OFW', 'NEXT_OF_KIN'], true)) {
            $missing[] = 'Client type';
        }

        if ($case->categories()->count() === 0) {
            $missing[] = 'Category';
        }

        $clientFields = [
            'First name' => $client?->first_name ?? $draftData['first_name'] ?? null,
            'Last name' => $client?->last_name ?? $draftData['last_name'] ?? null,
            'Date of birth' => $client?->date_of_birth ?? $draftData['date_of_birth'] ?? null,
            'Sex' => $client?->sex ?? $draftData['sex'] ?? null,
            'Contact number' => $client?->contact_number ?? $draftData['contact_number'] ?? null,
        ];

        foreach ($clientFields as $label => $value) {
            if ($this->isBlank($value)) {
                $missing[] = $label;
            }
        }

        if ($clientType === 'OFW' && $this->isBlank($this->resolveClientNotificationEmail($case))) {
            $missing[] = 'OFW email address';
        }

        if ($clientType === 'NEXT_OF_KIN') {
            $selectedNokEmail = $this->resolveClientNotificationEmail($case);
            if ($this->isBlank($selectedNokEmail)) {
                $missing[] = 'Selected next of kin email address';
            }
        }

        if (empty($case->client_id) && empty($draftData['consent']) && empty($case->consent_given_at)) {
            $missing[] = 'Data privacy consent';
        }

        $draftAddress = $draftData['address'] ?? [];
        $regionCode = $address?->region ?? $draftAddress['region'] ?? null;
        $provinces = $this->addressService->getProvinces($regionCode);
        $regionRequiresProvince = count($provinces) > 0;

        $addressFields = [
            'Region' => $regionCode,
            'City/Municipality' => $address?->city_municipality ?? $draftAddress['city_municipality'] ?? null,
            'Barangay' => $address?->barangay ?? $draftAddress['barangay'] ?? null,
        ];

        if ($regionRequiresProvince) {
            $addressFields['Province'] = $address?->province ?? $draftAddress['province'] ?? null;
        }

        foreach ($addressFields as $label => $value) {
            if ($this->isBlank($value)) {
                $missing[] = $label;
            }
        }

        if ($clientType === 'NEXT_OF_KIN' && $this->isBlank($draftData['selected_nok_index'] ?? null)) {
            $missing[] = 'Selected next of kin';
        }

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'draft' => 'Complete the draft before publishing. Missing: '.implode(', ', array_unique($missing)).'.',
            ]);
        }
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
        $draftData = $case->draft_client_data ?? [];

        if ($case->client_type === 'NEXT_OF_KIN') {
            $selectedIndex = $draftData['selected_nok_index'] ?? null;
            $nokRecords = $draftData['next_of_kin'] ?? [];

            if ($selectedIndex !== null && isset($nokRecords[(int) $selectedIndex]['email'])) {
                return $nokRecords[(int) $selectedIndex]['email'];
            }

            if (! empty($draftData['selected_nok_id'])) {
                return $case->client?->nextOfKin
                    ?->firstWhere('id', $draftData['selected_nok_id'])
                    ?->email;
            }

            if ($selectedIndex !== null && $case->client?->relationLoaded('nextOfKin')) {
                return $case->client->nextOfKin->values()->get((int) $selectedIndex)?->email;
            }

            return null;
        }

        return $case->client?->email ?? $draftData['email'] ?? null;
    }

    private function selectedNokIdFromData(array $data): ?string
    {
        if (! array_key_exists('selected_nok_index', $data)) {
            return null;
        }

        $index = $data['selected_nok_index'];
        if ($index === null || $index === '') {
            return null;
        }

        $nokList = $data['next_of_kin'] ?? [];

        return $nokList[(int) $index]['id'] ?? null;
    }

    private function normalizeDraftEmployment(mixed $employment): mixed
    {
        if (! is_array($employment)) {
            return $employment;
        }

        if (($employment['is_present'] ?? false) === true) {
            $employment['end_date'] = null;
        }

        return $employment;
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
        Cache::forget('tracking:data:'.$case->id);
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
            'client_type' => 'client_type',
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
                $q->where('case_number', 'ilike', "%{$search}%")
                    ->orWhere('tracker_number', 'ilike', "%{$search}%")
                    ->orWhere('client_type', 'ilike', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('middle_initial', 'ilike', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"])
                            ->orWhereRaw("CONCAT(first_name, ' ', middle_initial, ' ', last_name) ILIKE ?", ["%{$search}%"]);
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
            $old = $case->toArray();

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
            $old = $case->toArray();

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

    private function resolveAddressNames(array $address): array
    {
        $codes = array_filter([
            $address['region'] ?? null,
            $address['province'] ?? null,
            $address['city_municipality'] ?? null,
            $address['barangay'] ?? null,
        ]);

        if (empty($codes)) {
            return $address;
        }

        $names = $this->addressService->resolveNames($codes);

        // Only override keys that exist in the input (partial address data)
        foreach (['region', 'province', 'city_municipality', 'barangay'] as $key) {
            if (isset($address[$key])) {
                $address[$key] = $names[$address[$key]] ?? $address[$key];
            }
        }

        return $address;
    }

    private function normalizeNokData(array $data): array
    {
        $result = [
            'first_name' => $data['first_name'] ?? '',
            'middle_initial' => $data['middle_initial'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'is_primary' => $data['is_primary'] ?? false,
            'relationship' => $data['relationship'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'email' => $data['email'] ?? null,
            'full_address' => $data['full_address'] ?? null,
            'nok_vulnerability_indicator' => $data['nok_vulnerability_indicator'] ?? null,
            'region' => $data['region'] ?? $data['nok_address']['region'] ?? null,
            'province' => $data['province'] ?? $data['nok_address']['province'] ?? null,
            'city_municipality' => $data['city_municipality'] ?? $data['nok_address']['city_municipality'] ?? null,
            'barangay' => $data['barangay'] ?? $data['nok_address']['barangay'] ?? null,
            'street' => $data['street'] ?? $data['nok_address']['street'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ];

        // Resolve PSGC codes to location names (region, province, city_municipality, barangay)
        $codes = array_filter([
            $result['region'],
            $result['province'],
            $result['city_municipality'],
            $result['barangay'],
        ]);

        if (! empty($codes)) {
            $names = $this->addressService->resolveNames($codes);
            if (! empty($names)) {
                $result['region'] = $names[$result['region']] ?? $result['region'];
                $result['province'] = $names[$result['province']] ?? $result['province'];
                $result['city_municipality'] = $names[$result['city_municipality']] ?? $result['city_municipality'];
                $result['barangay'] = $names[$result['barangay']] ?? $result['barangay'];
            }
        }

        return $result;
    }

    private function ensureSinglePrimary(string $clientId): void
    {
        $noks = NextOfKin::where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $primaryCount = $noks->where('is_primary', true)->count();

        if ($primaryCount === 0 && $noks->isNotEmpty()) {
            $noks->first()->update(['is_primary' => true]);

            return;
        }

        if ($primaryCount > 1) {
            // Keep first primary, demote others
            $noks->where('is_primary', true)->slice(1)->each(fn ($n) => $n->update(['is_primary' => false]));
        }
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
                    COUNT(*) FILTER (WHERE status NOT IN ('DRAFT','ARCHIVED')) AS active,
                    COUNT(*) FILTER (WHERE status = 'OPEN') AS open,
                    COUNT(*) FILTER (WHERE status = 'CLOSED') AS closed,
                    COUNT(*) FILTER (WHERE status = 'ARCHIVED') AS archived,
                    COUNT(*) FILTER (WHERE client_type = 'OFW' AND status NOT IN ('DRAFT','ARCHIVED')) AS ofw,
                    COUNT(*) FILTER (WHERE client_type = 'NEXT_OF_KIN' AND status NOT IN ('DRAFT','ARCHIVED')) AS nok
                FROM cases
                WHERE is_deleted = false
            ");

            $totalReferrals = Referral::count();

            $categoryBreakdown = CaseFile::join('case_category', 'cases.id', '=', 'case_category.case_id')
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
                'ofw_cases' => (int) $counts->ofw,
                'nok_cases' => (int) $counts->nok,
                'total_referrals' => $totalReferrals,
                'category_breakdown' => $categoryBreakdown,
            ];
        });
    }

    /**
     * Normalize a job position string for consistent storage.
     * Trims whitespace and applies title case so "caregiver" and "CAREGIVER"
     * both resolve to "Caregiver", preventing duplicates in the dropdown.
     */
    private function normalizePosition(?string $position): ?string
    {
        if (empty(trim($position ?? ''))) {
            return null;
        }

        return ucwords(strtolower(trim($position)));
    }
}
