<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseCategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    public function test_admin_can_list_categories(): void
    {
        CaseCategory::factory()->create(['name' => 'Legal', 'sort_order' => 1, 'is_active' => true]);
        CaseCategory::factory()->create(['name' => 'Financial', 'sort_order' => 2, 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('admin.case-categories.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/CaseCategory/Index')
            ->has('categories', 2)
        );
    }

    public function test_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.case-categories.store'), [
            'name' => 'Medical',
            'description' => 'Medical-related cases',
            'color' => '#ff0000',
            'sort_order' => 3,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('case_categories', [
            'name' => 'Medical',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_category(): void
    {
        $category = CaseCategory::factory()->create(['name' => 'Old Name', 'color' => '#000000']);

        $response = $this->actingAs($this->admin)->patch(route('admin.case-categories.update', $category->id), [
            'name' => 'New Name',
            'color' => '#ffffff',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('case_categories', [
            'id' => $category->id,
            'name' => 'New Name',
            'color' => '#ffffff',
        ]);
    }

    public function test_admin_can_deactivate_category(): void
    {
        $category = CaseCategory::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)->delete(route('admin.case-categories.destroy', $category->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('case_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_non_admin_cannot_create_category(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->post(route('admin.case-categories.store'), [
            'name' => 'Hacked',
        ]);

        $response->assertForbidden();
    }

    public function test_validation_requires_name(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.case-categories.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
