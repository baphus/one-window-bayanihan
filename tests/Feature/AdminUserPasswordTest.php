<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\CheckMfaEnrolled;
use App\Http\Middleware\CheckUserActive;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_getting_invite_as_a_user_route_is_not_a_valid_user_lookup(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->withoutMiddleware([CheckUserActive::class, CheckMfaEnrolled::class])
            ->actingAs($admin)
            ->get('/admin/users/invite')
            ->assertNotFound();
    }

    public function test_admin_invite_requires_valid_email(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)->post('/admin/users/invite', [
            'email' => 'not-an-email',
            'role' => UserRole::CASE_MANAGER->value,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_admin_invite_requires_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)->post('/admin/users/invite', [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors('role');
    }

    public function test_admin_invite_creates_pending_invite(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)->post('/admin/users/invite', [
            'email' => 'invite@example.com',
            'role' => UserRole::CASE_MANAGER->value,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_invites', [
            'email' => 'invite@example.com',
            'role' => UserRole::CASE_MANAGER->value,
        ]);
    }

    public function test_invite_registration_rejects_weak_password(): void
    {
        $rawToken = Str::random(64);
        $invite = UserInvite::create([
            'email' => 'register@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_by' => User::factory()->create(['role' => UserRole::ADMIN->value])->id,
        ]);

        $response = $this->post("/invite/{$rawToken}", [
            'name' => 'Test User',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_invite_registration_accepts_strong_password(): void
    {
        $rawToken = Str::random(64);
        $invite = UserInvite::create([
            'email' => 'register2@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_by' => User::factory()->create(['role' => UserRole::ADMIN->value])->id,
        ]);

        $response = $this->post("/invite/{$rawToken}", [
            'name' => 'Test User',
            'password' => 'P@ssw0rd!',
            'password_confirmation' => 'P@ssw0rd!',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => 'register2@example.com',
        ]);
    }

    public function test_invite_registration_rejects_password_without_symbol(): void
    {
        $rawToken = Str::random(64);
        $invite = UserInvite::create([
            'email' => 'register3@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_by' => User::factory()->create(['role' => UserRole::ADMIN->value])->id,
        ]);

        $response = $this->post("/invite/{$rawToken}", [
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_admin_update_rejects_weak_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $targetUser = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$targetUser->id}", [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'role' => UserRole::CASE_MANAGER->value,
            'password' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
