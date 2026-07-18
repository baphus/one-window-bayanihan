<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Services\CaseEventRecorder;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

/**
 * The milestone timeline is a read model over the append-only case_events log.
 * These tests seed events through CaseEventRecorder (the canonical writer —
 * service write points are covered by CaseEventRecordingTest) and assert the
 * mapped, client-facing output.
 */
class BuildMilestoneTimelineTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    private function loadRelations(CaseFile $case): CaseFile
    {
        $case->load([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.milestones.user',
            'referrals.attachments',
            'user',
        ]);

        return $case;
    }

    private function recorder(): CaseEventRecorder
    {
        return app(CaseEventRecorder::class);
    }

    private function timeline(CaseFile $case): array
    {
        $this->loadRelations($case);

        return app(TrackingService::class)->buildTrackingData($case)['milestoneTimeline'];
    }

    public function test_case_opened_is_first_event(): void
    {
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $this->recorder()->caseOpened($case);
        $this->recorder()->referralSent($result['referrals']->first());

        $timeline = $this->timeline($case);

        $this->assertEquals('case_opened', $timeline[0]['type']);
        $this->assertEquals('Your case has been opened', $timeline[0]['title']);
        $this->assertNull($timeline[0]['agency']);
    }

    public function test_referral_sent_event(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $this->recorder()->referralSent($referral);

        $sentItems = array_values(array_filter(
            $this->timeline($case),
            fn ($item) => $item['type'] === 'referral_sent'
        ));

        $this->assertNotEmpty($sentItems);
        $agencyName = $case->referrals()->first()->agency->name;
        $this->assertEquals($agencyName, $sentItems[0]['agency']);
        $this->assertStringContainsString('Referred to', $sentItems[0]['title']);
        $this->assertStringContainsString($agencyName, $sentItems[0]['title']);
    }

    public function test_referral_status_events_for_each_status(): void
    {
        $statuses = [
            'PROCESSING' => '{agency} is now processing your referral',
            'FOR_COMPLIANCE' => '{agency} needs additional requirements',
            'COMPLETED' => 'Your referral with {agency} has been completed',
            'REJECTED' => '{agency} was unable to process your referral',
        ];

        foreach ($statuses as $status => $expectedTitleTemplate) {
            $case = CaseFile::factory()->create();
            $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => $status]);
            $this->recorder()->referralStatusChanged($referral, 'PENDING', $status);

            $statusItems = array_values(array_filter(
                $this->timeline($case),
                fn ($item) => $item['type'] === 'referral_status_changed'
            ));

            $this->assertNotEmpty($statusItems, "No referral_status_changed event for status $status");
            $agencyName = $case->referrals()->first()->agency->name;

            $expectedTitle = str_replace('{agency}', $agencyName, $expectedTitleTemplate);
            $this->assertEquals($expectedTitle, $statusItems[0]['title'], "Failed for status $status");
        }
    }

    public function test_milestone_event(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);
        $this->recorder()->milestoneAdded($referral, $milestone);

        $msItems = array_values(array_filter(
            $this->timeline($case),
            fn ($item) => $item['type'] === 'milestone_added'
        ));

        $this->assertNotEmpty($msItems);
        $this->assertEquals($milestone->title, $msItems[0]['title']);
        $this->assertEquals($milestone->description, $msItems[0]['description']);
        $this->assertEquals($case->referrals()->first()->agency->name, $msItems[0]['agency']);
    }

    public function test_case_closed_is_last(): void
    {
        $case = CaseFile::factory()->closed()->create();

        $this->travelTo(now()->subDays(3), fn () => $this->recorder()->caseOpened($case));
        $this->recorder()->caseClosed($case);

        $timeline = $this->timeline($case);
        $last = $timeline[count($timeline) - 1];

        $this->assertEquals('case_closed', $last['type']);
        $this->assertEquals('Your case has been resolved', $last['title']);
        $this->assertEquals('All referrals have been processed and your case is now closed.', $last['description']);
        $this->assertNull($last['agency']);
    }

    public function test_case_reopened_event(): void
    {
        $case = CaseFile::factory()->create();

        $this->travelTo(now()->subDays(2), fn () => $this->recorder()->caseOpened($case));
        $this->travelTo(now()->subDay(), fn () => $this->recorder()->caseClosed($case));
        $this->recorder()->caseReopened($case);

        $timeline = $this->timeline($case);
        $last = $timeline[count($timeline) - 1];

        $this->assertEquals('case_reopened', $last['type']);
        $this->assertEquals('Your case has been reopened', $last['title']);
    }

    public function test_audit_logs_never_reach_milestone_timeline(): void
    {
        $case = CaseFile::factory()->create();
        $this->recorder()->caseOpened($case);

        $this->createAuditLog(
            entityId: $case->id,
            action: 'UPDATE',
            module: 'case',
            description: 'set internal_note to confidential; set category_id to a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'
        );

        foreach ($this->timeline($case) as $item) {
            $this->assertStringNotContainsStringIgnoringCase('set ', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('changed', $item['title']);
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['title']);
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['description']);
        }
    }

    public function test_timeline_sorted_chronologically(): void
    {
        $case = CaseFile::factory()->create(['created_at' => now()->subDays(5)]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        // Record deliberately out of order — occurred_at must win.
        $this->travelTo(now()->subDay(), fn () => $this->recorder()->milestoneAdded($referral, $milestone));
        $this->travelTo(now()->subDays(5), fn () => $this->recorder()->caseOpened($case));
        $this->travelTo(now()->subDays(3), fn () => $this->recorder()->referralSent($referral));

        $dates = array_column($this->timeline($case), 'date');

        $sorted = $dates;
        sort($sorted);
        $this->assertEquals($sorted, $dates);
        $this->assertCount(3, $dates);
    }

    public function test_empty_state_no_recorded_events(): void
    {
        $case = CaseFile::factory()->create(['status' => 'OPEN']);

        $this->assertCount(0, $this->timeline($case));
    }

    public function test_fr_port_006_compliance(): void
    {
        $result = $this->createCompleteCase(referralCount: 2, milestonesPerReferral: 1);
        $case = $result['case'];

        $this->recorder()->caseOpened($case);
        foreach ($result['referrals'] as $referral) {
            $this->recorder()->referralSent($referral);
            foreach ($referral->milestones as $milestone) {
                $this->recorder()->milestoneAdded($referral, $milestone);
            }
        }

        // Add an audit log with internal data to confirm it does NOT leak
        $this->createAuditLog(
            entityId: $case->id,
            action: 'UPDATE',
            module: 'case',
            description: 'set internal_note to confidential discussion; set case_worker to user_a0eebc99'
        );

        $timeline = $this->timeline($case);
        $this->assertNotEmpty($timeline);

        foreach ($timeline as $item) {
            // All entries must have exactly these client-facing keys
            $this->assertArrayHasKey('date', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('agency', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('description', $item);

            // Must be one of the valid case event types
            $this->assertContains($item['type'], [
                'case_opened', 'referral_sent', 'referral_status_changed',
                'milestone_added', 'compliance_fulfilled', 'case_closed', 'case_reopened',
            ]);

            // Must NOT contain raw internal data: UUIDs, field names, internal notes
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['title']);
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['description']);
            $this->assertStringNotContainsStringIgnoringCase('internal_note', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('confidential', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('confidential', $item['description']);
            $this->assertStringNotContainsStringIgnoringCase('set ', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('case_worker', $item['description']);
        }

        // Confirm the timeline has events from both referrals
        $sentItems = array_values(array_filter($timeline, fn ($i) => $i['type'] === 'referral_sent'));
        $this->assertCount(2, $sentItems, 'Should have 2 referral_sent events for 2 referrals');
    }

    public function test_same_timestamp_insertion_order_tie_breaking(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        // Freeze time so all three events share the same occurred_at —
        // the sequence column must preserve insertion order.
        $this->travelTo(now(), function () use ($case, $referral, $milestone) {
            $this->recorder()->caseOpened($case);
            $this->recorder()->referralSent($referral);
            $this->recorder()->milestoneAdded($referral, $milestone);
        });

        $timeline = $this->timeline($case);

        $this->assertEquals('case_opened', $timeline[0]['type']);
        $this->assertEquals('referral_sent', $timeline[1]['type']);
        $this->assertEquals('milestone_added', $timeline[2]['type']);
        $this->assertSame($timeline[0]['date'], $timeline[2]['date']);
    }

    public function test_status_change_event_date_is_recorded_not_derived(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->processing()->create(['case_id' => $case->id]);

        $statusChangedAt = now()->subHour()->setMicroseconds(0);
        $this->travelTo($statusChangedAt, fn () => $this->recorder()->referralStatusChanged($referral, 'PENDING', 'PROCESSING'));

        // Drift updated_at forward — the event date must NOT move with it.
        $this->travelBack();
        $referral->update(['notes' => 'updated notes']);

        $statusItems = array_values(array_filter(
            $this->timeline($case),
            fn ($item) => $item['type'] === 'referral_status_changed'
        ));

        $this->assertNotEmpty($statusItems);
        $this->assertEquals($statusChangedAt->toISOString(), $statusItems[0]['date']);
    }

    public function test_agency_name_resolves_through_relation_at_read_time(): void
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $this->recorder()->referralSent($referral);

        $referral->agency->update(['name' => 'Renamed Agency Office']);

        $sentItems = array_values(array_filter(
            $this->timeline($case->fresh()),
            fn ($item) => $item['type'] === 'referral_sent'
        ));

        $this->assertEquals('Renamed Agency Office', $sentItems[0]['agency']);
    }
}
