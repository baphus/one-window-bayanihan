<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\Service;
use App\Models\SurveyInvitation;
use App\Models\User;
use App\Services\CaseService;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_dashboard_recent_logs_use_the_safe_audit_contract(): void
    {
        $sensitiveValue = 'private-case-note-must-not-reach-the-dashboard';
        $actor = User::factory()->create(['name' => 'Dashboard Tester']);

        $log = AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'referral',
            'user_id' => $actor->id,
            'description' => "Referral updated: {$sensitiveValue}",
            'old_value' => ['private_note' => 'previous private note'],
            'new_value' => ['private_note' => $sensitiveValue],
            'ip_address' => '203.0.113.72',
            'user_agent' => 'Sensitive Dashboard Test Agent',
            'timestamp' => now(),
        ]);

        $entry = collect(app(DashboardService::class)->getAdminData()['recentLogs'])
            ->firstWhere('id', $log->id);

        $this->assertNotNull($entry);
        foreach (['description', 'old_value', 'new_value', 'entity_id', 'ip_address', 'user_agent', 'request_id', 'prev_hash', 'user'] as $field) {
            $this->assertArrayNotHasKey($field, $entry, "{$field} must not reach the dashboard");
        }
        $this->assertStringNotContainsString($sensitiveValue, json_encode($entry));
        $this->assertStringNotContainsString('203.0.113.72', json_encode($entry));
        $this->assertStringNotContainsString('Sensitive Dashboard Test Agent', json_encode($entry));
    }

    #[Test]
    public function agency_dashboard_insights_are_scoped_and_actionable(): void
    {
        $agency = Agency::factory()->create(['name' => 'OWWA Cebu']);
        $otherAgency = Agency::factory()->create(['name' => 'Other Agency']);
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);
        $service = Service::create([
            'name' => 'Repatriation assistance',
            'description' => 'Help returning OFWs coordinate support.',
            'processing_days' => 5,
            'agcy_id' => $agency->id,
        ]);

        $agencyCase = $this->createCaseForClient();
        $otherCase = $this->createCaseForClient();

        $oldPending = Referral::factory()->create([
            'case_id' => $agencyCase->id,
            'agcy_id' => $agency->id,
            'required_services' => $service->name,
            'status' => 'PENDING',
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        $oldPending->services()->sync([$service->id]);

        $refForCompliance = Referral::factory()->create([
            'case_id' => $agencyCase->id,
            'agcy_id' => $agency->id,
            'required_services' => $service->name,
            'status' => 'FOR_COMPLIANCE',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        $refForCompliance->services()->sync([$service->id]);

        $refCompleted = Referral::factory()->completed()->create([
            'case_id' => $agencyCase->id,
            'agcy_id' => $agency->id,
            'required_services' => $service->name,
        ]);
        $refCompleted->services()->sync([$service->id]);
        Referral::factory()->create([
            'case_id' => $otherCase->id,
            'agcy_id' => $otherAgency->id,
            'status' => 'PENDING',
            'created_at' => now()->subDays(10),
        ]);
        SurveyInvitation::create([
            'agency_id' => $agency->id,
            'case_id' => $agencyCase->id,
            'referral_id' => $oldPending->id,
            'client_name' => 'Test Client',
            'client_email' => 'client@example.com',
            'service_name' => $service->name,
            'token' => 'test-survey-token',
            'expires_at' => now()->addDay(),
        ]);

        $data = app(DashboardService::class)->getAgencyData($agencyUser);

        $this->assertSame(3, $data['totalReferrals']);
        $this->assertSame(1, collect($data['workQueue'])->firstWhere('key', 'overdueReferrals')['count']);
        $this->assertArrayNotHasKey('priorityReferrals', $data);
        $this->assertCount(1, $data['pendingReferrals']);
        $this->assertContains($oldPending->id, array_column($data['pendingReferrals'], 'id'));
        $this->assertNull($data['pendingReferrals'][0]['agency_name']);
        $this->assertCount(1, $data['overdueReferrals']);
        $this->assertContains($oldPending->id, array_column($data['overdueReferrals'], 'id'));
        $this->assertSame($service->name, $data['serviceDemand'][0]['serviceName']);
        $this->assertLessThanOrEqual(5, count($data['pendingReferrals']));
        $this->assertLessThanOrEqual(5, count($data['overdueReferrals']));
        $this->assertSame('/referrals?status=PENDING', collect($data['workQueue'])->firstWhere('key', 'pendingReferrals')['href']);
        $this->assertSame('/referrals?age_min_days=7', collect($data['workQueue'])->firstWhere('key', 'overdueReferrals')['href']);
        $this->assertSame('/referrals?status=PROCESSING', collect($data['workQueue'])->firstWhere('key', 'processingReferrals')['href']);
    }

    #[Test]
    public function case_manager_dashboard_insights_surface_coordination_bottlenecks(): void
    {
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create(['name' => 'DMW Help Desk']);
        $slowAgency = Agency::factory()->create(['name' => 'Slow Partner']);

        $caseWithoutReferral = $this->createCaseForClient([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
            'created_at' => now()->subDays(9),
            'updated_at' => now()->subDays(9),
        ]);
        $returnedCase = $this->createCaseForClient([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
            'created_at' => now()->subDays(4),
        ]);
        $completedCase = $this->createCaseForClient([
            'user_id' => $caseManager->id,
            'status' => 'CLOSED',
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDay(),
        ]);

        $returnedReferral = Referral::factory()->rejected()->create([
            'case_id' => $returnedCase->id,
            'agcy_id' => $slowAgency->id,
            'created_at' => now()->subDays(6),
            'updated_at' => now()->subDays(2),
        ]);
        Referral::factory()->completed()->create([
            'case_id' => $completedCase->id,
            'agcy_id' => $agency->id,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(2),
        ]);
        Referral::factory()->pending()->create([
            'case_id' => $returnedCase->id,
            'agcy_id' => $slowAgency->id,
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        $data = app(DashboardService::class)->getCaseManagerData($caseManager);

        $this->assertSame($caseWithoutReferral->id, collect($data['priorityCases'])->firstWhere('reason', 'No referral yet')['id']);
        $this->assertSame($returnedReferral->id, $data['priorityReferrals'][0]['id']);
        $this->assertNotEmpty($data['agencyResponseScorecard']);
        $this->assertLessThanOrEqual(6, count($data['agencyResponseScorecard']));
        $this->assertLessThanOrEqual(8, count($data['priorityCases']));
        $this->assertContains('6-10 days', array_column($data['referralAgingBands'], 'label'));
    }

    #[Test]
    public function case_manager_case_filters_support_age_and_no_referrals(): void
    {
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();

        $matchingCase = $this->createCaseForClient([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
            'created_at' => now()->subDays(8),
        ]);
        $newCase = $this->createCaseForClient([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
            'created_at' => now()->subDays(2),
        ]);
        $referredCase = $this->createCaseForClient([
            'user_id' => $caseManager->id,
            'status' => 'OPEN',
            'created_at' => now()->subDays(9),
        ]);
        Referral::factory()->pending()->create([
            'case_id' => $referredCase->id,
            'agcy_id' => $agency->id,
        ]);

        $this->actingAs($caseManager);

        $cases = app(CaseService::class)->getCases([
            'status' => 'OPEN',
            'age_min_days' => 7,
            'referral_state' => 'none',
        ]);

        $this->assertSame([$matchingCase->id], $cases->pluck('id')->all());
        $this->assertNotContains($newCase->id, $cases->pluck('id')->all());
    }

    #[Test]
    public function case_manager_payload_omits_full_datasets(): void
    {
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $data = app(DashboardService::class)->getCaseManagerData($caseManager);

        $this->assertArrayNotHasKey('allReferrals', $data);
        $this->assertArrayHasKey('priorityCases', $data);
        $this->assertArrayHasKey('referralStatusDistribution', $data);
        $this->assertArrayHasKey('referralNews', $data);
    }

    #[Test]
    public function admin_payload_includes_scoped_referral_status_distribution(): void
    {
        $agency = Agency::factory()->create();
        $case = $this->createCaseForClient();
        Referral::factory()->pending()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $data = app(DashboardService::class)->getAdminData();

        $this->assertNotEmpty($data['referralStatusDistribution']);
        $this->assertContains('PENDING', array_column($data['referralStatusDistribution'], 'status'));
        $this->assertArrayHasKey('percent', $data['referralStatusDistribution'][0]);
        $this->assertArrayHasKey('tone', $data['referralStatusDistribution'][0]);

        $this->assertSame('/cases?status=OPEN', collect($data['operationalQueues'])->firstWhere('key', 'openCases')['href']);
        $this->assertSame('/referrals?status=PENDING', collect($data['operationalQueues'])->firstWhere('key', 'pendingReferrals')['href']);
        $this->assertSame('/referrals?status=PROCESSING', collect($data['operationalQueues'])->firstWhere('key', 'processingReferrals')['href']);
        $this->assertSame('/referrals?status=FOR_COMPLIANCE', collect($data['operationalQueues'])->firstWhere('key', 'forComplianceReferrals')['href']);
        $this->assertSame('/overdue-referrals', collect($data['operationalQueues'])->firstWhere('key', 'overdueReferrals')['href']);
    }

    #[Test]
    public function admin_counts_cache_hit_remains_usable(): void
    {
        Cache::forget('dashboard:admin_counts_v2');

        $first = app(DashboardService::class)->getAdminData();
        $second = app(DashboardService::class)->getAdminData();

        $this->assertSame($first['stats'], $second['stats']);
        $this->assertSame($first['totalCases'], $second['totalCases']);
        $this->assertSame($first['totalReferrals'], $second['totalReferrals']);
        $this->assertArrayHasKey('operationalQueues', $second);
    }

    #[Test]
    public function case_manager_period_data_returns_four_kpis_and_a_zero_filled_daily_series(): void
    {
        Cache::flush();

        $payload = app(DashboardService::class)->getCaseManagerPeriodData(30);

        $this->assertSame(30, $payload['period']['days']);
        $this->assertSame('Last 30 days', $payload['period']['label']);

        foreach (['active', 'awaiting_agency', 'needs_action', 'closed'] as $key) {
            $this->assertArrayHasKey($key, $payload['kpis']);
        }

        // One bucket per day across the whole window, with no gaps: a chart
        // that skipped empty days would draw a misleading line.
        $this->assertCount(30, $payload['volume']['labels']);
        $this->assertCount(30, $payload['volume']['received']);
        $this->assertCount(30, $payload['volume']['closed']);
    }

    #[Test]
    public function case_manager_period_data_only_accepts_the_offered_windows(): void
    {
        Cache::flush();

        // An unrecognised window falls back to the 30-day default rather than
        // running an unbounded scan.
        $payload = app(DashboardService::class)->getCaseManagerPeriodData(365);

        $this->assertSame(30, $payload['period']['days']);
        $this->assertCount(30, $payload['volume']['labels']);
    }

    #[Test]
    public function case_manager_period_data_counts_a_filing_in_the_received_series(): void
    {
        Cache::flush();

        $this->createCaseForClient([
            'status' => 'OPEN',
            'created_at' => now()->subDay(),
        ]);

        $payload = app(DashboardService::class)->getCaseManagerPeriodData(30);

        $this->assertGreaterThanOrEqual(1, array_sum($payload['volume']['received']));
        $this->assertGreaterThanOrEqual(1, $payload['kpis']['active']);
    }

    #[Test]
    public function agency_period_data_is_scoped_to_the_agencys_own_referrals(): void
    {
        Cache::flush();

        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();

        $this->createReferralForAgency($agency, ['status' => 'PENDING', 'created_at' => now()->subDays(2)]);
        $this->createReferralForAgency($agency, ['status' => 'COMPLETED', 'created_at' => now()->subDays(3)]);
        // Another agency's referral must never be counted.
        $this->createReferralForAgency($otherAgency, ['status' => 'PENDING', 'created_at' => now()->subDays(2)]);

        $payload = app(DashboardService::class)->getAgencyPeriodData($agency->id, 30);

        $this->assertSame(2, $payload['kpis']['assigned']);
        $this->assertSame(1, $payload['kpis']['awaiting']);
        $this->assertSame(1, $payload['kpis']['resolved']);
        $this->assertCount(30, $payload['volume']['labels']);
        $this->assertSame(2, array_sum($payload['volume']['received']));
    }

    #[Test]
    public function agency_period_data_returns_an_empty_shape_without_an_agency(): void
    {
        Cache::flush();

        // An account with no agency must not fall back to every agency's rows.
        $payload = app(DashboardService::class)->getAgencyPeriodData(null, 30);

        $this->assertSame(0, $payload['kpis']['assigned']);
        $this->assertSame([], $payload['volume']['labels']);
        $this->assertSame([], $payload['status_mix']);
        $this->assertSame([], $payload['category_mix']);
    }

    private function createReferralForAgency(Agency $agency, array $attributes = []): Referral
    {
        $case = $this->createCaseForClient();

        return Referral::factory()->create(array_merge([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $attributes));
    }

    private function createCaseForClient(array $attributes = []): CaseFile
    {
        $client = Client::factory()->create();

        return CaseFile::factory()->create(array_merge([
            'client_id' => $client->id,
        ], $attributes));
    }
}
