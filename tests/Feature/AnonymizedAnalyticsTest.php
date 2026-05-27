<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\AnonymizedAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnonymizedAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private AnonymizedAnalyticsService $service;

    private User $user;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AnonymizedAnalyticsService::class);
        $this->user = User::factory()->create(['role' => 'ADMIN']);
        $this->agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);
    }

    #[Test]
    public function cases_by_status_returns_aggregated_data(): void
    {
        CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-A1',
            'client_type' => 'OFW',
            'tracker_number' => 'TRK-A1',
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);
        CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-A2',
            'client_type' => 'OFW',
            'tracker_number' => 'TRK-A2',
            'status' => 'CLOSED',
            'user_id' => $this->user->id,
        ]);
        CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-A3',
            'client_type' => 'OFW',
            'tracker_number' => 'TRK-A3',
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);

        $result = $this->service->casesByStatus();

        $this->assertCount(2, $result);
        $openCount = collect($result)->firstWhere('status', 'OPEN')['total'] ?? 0;
        $closedCount = collect($result)->firstWhere('status', 'CLOSED')['total'] ?? 0;
        $this->assertEquals(2, $openCount);
        $this->assertEquals(1, $closedCount);
    }

    #[Test]
    public function cases_by_service_returns_client_type_aggregation(): void
    {
        CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-B1',
            'client_type' => 'OFW',
            'tracker_number' => 'TRK-B1',
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);
        CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-B2',
            'client_type' => 'OFW',
            'tracker_number' => 'TRK-B2',
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);
        CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-B3',
            'client_type' => 'NEXT_OF_KIN',
            'tracker_number' => 'TRK-B3',
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);

        $result = $this->service->casesByService();

        $this->assertCount(2, $result);
        $ofwCount = collect($result)->firstWhere('service', 'OFW')['total'] ?? 0;
        $nokCount = collect($result)->firstWhere('service', 'NEXT_OF_KIN')['total'] ?? 0;
        $this->assertEquals(2, $ofwCount);
        $this->assertEquals(1, $nokCount);
    }

    #[Test]
    public function total_clients_counts_all_clients(): void
    {
        $case1 = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-C1',
            'client_type' => 'OFW',
            'tracker_number' => 'TRK-C1',
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);
        Client::create([
            'case_id' => $case1->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);

        $case2 = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-C2',
            'client_type' => 'NEXT_OF_KIN',
            'tracker_number' => 'TRK-C2',
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);
        Client::create([
            'case_id' => $case2->id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);

        $total = $this->service->totalClients();
        $this->assertEquals(2, $total);
    }

    #[Test]
    public function referral_stats_returns_zero_when_no_referrals(): void
    {
        $stats = $this->service->referralStats();

        $this->assertEquals(0, $stats['total']);
    }

    #[Test]
    public function referral_stats_with_data(): void
    {
        $case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-D1',
            'client_type' => 'OFW',
            'tracker_number' => 'TRK-D1',
            'status' => 'OPEN',
            'user_id' => $this->user->id,
        ]);

        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Counseling',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
        ]);
        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Legal Aid',
            'status' => 'COMPLETED',
            'case_id' => $case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $stats = $this->service->referralStats();

        $this->assertEquals(2, $stats['total']);
        $this->assertCount(2, $stats['by_status']);
    }

    #[Test]
    public function total_clients_returns_zero_when_no_clients(): void
    {
        $total = $this->service->totalClients();
        $this->assertEquals(0, $total);
    }
}
