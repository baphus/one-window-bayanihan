<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\CaseStatus;
use App\Models\Client;
use App\Models\ClientEmployment;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsMetricsTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $service;

    private User $managerA;

    private User $managerB;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReportsService::class);
        $this->managerA = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->managerB = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->agency = Agency::factory()->create();
    }

    private function kpisFor(User $manager): array
    {
        return $this->service->getReferralKpis($manager->id, UserRole::CASE_MANAGER->value);
    }

    #[Test]
    public function kpis_expose_the_new_metric_fields(): void
    {
        $case = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        Referral::factory()->forCompliance()->create(['case_id' => $case->id, 'agcy_id' => $this->agency->id]);

        $kpis = $this->kpisFor($this->managerA);

        $this->assertArrayHasKey('openCases', $kpis);
        $this->assertArrayHasKey('forComplianceReferrals', $kpis);
        $this->assertArrayHasKey('avgResolutionDays', $kpis);
        $this->assertSame(1, $kpis['openCases']);
        $this->assertSame(1, $kpis['forComplianceReferrals']);
    }

    #[Test]
    public function case_manager_kpis_include_all_referrals(): void
    {
        $caseA = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        Referral::factory()->count(2)->pending()->create(['case_id' => $caseA->id, 'agcy_id' => $this->agency->id]);

        $caseB = CaseFile::factory()->create(['user_id' => $this->managerB->id, 'status' => 'OPEN']);
        Referral::factory()->count(3)->pending()->create(['case_id' => $caseB->id, 'agcy_id' => $this->agency->id]);

        $this->assertSame(5, $this->kpisFor($this->managerA)['totalReferrals']);
        $this->assertSame(5, $this->kpisFor($this->managerB)['totalReferrals']);
    }

    #[Test]
    public function case_manager_referral_trends_include_all_referrals_and_month_counts(): void
    {
        $caseA = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        $caseB = CaseFile::factory()->create(['user_id' => $this->managerB->id, 'status' => 'OPEN']);

        Referral::factory()->count(2)->pending()->create([
            'case_id' => $caseA->id,
            'agcy_id' => $this->agency->id,
            'created_at' => '2026-02-10 08:00:00',
            'updated_at' => '2026-02-10 08:00:00',
        ]);
        Referral::factory()->pending()->create([
            'case_id' => $caseA->id,
            'agcy_id' => $this->agency->id,
            'created_at' => '2026-03-05 08:00:00',
            'updated_at' => '2026-03-05 08:00:00',
        ]);
        Referral::factory()->count(3)->pending()->create([
            'case_id' => $caseB->id,
            'agcy_id' => $this->agency->id,
            'created_at' => '2026-02-15 08:00:00',
            'updated_at' => '2026-02-15 08:00:00',
        ]);

        $trends = $this->service->getReferralTrends(
            $this->managerA->id,
            UserRole::CASE_MANAGER->value,
            '2026-02-01',
            '2026-03-31',
            'referral_created_at'
        );

        $this->assertSame(['2026-02', '2026-03'], $trends['labels']);
        $this->assertSame('Referrals Created', $trends['datasets'][0]['label']);
        $this->assertSame([5, 1], array_map('intval', $trends['datasets'][0]['data']));
    }

    #[Test]
    public function referral_trends_are_scoped_to_the_agency(): void
    {
        $otherAgency = Agency::factory()->create();
        $case = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);

        Referral::factory()->count(2)->pending()->create([
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
            'created_at' => '2026-04-10 08:00:00',
            'updated_at' => '2026-04-10 08:00:00',
        ]);
        Referral::factory()->count(4)->pending()->create([
            'case_id' => $case->id,
            'agcy_id' => $otherAgency->id,
            'created_at' => '2026-04-11 08:00:00',
            'updated_at' => '2026-04-11 08:00:00',
        ]);

        $trends = $this->service->getReferralTrends(
            null,
            UserRole::AGENCY->value,
            '2026-04-01',
            '2026-04-30',
            'referral_created_at',
            null,
            null,
            $this->agency->id
        );

        $this->assertSame(['2026-04'], $trends['labels']);
        $this->assertSame([2], array_map('intval', $trends['datasets'][0]['data']));
    }

    #[Test]
    public function resolution_time_uses_the_real_close_timestamp(): void
    {
        // Case opened 10 days ago, closed today -> ~10 day resolution.
        CaseFile::factory()->create([
            'user_id' => $this->managerA->id,
            'status' => 'CLOSED',
            'created_at' => now()->subDays(10),
            'closed_at' => now(),
        ]);

        $avg = $this->kpisFor($this->managerA)['avgResolutionDays'];

        $this->assertGreaterThanOrEqual(9.5, $avg);
        $this->assertLessThanOrEqual(10.5, $avg);
    }

    #[Test]
    public function gender_distribution_reports_unknown_not_other_and_unscoped_for_case_manager(): void
    {
        $male = Client::factory()->create(['sex' => 'MALE']);
        $female = Client::factory()->create(['sex' => 'FEMALE']);
        $unknown = Client::factory()->create(['sex' => null]);

        foreach ([$male, $female, $unknown] as $client) {
            CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN', 'client_id' => $client->id]);
        }
        // A client that belongs to another manager must not be counted.
        $other = Client::factory()->create(['sex' => 'MALE']);
        CaseFile::factory()->create(['user_id' => $this->managerB->id, 'status' => 'OPEN', 'client_id' => $other->id]);

        $dist = $this->service->getGenderDistribution($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertSame(['Male', 'Female', 'Unknown'], $dist['labels']);
        $this->assertSame([2, 1, 1], $dist['data']);
        $this->assertNotContains('Other', $dist['labels']);
    }

    #[Test]
    public function reference_data_returns_active_ordered_statuses(): void
    {
        // case_statuses is seeded by migration with the canonical referral statuses.
        $ref = $this->service->getReferenceData();

        $this->assertArrayHasKey('referralStatuses', $ref);
        $slugs = array_column($ref['referralStatuses'], 'slug');
        $this->assertContains('PENDING', $slugs);
        $this->assertContains('COMPLETED', $slugs);
        $this->assertArrayHasKey('color', $ref['referralStatuses'][0]);

        // Inactive statuses must be excluded from the toggle list.
        CaseStatus::create(['name' => 'Temp Inactive', 'slug' => 'ZZ_TEMP', 'type' => 'referral', 'color' => '#000000', 'sort_order' => 99, 'is_active' => false]);
        $after = $this->service->getReferenceData();
        $this->assertNotContains('ZZ_TEMP', array_column($after['referralStatuses'], 'slug'));
    }

    #[Test]
    public function employment_occupation_breakdown_is_unscoped_for_case_manager_and_is_deterministic(): void
    {
        $otherManager = $this->managerB;
        $ownedClient = Client::factory()->create();
        $secondOwnedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        CaseFile::factory()->create(['user_id' => $this->managerA->id, 'client_id' => $ownedClient->id, 'status' => 'OPEN']);
        CaseFile::factory()->create(['user_id' => $this->managerA->id, 'client_id' => $secondOwnedClient->id, 'status' => 'OPEN']);
        CaseFile::factory()->create(['user_id' => $otherManager->id, 'client_id' => $otherClient->id, 'status' => 'OPEN']);

        // Multiple employment rows for one client count once for a position.
        ClientEmployment::create(['client_id' => $ownedClient->id, 'last_position' => 'Engineer']);
        ClientEmployment::create(['client_id' => $ownedClient->id, 'last_position' => 'Engineer']);
        ClientEmployment::create(['client_id' => $secondOwnedClient->id, 'last_position' => 'Nurse']);
        ClientEmployment::create(['client_id' => $otherClient->id, 'last_position' => 'Engineer']);

        $deleted = ClientEmployment::create(['client_id' => $ownedClient->id, 'last_position' => 'Deleted Position']);
        $deleted->forceFill(['is_deleted' => true])->save();
        ClientEmployment::create(['client_id' => $ownedClient->id, 'last_position' => null]);

        $breakdown = $this->service->getEmploymentOccupationBreakdown($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertSame(['Engineer', 'Nurse'], $breakdown['labels']);
        $this->assertSame([2, 1], array_map('intval', $breakdown['data']));
        $this->assertSame(2, $breakdown['total_distinct']);
    }

    #[Test]
    public function case_manager_report_payload_falls_back_to_global_when_user_id_absent(): void
    {
        $case = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        Referral::factory()->pending()->create(['case_id' => $case->id, 'agcy_id' => $this->agency->id]);

        $kpis = $this->service->getReferralKpis(null, UserRole::CASE_MANAGER->value);

        $this->assertSame(1, $kpis['totalReferrals']);
        $this->assertSame(1, $kpis['totalCases']);
    }

    #[Test]
    public function case_source_distribution_groups_internal_and_self_filed(): void
    {
        CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN', 'source' => 'internal']);
        CaseFile::factory()->count(2)->create(['user_id' => $this->managerA->id, 'status' => 'OPEN', 'source' => CaseFile::SOURCE_SELF_FILED]);

        $distribution = $this->service->getCaseSourceDistribution($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertSame(['Internal', 'Self-filed'], $distribution['labels']);
        $this->assertSame([1, 2], array_map('intval', $distribution['data']));
    }

    #[Test]
    public function closed_cases_over_time_buckets_closures_by_close_month(): void
    {
        CaseFile::factory()->closed()->create([
            'user_id' => $this->managerA->id,
            'created_at' => '2026-02-10 08:00:00',
            'closed_at' => '2026-02-15 10:00:00',
        ]);
        CaseFile::factory()->count(2)->closed()->create([
            'user_id' => $this->managerA->id,
            'created_at' => '2026-03-05 08:00:00',
            'closed_at' => '2026-03-20 10:00:00',
        ]);
        CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);

        $trend = $this->service->getClosedCasesOverTime($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertSame(['2026-02', '2026-03'], $trend['labels']);
        $this->assertSame('Cases Closed', $trend['datasets'][0]['label']);
        $this->assertSame([1, 2], array_map('intval', $trend['datasets'][0]['data']));
    }

    #[Test]
    public function reopened_stats_counts_reopens_and_repeat_clients(): void
    {
        $client = Client::factory()->create();
        $caseA = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'client_id' => $client->id, 'status' => 'OPEN']);
        CaseFile::factory()->create(['user_id' => $this->managerA->id, 'client_id' => $client->id, 'status' => 'OPEN']);
        CaseEvent::create([
            'case_id' => $caseA->id,
            'type' => CaseEvent::TYPE_CASE_REOPENED,
            'title' => 'Case reopened',
            'actor_type' => 'case_manager',
            'occurred_at' => now(),
        ]);

        $stats = $this->service->getReopenedStats($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertSame(1, $stats['reopenedCount']);
        $this->assertSame(1, $stats['repeatClients']);
        $this->assertSame(1, $stats['totalClients']);
        $this->assertSame(100.0, $stats['repeatClientRate']);
    }

    #[Test]
    public function case_status_distribution_includes_draft_slice(): void
    {
        CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        CaseFile::factory()->closed()->create(['user_id' => $this->managerA->id]);
        CaseFile::factory()->draft()->create(['user_id' => $this->managerA->id]);

        $distribution = $this->service->getCaseStatusDistribution($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertSame(['OPEN', 'CLOSED', 'DRAFT'], $distribution['labels']);
        $this->assertSame([1, 1, 1], array_map('intval', $distribution['data']));
    }

    #[Test]
    public function case_event_actor_distribution_groups_by_actor(): void
    {
        $case = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        foreach (['agency', 'agency', 'system'] as $actor) {
            CaseEvent::create([
                'case_id' => $case->id,
                'type' => CaseEvent::TYPE_MILESTONE_ADDED,
                'title' => 'Activity',
                'actor_type' => $actor,
                'occurred_at' => now(),
            ]);
        }

        $distribution = $this->service->getCaseEventActorDistribution($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertSame(['Agency', 'Case manager', 'System'], $distribution['labels']);
        $this->assertSame([2, 0, 1], array_map('intval', $distribution['data']));
    }

    #[Test]
    public function agency_scorecard_rows_carry_overdue_counts(): void
    {
        $case = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        Referral::factory()->pending()->create([
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ]);
        Referral::factory()->pending()->create(['case_id' => $case->id, 'agcy_id' => $this->agency->id]);

        $scorecard = $this->service->getAgencyScorecard($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertCount(1, $scorecard);
        $this->assertSame(2, $scorecard[0]['total']);
        $this->assertSame(1, $scorecard[0]['overdue']);
    }

    #[Test]
    public function agency_first_response_reports_median_days_per_agency(): void
    {
        $case = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        $fast = Referral::factory()->processing()->create([
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        $slow = Referral::factory()->processing()->create([
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        foreach ([[$fast, 4], [$slow, 2]] as [$referral, $daysAgo]) {
            CaseEvent::create([
                'case_id' => $case->id,
                'referral_id' => $referral->id,
                'type' => CaseEvent::TYPE_REFERRAL_STATUS_CHANGED,
                'title' => 'Accepted',
                'meta' => ['from' => 'PENDING', 'to' => 'PROCESSING'],
                'actor_type' => 'agency',
                'occurred_at' => now()->subDays($daysAgo),
            ]);
        }

        $response = $this->service->getAgencyFirstResponse($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertCount(1, $response);
        $this->assertSame($this->agency->name, $response[0]['agency']);
        $this->assertSame(7.0, $response[0]['medianDays']);
        $this->assertSame(2, $response[0]['samples']);
    }

    #[Test]
    public function client_request_type_distribution_groups_by_type(): void
    {
        $case = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        $referral = Referral::factory()->pending()->create(['case_id' => $case->id, 'agcy_id' => $this->agency->id]);
        ReferralClientRequest::factory()->count(2)->create([
            'referral_id' => $referral->id,
            'type' => ReferralClientRequest::TYPE_DOCUMENT_REQUEST,
        ]);
        ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'type' => ReferralClientRequest::TYPE_QUESTION,
        ]);

        $distribution = $this->service->getClientRequestTypeDistribution($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertSame(['Document request', 'Question', 'Information update'], $distribution['labels']);
        $this->assertSame([2, 1, 0], array_map('intval', $distribution['data']));
    }

    #[Test]
    public function empty_report_payload_carries_zero_shapes_for_new_aggregates(): void
    {
        $agencyless = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => null]);

        $payload = $this->service->getAll(
            userId: $agencyless->id,
            role: UserRole::AGENCY->value,
            agencyId: null,
            fromDate: '2026-01-01',
            toDate: '2026-12-31',
        );

        $this->assertSame([0, 0], $payload['caseSourceDistribution']['data']);
        $this->assertSame([], $payload['closedCasesOverTime']);
        $this->assertSame(0, $payload['reopenedStats']['reopenedCount']);
        $this->assertSame(0, $payload['reopenedStats']['repeatClientRate']);
        $this->assertSame([0, 0, 0], $payload['caseEventActorDistribution']['data']);
        $this->assertSame([], $payload['agencyFirstResponse']);
        $this->assertSame([0, 0, 0], $payload['clientRequestTypeDistribution']['data']);
    }

    #[Test]
    public function vulnerability_distribution_counts_null_indicators_as_none(): void
    {
        CaseFile::factory()->create([
            'user_id' => $this->managerA->id,
            'status' => 'OPEN',
            'vulnerability_indicator' => null,
            'nok_vulnerability_indicator' => null,
        ]);
        CaseFile::factory()->create([
            'user_id' => $this->managerA->id,
            'status' => 'OPEN',
            'vulnerability_indicator' => 'PWD',
            'nok_vulnerability_indicator' => null,
        ]);

        $distribution = $this->service->getVulnerabilityDistribution($this->managerA->id, UserRole::CASE_MANAGER->value);

        $combined = array_combine($distribution['labels'], array_map('intval', $distribution['data']));
        $this->assertSame(1, $combined['PWD']);
        $this->assertSame(1, $combined['None']);
    }

    #[Test]
    public function rejection_reason_distribution_groups_rejected_referrals(): void
    {
        $case = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        Referral::factory()->count(2)->rejected()->create([
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
            'rejection_reason' => 'INCOMPLETE_REQUIREMENTS',
        ]);
        Referral::factory()->rejected()->create([
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
            'rejection_reason' => 'OTHER',
        ]);
        Referral::factory()->pending()->create(['case_id' => $case->id, 'agcy_id' => $this->agency->id]);

        $distribution = $this->service->getRejectionReasonDistribution($this->managerA->id, UserRole::CASE_MANAGER->value);

        $this->assertSame(Referral::REJECTION_REASONS, $distribution['labels']);
        $this->assertCount(6, $distribution['data']);
        $combined = array_combine($distribution['labels'], $distribution['data']);
        $this->assertSame(2, (int) $combined['INCOMPLETE_REQUIREMENTS']);
        $this->assertSame(1, (int) $combined['OTHER']);
        $this->assertSame(0, (int) $combined['DUPLICATE_REFERRAL']);
        $this->assertSame(3, array_sum(array_map('intval', $distribution['data'])));
    }
}
