<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralComplianceRequirement;
use App\Models\User;
use App\Services\CaseEventRecorder;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class FullLifecycleIntegrationTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    /**
     * Eager-load all relationships that buildTrackingData() requires.
     * Includes complianceRequirements which is needed for agency card compliance data.
     */
    private function loadRelations(CaseFile $case): CaseFile
    {
        $case->load([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.complianceRequirements',
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

    public function test_full_lifecycle_from_case_intake_to_tracking_output(): void
    {
        // ARRANGE — simulate the full case lifecycle: DRAFT → OPEN → referral → milestone → CLOSED
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->draft()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        // Open the case
        $case->update(['status' => 'OPEN']);
        $case->refresh();
        $this->recorder()->caseOpened($case, $user->id);

        // Add a PENDING referral
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PENDING',
        ]);
        $this->recorder()->referralSent($referral, $user->id);

        // Add a milestone
        $milestone = Milestone::factory()->create([
            'refr_id' => $referral->id,
        ]);
        $this->recorder()->milestoneAdded($referral, $milestone, $user->id);

        // Close the case
        $case->update(['status' => 'CLOSED', 'closed_at' => now()]);
        $case->refresh();
        $this->recorder()->caseClosed($case, $user->id);

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);

        // ASSERT — trackingId matches the tracker_number
        $this->assertEquals($case->tracker_number, $data['trackingId']);

        // ASSERT — trackedCase shows correct client name
        $expectedName = $client->first_name.' '.$client->last_name;
        $this->assertEquals($expectedName, $data['trackedCase']['clientName']);

        // ASSERT — trackedCase status maps correctly (CLOSED → RESOLVED)
        $this->assertEquals('RESOLVED', $data['trackedCase']['status']);

        // ASSERT — client type maps correctly (OFW → Overseas Filipino Worker)
        $this->assertEquals('Overseas Filipino Worker', $data['trackedCase']['clientType']);

        // ASSERT — caseOverview has OFW data matching client
        $this->assertNotNull($data['caseOverview']['ofw']);
        $expectedFullName = trim("{$client->first_name} {$client->middle_initial} {$client->last_name} {$client->suffix}");
        $this->assertEquals($expectedFullName, $data['caseOverview']['ofw']['fullName']);

        // ASSERT — the legacy audit-log timeline is gone from the payload
        $this->assertArrayNotHasKey('caseTimeline', $data);

        // ASSERT — milestoneTimeline covers the full journey in order
        $this->assertEquals(
            ['case_opened', 'referral_sent', 'milestone_added', 'case_closed'],
            array_column($data['milestoneTimeline'], 'type')
        );
        $this->assertEquals('Your case has been opened', $data['milestoneTimeline'][0]['title']);

        // ASSERT — trackingAgencies has exactly 1 entry (one referral)
        $this->assertCount(1, $data['trackingAgencies']);

        // ASSERT — caseNotifications structure
        $this->assertArrayHasKey('unread_count', $data['caseNotifications']);
        $this->assertArrayHasKey('items', $data['caseNotifications']);
    }

    public function test_multiple_referrals_agency_cards_carry_status_not_presentation(): void
    {
        // ARRANGE
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        // Create 3 referrals at different statuses
        Referral::factory()->pending()->create(['case_id' => $case->id]);
        Referral::factory()->processing()->create(['case_id' => $case->id]);
        Referral::factory()->completed()->create(['case_id' => $case->id]);

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);

        // ASSERT — 3 agency cards, one per referral status
        $this->assertCount(3, $data['trackingAgencies']);
        $statuses = array_column($data['trackingAgencies'], 'status');
        sort($statuses);
        $this->assertEquals(['COMPLETED', 'PENDING', 'PROCESSING'], $statuses);

        // Styling is the frontend's job — the payload carries state, not CSS.
        foreach ($data['trackingAgencies'] as $agency) {
            $this->assertArrayNotHasKey('statusTone', $agency);
            $this->assertArrayNotHasKey('borderTone', $agency);
            $this->assertArrayNotHasKey('textTone', $agency);
            $this->assertArrayNotHasKey('lineTone', $agency);
        }
    }

    public function test_milestone_timeline_event_types_in_full_case_with_referrals(): void
    {
        // ARRANGE — create a fully-loaded case with referrals, milestones, and audit logs
        $result = $this->createCompleteCase(referralCount: 2, milestonesPerReferral: 1);
        $case = $result['case'];
        $referrals = $result['referrals'];

        $this->recorder()->caseOpened($case);
        foreach ($referrals as $referral) {
            $this->recorder()->referralSent($referral);
            foreach ($referral->milestones as $milestone) {
                $this->recorder()->milestoneAdded($referral, $milestone);
            }
        }

        // Second referral becomes PROCESSING
        $referrals[1]->update(['status' => 'PROCESSING']);
        $this->recorder()->referralStatusChanged($referrals[1], 'PENDING', 'PROCESSING');

        // Add an audit log with internal data — should NOT leak into milestoneTimeline
        $this->createAuditLog(
            entityId: $case->id,
            action: 'UPDATE',
            module: 'case',
            description: 'set internal_note to confidential discussion; set case_worker to user_a0eebc99',
        );

        // Close the case
        $case->update(['status' => 'CLOSED', 'closed_at' => now()]);
        $case->refresh();
        $this->recorder()->caseClosed($case);

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);
        $timeline = $data['milestoneTimeline'];

        // ASSERT — first event is case_opened
        $this->assertEquals('case_opened', $timeline[0]['type']);
        $this->assertEquals('Your case has been opened', $timeline[0]['title']);
        $this->assertNull($timeline[0]['agency']);

        // ASSERT — all expected event types are present
        $types = array_column($timeline, 'type');
        $this->assertContains('case_opened', $types);
        $this->assertContains('referral_sent', $types);
        $this->assertContains('referral_status_changed', $types);
        $this->assertContains('milestone_added', $types);
        $this->assertContains('case_closed', $types);

        // ASSERT — case_closed is the last event
        $lastIndex = count($timeline) - 1;
        $this->assertEquals('case_closed', $timeline[$lastIndex]['type']);
        $this->assertEquals('Your case has been resolved', $timeline[$lastIndex]['title']);
        $this->assertNull($timeline[$lastIndex]['agency']);

        // ASSERT — no audit log raw data leaks into milestoneTimeline
        foreach ($timeline as $item) {
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}/', $item['title']);
            $this->assertDoesNotMatchRegularExpression('/set /', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('case_worker', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('internal_note', $item['title']);
            $this->assertStringNotContainsStringIgnoringCase('confidential', $item['title']);
        }
    }

    public function test_audit_events_never_reach_tracking_payload(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $this->recorder()->caseOpened($case);
        $this->recorder()->referralSent($result['referrals']->first());

        // Create audit log entries for the case
        $this->createAuditLog(
            entityId: $case->id,
            action: 'CREATE',
            module: 'case_files',
            description: 'Case intake initiated',
        );
        $this->createAuditLog(
            entityId: $case->id,
            action: 'UPDATE',
            module: 'case_files',
            description: 'Case status updated to OPEN',
        );

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);

        // ASSERT — no legacy audit-log timeline in the payload
        $this->assertArrayNotHasKey('caseTimeline', $data);

        // ASSERT — milestoneTimeline carries only client-facing event types
        foreach ($data['milestoneTimeline'] as $event) {
            $this->assertContains($event['type'], [
                'case_opened', 'referral_sent', 'referral_status_changed',
                'milestone_added', 'compliance_fulfilled', 'case_closed', 'case_reopened',
            ]);
            $this->assertStringNotContainsStringIgnoringCase('set ', $event['title']);
        }

        // ASSERT — audit descriptions do not appear anywhere in the payload
        $payload = json_encode($data);
        $this->assertStringNotContainsString('Case intake initiated', $payload);
        $this->assertStringNotContainsString('Case status updated to OPEN', $payload);
    }

    public function test_tracking_data_field_level_consistency(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $client = $result['client'];

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);

        // ASSERT — trackingId matches case tracker_number exactly
        $this->assertEquals($case->tracker_number, $data['trackingId']);

        // ASSERT — trackedCase.clientName matches client's first + last name
        $expectedName = $client->first_name.' '.$client->last_name;
        $this->assertEquals($expectedName, $data['trackedCase']['clientName']);

        // ASSERT — status mapping for OPEN case → IN_PROGRESS
        $this->assertEquals('IN_PROGRESS', $data['trackedCase']['status']);

        // ASSERT — caseOverview.ofw.fullName matches client name (with middle initial and suffix)
        $expectedFullName = trim("{$client->first_name} {$client->middle_initial} {$client->last_name} {$client->suffix}");
        $this->assertEquals($expectedFullName, $data['caseOverview']['ofw']['fullName']);

        // Also verify CLOSED → RESOLVED mapping
        $case->update(['status' => 'CLOSED', 'closed_at' => now()]);
        $case->refresh();
        $this->loadRelations($case);
        $data = $service->buildTrackingData($case);
        $this->assertEquals('RESOLVED', $data['trackedCase']['status']);
    }

    public function test_empty_tracking_agencies_and_minimal_timelines(): void
    {
        // ARRANGE — OPEN case with a client but no referrals or milestones
        $client = Client::factory()->create();
        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
        ]);
        $this->recorder()->caseOpened($case);

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);

        // ASSERT — trackingAgencies is an empty array (no referrals)
        $this->assertEmpty($data['trackingAgencies']);

        // ASSERT — milestoneTimeline has only case_opened
        $this->assertCount(1, $data['milestoneTimeline']);
        $this->assertEquals('case_opened', $data['milestoneTimeline'][0]['type']);
        $this->assertEquals('Your case has been opened', $data['milestoneTimeline'][0]['title']);

        // Also test a CLOSED case with no referrals
        $closedCase = CaseFile::factory()->closed()->create([
            'client_id' => $client->id,
        ]);
        $this->recorder()->caseOpened($closedCase);
        $this->recorder()->caseClosed($closedCase);
        $this->loadRelations($closedCase);
        $data = $service->buildTrackingData($closedCase);

        $this->assertEmpty($data['trackingAgencies']);
        $this->assertCount(2, $data['milestoneTimeline']);
        $this->assertEquals('case_opened', $data['milestoneTimeline'][0]['type']);
        $this->assertEquals('case_closed', $data['milestoneTimeline'][1]['type']);
    }

    public function test_referral_with_compliance_agency_card_structure(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase(referralCount: 1);
        $case = $result['case'];
        $referral = $result['referrals']->first();

        // Set referral to FOR_COMPLIANCE status
        $referral->update(['status' => 'FOR_COMPLIANCE']);

        // Create two compliance requirements with different statuses
        ReferralComplianceRequirement::create([
            'referral_id' => $referral->id,
            'service_name' => 'Medical Examination',
            'requirement_name' => 'Chest X-Ray',
            'status' => 'PENDING',
        ]);
        ReferralComplianceRequirement::create([
            'referral_id' => $referral->id,
            'service_name' => 'Document Verification',
            'requirement_name' => 'Passport Copy',
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);
        $agencyCard = $data['trackingAgencies'][0];

        // ASSERT — compliance_requirements is present and populated
        $this->assertNotEmpty($agencyCard['compliance_requirements']);
        $this->assertCount(2, $agencyCard['compliance_requirements']);

        // ASSERT — each compliance requirement has the expected structure
        foreach ($agencyCard['compliance_requirements'] as $cr) {
            $this->assertArrayHasKey('id', $cr);
            $this->assertArrayHasKey('service_name', $cr);
            $this->assertArrayHasKey('requirement_name', $cr);
            $this->assertArrayHasKey('status', $cr);
            $this->assertArrayHasKey('completed_at', $cr);
        }

        // ASSERT — find requirements by service_name (order is non-deterministic due to UUID PKs)
        $reqsByService = [];
        foreach ($agencyCard['compliance_requirements'] as $cr) {
            $reqsByService[$cr['service_name']] = $cr;
        }

        $this->assertArrayHasKey('Medical Examination', $reqsByService);
        $this->assertEquals('Chest X-Ray', $reqsByService['Medical Examination']['requirement_name']);
        $this->assertEquals('PENDING', $reqsByService['Medical Examination']['status']);
        $this->assertNull($reqsByService['Medical Examination']['completed_at']);

        $this->assertArrayHasKey('Document Verification', $reqsByService);
        $this->assertEquals('Passport Copy', $reqsByService['Document Verification']['requirement_name']);
        $this->assertEquals('COMPLETED', $reqsByService['Document Verification']['status']);
        $this->assertNotNull($reqsByService['Document Verification']['completed_at']);

        // ASSERT — steps has 6 entries (compliance path: Created, Referred, Received, For Compliance, Processing after compliance, Completed)
        $this->assertCount(6, $agencyCard['steps']);
        $this->assertEquals('For Compliance', $agencyCard['steps'][3]['label']);
        $this->assertEquals('active', $agencyCard['steps'][3]['state']);
        $this->assertEquals('Processing after compliance', $agencyCard['steps'][4]['label']);
        $this->assertEquals('pending', $agencyCard['steps'][4]['state']);
        $this->assertEquals('Completed', $agencyCard['steps'][5]['label']);
        $this->assertEquals('pending', $agencyCard['steps'][5]['state']);
    }
}
