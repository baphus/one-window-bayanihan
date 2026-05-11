<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;

class TrackingService
{
    public function findCaseByTracker(string $trackerNumber): ?CaseFile
    {
        return CaseFile::with([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.milestones.user',
            'referrals.attachments',
            'user',
        ])->where('tracker_number', $trackerNumber)->first();
    }

    public function generateOtp(string $trackerNumber): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("otp:{$trackerNumber}", $otp, now()->addMinutes(5));
        return $otp;
    }

    public function verifyOtp(string $trackerNumber, string $otp): bool
    {
        $cached = Cache::get("otp:{$trackerNumber}");
        if (!$cached) {
            return false;
        }
        if ($cached !== $otp) {
            return false;
        }
        Cache::forget("otp:{$trackerNumber}");
        return true;
    }

    public function buildTrackingData(CaseFile $case): array
    {
        $client = $case->client;
        $referrals = $case->referrals;

        $caseOverview = [
            'narrative' => $case->summary ?? '',
            'ofw' => $client ? [
                'fullName' => trim("{$client->first_name} {$client->middle_name} {$client->last_name} {$client->suffix}"),
                'dateOfBirth' => $client->date_of_birth?->toDateString() ?? '',
                'gender' => $client->sex ?? '',
                'homeAddress' => $client->addresses->first()
                    ? trim("{$client->addresses->first()->line1}, {$client->addresses->first()->city}")
                    : '',
                'homeAddressParts' => $this->formatAddressParts($client->addresses->first()),
                'specialCategories' => [],
            ] : null,
            'nextOfKin' => null,
            'workHistory' => $client && $client->employments->isNotEmpty() ? [
                'lastCountry' => $client->employments->first()->country ?? '',
                'lastPosition' => $client->employments->first()->position ?? '',
                'arrivalDate' => $client->employments->first()->end_date?->toDateString() ?? '',
            ] : null,
        ];

        $timeline = collect();
        foreach ($referrals as $ref) {
            $timeline->push([
                'date' => $ref->created_at->toISOString(),
                'agency' => $ref->agency?->name ?? 'Unknown',
                'title' => "Referral sent to {$ref->agency?->name}",
                'detail' => $ref->required_services,
                'icon' => 'send',
                'logoUrl' => '',
            ]);

            foreach ($ref->milestones as $ms) {
                $timeline->push([
                    'date' => $ms->created_at->toISOString(),
                    'agency' => $ref->agency?->name ?? 'Unknown',
                    'title' => $ms->title,
                    'detail' => $ms->description ?? '',
                    'icon' => 'milestone',
                    'logoUrl' => '',
                ]);
            }
        }

        $auditLogs = AuditLog::where('entity_id', $case->id)
            ->orWhereIn('entity_id', $referrals->pluck('id'))
            ->orderBy('timestamp')
            ->get();

        foreach ($auditLogs as $log) {
            $timeline->push([
                'date' => $log->timestamp->toISOString(),
                'agency' => 'System',
                'title' => "{$log->action} {$log->module}",
                'detail' => $log->new_value ? json_encode($log->new_value) : '',
                'icon' => 'system',
                'logoUrl' => '',
            ]);
        }

        $timeline = $timeline->sortBy('date')->values()->toArray();

        $agencyCards = $referrals->map(function ($ref) {
            $latestMilestone = $ref->milestones->sortByDesc('created_at')->first();
            return [
                'name' => $ref->agency?->name ?? 'Unknown',
                'note' => $ref->notes ?? '',
                'status' => $ref->status,
                'statusTone' => match ($ref->status) {
                    'PENDING' => 'yellow',
                    'PROCESSING' => 'blue',
                    'FOR_COMPLIANCE' => 'orange',
                    'COMPLETED' => 'green',
                    'REJECTED' => 'red',
                    default => 'gray',
                },
                'borderTone' => 'border-gray-200',
                'textTone' => 'text-gray-700',
                'lineTone' => 'bg-gray-200',
                'steps' => [
                    ['label' => 'Pending', 'state' => 'complete'],
                    ['label' => 'Processing', 'state' => $ref->status === 'PROCESSING' ? 'active' : ($ref->milestones->count() > 0 ? 'complete' : 'pending')],
                    ['label' => 'Completed', 'state' => in_array($ref->status, ['COMPLETED', 'REJECTED']) ? 'complete' : 'pending'],
                ],
                'latestMilestoneLabel' => $latestMilestone?->title,
                'latestMilestonePath' => route('referrals.show', $ref),
            ];
        })->toArray();

        return [
            'trackingId' => $case->tracker_number,
            'trackedCase' => [
                'id' => $case->id,
                'caseNo' => $case->case_number,
                'clientName' => $client ? "{$client->first_name} {$client->last_name}" : 'Unknown',
                'clientType' => $case->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                'service' => $referrals->first()?->required_services ?? '',
                'milestone' => '',
                'status' => $case->status === 'OPEN' ? 'PENDING' : 'COMPLETED',
                'createdAt' => $case->created_at->toISOString(),
                'updatedAt' => $case->updated_at->toISOString(),
            ],
            'caseOverview' => $caseOverview,
            'caseTimeline' => $timeline,
            'trackingAgencies' => $agencyCards,
        ];
    }

    private function formatAddressParts($address): array
    {
        if (!$address) {
            return [
                'regionCode' => '', 'regionName' => '',
                'provinceCode' => '', 'provinceName' => '',
                'municipalityCode' => '', 'municipalityName' => '',
                'barangayCode' => '', 'barangayName' => '',
                'streetAddress' => '',
            ];
        }
        return [
            'regionCode' => '',
            'regionName' => '',
            'provinceCode' => '',
            'provinceName' => $address->province ?? '',
            'municipalityCode' => '',
            'municipalityName' => $address->city ?? '',
            'barangayCode' => '',
            'barangayName' => '',
            'streetAddress' => $address->line1 ?? '',
        ];
    }
}
