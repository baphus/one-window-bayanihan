<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Services\CaseEventRecorder;
use App\Services\CaseSwimlaneService;
use App\Services\DashboardService;
use App\Services\ReferralStatusPresentation;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Single canonical referral-status presentation.
 *
 * Every expectation below was snapshotted from the pre-refactor code before
 * the centralization (TrackingService weights/steps/case-remap,
 * CaseSwimlaneService labels, DashboardService labels/tones), so the suite
 * proves the refactor changed homes without changing browser-visible output.
 */
class ReferralStatusPresentationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const STATES = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];

    public function test_canonical_map_covers_all_states_plus_default(): void
    {
        $expectedClient = [
            'PENDING' => 'Awaiting receipt',
            'PROCESSING' => 'In process',
            'FOR_COMPLIANCE' => 'Needs documents',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Unable to assist',
        ];
        $expectedStaff = [
            'PENDING' => 'Sent to agency — awaiting response',
            'PROCESSING' => 'Accepted — now processing',
            'FOR_COMPLIANCE' => 'Set as For Compliance',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Rejected',
        ];
        $expectedDashboard = [
            'PENDING' => 'Pending',
            'PROCESSING' => 'Processing',
            'FOR_COMPLIANCE' => 'For compliance',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Rejected',
        ];
        $expectedTones = [
            'PENDING' => 'amber',
            'PROCESSING' => 'blue',
            'FOR_COMPLIANCE' => 'orange',
            'COMPLETED' => 'emerald',
            'REJECTED' => 'rose',
        ];
        $expectedWeights = [
            'PENDING' => 10,
            'PROCESSING' => 66,
            'FOR_COMPLIANCE' => 33,
            'COMPLETED' => 100,
            'REJECTED' => 0,
        ];

        foreach (self::STATES as $status) {
            $this->assertSame($expectedClient[$status], ReferralStatusPresentation::clientLabel($status));
            $this->assertSame($expectedStaff[$status], ReferralStatusPresentation::staffLabel($status));
            $this->assertSame($expectedDashboard[$status], ReferralStatusPresentation::dashboardLabel($status));
            $this->assertSame($expectedTones[$status], ReferralStatusPresentation::tone($status));
            $this->assertSame($expectedWeights[$status], ReferralStatusPresentation::weight($status));
        }

        $this->assertSame('Update available', ReferralStatusPresentation::clientLabel('BOGUS'));
        $this->assertSame('Status updated to BOGUS', ReferralStatusPresentation::staffLabel('BOGUS'));
        $this->assertSame('Some Status', ReferralStatusPresentation::dashboardLabel('SOME_STATUS'));
        $this->assertSame('slate', ReferralStatusPresentation::tone('BOGUS'));
        $this->assertSame(0, ReferralStatusPresentation::weight('BOGUS'));

        $this->assertTrue(ReferralStatusPresentation::isTerminal('COMPLETED'));
        $this->assertTrue(ReferralStatusPresentation::isTerminal('REJECTED'));
        $this->assertFalse(ReferralStatusPresentation::isTerminal('PENDING'));
        $this->assertFalse(ReferralStatusPresentation::isTerminal('PROCESSING'));
        $this->assertFalse(ReferralStatusPresentation::isTerminal('FOR_COMPLIANCE'));
    }

    public function test_staff_label_rejection_suffix_matches_legacy(): void
    {
        $referral = Referral::factory()->rejected()->create([
            'rejection_reason' => 'OUTSIDE_MANDATE',
            'decision_comment' => 'No capacity at this time',
        ]);

        $this->assertSame(
            'Rejected (Outside mandate): No capacity at this time',
            ReferralStatusPresentation::staffLabel('REJECTED', $referral->refresh())
        );
    }

    public function test_agency_steps_match_pre_refactor_snapshots(): void
    {
        $this->assertSame(
            [
                ['label' => 'Created', 'state' => 'complete'],
                ['label' => 'Referred to OWWA', 'state' => 'complete'],
                ['label' => 'Received by OWWA', 'state' => 'active'],
            ],
            ReferralStatusPresentation::agencySteps('PENDING', 'OWWA')
        );
        $this->assertSame(
            [
                ['label' => 'Created', 'state' => 'complete'],
                ['label' => 'Referred to OWWA', 'state' => 'complete'],
                ['label' => 'Received by OWWA', 'state' => 'complete'],
            ],
            ReferralStatusPresentation::agencySteps('REJECTED', 'OWWA')
        );
        $this->assertSame(
            [
                ['label' => 'Created', 'state' => 'complete'],
                ['label' => 'Referred to OWWA', 'state' => 'complete'],
                ['label' => 'Received by OWWA', 'state' => 'complete'],
                ['label' => 'Processing', 'state' => 'active'],
                ['label' => 'Completed', 'state' => 'pending'],
            ],
            ReferralStatusPresentation::agencySteps('PROCESSING', 'OWWA')
        );
        $this->assertSame(
            [
                ['label' => 'Created', 'state' => 'complete'],
                ['label' => 'Referred to OWWA', 'state' => 'complete'],
                ['label' => 'Received by OWWA', 'state' => 'complete'],
                ['label' => 'Processing', 'state' => 'complete'],
                ['label' => 'Completed', 'state' => 'active'],
            ],
            ReferralStatusPresentation::agencySteps('COMPLETED', 'OWWA')
        );
        $this->assertSame(
            [
                ['label' => 'Created', 'state' => 'complete'],
                ['label' => 'Referred to OWWA', 'state' => 'complete'],
                ['label' => 'Received by OWWA', 'state' => 'complete'],
                ['label' => 'Processing', 'state' => 'pending'],
                ['label' => 'Completed', 'state' => 'pending'],
            ],
            ReferralStatusPresentation::agencySteps('FOR_COMPLIANCE', 'OWWA')
        );
    }

    public function test_case_status_remap_matches_pre_refactor(): void
    {
        $this->assertSame('IN_PROGRESS', ReferralStatusPresentation::caseStatus('OPEN'));
        $this->assertSame('RESOLVED', ReferralStatusPresentation::caseStatus('CLOSED'));
        $this->assertSame('ARCHIVED', ReferralStatusPresentation::caseStatus('ARCHIVED'));
        $this->assertSame('BEING_PREPARED', ReferralStatusPresentation::caseStatus('DRAFT'));
        $this->assertSame('UNKNOWN', ReferralStatusPresentation::caseStatus('BOGUS'));
    }

    public function test_tracking_weights_resolve_through_canonical(): void
    {
        $expected = [
            'PENDING' => 10,
            'PROCESSING' => 66,
            'FOR_COMPLIANCE' => 33,
            'COMPLETED' => 100,
            'REJECTED' => 0,
        ];

        foreach ($expected as $status => $percentage) {
            $client = Client::factory()->create();
            $case = CaseFile::factory()->create(['client_id' => $client->id]);
            Referral::factory()->create(['case_id' => $case->id, 'status' => $status]);

            $data = app(TrackingService::class)->buildTrackingData($this->loadTrackingRelations($case));

            $this->assertSame($percentage, $data['completionPercentage'], "Weight mismatch for {$status}");
        }
    }

    public function test_tracking_steps_resolve_through_canonical(): void
    {
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PROCESSING']);
        $agencyName = $referral->agency->name;

        $steps = app(TrackingService::class)->buildTrackingData($this->loadTrackingRelations($case))['trackingAgencies'][0]['steps'];

        $this->assertSame(
            [
                ['label' => 'Created', 'state' => 'complete'],
                ['label' => "Referred to {$agencyName}", 'state' => 'complete'],
                ['label' => "Received by {$agencyName}", 'state' => 'complete'],
                ['label' => 'Processing', 'state' => 'active'],
                ['label' => 'Completed', 'state' => 'pending'],
            ],
            $steps
        );
    }

    public function test_tracking_case_status_resolves_through_canonical(): void
    {
        $cases = [
            'OPEN' => 'IN_PROGRESS',
            'CLOSED' => 'RESOLVED',
            'ARCHIVED' => 'ARCHIVED',
            'DRAFT' => 'BEING_PREPARED',
        ];

        foreach ($cases as $caseStatus => $expected) {
            $client = Client::factory()->create();
            $case = CaseFile::factory()->create(['client_id' => $client->id, 'status' => $caseStatus]);

            $data = app(TrackingService::class)->buildTrackingData($this->loadTrackingRelations($case));

            $this->assertSame($expected, $data['trackedCase']['status'], "Remap mismatch for {$caseStatus}");
        }
    }

    public function test_sanitized_agency_card_shape_survives_refactor(): void
    {
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);

        $card = app(TrackingService::class)->buildTrackingData($this->loadTrackingRelations($case))['trackingAgencies'][0];

        $this->assertSame(
            ['referralId', 'name', 'status', 'milestoneCount', 'steps', 'latestMilestoneLabel', 'milestonesUrl', 'services'],
            array_keys($card)
        );
        $this->assertSame(['label', 'state'], array_keys($card['steps'][0]));
    }

    public function test_swimlane_labels_resolve_through_canonical(): void
    {
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PENDING']);
        $recorder = app(CaseEventRecorder::class);

        $recorder->referralSent($referral);
        $recorder->referralStatusChanged($referral, 'PENDING', 'PROCESSING');
        $recorder->referralStatusChanged($referral, 'PROCESSING', 'COMPLETED');
        $referral->update(['status' => 'COMPLETED']);

        $service = app(CaseSwimlaneService::class);
        $internal = $service->buildSwimlaneTimeline(CaseFile::findOrFail($case->id));
        $clientLane = $service->buildClientSwimlaneTimeline(CaseFile::findOrFail($case->id));

        $this->assertSame(
            ['Sent to agency — awaiting response', 'Accepted — now processing', 'Completed'],
            array_column($internal['referrals'][0]['segments'], 'label')
        );
        $this->assertSame('Completed', $internal['referrals'][0]['statusLabel']);

        $this->assertSame(
            ['Awaiting receipt', 'In process', 'Completed'],
            array_column($clientLane['referrals'][0]['segments'], 'label')
        );
        $this->assertSame('Completed', $clientLane['referrals'][0]['statusLabel']);
    }

    public function test_dashboard_distribution_resolves_through_canonical(): void
    {
        $method = new \ReflectionMethod(DashboardService::class, 'buildStatusDistributionFromCounts');
        $method->setAccessible(true);

        $rows = $method->invoke(app(DashboardService::class), [
            'PENDING' => 2,
            'PROCESSING' => 1,
            'FOR_COMPLIANCE' => 1,
            'COMPLETED' => 3,
            'REJECTED' => 1,
            'BOGUS' => 0,
        ], 8);

        // Zero-count states are filtered; percents are unchanged math.
        $this->assertSame(
            [
                ['status' => 'PENDING', 'label' => 'Pending', 'count' => 2, 'percent' => 25, 'tone' => 'amber'],
                ['status' => 'PROCESSING', 'label' => 'Processing', 'count' => 1, 'percent' => 13, 'tone' => 'blue'],
                ['status' => 'FOR_COMPLIANCE', 'label' => 'For compliance', 'count' => 1, 'percent' => 13, 'tone' => 'orange'],
                ['status' => 'COMPLETED', 'label' => 'Completed', 'count' => 3, 'percent' => 38, 'tone' => 'emerald'],
                ['status' => 'REJECTED', 'label' => 'Rejected', 'count' => 1, 'percent' => 13, 'tone' => 'rose'],
            ],
            $rows
        );
    }

    private function loadTrackingRelations(CaseFile $case): CaseFile
    {
        return $case->load([
            'client.addresses',
            'client.employments',
            'client.nextOfKin',
            'referrals.agency',
            'referrals.services',
            'referrals.milestones.user',
            'user',
            'category',
            'categories',
        ]);
    }
}
