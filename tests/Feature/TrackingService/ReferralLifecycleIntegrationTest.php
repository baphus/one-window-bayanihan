<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralComplianceRequirement;
use App\Models\User;
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

    public function test_agency_card_tones_per_status(): void
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

        // Assert
        $expectedTones = [
            'PENDING' => ['statusTone' => 'bg-amber-100 text-amber-800', 'borderTone' => 'border-amber-300', 'textTone' => 'text-amber-700', 'lineTone' => 'bg-amber-400'],
            'PROCESSING' => ['statusTone' => 'bg-blue-100 text-blue-800',   'borderTone' => 'border-blue-300',  'textTone' => 'text-blue-700', 'lineTone' => 'bg-blue-400'],
            'FOR_COMPLIANCE' => ['statusTone' => 'bg-orange-100 text-orange-800', 'borderTone' => 'border-orange-300', 'textTone' => 'text-orange-700', 'lineTone' => 'bg-orange-400'],
            'COMPLETED' => ['statusTone' => 'bg-green-100 text-green-800', 'borderTone' => 'border-green-300', 'textTone' => 'text-green-700', 'lineTone' => 'bg-green-400'],
            'REJECTED' => ['statusTone' => 'bg-red-100 text-red-800',     'borderTone' => 'border-red-300',   'textTone' => 'text-red-700',  'lineTone' => 'bg-red-400'],
        ];

        $this->assertCount(5, $agencies);

        foreach ($agencies as $agency) {
            $status = $agency['status'];
            $this->assertArrayHasKey($status, $expectedTones, "Unexpected status '$status' in tracking agencies");
            $expected = $expectedTones[$status];
            $this->assertSame($expected['statusTone'], $agency['statusTone'], "statusTone mismatch for $status");
            $this->assertSame($expected['borderTone'], $agency['borderTone'], "borderTone mismatch for $status");
            $this->assertSame($expected['textTone'], $agency['textTone'], "textTone mismatch for $status");
            $this->assertSame($expected['lineTone'], $agency['lineTone'], "lineTone mismatch for $status");
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
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];

        // milestoneTimeline shows referral_status events for each non-PENDING referral.
        // Create one referral per non-PENDING status to produce distinct events.
        // Also include a PENDING referral (referral_sent covers it — no referral_status event).

        $refProcessing = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PROCESSING',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $refForCompliance = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'FOR_COMPLIANCE',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $refCompleted = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'COMPLETED',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        // Also add a PENDING referral to test that it does NOT generate referral_status
        Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PENDING',
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(7),
        ]);

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);
        $milestoneTimeline = $data['milestoneTimeline'];

        // Assert — collect referral_status events in chronological order
        $statusEvents = array_values(array_filter(
            $milestoneTimeline,
            fn ($event) => $event['type'] === 'referral_status'
        ));

        $this->assertCount(3, $statusEvents, 'Expected 3 referral_status events');

        // Chronological order: PROCESSING (oldest), FOR_COMPLIANCE, COMPLETED (newest)
        $this->assertStringContainsString(
            'processing your case',
            $statusEvents[0]['title'],
            'First referral_status should be PROCESSING'
        );

        $this->assertStringContainsString(
            'Additional documents',
            $statusEvents[1]['title'],
            'Second referral_status should be FOR_COMPLIANCE'
        );

        $this->assertStringContainsString(
            'has been completed',
            $statusEvents[2]['title'],
            'Third referral_status should be COMPLETED'
        );

        // Assert dates are in ascending order
        $this->assertLessThan($statusEvents[1]['date'], $statusEvents[0]['date']);
        $this->assertLessThan($statusEvents[2]['date'], $statusEvents[1]['date']);

        // PENDING referral does NOT generate a referral_status event
        // (proven by count: 4 referrals, 3 non-PENDING → 3 events)
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
        $setup = $this->createCaseWithClient();
        $case = $setup['case'];

        // milestoneTimeline per-referral:
        //   referral_sent  (always)
        //   referral_status(COMPLETED) when final status is COMPLETED
        //
        // To exercise both PROCESSING and COMPLETED status events we need two referrals:
        //   Ref A — PROCESSING with milestone -> produces: referral_sent,
        //            referral_status(PROCESSING), milestone
        //   Ref B — COMPLETED                 -> produces: referral_sent,
        //            referral_status(COMPLETED)
        $refProcessing = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'PROCESSING',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $milestone = Milestone::factory()->create([
            'refr_id' => $refProcessing->id,
            'title' => 'Background check initiated',
            'created_at' => now()->subDays(4),
        ]);

        $refCompleted = Referral::factory()->create([
            'case_id' => $case->id,
            'status' => 'COMPLETED',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);

        // Assert — caseTimeline has referral_sent
        $caseTimelineSent = array_values(array_filter(
            $data['caseTimeline'],
            fn ($entry) => isset($entry['icon']) && $entry['icon'] === 'send'
        ));
        $this->assertNotEmpty($caseTimelineSent, 'caseTimeline should contain referral_sent entries');

        // Assert — milestoneTimeline event presence
        $milestoneTimeline = $data['milestoneTimeline'];
        $eventTypes = array_column($milestoneTimeline, 'type');
        $eventTitles = array_column($milestoneTimeline, 'title');

        $this->assertContains('referral_sent', $eventTypes, 'milestoneTimeline should have referral_sent');

        $processingEvent = collect($milestoneTimeline)->firstWhere('type', 'referral_status');
        $this->assertNotNull($processingEvent);
        $this->assertStringContainsString(
            'processing your case',
            $processingEvent['title'] ?? ''
        );

        $milestoneEvents = array_values(array_filter(
            $milestoneTimeline,
            fn ($event) => $event['type'] === 'milestone'
        ));
        $this->assertNotEmpty($milestoneEvents, 'milestoneTimeline should have milestone events');
        $this->assertSame('Background check initiated', $milestoneEvents[0]['title']);

        // Find the COMPLETED referral_status event
        $completedStatusEvent = collect($milestoneTimeline)->firstWhere(
            fn ($event) => $event['type'] === 'referral_status'
                && str_contains($event['title'] ?? '', 'completed')
        );
        $this->assertNotNull($completedStatusEvent, 'milestoneTimeline should have COMPLETED referral_status');

        // Assert — trackingAgencies
        $this->assertNotEmpty($data['trackingAgencies']);

        // Find entry for the COMPLETED referral
        $completedAgencyEntry = collect($data['trackingAgencies'])->firstWhere('status', 'COMPLETED');
        $this->assertNotNull($completedAgencyEntry, 'Should have a trackingAgency with COMPLETED status');

        // Verify final status tones are applied to the COMPLETED entry
        $this->assertSame('bg-green-100 text-green-800', $completedAgencyEntry['statusTone']);
        $this->assertSame('COMPLETED', $completedAgencyEntry['status']);

        // Verify PROCESSING referral has its own entry with correct status
        $processingAgencyEntry = collect($data['trackingAgencies'])->firstWhere('status', 'PROCESSING');
        $this->assertNotNull($processingAgencyEntry, 'Should have a trackingAgency with PROCESSING status');
    }
}
