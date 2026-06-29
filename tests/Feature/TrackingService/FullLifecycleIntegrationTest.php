<?php

namespace Tests\Feature\TrackingService;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralComplianceRequirement;
use App\Models\User;
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

        // Add a PENDING referral
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PENDING',
        ]);

        // Add a milestone
        Milestone::factory()->create([
            'refr_id' => $referral->id,
        ]);

        // Close the case
        $case->update(['status' => 'CLOSED', 'closed_at' => now()]);
        $case->refresh();

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
        $expectedFullName = trim("{$client->first_name} {$client->middle_name} {$client->last_name} {$client->suffix}");
        $this->assertEquals($expectedFullName, $data['caseOverview']['ofw']['fullName']);

        // ASSERT — caseTimeline is populated
        $this->assertNotEmpty($data['caseTimeline']);

        // ASSERT — milestoneTimeline is populated and starts with case_opened
        $this->assertNotEmpty($data['milestoneTimeline']);
        $this->assertEquals('case_opened', $data['milestoneTimeline'][0]['type']);
        $this->assertEquals('Your case has been opened', $data['milestoneTimeline'][0]['title']);

        // ASSERT — trackingAgencies has exactly 1 entry (one referral)
        $this->assertCount(1, $data['trackingAgencies']);

        // ASSERT — caseNotifications structure
        $this->assertArrayHasKey('unread_count', $data['caseNotifications']);
        $this->assertArrayHasKey('items', $data['caseNotifications']);
    }

    public function test_multiple_referrals_agency_tone_classes_per_status(): void
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

        // ASSERT — 3 agency cards
        $this->assertCount(3, $data['trackingAgencies']);

        // Build a lookup by status for easier assertions
        $byStatus = [];
        foreach ($data['trackingAgencies'] as $agency) {
            $byStatus[$agency['status']] = $agency;
        }

        // PENDING tones
        $this->assertEquals('bg-amber-100 text-amber-800', $byStatus['PENDING']['statusTone']);
        $this->assertEquals('border-amber-300', $byStatus['PENDING']['borderTone']);
        $this->assertEquals('text-amber-700', $byStatus['PENDING']['textTone']);
        $this->assertEquals('bg-amber-400', $byStatus['PENDING']['lineTone']);

        // PROCESSING tones
        $this->assertEquals('bg-blue-100 text-blue-800', $byStatus['PROCESSING']['statusTone']);
        $this->assertEquals('border-blue-300', $byStatus['PROCESSING']['borderTone']);
        $this->assertEquals('text-blue-700', $byStatus['PROCESSING']['textTone']);
        $this->assertEquals('bg-blue-400', $byStatus['PROCESSING']['lineTone']);

        // COMPLETED tones
        $this->assertEquals('bg-green-100 text-green-800', $byStatus['COMPLETED']['statusTone']);
        $this->assertEquals('border-green-300', $byStatus['COMPLETED']['borderTone']);
        $this->assertEquals('text-green-700', $byStatus['COMPLETED']['textTone']);
        $this->assertEquals('bg-green-400', $byStatus['COMPLETED']['lineTone']);
    }

    public function test_milestone_timeline_event_types_in_full_case_with_referrals(): void
    {
        // ARRANGE — create a fully-loaded case with referrals, milestones, and audit logs
        $result = $this->createCompleteCase(referralCount: 2, milestonesPerReferral: 1);
        $case = $result['case'];
        $referrals = $result['referrals'];

        // First referral stays PENDING; second becomes PROCESSING to trigger a referral_status event
        $referrals[0]->update(['status' => 'PENDING']);
        $referrals[1]->update(['status' => 'PROCESSING']);

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
        $this->assertContains('referral_status', $types);
        $this->assertContains('milestone', $types);
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

    public function test_case_timeline_contains_audit_events_milestone_timeline_excludes(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

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

        // ASSERT — caseTimeline includes audit events with proper icons
        $auditIcons = [];
        foreach ($data['caseTimeline'] as $event) {
            if (in_array($event['icon'] ?? '', ['create', 'update', 'delete', 'auth', 'system'])) {
                $auditIcons[] = $event['icon'];
            }
        }
        $this->assertContains('create', $auditIcons, 'caseTimeline should contain CREATE audit events');
        $this->assertContains('update', $auditIcons, 'caseTimeline should contain UPDATE audit events');

        // ASSERT — caseTimeline events have expected structure
        foreach ($data['caseTimeline'] as $event) {
            $this->assertArrayHasKey('title', $event);
            $this->assertArrayHasKey('icon', $event);
            $this->assertArrayHasKey('date', $event);
            $this->assertArrayHasKey('agency', $event);
            $this->assertArrayHasKey('detail', $event);
        }

        // ASSERT — milestoneTimeline does NOT contain audit CRUD types
        $msTypes = array_column($data['milestoneTimeline'], 'type');
        $this->assertNotContains('create', $msTypes, 'milestoneTimeline should not have audit CRUD type');
        $this->assertNotContains('update', $msTypes);

        // ASSERT — milestoneTimeline events are human-readable client-facing types
        foreach ($data['milestoneTimeline'] as $event) {
            $this->assertContains($event['type'], [
                'case_opened', 'referral_sent', 'referral_status', 'milestone', 'case_closed',
            ]);
            $this->assertStringNotContainsStringIgnoringCase('set ', $event['title']);
        }
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

        // ASSERT — caseOverview.ofw.fullName matches client name (with middle name and suffix)
        $expectedFullName = trim("{$client->first_name} {$client->middle_name} {$client->last_name} {$client->suffix}");
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

        // ASSERT — caseTimeline has 1 event (the auto-generated case creation audit from AuditObserver)
        // Even with no manual audit logs, the AuditObserver creates one on CaseFile creation.
        $this->assertCount(1, $data['caseTimeline']);

        // Also test a CLOSED case with no referrals
        $closedCase = CaseFile::factory()->closed()->create([
            'client_id' => $client->id,
        ]);
        $this->loadRelations($closedCase);
        $data = $service->buildTrackingData($closedCase);

        $this->assertEmpty($data['trackingAgencies']);
        $this->assertCount(2, $data['milestoneTimeline']);
        $this->assertEquals('case_opened', $data['milestoneTimeline'][0]['type']);
        $this->assertEquals('case_closed', $data['milestoneTimeline'][1]['type']);
        // CLOSED case also has the case creation audit event (AuditObserver fires on create)
        $this->assertCount(1, $data['caseTimeline']);
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

    public function test_case_timeline_events_sorted_ascending(): void
    {
        // ARRANGE — create events with intentionally non-chronological timestamps
        // to verify the sortBy('date') in buildTrackingData works correctly.
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'created_at' => now()->subDays(10),
        ]);

        // Create referral (date: 5 days ago) — will be pushed first in the timeline loop
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'created_at' => now()->subDays(5),
        ]);

        // Create milestone (date: 3 days ago) — will be pushed second (inside referral loop)
        Milestone::factory()->create([
            'refr_id' => $referral->id,
            'created_at' => now()->subDays(3),
        ]);

        // Create audit log with manual timestamp (date: 7 days ago) — will be pushed third
        AuditLog::create([
            'entity_id' => $case->id,
            'action' => 'UPDATE',
            'module' => 'case',
            'description' => 'Initial case review completed',
            'user_id' => null,
            'timestamp' => now()->subDays(7),
        ]);

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);

        // ASSERT — caseTimeline sorted by date ascending
        $dates = array_map(fn (array $event): string => $event['date'], $data['caseTimeline']);
        $sorted = $dates;
        sort($sorted);
        $this->assertEquals($sorted, $dates, 'caseTimeline events must be sorted by date in ascending order');

        // ASSERT — the manual audit log (7 days ago) is the earliest event and should sort first
        $this->assertStringContainsString(
            'review',
            $data['caseTimeline'][0]['title'],
            'First caseTimeline event should be the manual audit log which has the earliest timestamp (7 days ago)',
        );
    }

    public function test_case_timeline_no_duplicate_referral_sent(): void
    {
        // ARRANGE — create a case with 2 referrals and 1 milestone per referral
        $result = $this->createCompleteCase(referralCount: 2, milestonesPerReferral: 1);
        $case = $result['case'];
        $referrals = $result['referrals'];

        // Create referral CREATE audit logs for both referrals.
        // These would normally be created by AuditObserver/CaseService.
        foreach ($referrals as $referral) {
            $this->createAuditLog(
                entityId: $referral->id,
                action: 'CREATE',
                module: 'referral',
            );
        }

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);
        $timeline = $data['caseTimeline'];

        // ASSERT — caseTimeline contains exactly 2 referral_sent events (one per referral)
        $sentEvents = array_values(array_filter(
            $timeline,
            fn (array $event): bool => $event['icon'] === 'send'
        ));
        $this->assertCount(2, $sentEvents);

        // The referral CREATE audit logs should have been deduped —
        // they match the inline referral_sent events (action=CREATE, module=referral,
        // entity_id matches a referral ID, timestamp within ±5s).
    }

    public function test_case_timeline_same_timestamp_tie_breaking(): void
    {
        // ARRANGE — create events at the same timestamp to verify _sort_index tie-breaking
        $now = now();
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'created_at' => $now->subDay(),
        ]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'created_at' => $now,
        ]);

        // Create an audit log at the EXACT same timestamp as the referral
        AuditLog::create([
            'entity_id' => $case->id,
            'action' => 'UPDATE',
            'module' => 'case',
            'description' => 'Same timestamp audit event',
            'timestamp' => $now,
        ]);

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);
        $timeline = $data['caseTimeline'];

        // ASSERT — referral_sent (icon='send') sorts before the audit event
        // (icon='update') because inline events get lower _sort_index values
        // than audit logs while sharing the same date.
        $sendIndex = null;
        $updateIndex = null;
        foreach ($timeline as $i => $event) {
            if ($event['icon'] === 'send' && $sendIndex === null) {
                $sendIndex = $i;
            }
            if ($event['icon'] === 'update' && $updateIndex === null) {
                $updateIndex = $i;
            }
        }
        $this->assertNotNull($sendIndex, 'Should have a send event');
        $this->assertNotNull($updateIndex, 'Should have an update event');
        $this->assertLessThan($updateIndex, $sendIndex, 'send event should appear before update event');
    }

    public function test_case_timeline_preserves_non_duplicate_audit_logs(): void
    {
        // ARRANGE — create a case with one referral
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $referral = $result['referrals']->first();

        // Create a CaseFile UPDATE audit log (entity_id = case->id).
        // This does NOT match dedup criteria (action=UPDATE, not CREATE), so it stays in caseTimeline.
        $this->createAuditLog(
            entityId: $case->id,
            action: 'UPDATE',
            module: 'case_files',
            description: 'Case status updated to OPEN',
        );

        // Create a referral CREATE audit log (entity_id = referral->id).
        // This DOES match dedup criteria so should be removed from caseTimeline.
        $this->createAuditLog(
            entityId: $referral->id,
            action: 'CREATE',
            module: 'referral',
        );

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);
        $timeline = $data['caseTimeline'];

        // ASSERT — The CaseFile UPDATE audit log (icon='update') is preserved in caseTimeline
        $updateEvents = array_values(array_filter(
            $timeline,
            fn (array $event): bool => $event['icon'] === 'update'
        ));
        $this->assertNotEmpty($updateEvents, 'CaseFile UPDATE audit log should appear in caseTimeline');

        // ASSERT — Only 1 referral_sent event exists (the inline one, no extra from failed dedup)
        $sentEvents = array_values(array_filter(
            $timeline,
            fn (array $event): bool => $event['icon'] === 'send'
        ));
        $this->assertCount(1, $sentEvents, 'Should have exactly 1 referral_sent event (referral CREATE audit log deduped)');
    }
}
