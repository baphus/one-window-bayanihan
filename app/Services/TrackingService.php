<?php

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Milestone;
use App\Models\Referral;
use Illuminate\Support\Collection;

class TrackingService
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly AddressNameResolver $addressResolver,
    ) {}

    public function findCaseByTracker(string $trackerNumber): ?CaseFile
    {
        $relations = [
            'client.addresses',
            'client.employments',
            'client.nextOfKin',
            'referrals.agency',
            'referrals.complianceRequirements',
            'referrals.milestones.user',
            'user:id,name',
            'category',
        ];
        if (method_exists(CaseFile::class, 'categories')) {
            $relations[] = 'categories';
        }

        return CaseFile::with($relations)->where('tracker_number', $trackerNumber)->first();
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
        return CacheHelper::safeRemember('tracking:data:'.$case->id, 90, function () use ($case) {
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
                    'fullName' => trim("{$client->first_name} {$client->middle_initial} {$client->last_name} {$client->suffix}"),
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

            // Single read of the append-only client-facing event log. Everything the
            // timeline and step machines need derives from this one query.
            $events = CaseEvent::where('case_id', $case->id)
                ->orderBy('occurred_at')
                ->orderBy('sequence')
                ->get();

            // Referral IDs that ever entered FOR_COMPLIANCE, per the event record.
            $complianceReferralIds = $events
                ->where('type', CaseEvent::TYPE_REFERRAL_STATUS_CHANGED)
                ->filter(fn (CaseEvent $event) => ($event->meta['to'] ?? null) === 'FOR_COMPLIANCE')
                ->pluck('referral_id')
                ->unique()
                ->all();

            // Agency cards with dynamic step progress
            $agencyCards = $referrals->map(function ($ref) use ($case, $complianceReferralIds) {
                $latestMilestone = $ref->milestones->sortByDesc('created_at')->first();
                $hasCompliance = $ref->status === 'FOR_COMPLIANCE' || in_array($ref->id, $complianceReferralIds, true);

                return [
                    'referralId' => $ref->id,
                    'name' => $ref->agency?->name ?? 'Unknown',
                    'note' => $ref->notes ?? '',
                    'status' => $ref->status,
                    'milestoneCount' => $ref->milestones->count(),
                    'steps' => $this->buildAgencySteps($ref, $hasCompliance),
                    'latestMilestoneLabel' => $latestMilestone?->title,
                    'milestonesUrl' => route('track.milestones', [
                        'tracker_number' => $case->tracker_number,
                        'referral' => $ref->id,
                    ]),
                    'compliance_requirements' => $ref->complianceRequirements->map(fn ($cr) => [
                        'id' => $cr->id,
                        'service_name' => $cr->service_name,
                        'requirement_name' => $cr->requirement_name,
                        'status' => $cr->status,
                        'completed_at' => $cr->completed_at?->toISOString(),
                    ])->values()->toArray(),
                ];
            })->toArray();

            // Overall completion percentage. Rejection is an outcome, not progress —
            // REJECTED weighs 0 and is surfaced separately via rejectedCount.
            $totalWeight = 0;
            $maxWeight = 0;
            foreach ($referrals as $ref) {
                $maxWeight += 100;
                $totalWeight += match ($ref->status) {
                    'COMPLETED' => 100,
                    'PROCESSING' => 66,
                    'FOR_COMPLIANCE' => 33,
                    'PENDING' => 10,
                    default => 0,
                };
            }
            $completionPercentage = $maxWeight > 0 ? (int) round(($totalWeight / $maxWeight) * 100) : 0;
            $rejectedCount = $referrals->where('status', 'REJECTED')->count();

            $unreadCount = 0;
            $caseNotifications = [];

            $recipientEmail = app(CaseRecipientResolver::class)->resolve($case);
            if ($recipientEmail) {
                $notifications = CaseNotification::where('case_id', $case->id)
                    ->where('client_email', $recipientEmail)
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
                    'categories' => $this->categoryPresentation($case),
                    'service' => $referrals->first()?->required_services ?? '',
                    'milestone' => '',
                    'status' => match ($case->status) {
                        'OPEN' => 'IN_PROGRESS',
                        'CLOSED' => 'RESOLVED',
                        'ARCHIVED' => 'ARCHIVED',
                        default => 'UNKNOWN',
                    },
                    'createdAt' => $case->created_at->toISOString(),
                    'updatedAt' => $case->updated_at->toISOString(),
                ],
                'caseOverview' => $caseOverview,
                'milestoneTimeline' => $this->buildMilestoneTimeline($case, $events),
                'completionPercentage' => $completionPercentage,
                'rejectedCount' => $rejectedCount,
                'trackingAgencies' => $agencyCards,
                'caseNotifications' => [
                    'unread_count' => $unreadCount,
                    'items' => $caseNotifications,
                ],
            ];
        });
    }

    private function categoryPresentation(CaseFile $case): array
    {
        $categories = method_exists($case, 'categories') ? $case->categories : collect();
        if ($categories->isNotEmpty()) {
            return $categories->map(fn ($category) => ['name' => $category->name, 'color' => $category->color])->values()->all();
        }

        return $case->category ? [['name' => $case->category->name, 'color' => $case->category->color]] : [];
    }

    public function buildAgencyMilestonesData(CaseFile $case, Referral $referral): array
    {
        return CacheHelper::safeRemember('tracking:milestones:'.$case->id.':'.$referral->id, 120, function () use ($case, $referral) {
            $case->loadMissing(['client.addresses', 'client.employments']);
            $referral->loadMissing(['agency', 'milestones.user']);

            $agencyName = $referral->agency?->name ?? 'Unknown agency';
            $latestMilestone = $referral->milestones->sortByDesc('created_at')->first();
            $referralStatusTitle = match ($referral->status) {
                'PROCESSING' => $agencyName.' is now processing your referral',
                'FOR_COMPLIANCE' => 'Additional documents may be needed for '.$agencyName,
                'COMPLETED' => 'Your referral with '.$agencyName.' has been completed',
                'REJECTED' => $agencyName.' was unable to process your referral',
                default => 'Referral received by '.$agencyName,
            };
            $referralStatusDescription = match ($referral->status) {
                'PROCESSING' => 'The agency is actively reviewing your request.',
                'FOR_COMPLIANCE' => 'Please prepare any additional requirements the agency may request.',
                'COMPLETED' => 'The agency has finished its part of the referral workflow.',
                'REJECTED' => 'This referral was not accepted for processing.',
                default => $referral->required_services
                    ? 'Requested services: '.$referral->required_services
                    : 'Your referral is waiting for the first milestone update.',
            };
            // Public page — attribute updates to the agency, never to staff names.
            $milestones = $referral->milestones
                ->sortBy('created_at')
                ->values()
                ->map(fn (Milestone $milestone) => [
                    'date' => $milestone->created_at->toISOString(),
                    'title' => $milestone->title,
                    'description' => $milestone->description ?? '',
                    'by' => $agencyName,
                ])
                ->toArray();

            return [
                'trackingId' => $case->tracker_number,
                'trackedCase' => [
                    'caseNo' => $case->case_number,
                    'clientName' => $case->client ? trim("{$case->client->first_name} {$case->client->last_name}") : 'Unknown',
                    'clientType' => $case->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                    'status' => match ($case->status) {
                        'OPEN' => 'IN_PROGRESS',
                        'CLOSED' => 'RESOLVED',
                        'ARCHIVED' => 'ARCHIVED',
                        default => 'UNKNOWN',
                    },
                ],
                'agencyMilestones' => [
                    'agencyName' => $agencyName,
                    'status' => $referral->status,
                    'requiredServices' => $referral->required_services ?? '',
                    'notes' => $referral->notes ?? '',
                    'milestoneCount' => $referral->milestones->count(),
                    'latestUpdate' => $latestMilestone
                        ? [
                            'date' => $latestMilestone->created_at->toISOString(),
                            'title' => $latestMilestone->title,
                            'description' => $latestMilestone->description ?? '',
                            'by' => $agencyName,
                        ]
                        : [
                            'date' => $referral->updated_at?->toISOString() ?? $referral->created_at->toISOString(),
                            'title' => $referralStatusTitle,
                            'description' => $referralStatusDescription,
                            'by' => $agencyName,
                        ],
                    'milestones' => $milestones,
                ],
            ];
        });
    }

    /**
     * Map the case's recorded events to the client-facing timeline.
     *
     * The event log is the single source of truth — no reconstruction from
     * current state, audit logs, or milestone-title matching. Agency names
     * resolve through the (eager-loaded) referral relation at read time so
     * renames stay consistent.
     */
    private function buildMilestoneTimeline(CaseFile $case, Collection $events): array
    {
        $agencyNames = $case->referrals->mapWithKeys(
            fn ($ref) => [$ref->id => $ref->agency?->name ?? 'a partner agency']
        );

        return $events
            ->map(fn (CaseEvent $event) => [
                'date' => $event->occurred_at->toISOString(),
                'type' => $event->type,
                'agency' => $event->referral_id ? ($agencyNames[$event->referral_id] ?? null) : null,
                'referralId' => $event->referral_id,
                'title' => $event->title,
                'description' => $event->description ?? '',
            ])
            ->values()
            ->toArray();
    }

    /**
     * Build dynamic agency progress steps based on the referral's current status
     * and compliance history (derived from recorded status-change events).
     * Returns 3–6 steps with label and state keys.
     */
    private function buildAgencySteps(Referral $referral, bool $hasCompliance): array
    {
        $agencyName = $referral->agency?->name ?? 'Agency';
        $status = $referral->status;

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
