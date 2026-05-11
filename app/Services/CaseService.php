<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\NextOfKin;
use App\Models\AuditLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CaseService
{
    public function createCase(array $data, string $userId): CaseFile
    {
        return DB::transaction(function () use ($data, $userId) {
            $case = CaseFile::create([
                'case_number' => $this->generateCaseNumber(),
                'tracker_number' => $this->generateTrackerNumber(),
                'client_type' => $data['client_type'],
                'summary' => $data['summary'] ?? null,
                'status' => 'OPEN',
                'consent_given_at' => !empty($data['consent']) ? now() : null,
                'user_id' => $userId,
            ]);

            $client = Client::create([
                'first_name' => $data['client']['first_name'],
                'last_name' => $data['client']['last_name'],
                'middle_name' => $data['client']['middle_name'] ?? null,
                'suffix' => $data['client']['suffix'] ?? null,
                'date_of_birth' => $data['client']['date_of_birth'] ?? null,
                'sex' => $data['client']['sex'] ?? null,
                'email' => $data['client']['email'] ?? null,
                'contact' => $data['client']['contact'] ?? null,
                'case_id' => $case->id,
            ]);

            if (!empty($data['address'])) {
                ClientAddress::create([
                    'client_id' => $client->id,
                    'line1' => $data['address']['line1'],
                    'line2' => $data['address']['line2'] ?? null,
                    'city' => $data['address']['city'] ?? null,
                    'province' => $data['address']['province'] ?? null,
                    'postal_code' => $data['address']['postal_code'] ?? null,
                    'country' => $data['address']['country'] ?? null,
                ]);
            }

            if (!empty($data['employment'])) {
                ClientEmployment::create([
                    'client_id' => $client->id,
                    'employer_name' => $data['employment']['employer_name'] ?? null,
                    'position' => $data['employment']['position'] ?? null,
                    'country' => $data['employment']['country'] ?? null,
                    'start_date' => $data['employment']['start_date'] ?? null,
                    'end_date' => $data['employment']['end_date'] ?? null,
                ]);
            }

            if (!empty($data['next_of_kin']) && !empty($data['next_of_kin']['first_name'])) {
                NextOfKin::create([
                    'case_id' => $case->id,
                    'first_name' => $data['next_of_kin']['first_name'],
                    'last_name' => $data['next_of_kin']['last_name'] ?? null,
                    'relationship' => $data['next_of_kin']['relationship'] ?? null,
                    'contact' => $data['next_of_kin']['contact'] ?? null,
                ]);
            }

            AuditLog::create([
                'action' => 'CREATE',
                'module' => 'CASE',
                'entity_id' => $case->id,
                'new_value' => $case->toArray(),
                'user_id' => $userId,
            ]);

            return $case->load(['client.addresses', 'client.employments', 'nextOfKin', 'user']);
        });
    }

    public function getCases(array $filters = [])
    {
        $query = CaseFile::with(['client', 'user', 'referrals'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
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
            'nextOfKin',
            'referrals.milestones',
            'referrals.agency',
            'referrals.attachments',
            'user',
        ])->findOrFail($id);
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
}
