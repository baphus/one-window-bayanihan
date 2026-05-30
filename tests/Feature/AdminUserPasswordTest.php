<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'ADMIN']);
    }

    public function test_admin_creation_rejects_weak_password(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'weak',
            'role' => 'CASE_MANAGER',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_admin_creation_accepts_strong_password(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'P@ssw0rd!',
            'role' => 'CASE_MANAGER',
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_admin_creation_rejects_password_without_symbol(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test3@example.com',
            'password' => 'Password1',
            'role' => 'CASE_MANAGER',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_admin_update_rejects_weak_password(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $targetUser = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($admin)->patch("/admin/users/{$targetUser->id}", [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'role' => 'CASE_MANAGER',
            'password' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
