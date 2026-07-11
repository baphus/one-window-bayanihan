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
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

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
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

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
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

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

    public function test_update_step_persists_step_key(): void
    {
        $user = User::factory()->create(['onboarding_step' => null]);

        $this->actingAs($user)
            ->postJson(route('onboarding.step'), ['step' => '2:1'])
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $user->refresh();
        $this->assertEquals('2:1', $user->onboarding_step);
    }

    public function test_guide_seen_marks_route_once(): void
    {
        $user = User::factory()->create(['seen_page_guides' => null]);

        $this->actingAs($user)
            ->postJson(route('onboarding.guide-seen'), ['route' => 'reports.index'])
            ->assertStatus(200);

        $this->actingAs($user)
            ->postJson(route('onboarding.guide-seen'), ['route' => 'reports.index'])
            ->assertStatus(200);

        $user->refresh();
        $this->assertEquals(['reports.index'], $user->seen_page_guides);
    }

    public function test_guide_seen_requires_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('onboarding.guide-seen'), [])
            ->assertStatus(422);
    }

    public function test_checklist_mark_and_dismiss(): void
    {
        $user = User::factory()->create(['checklist_progress' => null]);

        $this->actingAs($user)
            ->postJson(route('onboarding.checklist.mark'), ['item' => 'create-first-case'])
            ->assertStatus(200);

        $this->actingAs($user)
            ->postJson(route('onboarding.checklist.dismiss'))
            ->assertStatus(200);

        $user->refresh();
        $this->assertArrayHasKey('create-first-case', $user->checklist_progress['items']);
        $this->assertNotNull($user->checklist_progress['dismissed_at']);
    }

    public function test_state_includes_guide_and_checklist_fields(): void
    {
        $user = User::factory()->create([
            'seen_page_guides' => ['cases.index'],
            'checklist_progress' => ['items' => ['visit-reports' => '2026-07-11T00:00:00Z'], 'dismissed_at' => null],
        ]);

        $this->actingAs($user)
            ->getJson(route('onboarding.state'))
            ->assertStatus(200)
            ->assertJson([
                'seen_page_guides' => ['cases.index'],
                'checklist_progress' => [
                    'items' => ['visit-reports' => '2026-07-11T00:00:00Z'],
                    'dismissed_at' => null,
                ],
            ]);
    }
}
