<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
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

    public function test_draft_index_is_owner_scoped_and_exposes_publishable_id(): void
    {
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $other = User::factory()->create(['role' => 'CASE_MANAGER']);
        $owned = CaseFile::factory()->create(['status' => 'DRAFT', 'user_id' => $owner->id]);
        CaseFile::factory()->create(['status' => 'DRAFT', 'user_id' => $other->id]);

        $this->actingAs($owner)->get(route('cases.drafts'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Draft/Index')
                ->where('drafts.data.0.id', $owned->id));
    }

    public function test_publish_redirect_contains_published_case_id_for_owner(): void
    {
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $category = CaseCategory::factory()->create();
        $client = Client::factory()->create([
            'date_of_birth' => '1990-01-01', 'sex' => 'MALE',
            'contact_number' => '09171234567', 'email' => 'owner@example.test',
        ]);
        $client->addresses()->create([
            'region' => 'Region VII', 'province' => 'Cebu',
            'city_municipality' => 'Cebu City', 'barangay' => 'Lahug',
        ]);
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT', 'user_id' => $owner->id, 'client_id' => $client->id,
            'client_type' => 'OFW', 'category_id' => $category->id,
        ]);
        $case->categories()->attach($category->id);

        $response = $this->actingAs($owner)->post(route('cases.publish', $case));

        $response->assertRedirect(route('cases.show', $case));
    }

    public function test_cross_owner_and_admin_non_owner_cannot_publish_draft(): void
    {
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $case = CaseFile::factory()->create(['status' => 'DRAFT', 'user_id' => $owner->id]);

        $this->actingAs($manager)->post(route('cases.publish', $case))->assertForbidden();
        $this->actingAs($admin)->post(route('cases.publish', $case))->assertForbidden();
    }

    public function test_admin_owner_can_publish_own_draft(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $category = CaseCategory::factory()->create();
        $client = Client::factory()->create([
            'date_of_birth' => '1990-01-01', 'sex' => 'MALE',
            'contact_number' => '09171234567', 'email' => 'admin-owner@example.test',
        ]);
        $client->addresses()->create([
            'region' => 'Region VII', 'province' => 'Cebu',
            'city_municipality' => 'Cebu City', 'barangay' => 'Lahug',
        ]);
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT', 'user_id' => $admin->id, 'client_id' => $client->id,
            'client_type' => 'OFW', 'category_id' => $category->id,
        ]);
        $case->categories()->attach($category->id);

        $this->actingAs($admin)->post(route('cases.publish', $case))
            ->assertRedirect(route('cases.show', $case));
    }
}
