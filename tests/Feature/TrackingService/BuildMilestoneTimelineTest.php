<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

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

    public function test_case_opened_is_first_event(): void
    {
        $service = app(TrackingService::class);
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $timeline = $data['milestoneTimeline'];

        $this->assertEquals('case_opened', $timeline[0]['type']);
        $this->assertEquals('Your case has been opened', $timeline[0]['title']);
        $this->assertEquals('Your case is now being reviewed by the Bayanihan team.', $timeline[0]['description']);
        $this->assertNull($timeline[0]['agency']);
    }

    public function test_referral_sent_event(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $sentItems = array_values(array_filter(
            $data['milestoneTimeline'],
            fn ($item) => $item['type'] === 'referral_sent'
        ));

        $this->assertNotEmpty($sentItems);
        $agencyName = $case->referrals->first()->agency->name;
        $this->assertEquals($agencyName, $sentItems[0]['agency']);
        $this->assertStringContainsString('Referred to', $sentItems[0]['title']);
        $this->assertStringContainsString($agencyName, $sentItems[0]['title']);
    }

    public function test_referral_status_events_for_each_status(): void
    {
        $service = app(TrackingService::class);

        $statuses = [
            'PROCESSING' => '{agency} is now processing your case',
            'FOR_COMPLIANCE' => 'Additional documents may be needed for {agency}',
            'COMPLETED' => 'Your referral with {agency} has been completed',
            'REJECTED' => '{agency} was unable to process your referral',
        ];

        foreach ($statuses as $status => $expectedTitleTemplate) {
            $case = CaseFile::factory()->create();
            Referral::factory()->create(['case_id' => $case->id, 'status' => $status]);
            $this->loadRelations($case);

            $data = $service->buildTrackingData($case);
            $statusItems = array_values(array_filter(
                $data['milestoneTimeline'],
                fn ($item) => $item['type'] === 'referral_status'
            ));

            $this->assertNotEmpty($statusItems, "No referral_status event for status $status");
            $agencyName = $case->referrals->first()->agency->name;

            $expectedTitle = str_replace('{agency}', $agencyName, $expectedTitleTemplate);
            $this->assertEquals($expectedTitle, $statusItems[0]['title'], "Failed for status $status");
        }
    }

    public function test_milestone_event(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $msItems = array_values(array_filter(
            $data['milestoneTimeline'],
            fn ($item) => $item['type'] === 'milestone'
        ));

        $this->assertNotEmpty($msItems);
        $this->assertEquals($milestone->title, $msItems[0]['title']);
        $this->assertEquals($milestone->description, $msItems[0]['description']);
        $agencyName = $case->referrals->first()->agency->name;
        $this->assertEquals($agencyName, $msItems[0]['agency']);
    }

    public function test_case_closed_is_last(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->closed()->create();
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $timeline = $data['milestoneTimeline'];
        $lastIndex = count($timeline) - 1;

        $this->assertEquals('case_closed', $timeline[$lastIndex]['type']);
        $this->assertEquals('Your case has been resolved', $timeline[$lastIndex]['title']);
        $this->assertEquals('All referrals have been processed and your case is now closed.', $timeline[$lastIndex]['description']);
        $this->assertNull($timeline[$lastIndex]['agency']);
    }

    public function test_no_audit_logs_in_milestone_timeline(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create();

        $this->createAuditLog(
            entityId: $case->id,
            action: 'UPDATE',
            module: 'case',
            description: 'set draft_client_data to {"foo":"bar"}; set category_id to a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'
        );

        $this->loadRelations($case);
        $data = $service->buildTrackingData($case);

        foreach ($data['milestoneTimeline'] as $item) {
            $this->assertStringNotContainsStringIgnoringCase('set ', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('changed', $item['title']);
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['title']);
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['description']);
        }
    }

    public function test_timeline_sorted_chronologically(): void
    {
        $service = app(TrackingService::class);
        $case = CaseFile::factory()->create(['created_at' => now()->subDays(5)]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'created_at' => now()->subDays(3),
        ]);
        Milestone::factory()->create([
            'refr_id' => $referral->id,
            'created_at' => now()->subDay(),
        ]);
        $this->loadRelations($case);

        $data = $service->buildTrackingData($case);
        $dates = array_column($data['milestoneTimeline'], 'date');

        $sorted = $dates;
        sort($sorted);
        $this->assertEquals($sorted, $dates);
    }

    public function test_empty_state_no_referrals(): void
    {
        $service = app(TrackingService::class);

        // OPEN case — only case_opened
        $openCase = CaseFile::factory()->create(['status' => 'OPEN']);
        $this->loadRelations($openCase);
        $data = $service->buildTrackingData($openCase);
        $timeline = $data['milestoneTimeline'];

        $this->assertCount(1, $timeline);
        $this->assertEquals('case_opened', $timeline[0]['type']);

        // CLOSED case — case_opened + case_closed
        $closedCase = CaseFile::factory()->closed()->create();
        $this->loadRelations($closedCase);
        $data = $service->buildTrackingData($closedCase);
        $timeline = $data['milestoneTimeline'];

        $this->assertCount(2, $timeline);
        $this->assertEquals('case_opened', $timeline[0]['type']);
        $this->assertEquals('case_closed', $timeline[1]['type']);
    }

    public function test_fr_port_006_compliance(): void
    {
        $service = app(TrackingService::class);
        $result = $this->createCompleteCase(referralCount: 2, milestonesPerReferral: 1);
        $case = $result['case'];

        // Add an audit log with internal data to confirm it does NOT leak
        $this->createAuditLog(
            entityId: $case->id,
            action: 'UPDATE',
            module: 'case',
            description: 'set internal_note to confidential discussion; set case_worker to user_a0eebc99'
        );

        $this->loadRelations($case);
        $data = $service->buildTrackingData($case);
        $timeline = $data['milestoneTimeline'];

        $this->assertNotEmpty($timeline);

        foreach ($timeline as $item) {
            // All entries must have exactly these client-facing keys
            $this->assertArrayHasKey('date', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('agency', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('description', $item);

            // Must be one of the valid milestone event types
            $this->assertContains($item['type'], [
                'case_opened', 'referral_sent', 'referral_status',
                'milestone', 'case_closed',
            ]);

            // Must NOT contain raw internal data: UUIDs, field names, internal notes
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['title']);
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['description']);
            $this->assertStringNotContainsStringIgnoringCase('internal_note', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('internal_note', $item['description']);
            $this->assertStringNotContainsStringIgnoringCase('confidential', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('confidential', $item['description']);
            $this->assertStringNotContainsStringIgnoringCase('set ', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('set ', $item['description']);
            $this->assertStringNotContainsStringIgnoringCase('case_worker', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('case_worker', $item['description']);
        }

        // Confirm the timeline has events from both referrals
        $sentItems = array_values(array_filter($timeline, fn ($i) => $i['type'] === 'referral_sent'));
        $this->assertCount(2, $sentItems, 'Should have 2 referral_sent events for 2 referrals');
    }
}
