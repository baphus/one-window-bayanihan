<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Dashboard\DashboardQueries;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Frontend/backend dashboard contract tests.
 *
 * Every dashboard section rendered by CaseManager.jsx / Agency.jsx must be
 * backed by a produced key, and every produced key must have a consumer.
 * This file pins both directions after the dead-key cleanup:
 *
 *  - dead producers removed from the payloads stay removed (YAGNI);
 *  - keys produced for visible widgets carry the shape/values the JSX reads;
 *  - count totals stay exact while the rendered preview lists are capped;
 *  - the overdue cutoff follows the referral_overdue_days setting, not a
 *    frozen class constant;
 *  - the dashboard route renders for CM and agency users.
 */
class DashboardContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The dashboard builders cache counts for 30-300s; the array cache
        // persists for the whole process, so each test starts clean.
        Cache::flush();
    }

    #[Test]
    public function case_manager_payload_omits_removed_dead_producer_keys(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $data = app(DashboardService::class)->getCaseManagerData($manager);

        // Dead producers removed from the CM payload (nothing renders them).
        foreach (['workQueue', 'allCases', 'readyToClose', 'forComplianceList'] as $dead) {
            $this->assertArrayNotHasKey($dead, $data);
        }
    }

    #[Test]
    public function case_manager_produced_widget_keys_carry_consumer_shape(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        CaseFile::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'user_id' => $manager->id,
            'status' => 'DRAFT',
        ]);

        $pendingReview = CaseFile::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'user_id' => $manager->id,
            'status' => CaseFile::STATUS_PENDING_REVIEW,
        ]);

        $milestoneCase = CaseFile::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'user_id' => $manager->id,
            'status' => 'OPEN',
        ]);
        CaseEvent::create([
            'case_id' => $milestoneCase->id,
            'type' => CaseEvent::TYPE_MILESTONE_ADDED,
            'title' => 'OEC checkpoint reached',
            'actor_type' => 'case_manager',
            'occurred_at' => now()->subHours(2),
        ]);
        // Non-news event types must never leak into the referral news feed.
        CaseEvent::create([
            'case_id' => $milestoneCase->id,
            'type' => CaseEvent::TYPE_CASE_OPENED,
            'title' => 'Case opened',
            'actor_type' => 'case_manager',
            'occurred_at' => now()->subHours(1),
        ]);

        $data = app(DashboardService::class)->getCaseManagerData($manager);

        $this->assertSame(1, $data['myDraftCount']);
        $this->assertSame(1, $data['pendingReviewCount']);
        $this->assertContains($pendingReview->id, array_column($data['intakeReview'], 'id'));
        $this->assertSame(0, $data['pendingReviewCount'] - count($data['intakeReview']));

        // referralNews: CaseEvent-sourced rows in the shape ReferralNewsBlock reads.
        $this->assertArrayHasKey('referralNews', $data);
        $this->assertIsArray($data['referralNews']);
        $this->assertCount(1, $data['referralNews']);
        $news = $data['referralNews'][0];
        $this->assertSame('milestone_added', $news['kind']);
        $this->assertSame('OEC checkpoint reached', $news['milestone']);
        $this->assertSame($milestoneCase->case_number, $news['caseNumber']);
        $this->assertArrayHasKey('clientName', $news);
        $this->assertArrayHasKey('href', $news);
        $this->assertArrayHasKey('at', $news);
    }

    #[Test]
    public function case_manager_dashboard_payload_shape(): void
    {
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
        $this->assertSame(0, $data['myDraftCount']);

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
    public function agency_payload_omits_keys_the_agency_ui_never_renders(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);
        Referral::factory()->pending()->create([
            'case_id' => CaseFile::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'OPEN'])->id,
            'agcy_id' => $agency->id,
        ]);

        $data = app(DashboardService::class)->getAgencyData($agencyUser);

        // Live keys the Agency.jsx consumes stay produced.
        foreach (['totalReferrals', 'pendingReferrals', 'processingReferrals', 'overdueReferrals', 'workQueue', 'referralStatusDistribution', 'serviceDemand'] as $live) {
            $this->assertArrayHasKey($live, $data);
        }

        // OCA-verified dead producers: Agency.jsx + children consume none.
        foreach (['completedReferrals', 'rejectedReferrals', 'forComplianceReferrals', 'feedbackPulse', 'referralAgingBands'] as $dead) {
            $this->assertArrayNotHasKey($dead, $data);
        }

        // The work queue renders exactly three cells.
        $this->assertSame(
            ['pendingReferrals', 'processingReferrals', 'overdueReferrals'],
            array_column($data['workQueue'], 'key'),
        );
    }

    #[Test]
    public function agency_dashboard_payload_shape(): void
    {
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

    #[Test]
    public function overdue_threshold_follows_the_referral_overdue_days_setting(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $fresh = Referral::factory()->pending()->create([
            'case_id' => CaseFile::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'OPEN'])->id,
            'agcy_id' => $agency->id,
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);
        $stale = Referral::factory()->pending()->create([
            'case_id' => CaseFile::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'OPEN'])->id,
            'agcy_id' => $agency->id,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        // Threshold of 2 days: the 1d referral is not overdue, the 3d one is.
        // Under the old hardcoded 5 (or default 7) neither would be overdue.
        SystemSetting::setValue('referral_overdue_days', 2, 'system', 'test');
        $this->assertSame(2, DashboardQueries::overdueDays());

        $data = app(DashboardService::class)->getAgencyData($agencyUser);

        $overdueCell = collect($data['workQueue'])->firstWhere('key', 'overdueReferrals');
        $this->assertSame(1, $overdueCell['count']);
        $this->assertSame('/referrals?age_min_days=2', $overdueCell['href']);
        $this->assertContains($stale->id, array_column($data['overdueReferrals'], 'id'));
        $this->assertNotContains($fresh->id, array_column($data['overdueReferrals'], 'id'));

        // Raise the threshold: both referrals leave the overdue queue, proving
        // the count follows the setting instead of any baked-in constant.
        SystemSetting::setValue('referral_overdue_days', 8, 'system', 'new value');
        Cache::flush();

        $data = app(DashboardService::class)->getAgencyData($agencyUser);

        $this->assertSame(
            0,
            collect($data['workQueue'])->firstWhere('key', 'overdueReferrals')['count'],
        );
        $this->assertSame('/referrals?age_min_days=8', collect($data['workQueue'])->firstWhere('key', 'overdueReferrals')['href']);
    }

    #[Test]
    public function overdue_threshold_falls_back_to_seven_when_no_setting_row_exists(): void
    {
        $this->assertSame(7, DashboardQueries::overdueDays());

        // A zero/negative stored value must never zero out the overdue queue.
        SystemSetting::setValue('referral_overdue_days', 0, 'system', 'bad value');
        $this->assertSame(7, DashboardQueries::overdueDays());
    }

    #[Test]
    public function dashboard_route_renders_for_case_manager_and_agency_users(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));

        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($agencyUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')
                ->has('role'));
    }

    private function makeCase(array $attributes = []): CaseFile
    {
        return CaseFile::factory()->create(array_merge([
            'client_id' => Client::factory()->create()->id,
        ], $attributes));
    }
}
