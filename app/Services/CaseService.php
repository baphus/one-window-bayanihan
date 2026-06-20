<?php

namespace App\Services;

use App\Models\AuditLog;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ReferralService $referralService,
    ) {}

    public function createCase(array $data, string $userId): CaseFile
    {
        return DB::transaction(function () use ($data, $userId) {
            $createData = [
                'case_number' => $this->generateCaseNumber(),
                'tracker_number' => $this->generateTrackerNumber(),
                'client_type' => $data['client_type'],
                'vulnerability_indicator' => $data['vulnerability_indicator'] ?? null,
                'summary' => $data['summary'] ?? null,
                'status' => 'DRAFT',
                'consent_given_at' => ! empty($data['consent']) ? now() : null,
                'user_id' => $userId,
                'category_id' => $data['category_id'] ?? null,
                'case_issue_id' => $data['case_issue_id'] ?? null,
            ];

            $createData['draft_client_data'] = [
                'first_name' => $data['client']['first_name'] ?? '',
                'last_name' => $data['client']['last_name'] ?? '',
                'middle_name' => $data['client']['middle_name'] ?? null,
                'suffix' => $data['client']['suffix'] ?? null,
                'date_of_birth' => $data['client']['date_of_birth'] ?? null,
                'sex' => $data['client']['sex'] ?? null,
                'email' => $data['client']['email'] ?? null,
                'contact_number' => $data['client']['contact_number'] ?? null,
                'client_type' => $data['client_type'] ?? null,
                'selected_client_id' => $data['selected_client_id'] ?? null,
                'address' => $data['address'] ?? null,
                'employment' => $data['employment'] ?? null,
                'next_of_kin' => $data['next_of_kin'] ?? null,
                'consent' => $data['consent'] ?? false,
            ];

            $case = CaseFile::create($createData);

            $isExistingClient = ! empty($data['selected_client_id']);

            if ($isExistingClient) {
                $client = Client::findOrFail($data['selected_client_id']);

                if (! empty($data['address'])) {
                    $address = $client->addresses()->first();
                    if ($address) {
                        $address->update([
                            'region' => $data['address']['region'] ?? null,
                            'province' => $data['address']['province'] ?? null,
                            'city_municipality' => $data['address']['city_municipality'] ?? null,
                            'barangay' => $data['address']['barangay'] ?? null,
                            'street' => $data['address']['street'] ?? null,
                        ]);
                    } else {
                        $client->addresses()->create([
                            'region' => $data['address']['region'] ?? null,
                            'province' => $data['address']['province'] ?? null,
                            'city_municipality' => $data['address']['city_municipality'] ?? null,
                            'barangay' => $data['address']['barangay'] ?? null,
                            'street' => $data['address']['street'] ?? null,
                        ]);
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
                            'end_date' => $data['employment']['end_date'] ?? null,
                            'last_country' => $data['employment']['last_country'] ?? null,
                            'last_position' => $data['employment']['last_position'] ?? null,
                            'date_of_arrival' => $data['employment']['date_of_arrival'] ?? null,
                        ]);
                    } else {
                        $client->employments()->create([
                            'employer_name' => $data['employment']['employer_name'] ?? null,
                            'position' => $data['employment']['position'] ?? null,
                            'country' => $data['employment']['country'] ?? null,
                            'start_date' => $data['employment']['start_date'] ?? null,
                            'end_date' => $data['employment']['end_date'] ?? null,
                            'last_country' => $data['employment']['last_country'] ?? null,
                            'last_position' => $data['employment']['last_position'] ?? null,
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

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category', 'caseIssue']);
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
        $case = CaseFile::where('status', 'DRAFT')->findOrFail($id);

        if ($case->user_id !== $userId) {
            throw new AuthorizationException('You do not own this draft.');
        }

        return DB::transaction(function () use ($case, $data) {
            // Update CaseFile fields without triggering audit/notification events
            CaseFile::withoutEvents(function () use ($case, $data) {
                $updateData = [
                    'client_type' => $data['client_type'] ?? $case->client_type,
                    'vulnerability_indicator' => $data['vulnerability_indicator'] ?? $case->vulnerability_indicator,
                    'summary' => $data['summary'] ?? $case->summary,
                    'category_id' => $data['category_id'] ?? $case->category_id,
                    'case_issue_id' => $data['case_issue_id'] ?? $case->case_issue_id,
                ];

                $draftClientData = $case->draft_client_data ?? [];

                // Update draft_client_data when new client data is provided (no selected_client_id)
                if (! empty($data['client'])) {
                    $current = $draftClientData;
                    $draftClientData = array_merge($current, [
                        'first_name' => $data['client']['first_name'] ?? $current['first_name'] ?? '',
                        'last_name' => $data['client']['last_name'] ?? $current['last_name'] ?? '',
                        'middle_name' => $data['client']['middle_name'] ?? $current['middle_name'] ?? null,
                        'suffix' => $data['client']['suffix'] ?? $current['suffix'] ?? null,
                        'email' => $data['client']['email'] ?? $current['email'] ?? null,
                        'contact_number' => $data['client']['contact_number'] ?? $current['contact_number'] ?? null,
                        'sex' => $data['client']['sex'] ?? $current['sex'] ?? null,
                        'date_of_birth' => $data['client']['date_of_birth'] ?? $current['date_of_birth'] ?? null,
                    ]);
                }

                // Store address/employment/next_of_kin in draft_client_data for new-client drafts
                if (empty($data['selected_client_id']) && empty($case->client_id)) {
                    if (! empty($data['address'])) {
                        $draftClientData['address'] = $data['address'];
                    }
                    if (! empty($data['employment'])) {
                        $draftClientData['employment'] = $data['employment'];
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
            });

            // Update linked client record when selected_client_id is provided (existing client mode)
            // NOTE: This runs outside withoutEvents so that UsesUuid auto-generates IDs for new records
            if (! empty($data['selected_client_id'])) {
                $client = Client::findOrFail($data['selected_client_id']);

                if (! empty($data['address'])) {
                    $address = $client->addresses()->first();
                    if ($address) {
                        $address->update([
                            'region' => $data['address']['region'] ?? null,
                            'province' => $data['address']['province'] ?? null,
                            'city_municipality' => $data['address']['city_municipality'] ?? null,
                            'barangay' => $data['address']['barangay'] ?? null,
                            'street' => $data['address']['street'] ?? null,
                        ]);
                    } else {
                        $client->addresses()->create([
                            'region' => $data['address']['region'] ?? null,
                            'province' => $data['address']['province'] ?? null,
                            'city_municipality' => $data['address']['city_municipality'] ?? null,
                            'barangay' => $data['address']['barangay'] ?? null,
                            'street' => $data['address']['street'] ?? null,
                        ]);
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
                            'end_date' => $data['employment']['end_date'] ?? null,
                            'last_country' => $data['employment']['last_country'] ?? null,
                            'last_position' => $data['employment']['last_position'] ?? null,
                            'date_of_arrival' => $data['employment']['date_of_arrival'] ?? null,
                        ]);
                    } else {
                        $client->employments()->create([
                            'employer_name' => $data['employment']['employer_name'] ?? null,
                            'position' => $data['employment']['position'] ?? null,
                            'country' => $data['employment']['country'] ?? null,
                            'start_date' => $data['employment']['start_date'] ?? null,
                            'end_date' => $data['employment']['end_date'] ?? null,
                            'last_country' => $data['employment']['last_country'] ?? null,
                            'last_position' => $data['employment']['last_position'] ?? null,
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

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category', 'caseIssue']);
        });
    }

    public function getUserDrafts(string $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CaseFile::with('client', 'category')
            ->where('status', 'DRAFT')
            ->where('user_id', $userId);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
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
            $case = CaseFile::where('status', 'DRAFT')->findOrFail($id);

            if ($case->user_id !== $userId) {
                throw new AuthorizationException('You do not own this draft.');
            }

            // Create client from draft_client_data if no client_id exists (new-client draft)
            if (empty($case->client_id) && ! empty($case->draft_client_data)) {
                $draftData = $case->draft_client_data;

                $client = Client::create([
                    'first_name' => $draftData['first_name'] ?? '',
                    'last_name' => $draftData['last_name'] ?? '',
                    'middle_name' => $draftData['middle_name'] ?? null,
                    'suffix' => $draftData['suffix'] ?? null,
                    'date_of_birth' => $draftData['date_of_birth'] ?? null,
                    'sex' => ! empty($draftData['sex']) ? $draftData['sex'] : null,
                    'email' => $draftData['email'] ?? null,
                    'contact_number' => $draftData['contact_number'] ?? null,
                ]);

                if (! empty($draftData['address'])) {
                    ClientAddress::create([
                        'client_id' => $client->id,
                        'region' => $draftData['address']['region'] ?? null,
                        'province' => $draftData['address']['province'] ?? null,
                        'city_municipality' => $draftData['address']['city_municipality'] ?? null,
                        'barangay' => $draftData['address']['barangay'] ?? null,
                        'street' => $draftData['address']['street'] ?? null,
                    ]);
                }

                if (! empty($draftData['employment'])) {
                    ClientEmployment::create([
                        'client_id' => $client->id,
                        'employer_name' => $draftData['employment']['employer_name'] ?? null,
                        'position' => $draftData['employment']['position'] ?? null,
                        'country' => $draftData['employment']['country'] ?? null,
                        'start_date' => $draftData['employment']['start_date'] ?? null,
                        'end_date' => $draftData['employment']['end_date'] ?? null,
                        'last_country' => $draftData['employment']['last_country'] ?? null,
                        'last_position' => $draftData['employment']['last_position'] ?? null,
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

            AuditLog::create([
                'action' => 'PUBLISH',
                'module' => 'CASE',
                'entity_id' => $case->id,
                'new_value' => $case->toArray(),
                'user_id' => $userId,
            ]);

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category', 'caseIssue']);
        });
    }

    public function getCases(array $filters = [])
    {
        $query = CaseFile::with(['client', 'user', 'category', 'caseIssue', 'referrals.agency', 'referrals.milestones'])
            ->orderBy('created_at', 'desc');

        $query->where('status', '!=', 'DRAFT');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereNotIn('status', ['ARCHIVED']);
        }

        if (! empty($filters['client_type'])) {
            $query->where('client_type', $filters['client_type']);
        }

        if (! empty($filters['vulnerability_indicator'])) {
            $query->where('vulnerability_indicator', $filters['vulnerability_indicator']);
        }

        if (! empty($filters['nok_vulnerability_indicator'])) {
            $query->where('nok_vulnerability_indicator', $filters['nok_vulnerability_indicator']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['agcy_id'])) {
            $query->whereHas('referrals', function ($q) use ($filters) {
                $q->where('agcy_id', $filters['agcy_id']);
            });
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('tracker_number', 'like', "%{$search}%")
                    ->orWhere('client_type', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                            ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                    });
            });
        }

        return $query->paginate(15);
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
            'caseIssue',
            'documents' => fn ($q) => $q->where('is_deleted', false),
        ])->findOrFail($id);
    }

    public function updateCase(string $id, array $data, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $case = CaseFile::findOrFail($id);
            $old = $case->toArray();

            $case->update([
                'client_type' => $data['client_type'],
                'vulnerability_indicator' => $data['vulnerability_indicator'] ?? null,
                'nok_vulnerability_indicator' => $data['nok_vulnerability_indicator'] ?? null,
                'summary' => $data['summary'] ?? null,
                'category_id' => $data['category_id'] ?? $case->category_id,
                'case_issue_id' => $data['case_issue_id'] ?? $case->case_issue_id,
            ]);

            AuditLog::create([
                'action' => 'UPDATE',
                'module' => 'CASE',
                'entity_id' => $case->id,
                'old_value' => $old,
                'new_value' => $case->toArray(),
                'user_id' => $userId,
            ]);

            // Dispatch notifications
            $this->dispatchCaseUpdateNotification($case, $old, $userId);

            return $case->load([
                'client.addresses',
                'client.employments',
                'client.nextOfKin',
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
                'category',
                'caseIssue',
            ]);
        });
    }

    public function archiveCase(string $id, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $userId) {
            $case = CaseFile::findOrFail($id);

            abort_unless($case->status === 'CLOSED', 422, 'Only closed cases can be archived.');

            $canClose = $this->canClose($id);
            abort_unless($canClose['can_close'], 422, $canClose['reason']);

            $old = $case->toArray();

            $case->update([
                'status' => 'ARCHIVED',
            ]);

            AuditLog::create([
                'action' => 'ARCHIVE',
                'module' => 'CASE',
                'entity_id' => $case->id,
                'old_value' => $old,
                'new_value' => $case->toArray(),
                'user_id' => $userId,
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
                'caseIssue',
            ]);
        });
    }

    public function unarchiveCase(string $id, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $userId) {
            $case = CaseFile::findOrFail($id);
            $old = $case->toArray();

            $case->update([
                'status' => 'OPEN',
            ]);

            AuditLog::create([
                'action' => 'UNARCHIVE',
                'module' => 'CASE',
                'entity_id' => $case->id,
                'old_value' => $old,
                'new_value' => $case->toArray(),
                'user_id' => $userId,
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
                'caseIssue',
            ]);
        });
    }

    public function canClose(string $id): array
    {
        $case = CaseFile::findOrFail($id);

        $pendingReferrals = $case->referrals()
            ->where('type', '!=', 'intervention')
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
        return DB::transaction(function () use ($id, $userId) {
            $case = CaseFile::findOrFail($id);
            $old = $case->toArray();

            if ($case->status === 'OPEN') {
                $canClose = $this->canClose($id);
                if (! $canClose['can_close']) {
                    abort(422, $canClose['reason']);
                }
            }

            $case->update([
                'status' => $case->status === 'OPEN' ? 'CLOSED' : 'OPEN',
            ]);

            if ($case->wasChanged('status') && $case->status === 'CLOSED') {
                $case->update(['closed_at' => now()]);
            }

            AuditLog::create([
                'action' => 'UPDATE',
                'module' => 'CASE',
                'entity_id' => $case->id,
                'old_value' => $old,
                'new_value' => $case->toArray(),
                'user_id' => $userId,
            ]);

            // Dispatch status change notification
            $this->dispatchStatusChangeNotification($case, $old['status'] ?? 'UNKNOWN', $case->status, $userId);

            return $case->load([
                'client.addresses',
                'client.employments',
                'client.nextOfKin',
                'referrals.milestones',
                'referrals.agency',
                'referrals.attachments.user',
                'user',
                'category',
                'caseIssue',
            ]);
        });
    }

    private function dispatchCaseUpdateNotification(CaseFile $case, array $oldData, string $userId): void
    {
        $user = User::find($userId);
        $updatedBy = $user?->name ?? 'Unknown';
        $case->loadMissing('client');

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

        $clientEmail = $case->client?->email ?? '';

        $this->notificationService->notifyAll(
            $case,
            $notifyUsers,
            $clientEmail,
            new CaseUpdated($case, $updatedBy, $changes),
            'case_updated',
            'Case Updated',
            "Case #{$case->case_number} updated by {$updatedBy}",
            ['changes' => $changes],
            route('cases.show', $case->id),
        );
    }

    private function dispatchStatusChangeNotification(CaseFile $case, string $oldStatus, string $newStatus, string $userId): void
    {
        $user = User::find($userId);
        $updatedBy = $user?->name ?? 'Unknown';
        $case->loadMissing('client');

        $caseManager = User::find($case->user_id);
        $notifyUsers = $caseManager ? [$caseManager] : [];

        $clientEmail = $case->client?->email ?? '';

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

    private function normalizeNokData(array $data): array
    {
        return [
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
        $count = CaseFile::whereDate('created_at', today())->count();

        return "CASE-{$date}-".str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    private function generateTrackerNumber(): string
    {
        return 'OWBAP-'.strtoupper(Str::random(7));
    }

    public function getCaseStats(): array
    {
        $active = CaseFile::whereNotIn('status', ['DRAFT', 'ARCHIVED'])->count();
        $open = CaseFile::where('status', 'OPEN')->count();
        $closed = CaseFile::where('status', 'CLOSED')->count();
        $archived = CaseFile::where('status', 'ARCHIVED')->count();
        $ofw = CaseFile::where('client_type', 'OFW')->whereNotIn('status', ['DRAFT', 'ARCHIVED'])->count();
        $nok = CaseFile::where('client_type', 'NOK')->whereNotIn('status', ['DRAFT', 'ARCHIVED'])->count();
        $totalReferrals = Referral::count();

        $categoryBreakdown = CaseFile::join('case_categories', 'cases.category_id', '=', 'case_categories.id')
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->select('case_categories.id', 'case_categories.name', 'case_categories.color', DB::raw('count(*) as count'))
            ->groupBy('case_categories.id', 'case_categories.name', 'case_categories.color')
            ->orderBy('count', 'desc')
            ->get();

        return [
            'total_cases' => $active,
            'open_cases' => $open,
            'closed_cases' => $closed,
            'archived_cases' => $archived,
            'ofw_cases' => $ofw,
            'nok_cases' => $nok,
            'total_referrals' => $totalReferrals,
            'category_breakdown' => $categoryBreakdown,
        ];
    }
}
