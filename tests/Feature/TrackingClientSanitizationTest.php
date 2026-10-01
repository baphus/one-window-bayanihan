<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\Referral;
use App\Services\CaseEventRecorder;
use App\Services\CaseSwimlaneService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Client-payload leak fixes in TrackingService::buildTrackingData().
 *
 * The $forOfwPortal flag used to rewrite a single URL. It now also drops
 * staff free-text notes, strips raw status codes from notification data,
 * and rewrites staff deep-links to OFW-safe destinations. The staff
 * (flag-false) branch must stay byte-identical, and the two variants must
 * never share a cache entry.
 */
class TrackingClientSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCase(): array
    {
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PENDING',
            'notes' => 'INTERNAL staff note that must never reach clients',
        ]);

        app(CaseEventRecorder::class)->caseOpened($case);
        app(CaseEventRecorder::class)->referralSent($referral);

        CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $client->email,
            'type' => 'referral_status_changed',
            'title' => 'Your referral has an update',
            'message' => 'An office updated your referral.',
            'data' => [
                'referral_id' => $referral->id,
                'case_number' => $case->case_number,
                'status' => 'PROCESSING',
                'old_status' => 'PENDING',
                'new_status' => 'PROCESSING',
            ],
            'related_url' => route('cases.show', $case->id),
        ]);
        CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $client->email,
            'type' => 'milestone_added',
            'title' => 'New update on your case',
            'message' => 'An office posted an update.',
            'data' => ['referral_id' => $referral->id, 'milestone_title' => 'Intake review'],
            'related_url' => route('referrals.show', $referral->id),
        ]);
        CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $client->email,
            'type' => 'case_updated',
            'title' => 'Case Updated',
            'message' => 'The details of your case have been updated.',
            'data' => ['case_number' => $case->case_number],
            'related_url' => route('track.show', $case->tracker_number),
        ]);

        $case = $case->fresh();
        $case->load([
            'client.addresses',
            'client.employments',
            'client.nextOfKin',
            'referrals.agency',
            'referrals.services',
            'referrals.milestones.user',
            'user',
            'category',
            'categories',
        ]);

        return [$case, $referral];
    }

    public function test_portal_payload_drops_staff_notes(): void
    {
        [$case] = $this->makeCase();

        $portal = app(TrackingService::class)->buildTrackingData($case, forOfwPortal: true);

        $this->assertNotEmpty($portal['trackingAgencies']);
        $this->assertArrayNotHasKey('note', $portal['trackingAgencies'][0]);
    }

    public function test_public_branch_shares_the_sanitized_cards(): void
    {
        // Choice (i): the shared `false` branch carries no `note` key at
        // all. The staff CaseController consumer provably cannot miss it —
        // it forwards only milestoneTimeline — and the public page never
        // rendered it. Only milestonesUrl still differs per flag.
        [$case] = $this->makeCase();

        $public = app(TrackingService::class)->buildTrackingData($case);

        $this->assertArrayNotHasKey('note', $public['trackingAgencies'][0]);
        $this->assertStringContainsString(
            '/track/case/',
            $public['trackingAgencies'][0]['milestonesUrl']
        );
    }

    public function test_portal_payload_strips_codes_from_notification_data(): void
    {
        [$case] = $this->makeCase();

        $items = app(TrackingService::class)->buildTrackingData($case, forOfwPortal: true)['caseNotifications']['items'];
        $statusItem = collect($items)->firstWhere('type', 'referral_status_changed');

        $this->assertNotNull($statusItem);
        $this->assertArrayNotHasKey('status', $statusItem['data']);
        $this->assertArrayNotHasKey('old_status', $statusItem['data']);
        $this->assertArrayNotHasKey('new_status', $statusItem['data']);
        // Client-safe keys survive for the fallback renderers.
        $this->assertArrayHasKey('referral_id', $statusItem['data']);
        $this->assertArrayHasKey('case_number', $statusItem['data']);
    }

    public function test_public_branch_strips_codes_from_notification_data(): void
    {
        [$case] = $this->makeCase();

        $items = app(TrackingService::class)->buildTrackingData($case)['caseNotifications']['items'];
        $statusItem = collect($items)->firstWhere('type', 'referral_status_changed');

        $this->assertNotNull($statusItem);
        $this->assertArrayNotHasKey('status', $statusItem['data']);
        $this->assertArrayNotHasKey('old_status', $statusItem['data']);
        $this->assertArrayNotHasKey('new_status', $statusItem['data']);
        $this->assertArrayHasKey('referral_id', $statusItem['data']);
    }

    public function test_portal_payload_rewrites_staff_deep_links(): void
    {
        [$case, $referral] = $this->makeCase();

        $items = app(TrackingService::class)->buildTrackingData($case, forOfwPortal: true)['caseNotifications']['items'];
        $byType = collect($items)->keyBy('type');

        // Staff /cases/{id} becomes the OFW case page — click-through kept.
        $this->assertSame(
            route('ofw.case.show', $case->id),
            $byType['referral_status_changed']['related_url']
        );
        // Staff /referrals/{id} becomes the OFW milestones page.
        $this->assertSame(
            route('ofw.case.milestones', ['case' => $case->id, 'referral' => $referral->id]),
            $byType['milestone_added']['related_url']
        );
        // Public tracking links pass through untouched.
        $this->assertSame(
            route('track.show', $case->tracker_number),
            $byType['case_updated']['related_url']
        );
    }

    public function test_public_branch_rewrites_staff_deep_links(): void
    {
        [$case, $referral] = $this->makeCase();

        $items = app(TrackingService::class)->buildTrackingData($case)['caseNotifications']['items'];
        $byType = collect($items)->keyBy('type');

        $this->assertSame(route('ofw.case.show', $case->id), $byType['referral_status_changed']['related_url']);
        $this->assertSame(
            route('ofw.case.milestones', ['case' => $case->id, 'referral' => $referral->id]),
            $byType['milestone_added']['related_url']
        );
        $this->assertSame(
            route('track.show', $case->tracker_number),
            $byType['case_updated']['related_url']
        );
    }

    public function test_cache_keys_differ_per_flag(): void
    {
        [$case] = $this->makeCase();
        $service = app(TrackingService::class);

        $public = $service->buildTrackingData($case);
        $portal = $service->buildTrackingData($case, forOfwPortal: true);

        $this->assertTrue(Cache::has(TrackingService::trackingDataCacheKeyFor($case->id, false)));
        $this->assertTrue(Cache::has(TrackingService::trackingDataCacheKeyFor($case->id, true)));
        // Both variants are sanitized now; only the milestone family of URLs
        // still differs per flag — the public branch keeps the public route.
        $this->assertArrayNotHasKey('note', $public['trackingAgencies'][0]);
        $this->assertArrayNotHasKey('note', $portal['trackingAgencies'][0]);
        $this->assertNotSame($public['trackingAgencies'][0]['milestonesUrl'], $portal['trackingAgencies'][0]['milestonesUrl']);
        $this->assertStringContainsString('/track/case/', $public['trackingAgencies'][0]['milestonesUrl']);
        $this->assertStringContainsString('/my-cases/', $portal['trackingAgencies'][0]['milestonesUrl']);
    }

    public function test_invalidation_clears_both_variants_and_client_swimlane(): void
    {
        [$case] = $this->makeCase();
        $service = app(TrackingService::class);

        $service->buildTrackingData($case);
        $service->buildTrackingData($case, forOfwPortal: true);
        app(CaseSwimlaneService::class)->buildClientSwimlaneTimeline($case);

        $this->assertTrue(Cache::has(TrackingService::trackingDataCacheKeyFor($case->id, false)));
        $this->assertTrue(Cache::has(TrackingService::trackingDataCacheKeyFor($case->id, true)));
        $this->assertTrue(Cache::has(CaseSwimlaneService::clientCacheKey($case->id)));

        TrackingService::invalidateTrackingCache($case->id);

        $this->assertFalse(Cache::has(TrackingService::trackingDataCacheKeyFor($case->id, false)));
        $this->assertFalse(Cache::has(TrackingService::trackingDataCacheKeyFor($case->id, true)));
        $this->assertFalse(Cache::has(CaseSwimlaneService::clientCacheKey($case->id)));
    }
}
