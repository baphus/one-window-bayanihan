<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\Reports\CaseMetrics;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportsFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_rejects_malformed_date_filters_instead_of_querying_with_them(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAsWithMfa($user)->get(route('reports.index', [
            'from' => '2026-99-99',
            'to' => '2026-12-31',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_reports_page_rejects_malformed_to_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAsWithMfa($user)->get(route('reports.index', [
            'from' => '2026-01-01', 'to' => '2026-99-99',
        ]))->assertRedirect()->assertSessionHas('error');
    }

    public function test_reports_page_rejects_reversed_and_excessive_ranges(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        foreach ([
            ['from' => '2026-02-01', 'to' => '2026-01-01'],
            ['from' => '2020-01-01', 'to' => '2023-01-02'],
        ] as $filters) {
            $this->actingAsWithMfa($user)->get(route('reports.index', $filters))
                ->assertRedirect()->assertSessionHas('error');
        }
    }

    public function test_reports_page_rejects_unsupported_date_scope_and_malformed_agency_id(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAsWithMfa($user)->get(route('reports.index', [
            'date_scope' => 'created_by_attacker',
        ]))->assertRedirect()->assertSessionHas('error');

        $this->actingAsWithMfa($user)->get(route('reports.index', [
            'agency_id' => 'not-a-uuid',
        ]))->assertRedirect()->assertSessionHas('error');
    }

    public function test_deferred_metrics_only_compute_on_requested_reload_with_original_scope(): void
    {
        Cache::flush();
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $calls = 0;
        $cases = \Mockery::mock(CaseMetrics::class)->makePartial();
        $cases->shouldReceive('getCaseSourceDistribution')->once()
            ->with(null, UserRole::AGENCY->value, $agency->id, '2026-06-01', '2026-06-30', 'Cebu', 'Cebu City')
            ->andReturnUsing(function () use (&$calls) {
                $calls++;

                return ['labels' => ['Internal', 'Self-filed'], 'data' => [7, 2], 'colors' => []];
            });
        $this->app->instance(ReportsService::class, new ReportsService(cases: $cases));
        $url = route('reports.index', [
            'from' => '2026-06-01', 'to' => '2026-06-30',
            'province' => 'Cebu', 'city' => 'Cebu City',
            'agency_id' => Agency::factory()->create()->id,
        ]);

        $this->actingAsWithMfa($user)->get($url)->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Index')->missing('caseSourceDistribution'));
        $this->assertSame(0, $calls, 'Initial response must not compute deferred metrics.');

        $version = app(HandleInertiaRequests::class)->version(Request::create($url, 'GET'));
        $headers = [
            'X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? '',
            'X-Inertia-Partial-Component' => 'Reports/Index',
            'X-Inertia-Partial-Data' => 'caseSourceDistribution',
        ];
        $this->withHeaders($headers)->get($url)->assertOk()
            ->assertJsonPath('props.caseSourceDistribution.data', [7, 2])
            ->assertJsonMissingPath('props.kpis');
        $this->assertSame(1, $calls);
        $this->withHeaders($headers)->get($url)->assertOk()
            ->assertJsonPath('props.caseSourceDistribution.data', [7, 2]);
        $this->assertSame(1, $calls, 'Same scoped selection should reuse cache.');
    }

    public function test_reports_deferred_partial_props_are_available_over_http(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $inertiaRequest = Request::create(route('reports.index'), 'GET');
        $version = app(HandleInertiaRequests::class)->version($inertiaRequest);

        $response = $this->actingAsWithMfa($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version ?? '',
                'X-Inertia-Partial-Component' => 'Reports/Index',
                'X-Inertia-Partial-Data' => 'employmentOccupationBreakdown,caseStatusDistribution',
            ])->get(route('reports.index'));

        $response->assertOk();
        $response->assertHeader('X-Inertia', 'true');
    }

    public function test_admin_and_agency_reports_include_scoped_case_receipts(): void
    {
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->open()->create(['created_at' => '2026-06-15 04:00:00']);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        CaseFile::factory()->open()->create(['created_at' => '2026-06-16 04:00:00']);
        CaseFile::factory()->open()->create(['created_at' => '2026-05-15 04:00:00']);

        foreach ([UserRole::ADMIN, UserRole::AGENCY] as $role) {
            $user = User::factory()->create(['role' => $role->value, 'agcy_id' => $role === UserRole::AGENCY ? $agency->id : null]);

            $this->actingAsWithMfa($user)->get(route('reports.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Reports/Index')
                    ->where('casesOverTime.labels', ['2026-06'])
                    ->where('casesOverTime.datasets.0.data', [$role === UserRole::ADMIN ? 2 : 1]));
        }
    }

    public function test_average_finish_time_uses_filtered_referrals_for_every_staff_role(): void
    {
        $agency = Agency::factory()->create();
        foreach ([['2026-06-01 04:00:00', 2], ['2026-05-01 04:00:00', 20]] as [$createdAt, $days]) {
            $case = CaseFile::factory()->open()->create(['created_at' => $createdAt]);
            Referral::factory()->completed()->create([
                'case_id' => $case->id,
                'agcy_id' => $agency->id,
                'created_at' => $createdAt,
                'updated_at' => Carbon::parse($createdAt)->addDays($days),
            ]);
        }
        $version = app(HandleInertiaRequests::class)->version(Request::create(route('reports.index'), 'GET'));

        foreach ([UserRole::ADMIN, UserRole::CASE_MANAGER, UserRole::AGENCY] as $role) {
            $user = User::factory()->create(['role' => $role->value, 'agcy_id' => $role === UserRole::AGENCY ? $agency->id : null]);
            $this->actingAsWithMfa($user)->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version ?? '',
                'X-Inertia-Partial-Component' => 'Reports/Index',
                'X-Inertia-Partial-Data' => 'avgReferralCompletion',
            ])->get(route('reports.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
                ->assertOk()->assertJsonPath('props.avgReferralCompletion', 2);
        }
    }

    public function test_reports_page_exposes_the_new_aggregate_props(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $inertiaRequest = Request::create(route('reports.index'), 'GET');
        $version = app(HandleInertiaRequests::class)->version($inertiaRequest);

        $keys = implode(',', [
            'rejectionReasonDistribution',
            'caseSourceDistribution',
            'closedCasesOverTime',
            'agencyFirstResponse',
            'clientRequestTypeDistribution',
        ]);

        $response = $this->actingAsWithMfa($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version ?? '',
                'X-Inertia-Partial-Component' => 'Reports/Index',
                'X-Inertia-Partial-Data' => $keys,
            ])->get(route('reports.index'));

        $response->assertOk();
        $props = $response->json('props');
        foreach (explode(',', $keys) as $key) {
            $this->assertArrayHasKey($key, $props, "reports page must expose {$key}");
        }
    }
}
