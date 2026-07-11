<?php

namespace Tests\Feature\TrackController;

use App\Models\Referral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class MilestonesTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    public function test_valid_referral_renders_agency_milestones_page(): void
    {
        $result = $this->createCompleteCase(milestonesPerReferral: 2);
        $case = $result['case'];
        $referral = $result['referrals']->first();

        $response = $this->get(route('track.milestones', [
            'tracker_number' => $case->tracker_number,
            'referral' => $referral->id,
        ]));

        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/AgencyMilestones')
            ->has('trackingId')
            ->has('trackedCase')
            ->has('agencyMilestones')
            ->where('trackingId', $case->tracker_number)
            ->where('agencyMilestones.agencyName', $referral->agency->name)
            ->has('agencyMilestones.latestUpdate')
            ->has('agencyMilestones.milestones')
            ->has('agencyMilestones.milestones.0.title')
        );
    }

    public function test_referral_from_another_case_returns_404(): void
    {
        $caseA = $this->createCompleteCase()['case'];
        $resultB = $this->createCompleteCase(milestonesPerReferral: 1);
        /** @var Referral $foreignReferral */
        $foreignReferral = $resultB['referrals']->first();

        $response = $this->get(route('track.milestones', [
            'tracker_number' => $caseA->tracker_number,
            'referral' => $foreignReferral->id,
        ]));

        $response->assertNotFound();
    }
}
