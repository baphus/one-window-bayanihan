<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAiInsightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        // Mock ReportsService to avoid PostgreSQL dependency (SQLite-incompatible queries)
        $this->mock(ReportsService::class, function ($mock) {
            $mock->shouldReceive('getAll')
                ->andReturn([
                    'kpis' => [],
                    'overview' => [],
                    'caseStatusDistribution' => ['labels' => [], 'data' => []],
                    'categoryDistribution' => [],
                    'agencyScorecard' => [],
                    'referralStatusDistribution' => ['labels' => [], 'data' => []],
                    'geographicDistribution' => ['labels' => [], 'data' => []],
                    'employmentDistribution' => ['labels' => [], 'data' => []],
                    'cycleTimeDistribution' => ['labels' => [], 'data' => []],
                ]);
        });
    }

    public function test_ai_insight_rate_limiting(): void
    {
        $user = User::factory()->make(['role' => 'ADMIN']);

        for ($i = 0; $i < 11; $i++) {
            $response = $this->actingAs($user)->post(route('reports.ai-insight'), [
                'from' => '2026-01-01',
                'to' => '2026-06-01',
            ]);

            if ($i === 10) {
                $response->assertStatus(429);
            }
        }
    }

    public function test_ai_insight_requires_auth(): void
    {
        $response = $this->post(route('reports.ai-insight'), [
            'from' => '2026-01-01',
            'to' => '2026-06-01',
        ]);

        $response->assertStatus(302);
    }
}
