<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\Referral;
use App\Services\CaseEventRecorder;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client-payload sanitization in TrackingService::buildTrackingData().
 *
 * Staff free-text notes, raw status codes in notification data, and staff
 * deep-links never reach any browser: the only server-side consumers are the
 * public page, the OFW portal (both client surfaces), and the staff page,
 * which forwards only milestoneTimeline. Sanitization is therefore
 * unconditional — no flag, no key split.
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

    public function test_agency_cards_carry_no_staff_notes(): void
    {
        [$case] = $this->makeCase();
        $service = app(TrackingService::class);

        foreach ([false, true] as $flag) {
            $cards = $service->buildTrackingData($case, $flag)['trackingAgencies'];

            $this->assertNotEmpty($cards);
            $this->assertArrayNotHasKey('note', $cards[0]);
        }
    }

    public function test_notification_data_codes_are_stripped(): void
    {
        [$case] = $this->makeCase();
        $service = app(TrackingService::class);

        foreach ([false, true] as $flag) {
            $items = $service->buildTrackingData($case, $flag)['caseNotifications']['items'];
            $statusItem = collect($items)->firstWhere('type', 'referral_status_changed');

            $this->assertNotNull($statusItem);
            $this->assertArrayNotHasKey('status', $statusItem['data']);
            $this->assertArrayNotHasKey('old_status', $statusItem['data']);
            $this->assertArrayNotHasKey('new_status', $statusItem['data']);
            // Client-safe keys survive for the fallback renderers.
            $this->assertArrayHasKey('referral_id', $statusItem['data']);
            $this->assertArrayHasKey('case_number', $statusItem['data']);
        }
    }

    public function test_staff_deep_links_are_rewritten(): void
    {
        [$case, $referral] = $this->makeCase();
        $service = app(TrackingService::class);

        foreach ([false, true] as $flag) {
            $items = $service->buildTrackingData($case, $flag)['caseNotifications']['items'];
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
    }

    public function test_public_milestones_url_behavior_preserved(): void
    {
        [$case] = $this->makeCase();
        $service = app(TrackingService::class);

        $public = $service->buildTrackingData($case);
        $portal = $service->buildTrackingData($case, forOfwPortal: true);

        // Sanitized identically; only the milestone family of URLs differs.
        $this->assertArrayNotHasKey('note', $public['trackingAgencies'][0]);
        $this->assertArrayNotHasKey('note', $portal['trackingAgencies'][0]);
        $this->assertStringContainsString('/track/case/', $public['trackingAgencies'][0]['milestonesUrl']);
        $this->assertStringContainsString('/my-cases/', $portal['trackingAgencies'][0]['milestonesUrl']);
    }

    public function test_staff_milestone_timeline_unaffected(): void
    {
        [$case] = $this->makeCase();

        $timeline = app(TrackingService::class)->buildTrackingData($case)['milestoneTimeline'];

        $this->assertSame('case_opened', $timeline[0]['type']);
        $this->assertSame('referral_sent', $timeline[1]['type']);
        $this->assertNotEmpty($timeline[0]['title']);
    }

    public function test_reads_are_always_fresh_without_invalidation(): void
    {
        [$case] = $this->makeCase();
        $service = app(TrackingService::class);

        // No read-model cache exists for this payload, so a mutation is
        // visible on the very next read with no invalidation step.
        $this->assertSame('PENDING', $service->buildTrackingData($case)['trackingAgencies'][0]['status']);

        $case->referrals()->first()->update(['status' => 'PROCESSING']);

        $this->assertSame('PROCESSING', $service->buildTrackingData($case->load('referrals'))['trackingAgencies'][0]['status']);
    }
}
