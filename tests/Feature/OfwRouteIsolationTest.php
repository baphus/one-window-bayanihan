<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfwRouteIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_ofw_cannot_access_staff_dashboard(): void
    {
        $ofwUser = User::factory()->create(['role' => UserRole::OFW->value]);

        $response = $this->actingAsWithMfa($ofwUser)
            ->get('/dashboard');

        // DashboardController redirects OFW users to /my-cases
        $response->assertRedirect('/my-cases');
    }

    #[Test]
    public function test_ofw_cannot_access_cases_index(): void
    {
        $ofwUser = User::factory()->create(['role' => UserRole::OFW->value]);

        $response = $this->actingAsWithMfa($ofwUser)
            ->get('/cases');

        $response->assertStatus(403);
    }

    #[Test]
    public function test_staff_cannot_access_ofw_routes(): void
    {
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAsWithMfa($cm)
            ->get('/my-cases');

        $response->assertStatus(403);
    }
}
