<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\Service;
use App\Models\SurveyInvitation;
use App\Models\User;
use App\Services\CaseService;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function agency_dashboard_insights_are_scoped_and_actionable(): void
    {
        $agency = Agency::factory()->create(['name' => 'OWWA Cebu']);
        $otherAgency = Agency::factory()->create(['name' => 'Other Agency']);
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
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
        Referral::factory()->create([
            'case_id' => $agencyCase->id,
            'agcy_id' => $agency->id,
            'required_services' => $service->name,
            'status' => 'FOR_COMPLIANCE',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        Referral::factory()->completed()->create([
            'case_id' => $agencyCase->id,
            'agcy_id' => $agency->id,
            'required_services' => $service->name,
        ]);
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
        $this->assertSame(1, $data['forComplianceReferrals']);
        $this->assertSame(1, collect($data['workQueue'])->firstWhere('key', 'overdueReferrals')['count']);
        $this->assertNotEmpty($data['referralAgingBands']);
        $this->assertContains('11+ days', array_column($data['referralAgingBands'], 'label'));
        $this->assertCount(2, $data['priorityReferrals']);
        $this->assertContains($oldPending->id, array_column($data['priorityReferrals'], 'id'));
        $this->assertNull($data['priorityReferrals'][0]['agencyName']);
        $this->assertSame($service->name, $data['serviceDemand'][0]['serviceName']);
        $this->assertTrue($data['feedbackPulse']['hasData']);
        $this->assertLessThanOrEqual(8, count($data['priorityReferrals']));
        $this->assertSame('/referrals?status=PENDING', collect($data['workQueue'])->firstWhere('key', 'pendingReferrals')['href']);
        $this->assertSame('/referrals?status=REJECTED', collect($data['workQueue'])->firstWhere('key', 'returnedReferrals')['href']);
        $this->assertSame('/referrals?age_max_days=2', collect($data['workQueue'])->firstWhere('key', 'newReferrals')['href']);
        $this->assertSame('/referrals?age_min_days=5', collect($data['workQueue'])->firstWhere('key', 'overdueReferrals')['href']);
        $this->assertSame('/referrals?status=FOR_COMPLIANCE', collect($data['workQueue'])->firstWhere('key', 'forComplianceReferrals')['href']);
        $this->assertSame('/referrals?status=PROCESSING', collect($data['workQueue'])->firstWhere('key', 'processingReferrals')['href']);
    }

    #[Test]
    public function agency_dashboard_feedback_pulse_handles_sparse_data(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $data = app(DashboardService::class)->getAgencyData($agencyUser);

        $this->assertFalse($data['feedbackPulse']['hasData']);
        $this->assertSame(0, $data['feedbackPulse']['totalSent']);
        $this->assertSame(0, $data['feedbackPulse']['totalSubmitted']);
        $this->assertSame('/surveys', $data['feedbackPulse']['href']);
    }

    #[Test]
    public function agency_dashboard_feedback_pulse_counts_only_the_current_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);
        $agencyCase = $this->createCaseForClient();
        $otherCase = $this->createCaseForClient();
        $pendingCase = $this->createCaseForClient();
        $agencyReferral = Referral::factory()->create(['case_id' => $agencyCase->id, 'agcy_id' => $agency->id]);
        $otherReferral = Referral::factory()->create(['case_id' => $otherCase->id, 'agcy_id' => $otherAgency->id]);
        $pendingReferral = Referral::factory()->create(['case_id' => $pendingCase->id, 'agcy_id' => $agency->id]);

        SurveyInvitation::create([
            'agency_id' => $agency->id,
            'case_id' => $agencyCase->id,
            'referral_id' => $agencyReferral->id,
            'client_name' => 'Submitted Client',
            'client_email' => 'submitted@example.com',
            'service_name' => 'Test service',
            'token_hash' => hash('sha256', 'submitted-survey-token'),
            'expires_at' => now()->addDay(),
            'submitted_at' => now(),
        ]);
        SurveyInvitation::create([
            'agency_id' => $agency->id,
            'case_id' => $pendingCase->id,
            'referral_id' => $pendingReferral->id,
            'client_name' => 'Pending Client',
            'client_email' => 'pending@example.com',
            'service_name' => 'Test service',
            'token_hash' => hash('sha256', 'pending-survey-token'),
            'expires_at' => now()->addDay(),
        ]);
        SurveyInvitation::create([
            'agency_id' => $otherAgency->id,
            'case_id' => $otherCase->id,
            'referral_id' => $otherReferral->id,
            'client_name' => 'Other Client',
            'client_email' => 'other@example.com',
            'service_name' => 'Other service',
            'token_hash' => hash('sha256', 'other-survey-token'),
            'expires_at' => now()->addDay(),
            'submitted_at' => now(),
        ]);

        $pulse = app(DashboardService::class)->getAgencyData($agencyUser)['feedbackPulse'];

        $this->assertTrue($pulse['hasData']);
        $this->assertSame(2, $pulse['totalSent']);
        $this->assertSame(1, $pulse['totalSubmitted']);
        $this->assertSame(50.0, $pulse['responseRate']);
        $this->assertSame('/surveys', $pulse['href']);
    }

    #[Test]
    public function case_manager_dashboard_insights_surface_coordination_bottlenecks(): void
    {
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
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

        $this->assertSame(1, collect($data['workQueue'])->firstWhere('key', 'casesWithoutReferrals')['count']);
        $this->assertSame(1, collect($data['workQueue'])->firstWhere('key', 'rejectedReferrals')['count']);
        $this->assertSame('/cases?status=OPEN&age_min_days=7', collect($data['workQueue'])->firstWhere('key', 'agingOpenCases')['href']);
        $this->assertSame('/referrals?status=PENDING', collect($data['workQueue'])->firstWhere('key', 'pendingReferrals')['href']);
        $this->assertSame('/referrals?status=REJECTED', collect($data['workQueue'])->firstWhere('key', 'rejectedReferrals')['href']);
        $this->assertSame('/cases?status=OPEN&referral_state=none', collect($data['workQueue'])->firstWhere('key', 'casesWithoutReferrals')['href']);
        $this->assertNotEmpty($data['priorityCases']);
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
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
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
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $data = app(DashboardService::class)->getCaseManagerData($caseManager);

        $this->assertArrayNotHasKey('allReferrals', $data);
        $this->assertArrayHasKey('priorityCases', $data);
        $this->assertArrayHasKey('workQueue', $data);
        $this->assertArrayHasKey('referralStatusDistribution', $data);
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

    private function createCaseForClient(array $attributes = []): CaseFile
    {
        $client = Client::factory()->create();

        return CaseFile::factory()->create(array_merge([
            'client_id' => $client->id,
        ], $attributes));
    }
}
