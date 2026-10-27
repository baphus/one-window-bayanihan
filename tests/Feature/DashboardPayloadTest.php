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

        // Seven OPEN cases with staggered timestamps: the count totals stay
        // exact even though every rendered preview list is capped.
        $openCases = collect();
        foreach (range(6, 0) as $daysAgo) {
            $openCases->push($this->makeCase([
                'user_id' => $manager->id,
                'status' => 'OPEN',
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]));
        }

        $this->makeCase([
            'user_id' => $manager->id,
            'status' => 'CLOSED',
            'closed_at' => now(),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        // Review-queue seeds: two pending filings awaiting approval.
        $ownPending = $this->makeCase([
            'user_id' => $manager->id,
            'status' => CaseFile::STATUS_PENDING_REVIEW,
            'created_at' => now()->subDays(11),
            'updated_at' => now()->subDays(11),
        ]);
        $otherPending = $this->makeCase([
            'user_id' => null,
            'status' => CaseFile::STATUS_PENDING_REVIEW,
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(12),
        ]);

        // FOR_COMPLIANCE seed on an OPEN case.
        Referral::factory()->forCompliance()->create([
            'case_id' => $openCases->first()->id,
            'agcy_id' => $agency->id,
        ]);

        $data = app(DashboardService::class)->getCaseManagerData($manager);

        // Count totals stay exact even though the preview lists are capped.
        $this->assertSame(10, $data['totalCases']);
        $this->assertSame(7, $data['openCases']);
        $this->assertSame(1, $data['closedCases']);
        // Drafts are no longer a case lifecycle state: nothing can write
        // status='DRAFT', so the payload must not carry a draft counter.
        $this->assertArrayNotHasKey('myDraftCount', $data);

        // Intake review list present, correctly filtered, capped.
        $this->assertArrayHasKey('intakeReview', $data);
        $this->assertLessThanOrEqual(8, count($data['intakeReview']));
        $this->assertContains($ownPending->id, array_column($data['intakeReview'], 'id'));
        $this->assertContains($otherPending->id, array_column($data['intakeReview'], 'id'));
        $this->assertSame(2, $data['pendingReviewCount']);

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

        // Six PENDING referrals plus one fresh: the pending list caps at the
        // 5 oldest. The ages deliberately skip the overdue cutoff (7 days by
        // default): the SQL filter compares the raw fractional age while the
        // payload rounds it for display, so a referral sitting exactly on the
        // cutoff would flip in and out of the overdue list between runs.
        $stalePending = collect();
        foreach ([12, 11, 10, 9, 8, 6] as $daysAgo) {
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
        // Oldest active referral for the agency, so it is unambiguously
        // inside the capped overdue list.
        $compliance = Referral::factory()->forCompliance()->create([
            'case_id' => $this->makeCase(['user_id' => $manager->id, 'status' => 'OPEN'])->id,
            'agcy_id' => $agency->id,
            'created_at' => now()->subDays(13),
            'updated_at' => now()->subDays(13),
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

        // overdueReferrals: active referrals whose raw age reaches the
        // configured cutoff (7 days by default), oldest first, capped at 5.
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

        // Counts the agency UI never renders are not produced (YAGNI).
        $this->assertArrayNotHasKey('completedReferrals', $data);
        $this->assertArrayNotHasKey('rejectedReferrals', $data);
        $this->assertArrayNotHasKey('forComplianceReferrals', $data);
        $this->assertArrayNotHasKey('feedbackPulse', $data);
        $this->assertArrayNotHasKey('referralAgingBands', $data);
    }

    private function makeCase(array $attributes = []): CaseFile
    {
        return CaseFile::factory()->create(array_merge([
            'client_id' => Client::factory()->create()->id,
        ], $attributes));
    }
}
