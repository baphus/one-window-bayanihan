<?php

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Milestone;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TrackingService
{
    public const SESSION_KEY = 'tracking.verified';

    /**
     * How long a verified tracking session stays usable. The binding records a
     * Unix timestamp in `verified_at` and is rejected past this age, so a phone
     * left unattended on the public tracking page does not stay signed in.
     */
    private const BINDING_TTL_SECONDS = 30 * 60;

    /**
     * Payload version for the cached agency-milestones payload. Bump whenever
     * a key is removed or reshaped so already-cached entries from a previous
     * release are orphaned instead of served. v2: dropped the internal
     * `agencyMilestones.notes` key (staff free text).
     */
    private const MILESTONES_PAYLOAD_VERSION = 'v2';

    /**
     * Raw internal code fields stored in CaseNotification.data by the
     * writers (referral_created / referral_status_changed /
     * case_status_updated). Stripped from the client-facing payload.
     *
     * @var list<string>
     */
    private const CLIENT_STRIPPED_DATA_KEYS = ['status', 'old_status', 'new_status'];

    /**
     * Rows of case history loaded per public tracking render. A long-running
     * case would otherwise grow without bound on the public endpoint. The cap
     * keeps the newest events; the builder re-sorts them oldest-first for
     * display.
     *
     * ponytail: fixed cap, no "load more" — upgrade to real pagination when
     * an OFW case actually needs to see older events.
     */
    private const TIMELINE_EVENT_LIMIT = 200;

    private const NOTIFICATION_LIMIT = 50;

    public static function trackingMilestonesCacheKey(string $caseId, string $referralId): string
    {
        return 'tracking:milestones:'.$caseId.':'.$referralId.':'.self::MILESTONES_PAYLOAD_VERSION;
    }

    public function __construct(
        private readonly OtpService $otpService,
        private readonly PhilippineAddressService $addressResolver,
    ) {}

    public function findCaseByTracker(string $trackerNumber): ?CaseFile
    {
        $relations = [
            'client.addresses',
            'client.employments',
            'client.nextOfKin',
            'referrals.agency',
            'referrals.services',
            'referrals.milestones.user',
            'user:id,name',
            'category',
        ];
        if (method_exists(CaseFile::class, 'categories')) {
            $relations[] = 'categories';
        }

        return CaseFile::with($relations)
            ->clientVisible()
            ->where('tracker_number', $trackerNumber)
            ->first();
    }

    public function emailMatchesCase(CaseFile $case, string $email): bool
    {
        return $case->client?->email !== null
            && hash_equals(strtolower(trim($case->client->email)), strtolower(trim($email)));
    }

    public function hasValidSessionBinding(Request $request, string $trackerNumber): bool
    {
        $binding = $request->session()->get(self::SESSION_KEY);

        if (! is_array($binding)) {
            return false;
        }

        if (($binding['tracker_number'] ?? null) !== $trackerNumber) {
            return false;
        }

        if (! is_string($binding['email'] ?? null) || $binding['email'] === '') {
            return false;
        }

        // A numeric Unix timestamp, not a parsed date string: comparing an
        // integer against time() removes any dependency on the timezone or
        // format the value was serialised with.
        if (! isset($binding['verified_at']) || ! is_int($binding['verified_at'])) {
            return false;
        }

        return time() < $binding['verified_at'] + self::BINDING_TTL_SECONDS;
    }

    public function generateOtp(string $identifier, string $purpose = 'track', ?string $sessionId = null): string
    {
        return $this->otpService->generate($identifier, $purpose, $sessionId);
    }

    public function verifyOtp(string $identifier, string $otp, string $purpose = 'track', ?string $sessionId = null): bool
    {
        return $sessionId !== null && $this->otpService->verify($identifier, $purpose, $otp, $sessionId);
    }

    /**
     * Client-facing tracking payload for the given case.
     *
     * @param  bool  $forOfwPortal  True when rendered inside the authenticated
     *                              OFW portal: "View all updates" then links to
     *                              an authenticated milestone route instead of
     *                              the public tracking-session route, which
     *                              would 404 because the OFW never completed
     *                              the anonymous /track OTP binding.
     */
    public function buildTrackingData(CaseFile $case, bool $forOfwPortal = false): array
    {
        $client = $case->client;
        $referrals = $case->referrals;
        $caseNotifications = [];
        $unreadCount = 0;

        $primaryNok = $client && $client->nextOfKin->isNotEmpty()
            ? ($client->nextOfKin->where('is_primary', true)->first() ?? $client->nextOfKin->first())
            : null;

        $homeAddress = $client?->addresses->first();
        $lastEmployment = $client?->employments->first();

        $caseOverview = [
            'narrative' => $case->summary ?? '',
            'ofw' => $client ? [
                'fullName' => trim("{$client->first_name} {$client->middle_name} {$client->last_name} {$client->suffix}"),
                'dateOfBirth' => $client->date_of_birth?->toDateString() ?? '',
                'gender' => $client->sex ?? '',
                'homeAddress' => $homeAddress
                    ? $this->addressResolver->format(
                        $homeAddress->street,
                        $homeAddress->barangay,
                        $homeAddress->city_municipality,
                        $homeAddress->province,
                        $homeAddress->region,
                    )
                    : '',
                'homeAddressParts' => $this->formatAddressParts($homeAddress),
                'specialCategories' => [],
            ] : null,
            'nextOfKin' => $primaryNok ? [
                'fullName' => $primaryNok->first_name.' '.$primaryNok->last_name,
                'relationship' => $primaryNok->relationship,
                'contact' => $primaryNok->phone_number ?? $primaryNok->email,
            ] : null,
            'workHistory' => $lastEmployment ? [
                'lastCountry' => $lastEmployment->last_country ?? $lastEmployment->country ?? '',
                'lastPosition' => $lastEmployment->last_position ?? $lastEmployment->position ?? '',
                'arrivalDate' => $lastEmployment->date_of_arrival?->toDateString() ?? $lastEmployment->end_date?->toDateString() ?? '',
            ] : null,
        ];

        // Single read of the append-only client-facing event log. Everything the
        // timeline and step machines need derives from this one query. Capped:
        // a case that stays open for two years must not build an unbounded
        // timeline on every refresh of the public, unauthenticated endpoint.
        //
        // The cap keeps the NEWEST events, so the inner query orders descending
        // (that is the direction the limit must trim from) and the outer query
        // restores the display order — ascending, oldest first — the timeline
        // and step builder have always consumed.
        $events = CaseEvent::where('case_id', $case->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('sequence')
            ->limit(self::TIMELINE_EVENT_LIMIT)
            ->get()
            ->sortBy([
                ['occurred_at', 'asc'],
                ['sequence', 'asc'],
            ])
            ->values();

        // Agency cards with dynamic step progress.
        // INVARIANT (applies to every client-facing payload in this service,
        // i.e. both this builder and buildAgencyMilestonesData): staff
        // free-text referral notes are NEVER client-safe and must not be
        // read here, even indirectly. Consumers of these payloads are the
        // public /track page, the OFW portal, and nothing else — so a
        // `note`/`notes` key added to either builder leaks internal text to
        // an anonymous or citizen-owned client. Staff read notes from the
        // referral record on Referral/Show, never from a tracking payload.
        $agencyCards = $referrals->map(function ($ref) use ($case, $forOfwPortal) {
            $latestMilestone = $ref->milestones->sortByDesc('created_at')->first();
            $hasCompliance = $ref->status === 'FOR_COMPLIANCE';

            return [
                'referralId' => $ref->id,
                'name' => $ref->agency?->name ?? 'Unknown',
                'status' => $ref->status,
                'milestoneCount' => $ref->milestones->count(),
                'steps' => $this->buildAgencySteps($ref),
                'latestMilestoneLabel' => $latestMilestone?->title,
                'milestonesUrl' => $forOfwPortal
                    ? route('ofw.case.milestones', ['case' => $case->id, 'referral' => $ref->id])
                    : route('track.milestones', [
                        'tracker_number' => $case->tracker_number,
                        'referral' => $ref->id,
                    ]),
                'services' => $ref->services->pluck('name')->toArray(),
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

        if ($case->client && $case->client->email) {
            $notifications = CaseNotification::where('case_id', $case->id)
                ->where('client_email', $case->client->email)
                ->orderBy('created_at', 'desc')
                ->limit(self::NOTIFICATION_LIMIT)
                ->get();

            $unreadCount = $notifications->whereNull('read_at')->count();

            $caseNotifications = $notifications->map(function ($notification) use ($case) {
                // Writers store raw internal codes in the payload
                // (status / old_status / new_status). No consumer page
                // reads them (verified: Tracking/Show only matches on
                // `type`; OFW renderers read title/message/created_at
                // plus the bell endpoint's own action_url; Case/Show
                // never receives notifications — CaseController forwards
                // only milestoneTimeline), so strip them here. All other
                // data keys (referral_id, case_number, tracker_number,
                // milestone_*) are client-safe and stay.
                $data = is_array($notification->data) ? $notification->data : [];
                $data = array_diff_key($data, array_flip(self::CLIENT_STRIPPED_DATA_KEYS));

                // Staff deep-links (/cases/*, /referrals/*) are rewritten
                // to OFW-safe destinations for the same reason: the only
                // server-side consumers are the public page, the OFW
                // portal, and the staff page that discards notifications
                // entirely.
                $relatedUrl = $this->ofwSafeRelatedUrl($notification->related_url, $data, $case->id);

                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $data,
                    'related_url' => $relatedUrl,
                    'created_at' => $notification->created_at->toISOString(),
                    'read' => $notification->read_at !== null,
                ];
            })->values()->toArray();
        }

        return [
            'trackingId' => $case->tracker_number,
            'trackedCase' => [
                'id' => $case->id,
                'caseNo' => $case->case_number,
                'clientName' => $client ? "{$client->first_name} {$client->last_name}" : 'Unknown',
                'clientType' => ($client?->type ?? 'OFW') === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                'categories' => $this->categoryPresentation($case),
                'service' => $referrals->first()?->services->pluck('name')->implode(', ') ?? '',
                'milestone' => '',
                'status' => match ($case->status) {
                    'OPEN' => 'IN_PROGRESS',
                    'PENDING_REVIEW' => 'BEING_REVIEWED',
                    'CLOSED' => 'RESOLVED',
                    'ARCHIVED' => 'ARCHIVED',
                    'DRAFT' => 'BEING_PREPARED',
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
    }

    /**
     * Rewrite a staff deep-link to its OFW-safe equivalent so client payloads
     * never carry case-manager URLs. Staff /cases/{id} links become the OFW
     * case page; /referrals/{id} links become the OFW agency-milestones page
     * for the same case and referral (ids come from the notification payload,
     * never parsed out of the staff URL). Public /track links pass through
     * untouched; anything unrecognized is dropped rather than shipped.
     *
     * @param  array<string, mixed>  $data
     */
    private function ofwSafeRelatedUrl(?string $url, array $data, string $caseId): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (preg_match('#^/cases/[^/]+$#', $path) === 1) {
            return route('ofw.case.show', $caseId);
        }

        if (preg_match('#^/referrals/[^/]+$#', $path) === 1) {
            $referralId = $data['referral_id'] ?? null;
            if (! is_string($referralId) || $referralId === '') {
                return null;
            }

            return route('ofw.case.milestones', ['case' => $caseId, 'referral' => $referralId]);
        }

        if (str_starts_with($path, '/track')) {
            return $url;
        }

        return null;
    }

    private function categoryPresentation(CaseFile $case): array
    {
        $categories = method_exists($case, 'categories') ? $case->categories : collect();
        if ($categories->isNotEmpty()) {
            return $categories->map(fn ($category) => ['name' => $category->name, 'color' => $category->color])->values()->all();
        }

        return $case->category ? [['name' => $case->category->name, 'color' => $case->category->color]] : [];
    }

    /**
     * Client-facing payload for a single referral's milestone page.
     *
     * Served by the public `track.milestones` route (anonymous, gated only by
     * the tracking-number OTP session binding) and by the authenticated OFW
     * portal's equivalent route. Both audiences are the client — the OFW or
     * their representative — so this payload must obey the same INVARIANT as
     * buildTrackingData(): staff free-text referral notes are never
     * client-safe. There is deliberately no `notes` key here; it was removed
     * because it leaked internal agency/case-manager text to the public
     * portal. Staff read notes on Referral/Show from the referral record
     * itself. No staff-facing consumer of this payload exists, so no
     * staff-only variant is needed.
     *
     * The result is cached (see self::MILESTONES_PAYLOAD_VERSION), so a
     * removal like this one must also bump that version to orphan entries
     * already written by an earlier release.
     *
     * @return array<string, mixed>
     */
    public function buildAgencyMilestonesData(CaseFile $case, Referral $referral): array
    {
        return CacheHelper::safeRemember(self::trackingMilestonesCacheKey($case->id, $referral->id), 120, function () use ($case, $referral) {
            $case->loadMissing(['client.addresses', 'client.employments']);
            $referral->loadMissing(['agency', 'milestones.user', 'services']);

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
                default => $referral->relationLoaded('services') && $referral->services->isNotEmpty()
                    ? 'Requested services: '.$referral->services->pluck('name')->implode(', ')
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
                    'clientType' => ($case->client?->type ?? 'OFW') === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                    'status' => match ($case->status) {
                        'OPEN' => 'IN_PROGRESS',
                        'PENDING_REVIEW' => 'BEING_REVIEWED',
                        'CLOSED' => 'RESOLVED',
                        'ARCHIVED' => 'ARCHIVED',
                        'DRAFT' => 'BEING_PREPARED',
                        default => 'UNKNOWN',
                    },
                ],
                'agencyMilestones' => [
                    'referralId' => $referral->id,
                    'agencyName' => $agencyName,
                    'status' => $referral->status,
                    'requiredServices' => $referral->relationLoaded('services') && $referral->services->isNotEmpty()
                        ? $referral->services->pluck('name')->implode(', ')
                        : ($referral->required_services ?? ''),
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
    private function buildAgencySteps(Referral $referral): array
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

        if ($status === 'PROCESSING') {
            $steps[] = ['label' => 'Processing', 'state' => 'active'];
            $steps[] = ['label' => 'Completed', 'state' => 'pending'];
        } elseif ($status === 'COMPLETED') {
            $steps[] = ['label' => 'Processing', 'state' => 'complete'];
            $steps[] = ['label' => 'Completed', 'state' => 'active'];
        } else {
            $steps[] = ['label' => 'Processing', 'state' => 'pending'];
            $steps[] = ['label' => 'Completed', 'state' => 'pending'];
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
