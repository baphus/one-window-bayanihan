<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Services\CaseEventRecorder;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralTimelinePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_payload_lists_client_request_activity_newest_last_without_internal_data(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $request = ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'title' => 'Submit passport copy',
        ]);

        app(CaseEventRecorder::class)->clientRequestActivity(
            $request->load('referral'),
            'opened',
            'Submit passport copy — request opened',
            'The agency sent you a request.',
            'agency',
        );
        $request->update(['status' => ReferralClientRequest::STATUS_CLIENT_RESPONDED]);

        $this->travel(1)->minutes();

        app(CaseEventRecorder::class)->clientRequestActivity(
            $request,
            'client_responded',
            'You responded to “Submit passport copy”',
            'You sent a response and submitted 2 documents.',
            'client',
            2,
        );

        $payload = app(TrackingService::class)->buildAgencyMilestonesData($case, $referral);
        $timeline = $payload['agencyMilestones']['milestones'];

        $this->assertSame(['Agency', 'You'], array_column($timeline, 'actor'));
        $this->assertSame(['client_request', 'client_response'], array_column($timeline, 'type'));
        $this->assertSame(
            [ReferralClientRequest::STATUS_OPEN, ReferralClientRequest::STATUS_CLIENT_RESPONDED],
            array_column($timeline, 'requestStatus'),
        );
        $this->assertNull($timeline[0]['actionUrl']);
        $this->assertStringNotContainsString('instructions', json_encode($payload));
        $this->assertArrayNotHasKey('activity', $payload['agencyMilestones']);

        $ofwPayload = app(TrackingService::class)->buildAgencyMilestonesData($case, $referral, forOfwPortal: true);
        $this->assertSame(
            route('ofw.case.requests.show', ['case' => $case->id, 'referral' => $referral->id, 'request' => $request->id]),
            $ofwPayload['agencyMilestones']['milestones'][1]['actionUrl'],
        );
        $this->assertSame('You responded to “Submit passport copy”', $ofwPayload['agencyMilestones']['latestUpdate']['title']);
        $this->assertSame('You', $ofwPayload['agencyMilestones']['latestUpdate']['actor']);
    }

    public function test_case_reopening_appears_on_the_referral_timeline(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);

        app(CaseEventRecorder::class)->caseReopened($case);

        $timeline = app(TrackingService::class)->buildAgencyMilestonesData($case, $referral)['agencyMilestones']['milestones'];

        $this->assertSame(['case_reopened'], array_column($timeline, 'type'));
        $this->assertSame('System', $timeline[0]['actor']);
    }

    public function test_a_milestone_with_a_matching_event_is_listed_once(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $milestone = Milestone::factory()->create([
            'refr_id' => $referral->id,
            'title' => 'Documents verified',
        ]);
        app(CaseEventRecorder::class)->milestoneAdded($referral, $milestone);

        $timeline = app(TrackingService::class)->buildAgencyMilestonesData($case, $referral)['agencyMilestones']['milestones'];

        $this->assertCount(1, $timeline);
        $this->assertSame('milestone_added', $timeline[0]['type']);
    }

    public function test_the_referral_timeline_is_capped_like_the_public_timeline(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $base = now()->subDays(5);

        foreach (range(1, 230) as $index) {
            CaseEvent::create([
                'case_id' => $case->id,
                'referral_id' => $referral->id,
                'type' => CaseEvent::TYPE_REFERRAL_STATUS_CHANGED,
                'title' => 'Update '.$index,
                'occurred_at' => $base->copy()->addSeconds($index),
            ]);
        }

        $timeline = app(TrackingService::class)->buildAgencyMilestonesData($case, $referral)['agencyMilestones']['milestones'];

        $this->assertCount(200, $timeline);
        // The cap keeps the NEWEST events: the oldest 30 fall away.
        $this->assertSame('Update 31', $timeline[0]['title']);
        $this->assertSame('Update 230', $timeline[199]['title']);
    }

    public function test_a_rejected_referral_ends_with_a_terminal_step(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->rejected()->create(['case_id' => $case->id]);

        $card = app(TrackingService::class)->buildTrackingData($case)['trackingAgencies'][0];

        $this->assertSame('Unable to assist', $card['steps'][3]['label']);
        $this->assertSame('rejected', $card['steps'][3]['state']);
    }
}
