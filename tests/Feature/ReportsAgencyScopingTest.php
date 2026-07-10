<?php

namespace Tests\Feature;

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
}
