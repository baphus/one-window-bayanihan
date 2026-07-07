<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\CaseStatus;
use App\Models\Client;
use App\Models\Referral;
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
        $this->managerA = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->managerB = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->agency = Agency::factory()->create();
    }

    private function kpisFor(User $manager): array
    {
        return $this->service->getReferralKpis($manager->id, 'CASE_MANAGER');
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
    public function kpis_are_scoped_to_the_case_manager(): void
    {
        $caseA = CaseFile::factory()->create(['user_id' => $this->managerA->id, 'status' => 'OPEN']);
        Referral::factory()->count(2)->pending()->create(['case_id' => $caseA->id, 'agcy_id' => $this->agency->id]);

        $caseB = CaseFile::factory()->create(['user_id' => $this->managerB->id, 'status' => 'OPEN']);
        Referral::factory()->count(3)->pending()->create(['case_id' => $caseB->id, 'agcy_id' => $this->agency->id]);

        $this->assertSame(2, $this->kpisFor($this->managerA)['totalReferrals']);
        $this->assertSame(3, $this->kpisFor($this->managerB)['totalReferrals']);
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
    public function gender_distribution_reports_unknown_not_other_and_respects_scope(): void
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

        $dist = $this->service->getGenderDistribution($this->managerA->id, 'CASE_MANAGER');

        $this->assertSame(['Male', 'Female', 'Unknown'], $dist['labels']);
        $this->assertSame([1, 1, 1], $dist['data']);
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
}
