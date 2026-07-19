<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportsFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_rejects_malformed_date_filters_instead_of_querying_with_them(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'from' => '2026-99-99',
            'to' => '2026-12-31',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_reports_page_rejects_malformed_to_date(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->actingAs($user)->get(route('reports.index', [
            'from' => '2026-01-01', 'to' => '2026-99-99',
        ]))->assertRedirect()->assertSessionHas('error');
    }

    public function test_reports_page_rejects_reversed_and_excessive_ranges(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        foreach ([
            ['from' => '2026-02-01', 'to' => '2026-01-01'],
            ['from' => '2020-01-01', 'to' => '2023-01-02'],
        ] as $filters) {
            $this->actingAs($user)->get(route('reports.index', $filters))
                ->assertRedirect()->assertSessionHas('error');
        }
    }

    public function test_reports_page_rejects_unsupported_date_scope_and_malformed_agency_id(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($user)->get(route('reports.index', [
            'date_scope' => 'created_by_attacker',
        ]))->assertRedirect()->assertSessionHas('error');

        $this->actingAs($user)->get(route('reports.index', [
            'agency_id' => 'not-a-uuid',
        ]))->assertRedirect()->assertSessionHas('error');
    }

    public function test_reports_deferred_partial_props_are_available_over_http(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $inertiaRequest = Request::create(route('reports.index'), 'GET');
        $version = app(HandleInertiaRequests::class)->version($inertiaRequest);

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version ?? '',
                'X-Inertia-Partial-Component' => 'Reports/Index',
                'X-Inertia-Partial-Data' => 'employmentPositionBreakdown,caseStatusDistribution',
            ])->get(route('reports.index'));

        $response->assertOk();
        $response->assertHeader('X-Inertia', 'true');
    }
}
