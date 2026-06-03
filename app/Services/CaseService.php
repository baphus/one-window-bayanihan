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
    ) {}

    public function createCase(array $data, string $userId, bool $isDraft = false): CaseFile
    {
        return DB::transaction(function () use ($data, $userId, $isDraft) {
            $createData = [
                'case_number' => $this->generateCaseNumber(),
                'tracker_number' => $this->generateTrackerNumber(),
                'client_type' => $data['client_type'],
                'vulnerability_indicator' => $data['vulnerability_indicator'] ?? null,
                'summary' => $data['summary'] ?? null,
                'status' => $isDraft ? 'DRAFT' : 'OPEN',
                'consent_given_at' => ! empty($data['consent']) ? now() : null,
                'user_id' => $userId,
                'category_id' => $data['category_id'] ?? null,
            ];

            if ($isDraft) {
                $createData['draft_client_data'] = [
                    'first_name' => $data['client']['first_name'] ?? '',
                    'last_name' => $data['client']['last_name'] ?? '',
                    'middle_name' => $data['client']['middle_name'] ?? null,
                    'email' => $data['client']['email'] ?? null,
                    'contact_number' => $data['client']['contact_number'] ?? null,
                    'client_type' => $data['client_type'] ?? null,
                    'selected_client_id' => $data['selected_client_id'] ?? null,
                    'sex' => $data['client']['sex'] ?? null,
                    'date_of_birth' => $data['client']['date_of_birth'] ?? null,
                ];
            }

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

                if (! empty($data['next_of_kin']) && ! empty($data['next_of_kin']['first_name'])) {
                    $nok = $client->nextOfKin()->first();
                    if ($nok) {
                        $nok->update([
                            'first_name' => $data['next_of_kin']['first_name'],
                            'middle_initial' => $data['next_of_kin']['middle_initial'] ?? null,
                            'last_name' => $data['next_of_kin']['last_name'] ?? null,
                            'is_primary' => $data['next_of_kin']['is_primary'] ?? false,
                            'relationship' => $data['next_of_kin']['relationship'] ?? null,
                            'phone_number' => $data['next_of_kin']['phone_number'] ?? null,
                            'email' => $data['next_of_kin']['email'] ?? null,
                            'full_address' => $data['next_of_kin']['full_address'] ?? null,
                        ]);
                    } else {
                        $client->nextOfKin()->create([
                            'first_name' => $data['next_of_kin']['first_name'],
                            'middle_initial' => $data['next_of_kin']['middle_initial'] ?? null,
                            'last_name' => $data['next_of_kin']['last_name'] ?? null,
                            'is_primary' => $data['next_of_kin']['is_primary'] ?? false,
                            'relationship' => $data['next_of_kin']['relationship'] ?? null,
                            'phone_number' => $data['next_of_kin']['phone_number'] ?? null,
                            'email' => $data['next_of_kin']['email'] ?? null,
                            'full_address' => $data['next_of_kin']['full_address'] ?? null,
                        ]);
                    }
                }
            } else {
                $client = Client::create([
                    'first_name' => $data['client']['first_name'] ?? '',
                    'last_name' => $data['client']['last_name'] ?? '',
                    'middle_name' => $data['client']['middle_name'] ?? null,
                    'suffix' => $data['client']['suffix'] ?? null,
                    'date_of_birth' => $data['client']['date_of_birth'] ?? null,
                    'sex' => ! empty($data['client']['sex']) ? strtoupper($data['client']['sex']) : null,
                    'email' => $data['client']['email'] ?? null,
                    'contact_number' => $data['client']['contact_number'] ?? null,
                ]);

                if (! empty($data['address'])) {
                    ClientAddress::create([
                        'client_id' => $client->id,
                        'region' => $data['address']['region'] ?? null,
                        'province' => $data['address']['province'] ?? null,
                        'city_municipality' => $data['address']['city_municipality'] ?? null,
                        'barangay' => $data['address']['barangay'] ?? null,
                        'street' => $data['address']['street'] ?? null,
                    ]);
                }

                if (! empty($data['employment'])) {
                    ClientEmployment::create([
                        'client_id' => $client->id,
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

                if (! empty($data['next_of_kin']) && ! empty($data['next_of_kin']['first_name'])) {
                    NextOfKin::create([
                        'client_id' => $client->id,
                        'first_name' => $data['next_of_kin']['first_name'],
                        'middle_initial' => $data['next_of_kin']['middle_initial'] ?? null,
                        'last_name' => $data['next_of_kin']['last_name'] ?? null,
                        'is_primary' => $data['next_of_kin']['is_primary'] ?? false,
                        'relationship' => $data['next_of_kin']['relationship'] ?? null,
                        'phone_number' => $data['next_of_kin']['phone_number'] ?? null,
                        'email' => $data['next_of_kin']['email'] ?? null,
                        'full_address' => $data['next_of_kin']['full_address'] ?? null,
                    ]);
                }
            }

            $case->client_id = $client->id;
            $case->save();

            if (! $isDraft) {
                AuditLog::create([
                    'action' => 'CREATE',
                    'module' => 'CASE',
                    'entity_id' => $case->id,
                    'new_value' => $case->toArray(),
                    'user_id' => $userId,
                ]);
            }

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category']);
        });
    }

    public function deleteDraft(string $id, string $userId): void
    {
        $case = CaseFile::where('status', 'DRAFT')->findOrFail($id);

        if ($case->user_id !== $userId) {
            throw new AuthorizationException('You do not own this draft.');
        }

        DB::transaction(function () use ($case) {
            DB::table('cases')->where('id', $case->id)->delete();
        });
    }

    public function getUserDrafts(string $userId, int $perPage = 15): LengthAwarePaginator
    {
        return CaseFile::with('client', 'category')
            ->where('status', 'DRAFT')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function publishDraft(string $id, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $userId) {
            $case = CaseFile::where('status', 'DRAFT')->findOrFail($id);

            if ($case->user_id !== $userId) {
                throw new AuthorizationException('You do not own this draft.');
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

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user', 'category']);
        });
    }

    public function getCases(array $filters = [])
    {
        $query = CaseFile::with(['client', 'user', 'referrals'])
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
                'summary' => $data['summary'] ?? null,
                'category_id' => $data['category_id'] ?? $case->category_id,
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
            ]);
        });
    }

    private function dispatchCaseUpdateNotification(CaseFile $case, array $oldData, string $userId): void
    {
        $user = User::find($userId);
        $updatedBy = $user?->name ?? 'Unknown';
        $case->loadMissing('client');

        $changes = [];
        $meaningfulFields = ['client_type', 'vulnerability_indicator', 'summary'];

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
