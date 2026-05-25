<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\NextOfKin;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseService
{
    public function createCase(array $data, string $userId, bool $isDraft = false): CaseFile
    {
        return DB::transaction(function () use ($data, $userId, $isDraft) {
            $case = CaseFile::create([
                'case_number' => $this->generateCaseNumber(),
                'tracker_number' => $this->generateTrackerNumber(),
                'client_type' => $data['client_type'],
                'vulnerability_indicator' => $data['vulnerability_indicator'] ?? null,
                'summary' => $data['summary'] ?? null,
                'status' => $isDraft ? 'DRAFT' : 'OPEN',
                'consent_given_at' => ! empty($data['consent']) ? now() : null,
                'user_id' => $userId,
            ]);

            $client = Client::create([
                'first_name' => $data['client']['first_name'] ?? '',
                'last_name' => $data['client']['last_name'] ?? '',
                'middle_name' => $data['client']['middle_name'] ?? null,
                'suffix' => $data['client']['suffix'] ?? null,
                'date_of_birth' => $data['client']['date_of_birth'] ?? null,
                'sex' => $data['client']['sex'] ?? null,
                'email' => $data['client']['email'] ?? null,
                'contact_number' => $data['client']['contact_number'] ?? null,
                'case_id' => $case->id,
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

            if (! $isDraft) {
                AuditLog::create([
                    'action' => 'CREATE',
                    'module' => 'CASE',
                    'entity_id' => $case->id,
                    'new_value' => $case->toArray(),
                    'user_id' => $userId,
                ]);
            }

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user']);
        });
    }

    public function publishDraft(string $id): CaseFile
    {
        return DB::transaction(function () use ($id) {
            $case = CaseFile::where('status', 'DRAFT')->findOrFail($id);

            $case->update([
                'status' => 'OPEN',
            ]);

            AuditLog::create([
                'action' => 'PUBLISH',
                'module' => 'CASE',
                'entity_id' => $case->id,
                'new_value' => $case->toArray(),
                'user_id' => request()->user()->id,
            ]);

            return $case->load(['client.addresses', 'client.employments', 'client.nextOfKin', 'user']);
        });
    }

    public function getCases(array $filters = [])
    {
        $query = CaseFile::with(['client', 'user', 'referrals'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', '!=', 'DRAFT');
        }

        if (! empty($filters['client_type'])) {
            $query->where('client_type', $filters['client_type']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")
                    ->orWhere('tracker_number', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
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
            ]);

            AuditLog::create([
                'action' => 'UPDATE',
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
            ]);
        });
    }

    public function toggleCaseStatus(string $id, string $userId): CaseFile
    {
        return DB::transaction(function () use ($id, $userId) {
            $case = CaseFile::findOrFail($id);
            $old = $case->toArray();

            $case->update([
                'status' => $case->status === 'OPEN' ? 'CLOSED' : 'OPEN',
            ]);

            AuditLog::create([
                'action' => 'UPDATE',
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
            ]);
        });
    }

    private function generateCaseNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));

        return "CASE-{$date}-{$random}";
    }

    private function generateTrackerNumber(): string
    {
        $random = strtoupper(Str::random(10));

        return "TRK-{$random}";
    }

    public function getCaseStats(): array
    {
        $total = CaseFile::where('status', '!=', 'DRAFT')->count();
        $open = CaseFile::where('status', 'OPEN')->count();
        $closed = CaseFile::where('status', 'CLOSED')->count();
        $draft = CaseFile::where('status', 'DRAFT')->count();
        $ofw = CaseFile::where('client_type', 'OFW')->where('status', '!=', 'DRAFT')->count();
        $nok = CaseFile::where('client_type', 'NOK')->where('status', '!=', 'DRAFT')->count();
        $totalReferrals = Referral::count();

        return [
            'total_cases' => $total,
            'open_cases' => $open,
            'closed_cases' => $closed,
            'draft_cases' => $draft,
            'ofw_cases' => $ofw,
            'nok_cases' => $nok,
            'total_referrals' => $totalReferrals,
        ];
    }
}
