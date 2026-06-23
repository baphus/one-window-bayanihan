<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_returns_onboarding_required_true(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('onboarding.state'));

        $response->assertStatus(200);
        $response->assertJson([
            'required' => true,
        ]);
    }

    public function test_skip_updates_database(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('onboarding.skip'))
            ->assertStatus(302);

        // Verify the database was updated
        $user->refresh();
        $this->assertNotNull($user->onboarding_completed_at);

        // Confirm state now returns required=false
        $stateResponse = $this->actingAs($user)
            ->getJson(route('onboarding.state'));

        $stateResponse->assertJson([
            'required' => false,
        ]);
    }

    public function test_complete_updates_database(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('onboarding.complete'))
            ->assertStatus(302);

        $user->refresh();
        $this->assertNotNull($user->onboarding_completed_at);

        $stateResponse = $this->actingAs($user)
            ->getJson(route('onboarding.state'));

        $stateResponse->assertJson([
            'required' => false,
        ]);
    }

    public function test_replay_resets_onboarding(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => now(),
        ]);

        // First verify onboarding is complete
        $stateResponse = $this->actingAs($user)
            ->getJson(route('onboarding.state'));

        $stateResponse->assertJson([
            'required' => false,
        ]);

        // Replay (reset)
        $this->actingAs($user)
            ->postJson(route('onboarding.replay'))
            ->assertStatus(302);

        $user->refresh();
        $this->assertNull($user->onboarding_completed_at);

        // State should now return required=true
        $stateAfterReplay = $this->actingAs($user)
            ->getJson(route('onboarding.state'));

        $stateAfterReplay->assertJson([
            'required' => true,
        ]);
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $response = $this->getJson(route('onboarding.state'));

        $response->assertStatus(401);
    }

    public function test_skip_without_auth_returns_401(): void
    {
        $response = $this->postJson(route('onboarding.skip'));

        $response->assertStatus(401);
    }

    public function test_complete_without_auth_returns_401(): void
    {
        $response = $this->postJson(route('onboarding.complete'));

        $response->assertStatus(401);
    }
}
