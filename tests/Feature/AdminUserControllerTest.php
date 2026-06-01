<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $target = User::factory()->create([
            'role' => 'AGENCY',
            'is_active' => true,
            'is_deleted' => false,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $target->id));

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_deleted' => true,
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_delete_nonexistent_user(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $fakeId));

        $response->assertNotFound();
    }
}
