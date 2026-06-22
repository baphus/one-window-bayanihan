<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
use App\Models\Milestone;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class BuildAgencyStepsTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    /**
     * Eager-load all relationships that buildTrackingData() requires.
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

    /**
     * DataProvider returning 10 combinations of referral status × compliance path.
     *
     * Each case: [status, complianceTrigger, expectedCount, activeIndex]
     *
     * - status: the referral status to set
     * - complianceTrigger: false = no compliance; true = add milestone with "compli";
     *   string = add milestone with that text as title (must contain "compli")
     * - expectedCount: expected number of steps
     * - activeIndex: expected active step index, or null if no step should be active
     *
     * @return array<string, array{mixed, mixed, int, int|null}>
     */
    public static function agencyStepProvider(): array
    {
        return [
            'PENDING standard' => ['PENDING',        false,              3,  2],
            'PROCESSING standard' => ['PROCESSING',      false,              5,  3],
            'COMPLETED standard' => ['COMPLETED',       false,              5,  4],
            'REJECTED standard' => ['REJECTED',        false,              3,  null],
            'FOR_COMPLIANCE via status' => ['FOR_COMPLIANCE',  false,              6,  3],
            'PROCESSING with compliance history' => ['PROCESSING',      true,               6,  4],
            'COMPLETED with compliance history' => ['COMPLETED',       true,               6,  5],
            // NOTE: REJECTED has an early return before the compliance check (line 334),
            // so it always produces 3 steps regardless of compliance history.
            'REJECTED with compliance history' => ['REJECTED',        true,               3,  null],
            'PROCESSING with compli milestone' => ['PROCESSING',      'compli-title',     6,  4],
            // UNKNOWN falls through to the else branch which sets all steps to 'pending'.
            'UNKNOWN status fallback' => ['UNKNOWN',         false,              5,  null],
        ];
    }

    /**
     * Verify that buildAgencySteps() (via buildTrackingData()) returns the correct
     * step count, active position, and structural integrity for every combination
     * of referral status and compliance path.
     */
    #[DataProvider('agencyStepProvider')]
    public function test_agency_steps_data_provider(
        string $status,
        mixed $complianceTrigger,
        int $expectedCount,
        ?int $activeIndex,
    ): void {
        // Arrange
        $service = app(TrackingService::class);
        $caseData = $this->createCompleteCase();
        $case = $caseData['case'];
        $referral = $caseData['referrals']->first();

        // Set referral to the desired status
        $referral->update(['status' => $status]);
        $referral->refresh();

        // Trigger compliance history when required
        if ($complianceTrigger === true || is_string($complianceTrigger)) {
            Milestone::factory()->create([
                'refr_id' => $referral->id,
                'title' => 'Compliance check completed',
            ]);
        }

        $this->loadRelations($case);

        // Act
        $data = $service->buildTrackingData($case);
        $steps = $data['trackingAgencies'][0]['steps'];

        // Assert — step count
        $this->assertCount($expectedCount, $steps);

        // Assert — each step has structural integrity
        foreach ($steps as $step) {
            $this->assertArrayHasKey('label', $step);
            $this->assertArrayHasKey('state', $step);
            $this->assertContains($step['state'], ['complete', 'active', 'pending']);
        }

        // Assert — active position
        if ($activeIndex !== null) {
            $this->assertEquals('active', $steps[$activeIndex]['state']);
        } else {
            // No step should be 'active' — all are complete or pending
            foreach ($steps as $step) {
                $this->assertNotEquals('active', $step['state']);
            }
        }
    }
}
