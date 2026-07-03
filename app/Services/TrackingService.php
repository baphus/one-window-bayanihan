<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Referral;
use Carbon\Carbon;

class TrackingService
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly AuditLogFormatter $auditFormatter,
        private readonly AddressNameResolver $addressResolver,
    ) {}

    /**
     * Query the audit log for the actual status-change timestamp of a referral.
     * Falls back gracefully when no status-change audit log exists.
     *
     * Two code paths create referral audit logs with different module casing:
     * - ReferralService::updateStatus() uses 'REFERRAL' (uppercase)
     * - AuditObserver::updated() uses 'referral' (lowercase)
     *
     * Must match BOTH to find the status-change event reliably.
     */
    private function getStatusChangeTimestamp(Referral $referral): ?Carbon
    {
        $logs = AuditLog::whereIn('module', ['REFERRAL', 'referral'])
            ->where('entity_id', $referral->id)
            ->where('action', 'UPDATE')
            ->orderBy('timestamp', 'desc')
            ->get()
            ->filter(fn (AuditLog $log) => ($log->old_value['status'] ?? null) !== ($log->new_value['status'] ?? null)
            );

        return $logs->first()?->timestamp;
    }

    public function findCaseByTracker(string $trackerNumber): ?CaseFile
    {
        return CaseFile::with([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.complianceRequirements',
            'referrals.milestones.user',
            'referrals.attachments',
            'user',
        ])->where('tracker_number', $trackerNumber)->first();
    }

    public function generateOtp(string $identifier, string $purpose = 'track'): string
    {
        return $this->otpService->generate($identifier, $purpose);
    }

    public function verifyOtp(string $identifier, string $otp, string $purpose = 'track'): bool
    {
        return $this->otpService->verify($identifier, $purpose, $otp);
    }

    public function buildTrackingData(CaseFile $case): array
    {
        $client = $case->client;
        $referrals = $case->referrals;
        $caseNotifications = [];
        $unreadCount = 0;

        $primaryNok = $client && $client->nextOfKin->isNotEmpty()
            ? ($client->nextOfKin->where('is_primary', true)->first() ?? $client->nextOfKin->first())
            : null;

        $caseOverview = [
            'narrative' => $case->summary ?? '',
            'ofw' => $client ? [
                'fullName' => trim("{$client->first_name} {$client->middle_name} {$client->last_name} {$client->suffix}"),
                'dateOfBirth' => $client->date_of_birth?->toDateString() ?? '',
                'gender' => $client->sex ?? '',
                'homeAddress' => $client->addresses->first()
                    ? $this->addressResolver->format(
                        $client->addresses->first()->street,
                        $client->addresses->first()->barangay,
                        $client->addresses->first()->city_municipality,
                        $client->addresses->first()->province,
                        $client->addresses->first()->region,
                    )
                    : '',
                'homeAddressParts' => $this->formatAddressParts($client->addresses->first()),
                'specialCategories' => [],
            ] : null,
            'nextOfKin' => $primaryNok ? [
                'fullName' => $primaryNok->first_name.' '.$primaryNok->last_name,
                'relationship' => $primaryNok->relationship,
                'contact' => $primaryNok->phone_number ?? $primaryNok->email,
            ] : null,
            'workHistory' => $client && $client->employments->isNotEmpty() ? [
                'lastCountry' => $client->employments->first()->last_country ?? $client->employments->first()->country ?? '',
                'lastPosition' => $client->employments->first()->last_position ?? $client->employments->first()->position ?? '',
                'arrivalDate' => $client->employments->first()->date_of_arrival?->toDateString() ?? $client->employments->first()->end_date?->toDateString() ?? '',
            ] : null,
        ];

        // Legacy caseTimeline (kept for backward compatibility — includes raw audit logs)
        $timeline = collect();
        $_sortIndex = 0;
        foreach ($referrals as $ref) {
            $agencyLogo = $ref->agency?->logo_url ?? '';
            $timeline->push([
                'date' => $ref->created_at->toISOString(),
                'agency' => $ref->agency?->name ?? 'Unknown',
                'title' => "Referral sent to {$ref->agency?->name}",
                'detail' => $ref->required_services,
                'icon' => 'send',
                'logoUrl' => $agencyLogo,
                '_sort_index' => $_sortIndex++,
            ]);

            foreach ($ref->milestones as $ms) {
                $timeline->push([
                    'date' => $ms->created_at->toISOString(),
                    'agency' => $ref->agency?->name ?? 'Unknown',
                    'title' => $ms->title,
                    'detail' => $ms->description ?? '',
                    'icon' => 'milestone',
                    'logoUrl' => $agencyLogo,
                    '_sort_index' => $_sortIndex++,
                ]);
            }
        }

        $auditLogs = AuditLog::with('user')->where('entity_id', $case->id)
            ->orWhereIn('entity_id', $referrals->pluck('id'))
            ->orderBy('timestamp')
            ->get();

        // Build lookup: referral ID → timestamp of inline referral_sent event
        $referralSentDates = [];
        foreach ($referrals as $ref) {
            $referralSentDates[$ref->id] = $ref->created_at->timestamp;
        }

        foreach ($auditLogs as $log) {
            // Skip referral CREATE audit logs that duplicate inline referral_sent events
            if (
                $log->action === 'CREATE'
                && in_array($log->module, ['referral', 'REFERRAL'], true)
                && isset($referralSentDates[$log->entity_id])
                && abs($log->timestamp->timestamp - $referralSentDates[$log->entity_id]) <= 5
            ) {
                continue;
            }
            $display = $this->auditFormatter->formatForDisplay($log);

            $timeline->push([
                'date' => $display['timestamp'] ?? $log->timestamp->toISOString(),
                'agency' => $display['actor'] ?? 'System',
                'title' => $display['message'],
                'detail' => $display['detail'],
                'icon' => match ($display['action']) {
                    'CREATE' => 'create',
                    'UPDATE' => 'update',
                    'DELETE' => 'delete',
                    'LOGIN', 'LOGOUT' => 'auth',
                    default => 'system',
                },
                'logoUrl' => '',
                '_sort_index' => $_sortIndex++,
            ]);
        }

        $timeline = $timeline->sortBy([['date', 'asc'], ['_sort_index', 'asc']])->values()->toArray();

        // Agency cards with 4-step progress model
        $agencyCards = $referrals->map(function ($ref) {
            $latestMilestone = $ref->milestones->sortByDesc('created_at')->first();

            return [
                'name' => $ref->agency?->name ?? 'Unknown',
                'note' => $ref->notes ?? '',
                'status' => $ref->status,
                'statusTone' => match ($ref->status) {
                    'PENDING' => 'bg-amber-100 text-amber-800',
                    'PROCESSING' => 'bg-blue-100 text-blue-800',
                    'FOR_COMPLIANCE' => 'bg-orange-100 text-orange-800',
                    'COMPLETED' => 'bg-green-100 text-green-800',
                    'REJECTED' => 'bg-red-100 text-red-800',
                    default => 'bg-slate-100 text-slate-700',
                },
                'borderTone' => match ($ref->status) {
                    'PENDING' => 'border-amber-300',
                    'PROCESSING' => 'border-blue-300',
                    'FOR_COMPLIANCE' => 'border-orange-300',
                    'COMPLETED' => 'border-green-300',
                    'REJECTED' => 'border-red-300',
                    default => 'border-slate-200',
                },
                'textTone' => match ($ref->status) {
                    'PENDING' => 'text-amber-700',
                    'PROCESSING' => 'text-blue-700',
                    'FOR_COMPLIANCE' => 'text-orange-700',
                    'COMPLETED' => 'text-green-700',
                    'REJECTED' => 'text-red-700',
                    default => 'text-slate-600',
                },
                'lineTone' => match ($ref->status) {
                    'PENDING' => 'bg-amber-400',
                    'PROCESSING' => 'bg-blue-400',
                    'FOR_COMPLIANCE' => 'bg-orange-400',
                    'COMPLETED' => 'bg-green-400',
                    'REJECTED' => 'bg-red-400',
                    default => 'bg-slate-300',
                },
                'steps' => $this->buildAgencySteps($ref),
                'latestMilestoneLabel' => $latestMilestone?->title,
                'compliance_requirements' => $ref->complianceRequirements->map(fn ($cr) => [
                    'id' => $cr->id,
                    'service_name' => $cr->service_name,
                    'requirement_name' => $cr->requirement_name,
                    'status' => $cr->status,
                    'completed_at' => $cr->completed_at?->toISOString(),
                ])->values()->toArray(),
            ];
        })->toArray();

        $unreadCount = 0;
        $caseNotifications = [];

        if ($case->client && $case->client->email) {
            $notifications = CaseNotification::where('case_id', $case->id)
                ->where('client_email', $case->client->email)
                ->orderBy('created_at', 'desc')
                ->get();

            $unreadCount = $notifications->whereNull('read_at')->count();

            $caseNotifications = $notifications->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'related_url' => $notification->related_url,
                'created_at' => $notification->created_at->toISOString(),
                'read' => $notification->read_at !== null,
            ])->values()->toArray();
        }

        return [
            'trackingId' => $case->tracker_number,
            'trackedCase' => [
                'id' => $case->id,
                'caseNo' => $case->case_number,
                'clientName' => $client ? "{$client->first_name} {$client->last_name}" : 'Unknown',
                'clientType' => $case->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                'service' => $referrals->first()?->required_services ?? '',
                'milestone' => '',
                'status' => match ($case->status) {
                    'OPEN' => 'IN_PROGRESS',
                    'CLOSED' => 'RESOLVED',
                    'ARCHIVED' => 'ARCHIVED',
                    'DRAFT' => 'BEING_PREPARED',
                    default => 'UNKNOWN',
                },
                'createdAt' => $case->created_at->toISOString(),
                'updatedAt' => $case->updated_at->toISOString(),
            ],
            'caseOverview' => $caseOverview,
            'caseTimeline' => $timeline,
            'milestoneTimeline' => $this->buildMilestoneTimeline($case),
            'trackingAgencies' => $agencyCards,
            'caseNotifications' => [
                'unread_count' => $unreadCount,
                'items' => $caseNotifications,
            ],
        ];
    }

    /**
     * Build a clean, client-facing milestone timeline.
     * Contains ONLY human-readable events — no raw audit logs, no UUIDs, no field names.
     */
    private function buildMilestoneTimeline(CaseFile $case): array
    {
        $events = collect();
        $referrals = $case->referrals;

        $_sortIndex = 0;

        // 1. Case opened — always first
        $events->push([
            'date' => $case->created_at->toISOString(),
            'type' => 'case_opened',
            'agency' => null,
            'title' => 'Your case has been opened',
            'description' => 'Your case is now being reviewed by the Bayanihan team.',
            '_sort_index' => $_sortIndex++,
        ]);

        foreach ($referrals as $ref) {
            $agencyName = $ref->agency?->name ?? 'an agency';

            // 2. Referral sent
            $events->push([
                'date' => $ref->created_at->toISOString(),
                'type' => 'referral_sent',
                'agency' => $agencyName,
                'title' => 'Referred to '.$agencyName,
                'description' => $ref->required_services
                    ? 'Services requested: '.$ref->required_services
                    : '',
                '_sort_index' => $_sortIndex++,
            ]);

            // 3. Referral status (only if past PENDING — PENDING is already covered by referral_sent)
            if (! in_array($ref->status, ['PENDING'], true)) {
                $statusTitle = match ($ref->status) {
                    'PROCESSING' => $agencyName.' is now processing your case',
                    'FOR_COMPLIANCE' => 'Additional documents may be needed for '.$agencyName,
                    'COMPLETED' => 'Your referral with '.$agencyName.' has been completed',
                    'REJECTED' => $agencyName.' was unable to process your referral',
                    default => $agencyName.' updated your referral',
                };

                $events->push([
                    'date' => ($this->getStatusChangeTimestamp($ref) ?? $ref->updated_at)->toISOString(),
                    'type' => 'referral_status',
                    'agency' => $agencyName,
                    'title' => $statusTitle,
                    'description' => '',
                    '_sort_index' => $_sortIndex++,
                ]);
            }

            // 4. Milestones
            foreach ($ref->milestones->sortBy('created_at') as $ms) {
                $events->push([
                    'date' => $ms->created_at->toISOString(),
                    'type' => 'milestone',
                    'agency' => $agencyName,
                    'title' => $ms->title,
                    'description' => $ms->description ?? '',
                    '_sort_index' => $_sortIndex++,
                ]);
            }
        }

        // 5. Case closed — only if CLOSED
        if ($case->status === 'CLOSED') {
            $events->push([
                'date' => ($case->closed_at ?? $case->updated_at)->toISOString(),
                'type' => 'case_closed',
                'agency' => null,
                'title' => 'Your case has been resolved',
                'description' => 'All referrals have been processed and your case is now closed.',
                '_sort_index' => $_sortIndex++,
            ]);
        }

        return $events->sortBy([['date', 'asc'], ['_sort_index', 'asc']])->values()->toArray();
    }

    /**
     * Build dynamic agency progress steps based on the referral's current status
     * and compliance history. Returns 3–6 steps with label and state keys.
     */
    private function buildAgencySteps(Referral $referral): array
    {
        $agencyName = $referral->agency?->name ?? 'Agency';
        $status = $referral->status;
        $hasCompliance = $this->hasComplianceHistory($referral);

        $steps = [];

        // Step 1: Created — always complete
        $steps[] = ['label' => 'Created', 'state' => 'complete'];

        // Step 2: Referred to {agency} — always complete
        $steps[] = ['label' => "Referred to {$agencyName}", 'state' => 'complete'];

        // Step 3: Received by {agency}
        if ($status === 'PENDING') {
            $steps[] = ['label' => "Received by {$agencyName}", 'state' => 'active'];

            return $steps;
        }
        $steps[] = ['label' => "Received by {$agencyName}", 'state' => 'complete'];

        if ($status === 'REJECTED') {
            return $steps;
        }

        if ($hasCompliance || $status === 'FOR_COMPLIANCE') {
            // === COMPLIANCE PATH ===
            if ($status === 'FOR_COMPLIANCE') {
                $steps[] = ['label' => 'For Compliance', 'state' => 'active'];
                $steps[] = ['label' => 'Processing after compliance', 'state' => 'pending'];
                $steps[] = ['label' => 'Completed', 'state' => 'pending'];
            } elseif ($status === 'COMPLETED') {
                $steps[] = ['label' => 'For Compliance', 'state' => 'complete'];
                $steps[] = ['label' => 'Processing after compliance', 'state' => 'complete'];
                $steps[] = ['label' => 'Completed', 'state' => 'active'];
            } elseif ($status === 'REJECTED') {
                $steps[] = ['label' => 'For Compliance', 'state' => 'complete'];
                $steps[] = ['label' => 'Processing after compliance', 'state' => 'complete'];
                $steps[] = ['label' => 'Completed', 'state' => 'pending'];
            } else {
                // PROCESSING (after having been in compliance)
                $steps[] = ['label' => 'For Compliance', 'state' => 'complete'];
                $steps[] = ['label' => 'Processing after compliance', 'state' => 'active'];
                $steps[] = ['label' => 'Completed', 'state' => 'pending'];
            }
        } else {
            // === STANDARD PATH (no compliance history) ===
            if ($status === 'PROCESSING') {
                $steps[] = ['label' => 'Processing', 'state' => 'active'];
                $steps[] = ['label' => 'Completed', 'state' => 'pending'];
            } elseif ($status === 'COMPLETED') {
                $steps[] = ['label' => 'Processing', 'state' => 'complete'];
                $steps[] = ['label' => 'Completed', 'state' => 'active'];
            } else {
                // fallback (shouldn't happen but handle gracefully)
                $steps[] = ['label' => 'Processing', 'state' => 'pending'];
                $steps[] = ['label' => 'Completed', 'state' => 'pending'];
            }
        }

        return $steps;
    }

    /**
     * Determine whether the referral has ever entered a compliance-related state.
     * Returns true if status is FOR_COMPLIANCE or any milestone title contains "compli".
     */
    private function hasComplianceHistory(Referral $referral): bool
    {
        if ($referral->status === 'FOR_COMPLIANCE') {
            return true;
        }

        return $referral->milestones->contains(function ($ms) {
            return mb_stripos($ms->title, 'compli') !== false;
        });
    }

    /**
     * Determine the visual state of a step given the current referral status.
     * Returns 'complete', 'active', or 'pending'.
     */
    private function stepState(string $currentStatus, string $stepStatus): string
    {
        $order = [
            'PENDING' => 0,
            'PROCESSING' => 1,
            'FOR_COMPLIANCE' => 2,
            'COMPLETED' => 3,
            'REJECTED' => 4,
        ];

        // REJECTED: only the first step (Received) is complete, rest are pending
        if ($currentStatus === 'REJECTED') {
            return $stepStatus === 'PENDING' ? 'complete' : 'pending';
        }

        $current = $order[$currentStatus] ?? 0;
        $step = $order[$stepStatus] ?? 0;

        if ($current === $step) {
            return 'active';
        }

        return $current > $step ? 'complete' : 'pending';
    }

    private function formatAddressParts($address): array
    {
        if (! $address) {
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
            'regionName' => $this->addressResolver->resolve($address->region ?? null),
            'provinceCode' => '',
            'provinceName' => $this->addressResolver->resolve($address->province ?? null),
            'municipalityCode' => '',
            'municipalityName' => $this->addressResolver->resolve($address->city_municipality ?? null),
            'barangayCode' => '',
            'barangayName' => $this->addressResolver->resolve($address->barangay ?? null),
            'streetAddress' => $address->street ?? '',
        ];
    }
}
