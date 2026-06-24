<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAiInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_insight_rate_limiting(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);

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
