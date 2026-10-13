<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
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
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($ofwUser)
            ->get('/dashboard');

        // DashboardController redirects OFW users to /my-cases
        $response->assertRedirect('/my-cases');
    }

    #[Test]
    public function test_ofw_cannot_access_cases_index(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($ofwUser)
            ->get('/cases');

        $response->assertStatus(403);
    }

    #[Test]
    public function test_staff_cannot_access_ofw_routes(): void
    {
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($cm)
            ->get('/my-cases');

        $response->assertStatus(403);
    }
}
