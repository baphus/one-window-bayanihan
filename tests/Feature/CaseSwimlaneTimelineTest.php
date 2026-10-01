<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Services\CaseEventRecorder;
use App\Services\CaseSwimlaneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Case-manager-only swimlane timeline read model.
 *
 * Events are seeded through CaseEventRecorder (the canonical writer) with
 * frozen clocks so segment boundaries assert against exact ISO8601 instants.
 */
class CaseSwimlaneTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function recorder(): CaseEventRecorder
    {
        return app(CaseEventRecorder::class);
    }

    private function swimlane(CaseFile $case): array
    {
        return app(CaseSwimlaneService::class)->buildSwimlaneTimeline(CaseFile::findOrFail($case->id));
    }

    public function test_completed_referral_produces_three_contiguous_closed_segments(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        $t0 = now()->subDays(4)->setMicroseconds(0);
        $t1 = now()->subDays(3)->setMicroseconds(0);
        $t2 = now()->subDays(2)->setMicroseconds(0);
        $tMid = now()->subDays(2)->addHour()->setMicroseconds(0);
        $t3 = now()->subDay()->setMicroseconds(0);

        $this->travelTo($t0, fn () => $this->recorder()->caseOpened($case));
        $this->travelTo($t1, fn () => $this->recorder()->referralSent($referral));
        $this->travelTo($t2, fn () => $this->recorder()->referralStatusChanged($referral, 'PENDING', 'PROCESSING'));
        $this->travelTo($tMid, fn () => $this->recorder()->milestoneAdded($referral, $milestone));
        $this->travelTo($t3, fn () => $this->recorder()->referralStatusChanged($referral, 'PROCESSING', 'COMPLETED'));
        $referral->update(['status' => 'COMPLETED']);

        $payload = $this->swimlane($case);

        $this->assertSame($t0->toISOString(), $payload['caseOpenedAt']);
        $this->assertNull($payload['caseClosedAt']);
        $this->assertNotEmpty($payload['generatedAt']);

        $this->assertCount(1, $payload['referrals']);
        $lane = $payload['referrals'][0];

        $this->assertSame($referral->id, $lane['id']);
        $this->assertSame('COMPLETED', $lane['status']);
        $this->assertSame($t1->toISOString(), $lane['sentAt']);
        $this->assertTrue($lane['isTerminal']);
        $this->assertSame($t3->toISOString(), $lane['terminalAt']);
        $this->assertSame(3, $lane['segmentCount']);

        $segments = $lane['segments'];
        $this->assertSame(['PENDING', 'PROCESSING', 'COMPLETED'], array_column($segments, 'status'));
        $this->assertSame($t1->toISOString(), $segments[0]['start']);
        $this->assertSame($t2->toISOString(), $segments[0]['end']);
        $this->assertSame($t2->toISOString(), $segments[1]['start']);
        $this->assertSame($t3->toISOString(), $segments[1]['end']);
        $this->assertSame($t3->toISOString(), $segments[2]['start']);
        $this->assertSame($t3->toISOString(), $segments[2]['end']);

        // Contiguous non-overlapping [start, end): every boundary touches.
        $this->assertSame($segments[0]['end'], $segments[1]['start']);
        $this->assertSame($segments[1]['end'], $segments[2]['start']);

        foreach ($segments as $segment) {
            $this->assertFalse($segment['isOpen']);
            $this->assertNotNull($segment['end']);
            $this->assertNotEmpty($segment['label']);
        }

        // The milestone landed inside the PROCESSING segment.
        $this->assertSame(0, $segments[0]['milestoneCount']);
        $this->assertSame(1, $segments[1]['milestoneCount']);
        $this->assertSame(0, $segments[2]['milestoneCount']);
        $this->assertCount(1, $lane['milestones']);
        $this->assertSame($tMid->toISOString(), $lane['milestones'][0]['at']);
        $this->assertSame($milestone->title, $lane['milestones'][0]['title']);
    }

    public function test_terminal_referral_has_terminal_at_and_no_open_segment(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $t1 = now()->subDays(2)->setMicroseconds(0);
        $t2 = now()->subDay()->setMicroseconds(0);

        $this->travelTo($t1, fn () => $this->recorder()->referralSent($referral));
        $this->travelTo($t2, fn () => $this->recorder()->referralStatusChanged($referral, 'PENDING', 'REJECTED'));
        $referral->update(['status' => 'REJECTED']);

        $lane = $this->swimlane($case)['referrals'][0];

        $this->assertTrue($lane['isTerminal']);
        $this->assertSame($t2->toISOString(), $lane['terminalAt']);

        $open = array_filter($lane['segments'], fn (array $segment): bool => $segment['isOpen']);
        $this->assertCount(0, $open);
    }

    public function test_ready_to_close_tracks_terminal_state_of_all_referrals(): void
    {
        $case = CaseFile::factory()->create();
        $refA = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $refB = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $t1 = now()->subDays(3)->setMicroseconds(0);
        $t2 = now()->subDays(2)->setMicroseconds(0);
        $t3 = now()->subDay()->setMicroseconds(0);

        $this->travelTo($t1, function () use ($refA, $refB) {
            $this->recorder()->referralSent($refA);
            $this->recorder()->referralSent($refB);
        });
        $this->travelTo($t2, function () use ($refA, $refB) {
            $this->recorder()->referralStatusChanged($refA, 'PENDING', 'PROCESSING');
            $this->recorder()->referralStatusChanged($refB, 'PENDING', 'PROCESSING');
        });
        $this->travelTo($t3, fn () => $this->recorder()->referralStatusChanged($refA, 'PROCESSING', 'COMPLETED'));
        $refA->update(['status' => 'COMPLETED']);
        $refB->update(['status' => 'PROCESSING']);

        $payload = $this->swimlane($case);

        $this->assertFalse($payload['caseManagerLane']['readyToClose']);
        $this->assertSame(1, $payload['caseManagerLane']['receivedCount']);
        $this->assertSame(['referrals' => 2, 'active' => 1, 'terminal' => 1, 'milestones' => 0], $payload['totals']);

        $t4 = now()->setMicroseconds(0);
        $this->travelTo($t4, fn () => $this->recorder()->referralStatusChanged($refB, 'PROCESSING', 'COMPLETED'));
        $refB->update(['status' => 'COMPLETED']);

        $payload = $this->swimlane($case);

        $this->assertTrue($payload['caseManagerLane']['readyToClose']);
        $this->assertSame(2, $payload['caseManagerLane']['receivedCount']);
        $this->assertSame(['referrals' => 2, 'active' => 0, 'terminal' => 2, 'milestones' => 0], $payload['totals']);
    }

    public function test_case_closed_event_closes_case_manager_lane(): void
    {
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $t0 = now()->subDays(4)->setMicroseconds(0);
        $t1 = now()->subDays(3)->setMicroseconds(0);
        $t3 = now()->subDay()->setMicroseconds(0);
        $t4 = now()->setMicroseconds(0);

        $this->travelTo($t0, fn () => $this->recorder()->caseOpened($case));
        $this->travelTo($t1, fn () => $this->recorder()->referralSent($referral));
        $this->travelTo($t3, fn () => $this->recorder()->referralStatusChanged($referral, 'PENDING', 'COMPLETED'));
        $referral->update(['status' => 'COMPLETED']);
        $this->travelTo($t4, fn () => $this->recorder()->caseClosed($case));
        $case->update(['status' => 'CLOSED', 'closed_at' => $t4]);

        $payload = $this->swimlane($case);

        $this->assertSame($t4->toISOString(), $payload['caseClosedAt']);

        $managerLane = $payload['caseManagerLane'];
        $this->assertSame(1, $managerLane['receivedCount']);
        $this->assertSame($t4->toISOString(), $managerLane['closedAt']);
        $this->assertCount(1, $managerLane['segments']);

        $segment = $managerLane['segments'][0];
        $this->assertSame('CASE_MANAGER', $segment['status']);
        $this->assertSame('Case Manager', $segment['label']);
        $this->assertSame($t3->toISOString(), $segment['start']);
        $this->assertSame($t4->toISOString(), $segment['end']);
        $this->assertFalse($segment['isOpen']);

        // Already closed, so there is nothing left to become ready.
        $this->assertFalse($managerLane['readyToClose']);
    }

    public function test_referral_without_events_still_emits_a_lane(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $payload = $this->swimlane($case);

        $this->assertCount(1, $payload['referrals']);
        $lane = $payload['referrals'][0];

        $this->assertSame($referral->id, $lane['id']);
        $this->assertSame($referral->refresh()->created_at->toISOString(), $lane['sentAt']);
        $this->assertSame([], $lane['segments']);
        $this->assertSame(0, $lane['segmentCount']);
        $this->assertFalse($lane['isTerminal']);
        $this->assertNull($lane['terminalAt']);
        $this->assertFalse($payload['caseManagerLane']['readyToClose']);
        $this->assertSame([], $payload['caseManagerLane']['segments']);
    }

    public function test_payload_conforms_to_contract_keys(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $this->recorder()->referralSent($referral);

        $payload = $this->swimlane($case);

        $this->assertSame(
            ['caseOpenedAt', 'caseClosedAt', 'generatedAt', 'referrals', 'caseManagerLane', 'totals'],
            array_keys($payload)
        );
        $this->assertSame(
            ['id', 'agency', 'service', 'status', 'statusLabel', 'sentAt', 'isTerminal', 'terminalAt', 'segmentCount', 'milestoneCount', 'segments', 'milestones'],
            array_keys($payload['referrals'][0])
        );
        $this->assertSame(
            ['status', 'label', 'start', 'end', 'milestoneCount', 'isOpen'],
            array_keys($payload['referrals'][0]['segments'][0])
        );
        $this->assertSame(
            ['segments', 'receivedCount', 'readyToClose', 'closedAt'],
            array_keys($payload['caseManagerLane'])
        );
        $this->assertSame(
            ['referrals', 'active', 'terminal', 'milestones'],
            array_keys($payload['totals'])
        );
    }
}
