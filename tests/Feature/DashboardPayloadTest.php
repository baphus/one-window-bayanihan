<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPayloadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function case_manager_dashboard_payload_shape(): void
    {
        Cache::flush();

        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();

        // Seven OPEN cases with staggered timestamps: the preview must cap
        // at the 5 newest while the count totals stay exact.
        $openCases = collect();
        foreach (range(6, 0) as $daysAgo) {
            $openCases->push($this->makeCase([
                'user_id' => $manager->id,
                'status' => 'OPEN',
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]));
        }
        $newestOpen = $openCases->last();

        $this->makeCase([
            'user_id' => $manager->id,
            'status' => 'CLOSED',
            'closed_at' => now(),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        // Intake-review seeds: one own DRAFT plus one self-filed intake.
        $ownDraft = $this->makeCase([
            'user_id' => $manager->id,
            'status' => 'DRAFT',
            'source' => 'internal',
        ]);
        $selfFiled = $this->makeCase([
            'user_id' => null,
            'status' => 'DRAFT',
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        // FOR_COMPLIANCE seed on an OPEN case.
        $complianceReferral = Referral::factory()->forCompliance()->create([
            'case_id' => $openCases->first()->id,
            'agcy_id' => $agency->id,
        ]);

        // Ready-to-close seed: OPEN case whose only referral is COMPLETED.
        $readyCase = $this->makeCase([
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'created_at' => now()->subDays(9),
            'updated_at' => now()->subDays(9),
        ]);
        Referral::factory()->completed()->create([
            'case_id' => $readyCase->id,
            'agcy_id' => $agency->id,
        ]);

        $data = app(DashboardService::class)->getCaseManagerData($manager);

        // 1.1 — 5-row cap with exact totals.
        $this->assertCount(5, $data['allCases']);
        $this->assertSame($newestOpen->id, $data['allCases'][0]['id']);
        $createdAts = array_column($data['allCases'], 'createdAt');
        $sorted = $createdAts;
        rsort($sorted);
        $this->assertSame($sorted, $createdAts, 'allCases must be newest first');
        $this->assertSame(9, $data['totalCases']);
        $this->assertSame(8, $data['openCases']);
        $this->assertSame(1, $data['closedCases']);

        // 1.2 — new lists present, correctly filtered, capped.
        foreach (['intakeReview', 'forComplianceList', 'readyToClose'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        $this->assertLessThanOrEqual(8, count($data['intakeReview']));
        $this->assertContains($ownDraft->id, array_column($data['intakeReview'], 'id'));
        $this->assertContains($selfFiled->id, array_column($data['intakeReview'], 'id'));

        $this->assertLessThanOrEqual(8, count($data['forComplianceList']));
        $this->assertNotEmpty($data['forComplianceList']);
        $this->assertContains($complianceReferral->id, array_column($data['forComplianceList'], 'id'));
        foreach ($data['forComplianceList'] as $row) {
            $this->assertSame('FOR_COMPLIANCE', $row['status']);
        }

        $this->assertLessThanOrEqual(8, count($data['readyToClose']));
        $this->assertContains($readyCase->id, array_column($data['readyToClose'], 'id'));

        // Backward-compatible work-queue additions.
        foreach (['intakeReview', 'forComplianceReferrals', 'readyToClose'] as $key) {
            $this->assertNotNull(collect($data['workQueue'])->firstWhere('key', $key), "workQueue must include {$key}");
        }
        $this->assertSame(1, collect($data['workQueue'])->firstWhere('key', 'readyToClose')['count']);

        // 1.3 — trend datasets are non-empty.
        $this->assertNotEmpty($data['casesOverTime']['labels']);
        $this->assertNotEmpty($data['casesOverTime']['datasets'][0]['data']);
        $this->assertGreaterThan(0, array_sum($data['casesOverTime']['datasets'][0]['data']));
        $this->assertNotEmpty($data['referralTrends']['labels']);
        $this->assertNotEmpty($data['referralTrends']['datasets'][0]['data']);
        $this->assertGreaterThan(0, array_sum($data['referralTrends']['datasets'][0]['data']));
    }

    #[Test]
    public function agency_dashboard_payload_shape(): void
    {
        Cache::flush();

        $agency = Agency::factory()->create(['name' => 'OWWA Cebu']);
        $otherAgency = Agency::factory()->create(['name' => 'Other Agency']);
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        // Six stale PENDING referrals plus one fresh: the pending list caps
        // at the 5 oldest.
        $stalePending = collect();
        foreach ([10, 9, 8, 7, 6, 5] as $daysAgo) {
            $stalePending->push(Referral::factory()->pending()->create([
                'case_id' => $this->makeCase(['user_id' => $manager->id, 'status' => 'OPEN'])->id,
                'agcy_id' => $agency->id,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]));
        }
        $oldestPending = $stalePending->first();
        Referral::factory()->pending()->create([
            'case_id' => $this->makeCase(['user_id' => $manager->id, 'status' => 'OPEN'])->id,
            'agcy_id' => $agency->id,
        ]);

        // Non-pending / non-overdue seeds must not leak into the lists.
        Referral::factory()->processing()->create([
            'case_id' => $this->makeCase(['user_id' => $manager->id, 'status' => 'OPEN'])->id,
            'agcy_id' => $agency->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        Referral::factory()->completed()->create([
            'case_id' => $this->makeCase(['user_id' => $manager->id, 'status' => 'OPEN'])->id,
            'agcy_id' => $agency->id,
        ]);
        $compliance = Referral::factory()->forCompliance()->create([
            'case_id' => $this->makeCase(['user_id' => $manager->id, 'status' => 'OPEN'])->id,
            'agcy_id' => $agency->id,
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        Referral::factory()->pending()->create([
            'case_id' => $this->makeCase(['user_id' => $manager->id, 'status' => 'OPEN'])->id,
            'agcy_id' => $otherAgency->id,
            'created_at' => now()->subDays(30),
        ]);

        $data = app(DashboardService::class)->getAgencyData($agencyUser);

        // Old key dropped, new keys present.
        $this->assertArrayNotHasKey('priorityReferrals', $data);
        foreach (['pendingReferrals', 'overdueReferrals'] as $key) {
            $this->assertArrayHasKey($key, $data);
            $this->assertIsArray($data[$key]);
        }

        // pendingReferrals: PENDING only, oldest first, capped at 5.
        $pending = $data['pendingReferrals'];
        $this->assertCount(5, $pending);
        foreach ($pending as $row) {
            $this->assertSame('PENDING', $row['status']);
        }
        $this->assertSame($oldestPending->id, $pending[0]['id']);
        $pendingAges = array_column($pending, 'age_days');
        $pendingDesc = $pendingAges;
        rsort($pendingDesc);
        $this->assertSame($pendingDesc, $pendingAges, 'pendingReferrals must be oldest first');

        // overdueReferrals: active + age >= 5d, oldest first, capped at 5.
        $overdue = $data['overdueReferrals'];
        $this->assertCount(5, $overdue);
        foreach ($overdue as $row) {
            $this->assertContains($row['status'], ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE']);
            $this->assertGreaterThanOrEqual(5, $row['age_days']);
        }
        $this->assertContains($oldestPending->id, array_column($overdue, 'id'));
        $this->assertContains($compliance->id, array_column($overdue, 'id'));
        $overdueAges = array_column($overdue, 'age_days');
        $overdueDesc = $overdueAges;
        rsort($overdueDesc);
        $this->assertSame($overdueDesc, $overdueAges, 'overdueReferrals must be oldest first');

        // Exact row shape, agency_name null as before for agency scope.
        $this->assertSame(
            ['id', 'case_id', 'case_number', 'tracking_number', 'client_name', 'service', 'agency_name', 'status', 'age_days', 'referred_at', 'href'],
            array_keys($pending[0])
        );
        $this->assertNull($pending[0]['agency_name']);
        $this->assertNotEmpty($pending[0]['referred_at']);

        // Count totals stay exact (processingReferrals is a list in the agency payload).
        $this->assertSame(10, $data['totalReferrals']);
        $this->assertCount(1, $data['processingReferrals']);
        $this->assertSame(1, $data['completedReferrals']);
    }

    private function makeCase(array $attributes = []): CaseFile
    {
        return CaseFile::factory()->create(array_merge([
            'client_id' => Client::factory()->create()->id,
        ], $attributes));
    }
}
