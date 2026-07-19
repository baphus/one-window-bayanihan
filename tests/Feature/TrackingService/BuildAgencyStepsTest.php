<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
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
     * DataProvider returning combinations of referral status × expected steps.
     *
     * Each case: [status, expectedCount, activeIndex]
     *
     * @return array<string, array{mixed, int, int|null}>
     */
    public static function agencyStepProvider(): array
    {
        return [
            'PENDING standard' => ['PENDING',    3,  2],
            'PROCESSING' => ['PROCESSING',  5,  3],
            'COMPLETED' => ['COMPLETED',   5,  4],
            'REJECTED' => ['REJECTED',    3,  null],
            'UNKNOWN status' => ['UNKNOWN',     5,  null],
        ];
    }

    /**
     * Verify that buildAgencySteps() (via buildTrackingData()) returns the correct
     * step count, active position, and structural integrity for every combination
     * of referral status.
     */
    #[DataProvider('agencyStepProvider')]
    public function test_agency_steps_data_provider(
        string $status,
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
