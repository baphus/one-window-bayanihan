<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Mail\EmailChangedNotification;
use App\Mail\UserInviteMail;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    public function test_admin_can_create_user_and_audit_is_logged(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Case Manager',
                'email' => 'new-cm@example.com',
                'password' => 'Str0ng!Pass',
                'role' => UserRole::CASE_MANAGER->value,
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
        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($cm)
            ->post(route('admin.users.store'), [
                'name' => 'Nope',
                'email' => 'nope@example.com',
                'password' => 'Str0ng!Pass',
                'role' => UserRole::CASE_MANAGER->value,
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_invite_user_and_mail_is_queued(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'invited@example.com',
                'role' => UserRole::AGENCY->value,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_invites', [
            'email' => 'invited@example.com',
            'role' => UserRole::AGENCY->value,
            'created_by' => $this->admin->id,
        ]);

        Mail::assertQueued(UserInviteMail::class);
    }

    public function test_duplicate_pending_invite_returns_warning(): void
    {
        Mail::fake();

        UserInvite::create([
            'email' => 'dup@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'dup@example.com',
                'role' => UserRole::CASE_MANAGER->value,
            ]);

        $response->assertSessionHas('warning');
        Mail::assertNotQueued(UserInviteMail::class);
    }

    public function test_admin_can_resend_invite(): void
    {
        Mail::fake();

        $invite = UserInvite::create([
            'email' => 'resend@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'token_hash' => hash('sha256', $rawToken = Str::random(64)),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);
        $oldToken = $invite->token_hash;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invites.resend', $invite->id));

        $response->assertRedirect();
        $this->assertNotEquals($oldToken, $invite->fresh()->token_hash);
        Mail::assertQueued(UserInviteMail::class);
    }

    public function test_admin_can_cancel_invite(): void
    {
        $invite = UserInvite::create([
            'email' => 'cancel@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'token_hash' => hash('sha256', Str::random(64)),
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
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), [
                'name' => 'Updated Name',
                'email' => $target->email,
                'role' => UserRole::CASE_MANAGER->value,
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

        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $oldEmail = $target->email;
        $newEmail = 'changed-'.$oldEmail;

        $response = $this->actingAs($this->admin)
            ->withSession(['verified_new_email_admin_'.$target->id => $newEmail])
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
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $originalRememberToken = $target->remember_token;

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
        // Deleting session rows is not enough: a remember-me cookie
        // re-authenticates from remember_token, so it must be rotated too.
        $this->assertNotSame($originalRememberToken, $fresh->remember_token);
    }

    public function test_admin_destroy_force_deletes_inactive_user(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value, 'is_active' => false]);

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
            'role' => UserRole::CASE_MANAGER->value,
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
        $target = User::factory()->mfaEnabled()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->assertNotNull($target->fresh()->mfa_enabled_at);
        $originalRememberToken = $target->remember_token;

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
        $this->assertNotSame($originalRememberToken, $fresh->remember_token);
        $this->assertDatabaseHas('audit_logs', ['module' => 'mfa']);
    }

    public function test_admin_password_change_kills_sessions_and_rotates_remember_token(): void
    {
        Mail::fake();

        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $originalRememberToken = $target->remember_token;

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), [
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
                'password' => 'N3w-P@ss!word',
                'current_password' => 'P@ssw0rd!',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasNoErrors();

        $fresh = $target->fresh();
        $this->assertTrue(Hash::check('N3w-P@ss!word', $fresh->password));
        // Resetting a credential must end every existing login for that user.
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertNotSame($originalRememberToken, $fresh->remember_token);
    }

    public function test_admin_update_without_password_keeps_active_sessions(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $originalRememberToken = $target->remember_token;
        $sessionId = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), [
                'name' => 'Renamed Only',
                'email' => $target->email,
                'role' => $target->role,
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasNoErrors();

        // A profile-field edit is not a credential change: the user must not be
        // logged out of their other browsers.
        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
        $this->assertSame($originalRememberToken, $target->fresh()->remember_token);
    }

    public function test_admin_reset_mfa_rejects_wrong_password(): void
    {
        $target = User::factory()->mfaEnabled()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.reset-mfa', $target->id), [
                'password' => 'Wrong-Password-1!',
            ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertNotNull($target->fresh()->mfa_enabled_at);
    }

    public function test_invite_for_a_deactivated_account_email_points_at_reactivation(): void
    {
        Mail::fake();

        $target = User::factory()->create([
            'email' => 'deactivated@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'is_active' => false,
            'is_deleted' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'deactivated@example.com',
                'role' => UserRole::CASE_MANAGER->value,
            ]);

        // "The email has already been taken" is useless here: the address is
        // the admin's own deactivated colleague, who reactivates in one click
        // and cannot be re-onboarded by inviting.
        $response->assertSessionHasErrors([
            'email' => 'An account already exists for deactivated@example.com but it is deactivated. Reactivate that user instead of sending a new invitation, or permanently delete the account first to free the address.',
        ]);

        $this->assertDatabaseMissing('user_invites', ['email' => 'deactivated@example.com']);
        Mail::assertNotQueued(UserInviteMail::class);
    }

    public function test_invite_for_a_soft_deleted_account_email_points_at_reactivation(): void
    {
        Mail::fake();

        $target = User::factory()->create([
            'email' => 'trashed@example.com',
            'role' => UserRole::CASE_MANAGER->value,
        ]);
        $target->delete();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'trashed@example.com',
                'role' => UserRole::CASE_MANAGER->value,
            ]);

        // A trashed row still owns the address through users_email_unique, so
        // the invite could never be consumed; saying so beats a 500 later.
        $response->assertSessionHasErrors(['email']);

        $this->assertStringContainsString('deactivated', session('errors')->first('email'));
        $this->assertDatabaseMissing('user_invites', ['email' => 'trashed@example.com']);
        Mail::assertNotQueued(UserInviteMail::class);
    }

    public function test_invite_succeeds_once_a_deactivated_account_is_permanently_deleted(): void
    {
        Mail::fake();

        $target = User::factory()->create([
            'email' => 'purged@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'is_active' => false,
            'is_deleted' => true,
        ]);

        // The destroy route purges an already-deactivated account, which is the
        // documented way out of the message above and the only thing that
        // actually frees the address.
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'purged@example.com',
                'role' => UserRole::CASE_MANAGER->value,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_invites', ['email' => 'purged@example.com']);
        Mail::assertQueued(UserInviteMail::class);
    }

    public function test_invite_for_an_active_account_email_still_fails_as_a_plain_duplicate(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'active-colleague@example.com',
            'role' => UserRole::CASE_MANAGER->value,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'active-colleague@example.com',
                'role' => UserRole::CASE_MANAGER->value,
            ]);

        $response->assertSessionHasErrors([
            'email' => 'An active account already uses active-colleague@example.com. Update that user instead of sending a new invitation.',
        ]);

        $this->assertDatabaseMissing('user_invites', ['email' => 'active-colleague@example.com']);
        Mail::assertNotQueued(UserInviteMail::class);
    }
}
