<?php

namespace Tests\Feature\Admin;

use App\Mail\EmailChangedNotification;
use App\Mail\UserInviteMail;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    public function test_admin_can_create_user_and_audit_is_logged(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Case Manager',
                'email' => 'new-cm@example.com',
                'password' => 'Str0ng!Pass',
                'role' => 'CASE_MANAGER',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user = User::where('email', 'new-cm@example.com')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CREATE',
            'module' => 'user',
            'entity_id' => $user->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_user_store_requires_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'role']);
        $this->assertDatabaseMissing('users', ['name' => 'Should Not Exist']);
    }

    public function test_case_manager_cannot_create_user(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($cm)
            ->post(route('admin.users.store'), [
                'name' => 'Nope',
                'email' => 'nope@example.com',
                'password' => 'Str0ng!Pass',
                'role' => 'CASE_MANAGER',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_invite_user_and_mail_is_queued(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'invited@example.com',
                'role' => 'AGENCY',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_invites', [
            'email' => 'invited@example.com',
            'role' => 'AGENCY',
            'created_by' => $this->admin->id,
        ]);

        Mail::assertQueued(UserInviteMail::class);
    }

    public function test_duplicate_pending_invite_returns_warning(): void
    {
        Mail::fake();

        UserInvite::create([
            'email' => 'dup@example.com',
            'role' => 'CASE_MANAGER',
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'dup@example.com',
                'role' => 'CASE_MANAGER',
            ]);

        $response->assertSessionHas('warning');
        Mail::assertNotQueued(UserInviteMail::class);
    }

    public function test_admin_can_resend_invite(): void
    {
        Mail::fake();

        $invite = UserInvite::create([
            'email' => 'resend@example.com',
            'role' => 'CASE_MANAGER',
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);
        $oldToken = $invite->token;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invites.resend', $invite->id));

        $response->assertRedirect();
        $this->assertNotEquals($oldToken, $invite->fresh()->token);
        Mail::assertQueued(UserInviteMail::class);
    }

    public function test_admin_can_cancel_invite(): void
    {
        $invite = UserInvite::create([
            'email' => 'cancel@example.com',
            'role' => 'CASE_MANAGER',
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.invites.cancel', $invite->id));

        $response->assertRedirect();
        $this->assertNotNull($invite->fresh()->cancelled_at);
    }

    public function test_admin_can_update_user(): void
    {
        $target = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), [
                'name' => 'Updated Name',
                'email' => $target->email,
                'role' => 'CASE_MANAGER',
                'position' => 'Focal Person',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertEquals('Updated Name', $target->fresh()->name);
        $this->assertEquals('Focal Person', $target->fresh()->position);
    }

    public function test_admin_email_change_logs_audit_and_mails_old_address(): void
    {
        Mail::fake();

        $target = User::factory()->create(['role' => 'CASE_MANAGER']);
        $oldEmail = $target->email;
        $newEmail = 'changed-'.$oldEmail;

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), [
                'name' => $target->name,
                'email' => $newEmail,
                'role' => $target->role,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $this->assertEquals($newEmail, $target->fresh()->email);
        $this->assertNotNull($target->fresh()->email_verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE',
            'module' => 'user',
            'entity_id' => $target->id,
            'user_id' => $this->admin->id,
        ]);

        Mail::assertQueued(EmailChangedNotification::class);
    }

    public function test_admin_destroy_deactivates_active_user_and_kills_sessions(): void
    {
        $target = User::factory()->create(['role' => 'CASE_MANAGER']);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target->id));

        $response->assertRedirect(route('admin.users.index'));

        $fresh = $target->fresh();
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertTrue((bool) $fresh->is_deleted);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    }

    public function test_admin_destroy_force_deletes_inactive_user(): void
    {
        $target = User::factory()->create(['role' => 'CASE_MANAGER', 'is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target->id));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_admin_can_reactivate_user_and_audit_is_logged(): void
    {
        $target = User::factory()->create([
            'role' => 'CASE_MANAGER',
            'is_active' => false,
            'is_deleted' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.reactivate', $target->id));

        $response->assertRedirect(route('admin.users.index'));

        $fresh = $target->fresh();
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertFalse((bool) $fresh->is_deleted);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE',
            'module' => 'user',
            'entity_id' => $target->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_reset_mfa_and_kill_sessions_with_audit(): void
    {
        $target = User::factory()->mfaEnabled()->create(['role' => 'CASE_MANAGER']);
        $this->assertNotNull($target->fresh()->mfa_enabled_at);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.reset-mfa', $target->id), [
                'password' => 'P@ssw0rd!',
            ]);

        $response->assertRedirect();

        $fresh = $target->fresh();
        $this->assertNull($fresh->mfa_secret);
        $this->assertNull($fresh->mfa_enabled_at);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'mfa']);
    }

    public function test_admin_reset_mfa_rejects_wrong_password(): void
    {
        $target = User::factory()->mfaEnabled()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.reset-mfa', $target->id), [
                'password' => 'Wrong-Password-1!',
            ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertNotNull($target->fresh()->mfa_enabled_at);
    }
}
