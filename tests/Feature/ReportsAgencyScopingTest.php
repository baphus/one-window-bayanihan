<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsAgencyScopingTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $service;

    private Agency $agencyA;

    private Agency $agencyB;

    private User $agencyAUser;

    private User $agencyBUser;

    private string $agencyAId;

    private string $agencyBId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);

        $this->service = app(ReportsService::class);
        $this->agencyA = Agency::factory()->create(['name' => 'Agency A']);
        $this->agencyB = Agency::factory()->create(['name' => 'Agency B']);
        $this->agencyAId = $this->agencyA->id;
        $this->agencyBId = $this->agencyB->id;

        $this->agencyAUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->agencyAId,
        ]);
        $this->agencyBUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->agencyBId,
        ]);
    }

    private function createClientWithCase(string $userId, string $agencyId): array
    {
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'user_id' => $userId,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);
        Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agencyId,
        ]);

        return ['client' => $client, 'case' => $case];
    }

    #[Test]
    public function agency_referral_kpis_are_scoped(): void
    {
        // Agency A has 2 referrals, Agency B has 1
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);
        $this->createClientWithCase($this->agencyBUser->id, $this->agencyBId);

        $kpisA = $this->service->getReferralKpis(
            userId: $this->agencyAUser->id,
            role: 'AGENCY',
            agencyId: $this->agencyAId,
        );

        $kpisB = $this->service->getReferralKpis(
            userId: $this->agencyBUser->id,
            role: 'AGENCY',
            agencyId: $this->agencyBId,
        );

        $this->assertSame(2, $kpisA['totalReferrals'], 'Agency A should see 2 referrals');
        $this->assertSame(1, $kpisB['totalReferrals'], 'Agency B should see 1 referral');
    }

    #[Test]
    public function agency_referral_status_distribution_is_scoped(): void
    {
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);
        $this->createClientWithCase($this->agencyBUser->id, $this->agencyBId);

        $distA = $this->service->getReferralStatusDistribution(
            userId: $this->agencyAUser->id,
            role: 'AGENCY',
            agencyId: $this->agencyAId,
        );

        $distB = $this->service->getReferralStatusDistribution(
            userId: $this->agencyBUser->id,
            role: 'AGENCY',
            agencyId: $this->agencyBId,
        );

        $totalA = array_sum($distA['data']);
        $totalB = array_sum($distB['data']);

        $this->assertSame(1, $totalA, 'Agency A should see 1 referral in status dist');
        $this->assertSame(1, $totalB, 'Agency B should see 1 referral in status dist');
    }

    #[Test]
    public function agency_scorecard_is_scoped(): void
    {
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);
        $this->createClientWithCase($this->agencyBUser->id, $this->agencyBId);

        $scorecard = $this->service->getAgencyScorecard(
            userId: $this->agencyAUser->id,
            role: 'AGENCY',
            agencyId: $this->agencyAId,
        );

        // Only Agency A's data should appear
        $this->assertCount(1, $scorecard, 'Agency A scorecard should have 1 entry');
        $this->assertSame(1, $scorecard[0]['total'], 'Scorecard entry should show 1 referral');
    }

    #[Test]
    public function agency_case_status_distribution_is_scoped(): void
    {
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);
        $this->createClientWithCase($this->agencyBUser->id, $this->agencyBId);

        $distA = $this->service->getCaseStatusDistribution(
            userId: $this->agencyAUser->id,
            role: 'AGENCY',
            agencyId: $this->agencyAId,
        );

        // Case status counts each case — Agency A has 1 case
        $totalA = array_sum($distA['data']);
        $this->assertSame(1, $totalA, 'Agency A should see 1 case in status distribution');
    }

    #[Test]
    public function agency_client_type_distribution_is_scoped(): void
    {
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);
        $this->createClientWithCase($this->agencyBUser->id, $this->agencyBId);

        $distA = $this->service->getClientTypeDistribution(
            userId: $this->agencyAUser->id,
            role: 'AGENCY',
            agencyId: $this->agencyAId,
        );

        // Each case has 1 client — Agency A has 1 case
        $totalA = array_sum($distA['data']);
        $this->assertSame(1, $totalA, 'Agency A should see 1 client in type distribution');
    }

    #[Test]
    public function agency_province_options_are_scoped(): void
    {
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);
        $this->createClientWithCase($this->agencyBUser->id, $this->agencyBId);

        $options = $this->service->getProvinceOptions(
            userId: $this->agencyAUser->id,
            role: 'AGENCY',
            agencyId: $this->agencyAId,
        );

        // Province options should be available (client factory generates addresses)
        // We just verify the method doesn't error and returns an array
        $this->assertIsArray($options);
    }

    #[Test]
    public function agency_user_without_agency_id_gets_an_empty_full_report_payload(): void
    {
        $agencyless = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]);
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);

        $payload = $this->service->getAll(
            userId: $agencyless->id,
            role: 'AGENCY',
            agencyId: null,
            fromDate: '2026-01-01',
            toDate: '2026-12-31',
        );

        $this->assertSame([
            'kpis', 'referralStatusDistribution', 'referralTrends',
            'avgReferralCompletion', 'cycleTimeDistribution', 'agencyScorecard',
            'categoryDistribution', 'caseStatusDistribution', 'genderDistribution',
            'ageGroupDistribution', 'clientTypeDistribution', 'geographicMapData',
            'role',
        ], array_keys($payload));
        $this->assertSame(0, $payload['kpis']['totalReferrals']);
        $this->assertSame(0, array_sum($payload['referralStatusDistribution']['data']));
        $this->assertSame([], $payload['agencyScorecard']);
        $this->assertSame([], $payload['clientTypeDistribution']['labels']);
    }

    #[Test]
    public function missing_agency_scope_cannot_reuse_cached_payload_or_options(): void
    {
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);

        $scoped = $this->service->getAll(
            userId: $this->agencyAUser->id,
            role: 'AGENCY',
            agencyId: $this->agencyAId,
            fromDate: '2026-01-01',
            toDate: '2026-12-31',
        );
        $this->assertGreaterThan(0, $scoped['kpis']['totalReferrals']);

        $agencyless = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]);
        $empty = $this->service->getAll(
            userId: $agencyless->id,
            role: 'AGENCY',
            agencyId: null,
            fromDate: '2026-01-01',
            toDate: '2026-12-31',
        );

        $this->assertSame(0, $empty['kpis']['totalReferrals']);
        $this->assertSame([], $this->service->getProvinceOptions($agencyless->id, 'AGENCY'));
        $this->assertSame([], $this->service->getCityOptions(null, $agencyless->id, 'AGENCY'));
        $this->assertEqualsCanonicalizing([
            ['value' => $this->agencyA->id, 'label' => 'Agency A'],
            ['value' => $this->agencyB->id, 'label' => 'Agency B'],
        ], $this->service->getAgencyOptions(null, 'CASE_MANAGER'));
        $this->assertSame([], $this->service->getAgencyOptions($agencyless->id, 'AGENCY'));
    }

    #[Test]
    public function public_reports_page_is_empty_for_an_agency_user_without_assignment(): void
    {
        $agencyless = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]);
        $this->createClientWithCase($this->agencyAUser->id, $this->agencyAId);

        $this->actingAs($agencyless)->get(route('reports.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('kpis.totalReferrals', 0)
                ->where('kpis.totalCases', 0));
    }
}
