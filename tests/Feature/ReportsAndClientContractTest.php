<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportsAndClientContractTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVED_REPORT_PROPS = [
        'referenceData', 'overdueReferrals', 'agencyWorkload', 'referralAging', 'reopenedStats', 'caseEventActorDistribution',
    ];

    public function test_reports_index_hides_dead_props_for_every_role(): void
    {
        foreach ([
            User::factory()->create(['role' => UserRole::CASE_MANAGER->value]),
            User::factory()->create(['role' => UserRole::ADMIN->value]),
        ] as $user) {
            $page = $this->inertiaPage($user);
            foreach (self::REMOVED_REPORT_PROPS as $key) {
                $this->assertArrayNotHasKey($key, $page['props']);
                $this->assertNotContains($key, $this->deferredPropNames($page));
            }
            $this->assertArrayHasKey('kpis', $page['props']);
            $this->assertArrayHasKey('referralStatusDistribution', $page['props']);
            $this->assertArrayHasKey('agencyScorecard', $page['props']);
            $this->assertContains('closedCasesOverTime', $this->deferredPropNames($page));
            $this->assertContains('agencyFirstResponse', $this->deferredPropNames($page));
        }
    }

    public function test_reports_page_resolves_remaining_props(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $version = app(HandleInertiaRequests::class)->version(Request::create(route('reports.index'), 'GET'));
        $response = $this->actingAsWithMfa($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
            'X-Inertia-Partial-Component' => 'Reports/Index',
            'X-Inertia-Partial-Data' => 'referralStatusDistribution,agencyScorecard',
        ])->get(route('reports.index'));
        $response->assertOk();
        $this->assertArrayHasKey('referralStatusDistribution', $response->json('props'));
        $this->assertArrayHasKey('agencyScorecard', $response->json('props'));
    }

    public function test_report_category_and_case_counts_are_case_owned_and_referral_classified(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->create(['status' => 'OPEN', 'ofw_user_id' => $owner->id, 'client_type' => 'OFW']);
        $category = CaseCategory::factory()->create();
        $case->categories()->attach($category->id);
        Referral::factory()->count(2)->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $category->id]);

        $this->actingAsWithMfa($manager)->get(route('reports.index'))->assertOk();
        $rows = app(ReportsService::class)->categoryDistribution($manager->id, UserRole::CASE_MANAGER->value);
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['count']);
    }

    private function inertiaPage(User $user): array
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create(route('reports.index'), 'GET'));
        $response = $this->actingAsWithMfa($user)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? ''])->get(route('reports.index'));
        $response->assertOk()->assertHeader('X-Inertia', 'true');

        return $response->json();
    }

    private function deferredPropNames(array $page): array
    {
        $names = [];
        foreach ((array) ($page['deferredProps'] ?? []) as $groupProps) {
            if (is_array($groupProps)) {
                array_push($names, ...$groupProps);
            } else {
                $names[] = (string) $groupProps;
            }
        }

        return $names;
    }
}
