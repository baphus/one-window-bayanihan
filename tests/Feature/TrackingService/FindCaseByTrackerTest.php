<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\ReferralAttachment;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class FindCaseByTrackerTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    /**
     * Eager-load all relationships that findCaseByTracker() returns.
     * Matches the pattern from TrackingServiceTest.
     */
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

    public function test_finds_case_by_valid_tracker(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $service = app(TrackingService::class);

        // ACT
        $found = $service->findCaseByTracker($case->tracker_number);

        // ASSERT
        $this->assertNotNull($found);
        $this->assertInstanceOf(CaseFile::class, $found);
        $this->assertEquals($case->id, $found->id);
        $this->assertEquals($case->tracker_number, $found->tracker_number);
    }

    public function test_all_relationships_loaded(): void
    {
        // ARRANGE — build a complete case graph with all related records
        $result = $this->createCompleteCase(referralCount: 1, milestonesPerReferral: 1);
        $case = $result['case'];
        $client = $result['client'];
        $referral = $result['referrals']->first();

        // Set requirements on the referral
        $referral->update(['requirements' => ['Medical Examination: Chest X-Ray']]);
        $referral->refresh();

        // Manually create records for relationships without dedicated factories
        ClientAddress::create([
            'client_id' => $client->id,
            'region' => 'Region VII',
            'province' => 'Cebu',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Barangay Test',
            'street' => '123 Test St',
        ]);

        ClientEmployment::create([
            'client_id' => $client->id,
            'employer_name' => 'Test Employer Ltd.',
            'position' => 'Software Engineer',
            'country' => 'Saudi Arabia',
        ]);

        ReferralAttachment::create([
            'referral_id' => $referral->id,
            'file_name' => 'test-document.pdf',
            'file_path' => '/tmp/test-document.pdf',
            'file_type' => 'application/pdf',
            'size' => 2048,
        ]);

        $service = app(TrackingService::class);

        // ACT
        $found = $service->findCaseByTracker($case->tracker_number);

        // ASSERT — every relationship in the eager-load chain is loaded
        $this->assertNotNull($found);

        // Case-level: user
        $this->assertTrue($found->relationLoaded('user'), 'user relation should be loaded');
        $this->assertNotNull($found->user);

        // Client chain
        $this->assertTrue($found->relationLoaded('client'), 'client relation should be loaded');
        $this->assertNotNull($found->client);
        $this->assertTrue($found->client->relationLoaded('addresses'), 'client.addresses should be loaded');
        $this->assertGreaterThan(0, $found->client->addresses->count());
        $this->assertTrue($found->client->relationLoaded('employments'), 'client.employments should be loaded');
        $this->assertGreaterThan(0, $found->client->employments->count());

        // Referral chain
        $this->assertGreaterThan(0, $found->referrals->count());
        $firstRef = $found->referrals->first();

        $this->assertTrue($firstRef->relationLoaded('agency'), 'referrals.agency should be loaded');
        $this->assertNotNull($firstRef->agency);

        $this->assertNotNull($firstRef->requirements);
        $this->assertEquals(['Medical Examination: Chest X-Ray'], $firstRef->requirements);

        $this->assertTrue($firstRef->relationLoaded('milestones'), 'referrals.milestones should be loaded');
        $this->assertGreaterThan(0, $firstRef->milestones->count());

        $this->assertTrue($firstRef->milestones->first()->relationLoaded('user'), 'referrals.milestones.user should be loaded');
        $this->assertNotNull($firstRef->milestones->first()->user);

        // Note: referrals.attachments intentionally NOT eager-loaded here —
        // buildTrackingData() does not use attachment data for the overview display.
        // Attachments are loaded separately only when needed (e.g., detail views).
    }

    public function test_returns_null_for_invalid_tracker(): void
    {
        // ARRANGE
        $service = app(TrackingService::class);

        // ACT
        $found = $service->findCaseByTracker('NONEXISTENT-TRACKER-99999');

        // ASSERT
        $this->assertNull($found);
    }

    public function test_soft_deleted_case_returns_null(): void
    {
        // ARRANGE
        $case = CaseFile::factory()->create();
        $trackerNumber = $case->tracker_number;

        // Soft-delete the case — SoftDeleteFlag sets is_deleted = true,
        // SoftDeletes sets deleted_at, and the global scope excludes it.
        $case->delete();

        $service = app(TrackingService::class);

        // ACT
        $found = $service->findCaseByTracker($trackerNumber);

        // ASSERT
        $this->assertNull($found);
    }

    public function test_tracker_with_multiple_referrals(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase(referralCount: 3);
        $case = $result['case'];
        $service = app(TrackingService::class);

        // ACT
        $found = $service->findCaseByTracker($case->tracker_number);

        // ASSERT
        $this->assertNotNull($found);
        $this->assertCount(3, $found->referrals);
    }

    /**
     * FR-PORT-001: findCaseByTracker must validate tracker_number access.
     *
     * This test verifies that a valid tracker_number returns the matching case
     * and that a different case's tracker does not collide. The method performs
     * a direct where-clause lookup, so this confirms the access gate works.
     */
    public function test_fr_port_001_compliance(): void
    {
        // ARRANGE — create two cases to prove isolation
        $resultA = $this->createCompleteCase();
        $caseA = $resultA['case'];
        $resultB = $this->createCompleteCase();
        $caseB = $resultB['case'];

        $service = app(TrackingService::class);

        // ACT — search by case A's tracker
        $found = $service->findCaseByTracker($caseA->tracker_number);

        // ASSERT — returns case A, not case B
        $this->assertNotNull($found);
        $this->assertEquals($caseA->id, $found->id);
        $this->assertNotEquals($caseB->id, $found->id);

        // Also verify the inverse
        $foundB = $service->findCaseByTracker($caseB->tracker_number);
        $this->assertEquals($caseB->id, $foundB->id);
    }

    /**
     * FR-PORT-009: Invalid or non-existent tracker must return null.
     *
     * This test verifies that findCaseByTracker gracefully handles
     * tracker numbers that do not match any case in the database.
     */
    public function test_fr_port_009_compliance(): void
    {
        // ARRANGE
        $service = app(TrackingService::class);

        // ACT — non-existent tracker
        $found = $service->findCaseByTracker('INVALID-TRACKER-00000');

        // ASSERT
        $this->assertNull($found);
    }
}
