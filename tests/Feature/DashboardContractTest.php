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
}
