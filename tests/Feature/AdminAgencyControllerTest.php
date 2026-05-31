<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAgencyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'ADMIN']);
    }

    public function test_admin_can_delete_agency(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $agency = Agency::factory()->create(['is_active' => true, 'is_deleted' => false]);

        $response = $this->actingAs($admin)->delete(route('admin.agencies.destroy', $agency->id));

        $response->assertRedirect(route('admin.agencies.index'));

        $this->assertDatabaseHas('agencies', [
            'id' => $agency->id,
            'is_deleted' => true,
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_delete_nonexistent_agency(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($admin)->delete(route('admin.agencies.destroy', $fakeId));

        $response->assertNotFound();
    }
}
