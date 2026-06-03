<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_draft_returns_draft_data(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('cases.edit-draft', $case));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Case/Create')
                ->has('existingDraft')
                ->has('existingClients')
                ->has('categories')
            );
    }

    public function test_edit_draft_403_for_other_user(): void
    {
        $userA = User::factory()->create(['role' => 'CASE_MANAGER']);
        $userB = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $userA->id,
        ]);

        $response = $this->actingAs($userB)
            ->get(route('cases.edit-draft', $case));

        $response->assertStatus(403);
    }

    public function test_edit_draft_404_for_non_draft(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'status' => 'OPEN',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('cases.edit-draft', $case));

        $response->assertStatus(404);
    }

    public function test_save_draft_updates_draft(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('cases.save-draft', $case), [
                'client_type' => 'NEXT_OF_KIN',
                'summary' => 'Updated draft summary',
            ]);

        $response->assertRedirect(route('cases.drafts'));
        $response->assertSessionHas('success', 'Draft updated successfully.');

        $this->assertDatabaseHas('cases', [
            'id' => $case->id,
            'client_type' => 'NEXT_OF_KIN',
            'summary' => 'Updated draft summary',
            'status' => 'DRAFT',
        ]);
    }

    public function test_save_draft_403_for_other_user(): void
    {
        $userA = User::factory()->create(['role' => 'CASE_MANAGER']);
        $userB = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $userA->id,
        ]);

        $response = $this->actingAs($userB)
            ->put(route('cases.save-draft', $case), [
                'client_type' => 'NEXT_OF_KIN',
            ]);

        $response->assertStatus(403);
    }
}
