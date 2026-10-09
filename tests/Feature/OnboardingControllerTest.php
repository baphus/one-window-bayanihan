<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_checklist_mark_without_auth_returns_401(): void
    {
        $response = $this->postJson(route('onboarding.checklist.mark'), ['item' => 'create-first-case']);

        $response->assertStatus(401);
    }

    public function test_checklist_dismiss_without_auth_returns_401(): void
    {
        $response = $this->postJson(route('onboarding.checklist.dismiss'));

        $response->assertStatus(401);
    }

    public function test_checklist_mark_rejects_malformed_item_ids(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('onboarding.checklist.mark'), ['item' => 'UPPER CASE!!'])
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
}
