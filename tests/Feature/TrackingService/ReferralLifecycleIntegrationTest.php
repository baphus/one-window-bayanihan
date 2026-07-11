<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralComplianceRequirement;
use App\Models\User;
use App\Services\CaseEventRecorder;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class ReferralLifecycleIntegrationTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    /**
     * Eager-load all relationships that buildTrackingData() requires.
     * Includes complianceRequirements so compliance_requirements is populated.
     */
    private function loadRelations(CaseFile $case): CaseFile
    {
        $case->load([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.milestones.user',
            'referrals.attachments',
            'referrals.complianceRequirements',
            'user',
        ]);

        return $case;
    }

    /**
     * Create a simple case with a client for isolated tests.
     *
     * @return array{case: CaseFile, client: Client, user: User}
     */
    private function createCaseWithClient(): array
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        return ['case' => $case, 'client' => $client, 'user' => $user];
    }

    // ----------------------------------------------------------------
    //  1. Agency card tones per status
    // ----------------------------------------------------------------

    public function test_agency_card_structure_per_status(): void
    {
        // Arrange
        $service = $this->app->make(TrackingService::class);
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];

        $statuses = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];
        foreach ($statuses as $status) {
            Referral::factory()->create([
                'case_id' => $case->id,
                'status' => $status,
            ]);
        }

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);
        $agencies = $data['trackingAgencies'];

        // Assert — one card per referral, each carrying the state the UI needs
        $this->assertCount(5, $agencies);

        foreach ($agencies as $agency) {
            $this->assertContains($agency['status'], $statuses, "Unexpected status '{$agency['status']}' in tracking agencies");
            $this->assertArrayHasKey('referralId', $agency);
            $this->assertArrayHasKey('name', $agency);
            $this->assertArrayHasKey('steps', $agency);
            $this->assertArrayHasKey('milestonesUrl', $agency);
            $this->assertArrayHasKey('compliance_requirements', $agency);

            // Styling is the frontend's job — the payload carries state, not CSS.
            $this->assertArrayNotHasKey('statusTone', $agency);
            $this->assertArrayNotHasKey('borderTone', $agency);
            $this->assertArrayNotHasKey('textTone', $agency);
            $this->assertArrayNotHasKey('lineTone', $agency);
        }
    }

    // ----------------------------------------------------------------
    //  2. Compliance requirements in agency card
    // ----------------------------------------------------------------

    public function test_compliance_requirements_in_agency_card(): void
    {
        // Arrange
        $service = $this->app->make(TrackingService::class);
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];
        $user = $setup['user'];

        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'FOR_COMPLIANCE',
        ]);

        // Create one pending and one completed compliance requirement
        $pending = ReferralComplianceRequirement::create([
            'referral_id' => $referral->id,
            'service_name' => 'Medical Clearance',
            'requirement_name' => 'Chest X-ray',
            'status' => 'pending',
            'completed_at' => null,
        ]);

        $completed = ReferralComplianceRequirement::create([
            'referral_id' => $referral->id,
            'service_name' => 'Legal Assistance',
            'requirement_name' => 'Affidavit of Support',
            'status' => 'completed',
            'completed_at' => now()->subDay(),
            'fulfilled_by' => $user->id,
        ]);

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);
        $complianceRequirements = $data['trackingAgencies'][0]['compliance_requirements'];

        // Assert
        $this->assertCount(2, $complianceRequirements);

        // Find pending entry
        $pendingEntry = collect($complianceRequirements)->firstWhere('status', 'pending');
        $this->assertNotNull($pendingEntry, 'Pending compliance requirement not found');
        $this->assertSame($pending->id, $pendingEntry['id']);
        $this->assertSame('Medical Clearance', $pendingEntry['service_name']);
        $this->assertSame('Chest X-ray', $pendingEntry['requirement_name']);
        $this->assertNull($pendingEntry['completed_at']);

        // Find completed entry
        $completedEntry = collect($complianceRequirements)->firstWhere('status', 'completed');
        $this->assertNotNull($completedEntry, 'Completed compliance requirement not found');
        $this->assertSame($completed->id, $completedEntry['id']);
        $this->assertSame('Legal Assistance', $completedEntry['service_name']);
        $this->assertSame('Affidavit of Support', $completedEntry['requirement_name']);
        $this->assertNotNull($completedEntry['completed_at']);
        $this->assertStringEndsWith('Z', $completedEntry['completed_at']); // ISO format
    }

    // ----------------------------------------------------------------
    //  3. Latest milestone label
    // ----------------------------------------------------------------

    public function test_latest_milestone_label(): void
    {
        // Arrange
        $service = $this->app->make(TrackingService::class);
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];

        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PROCESSING',
        ]);

        // Create milestones at different times — middle, oldest, newest
        $milestoneMiddle = Milestone::factory()->create([
            'refr_id' => $referral->id,
            'title' => 'Document review in progress',
            'created_at' => now()->subDays(5),
        ]);
        $milestoneOldest = Milestone::factory()->create([
            'refr_id' => $referral->id,
            'title' => 'Initial assessment completed',
            'created_at' => now()->subDays(10),
        ]);
        $milestoneNewest = Milestone::factory()->create([
            'refr_id' => $referral->id,
            'title' => 'Final review completed',
            'created_at' => now()->subDay(),
        ]);

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);

        // Assert
        $this->assertSame('Final review completed', $data['trackingAgencies'][0]['latestMilestoneLabel']);
    }

    // ----------------------------------------------------------------
    //  4. No milestones → null label
    // ----------------------------------------------------------------

    public function test_no_milestones_returns_null_label(): void
    {
        // Arrange
        $service = $this->app->make(TrackingService::class);
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];

        Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PROCESSING',
        ]);

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);

        // Assert
        $this->assertNull($data['trackingAgencies'][0]['latestMilestoneLabel']);
    }

    // ----------------------------------------------------------------
    //  5. Referral status through timeline
    // ----------------------------------------------------------------

    public function test_referral_status_through_timeline(): void
    {
        // Arrange
        $service = $this->app->make(TrackingService::class);
        $recorder = $this->app->make(CaseEventRecorder::class);
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];

        // The timeline shows one referral_status_changed event per recorded
        // transition. Record transitions at distinct times for three referrals;
        // a fourth referral stays PENDING with no status event.

        $refProcessing = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PROCESSING',
        ]);
        $this->travelTo(now()->subDays(5), fn () => $recorder->referralStatusChanged($refProcessing, 'PENDING', 'PROCESSING'));

        $refForCompliance = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'FOR_COMPLIANCE',
        ]);
        $this->travelTo(now()->subDays(3), fn () => $recorder->referralStatusChanged($refForCompliance, 'PENDING', 'FOR_COMPLIANCE'));

        $refCompleted = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'COMPLETED',
        ]);
        $this->travelTo(now()->subDay(), fn () => $recorder->referralStatusChanged($refCompleted, 'PROCESSING', 'COMPLETED'));

        // Also add a PENDING referral — no status transition, no status event
        Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PENDING',
        ]);

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);
        $milestoneTimeline = $data['milestoneTimeline'];

        // Assert — collect referral_status_changed events in chronological order
        $statusEvents = array_values(array_filter(
            $milestoneTimeline,
            fn ($event) => $event['type'] === 'referral_status_changed'
        ));

        $this->assertCount(3, $statusEvents, 'Expected 3 referral_status_changed events');

        // Chronological order: PROCESSING (oldest), FOR_COMPLIANCE, COMPLETED (newest)
        $this->assertStringContainsString(
            'processing your referral',
            $statusEvents[0]['title'],
            'First status event should be PROCESSING'
        );

        $this->assertStringContainsString(
            'needs additional requirements',
            $statusEvents[1]['title'],
            'Second status event should be FOR_COMPLIANCE'
        );

        $this->assertStringContainsString(
            'has been completed',
            $statusEvents[2]['title'],
            'Third status event should be COMPLETED'
        );

        // Assert dates are in ascending order
        $this->assertLessThan($statusEvents[1]['date'], $statusEvents[0]['date']);
        $this->assertLessThan($statusEvents[2]['date'], $statusEvents[1]['date']);
    }

    // ----------------------------------------------------------------
    //  6. Agency steps count per status
    // ----------------------------------------------------------------

    public function test_agency_steps_count_per_status(): void
    {
        // Arrange
        $service = $this->app->make(TrackingService::class);
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];

        // Status → expected step count (standard path, no compliance history)
        $expectedCounts = [
            'PENDING' => 3,
            'PROCESSING' => 5,
            'COMPLETED' => 5,
            'REJECTED' => 3,
            'FOR_COMPLIANCE' => 6,
        ];

        foreach ($expectedCounts as $status => $expectedCount) {
            Referral::factory()->create([
                'case_id' => $case->id,
                'status' => $status,
            ]);
        }

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);
        $agencies = $data['trackingAgencies'];

        // Assert
        $this->assertCount(5, $agencies);

        foreach ($agencies as $agency) {
            $status = $agency['status'];
            $this->assertArrayHasKey($status, $expectedCounts, "Unexpected status '$status' in agencies");
            $this->assertCount(
                $expectedCounts[$status],
                $agency['steps'],
                "Step count mismatch for status $status"
            );
        }
    }

    // ----------------------------------------------------------------
    //  7. Referral with attachments
    // ----------------------------------------------------------------

    public function test_referral_with_attachments(): void
    {
        // Arrange
        $service = $this->app->make(TrackingService::class);
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];
        $user = $setup['user'];

        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PROCESSING',
        ]);

        ReferralAttachment::create([
            'referral_id' => $referral->id,
            'file_name' => 'supporting_doc.pdf',
            'file_path' => 'referrals/supporting_doc.pdf',
            'file_type' => 'application/pdf',
            'size' => 204800,
            'user_id' => $user->id,
        ]);

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);

        // Assert — the relationship is loaded on the referral model via referrals.attachments
        $referralFromCase = $case->referrals->firstWhere('id', $referral->id);
        $this->assertNotNull($referralFromCase);
        $this->assertTrue($referralFromCase->relationLoaded('attachments'));
        $this->assertCount(1, $referralFromCase->attachments);
        $this->assertSame('supporting_doc.pdf', $referralFromCase->attachments->first()->file_name);
    }

    // ----------------------------------------------------------------
    //  8. Referral lifecycle through full data flow
    // ----------------------------------------------------------------

    public function test_referral_lifecycle_full_data_flow(): void
    {
        // Arrange
        $service = $this->app->make(TrackingService::class);
        $recorder = $this->app->make(CaseEventRecorder::class);
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];

        // Two referral journeys recorded as events:
        //   Ref A — sent, moved to PROCESSING, milestone added
        //   Ref B — sent, moved to COMPLETED
        $refProcessing = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PROCESSING',
        ]);
        $this->travelTo(now()->subDays(5), function () use ($recorder, $refProcessing) {
            $recorder->referralSent($refProcessing);
            $recorder->referralStatusChanged($refProcessing, 'PENDING', 'PROCESSING');
        });

        $milestone = Milestone::factory()->create([
            'refr_id' => $refProcessing->id,
            'title' => 'Background check initiated',
        ]);
        $this->travelTo(now()->subDays(4), fn () => $recorder->milestoneAdded($refProcessing, $milestone));

        $refCompleted = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'COMPLETED',
        ]);
        $this->travelTo(now()->subDays(2), function () use ($recorder, $refCompleted) {
            $recorder->referralSent($refCompleted);
            $recorder->referralStatusChanged($refCompleted, 'PROCESSING', 'COMPLETED');
        });

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);

        // Assert — the legacy audit-log timeline is gone from the payload
        $this->assertArrayNotHasKey('caseTimeline', $data);

        // Assert — milestoneTimeline event presence
        $milestoneTimeline = $data['milestoneTimeline'];
        $eventTypes = array_column($milestoneTimeline, 'type');

        $this->assertContains('referral_sent', $eventTypes, 'milestoneTimeline should have referral_sent');

        $processingEvent = collect($milestoneTimeline)->firstWhere('type', 'referral_status_changed');
        $this->assertNotNull($processingEvent);
        $this->assertStringContainsString(
            'processing your referral',
            $processingEvent['title'] ?? ''
        );

        $milestoneEvents = array_values(array_filter(
            $milestoneTimeline,
            fn ($event) => $event['type'] === 'milestone_added'
        ));
        $this->assertNotEmpty($milestoneEvents, 'milestoneTimeline should have milestone events');
        $this->assertSame('Background check initiated', $milestoneEvents[0]['title']);

        // Find the COMPLETED status event
        $completedStatusEvent = collect($milestoneTimeline)->firstWhere(
            fn ($event) => $event['type'] === 'referral_status_changed'
                && str_contains($event['title'] ?? '', 'completed')
        );
        $this->assertNotNull($completedStatusEvent, 'milestoneTimeline should have a COMPLETED status event');

        // Assert — trackingAgencies
        $this->assertNotEmpty($data['trackingAgencies']);

        // Find entry for the COMPLETED referral
        $completedAgencyEntry = collect($data['trackingAgencies'])->firstWhere('status', 'COMPLETED');
        $this->assertNotNull($completedAgencyEntry, 'Should have a trackingAgency with COMPLETED status');
        $this->assertSame('COMPLETED', $completedAgencyEntry['status']);

        // Verify PROCESSING referral has its own entry with correct status
        $processingAgencyEntry = collect($data['trackingAgencies'])->firstWhere('status', 'PROCESSING');
        $this->assertNotNull($processingAgencyEntry, 'Should have a trackingAgency with PROCESSING status');
    }
}
