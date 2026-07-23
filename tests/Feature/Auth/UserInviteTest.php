<?php

namespace Tests\Feature\Auth;

use App\Mail\UserInviteMail;
use App\Models\Agency;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserInviteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->agency = Agency::factory()->create();
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    public function test_admin_can_send_invite(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'newuser@example.com',
                'role' => 'CASE_MANAGER',
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Invitation sent to newuser@example.com');

        $this->assertDatabaseHas('user_invites', [
            'email' => 'newuser@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'created_by' => $this->admin->id,
            'consumed_at' => null,
            'cancelled_at' => null,
        ]);

        Mail::assertQueued(UserInviteMail::class);
    }

    public function test_admin_cannot_invite_existing_user(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'existing@example.com',
                'role' => 'CASE_MANAGER',
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('user_invites', [
            'email' => 'existing@example.com',
        ]);
    }

    public function test_admin_cannot_invite_pending_email(): void
    {
        UserInvite::create([
            'email' => 'pending@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'token' => 'existing-token',
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'pending@example.com',
                'role' => 'CASE_MANAGER',
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning');
    }

    public function test_admin_can_resend_invite(): void
    {
        $invite = UserInvite::create([
            'email' => 'resend@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'token' => 'original-token',
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $originalToken = $invite->token;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invites.resend', $invite->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $invite->refresh();
        $this->assertNotEquals($originalToken, $invite->token);
        $this->assertNull($invite->consumed_at);
        $this->assertNull($invite->cancelled_at);

        Mail::assertQueued(UserInviteMail::class, 1);
    }

    public function test_invite_management_routes_reject_non_uuid_ids(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users/invites/not-a-uuid/resend')
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete('/admin/users/invites/not-a-uuid')
            ->assertNotFound();
    }

    public function test_admin_cannot_resend_consumed_invite(): void
    {
        $invite = UserInvite::create([
            'email' => 'consumed@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'token' => 'consumed-token',
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
            'consumed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invites.resend', $invite->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_admin_can_cancel_invite(): void
    {
        $invite = UserInvite::create([
            'email' => 'cancel@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'token' => 'cancel-token',
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.invites.cancel', $invite->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $invite->refresh();
        $this->assertNotNull($invite->cancelled_at);
    }

    public function test_admin_cannot_cancel_consumed_invite(): void
    {
        $invite = UserInvite::create([
            'email' => 'already-consumed@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'token' => 'consumed-token-2',
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
            'consumed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.invites.cancel', $invite->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_user_registers_via_invite(): void
    {
        $invite = UserInvite::create([
            'email' => 'register-via@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'token' => 'registration-token-123',
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        // Show the invite page
        $showResponse = $this->get(route('register-via-invite', $invite->token));
        $showResponse->assertStatus(200);

        // Submit registration
        $storeResponse = $this->post(route('register-via-invite.store', $invite->token), [
            'name' => 'New Invited User',
            'password' => 'SecureP@ss1',
            'password_confirmation' => 'SecureP@ss1',
            'position' => 'Case Worker',
            'department' => 'Field Operations',
            'contact_number' => '09171234567',
        ]);

        $storeResponse->assertRedirect(route('login'));
        $storeResponse->assertSessionHas('success');

        // Assert user was created
        $this->assertDatabaseHas('users', [
            'email' => 'register-via@example.com',
            'name' => 'New Invited User',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'position' => 'Case Worker',
            'department' => 'Field Operations',
            'contact_number' => '09171234567',
            'is_active' => true,
        ]);

        $user = User::where('email', 'register-via@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);

        // Assert invite was consumed
        $invite->refresh();
        $this->assertNotNull($invite->consumed_at);
    }

    public function test_expired_invite_shows_error(): void
    {
        $invite = UserInvite::create([
            'email' => 'expired@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'token' => 'expired-token',
            'expires_at' => now()->subDay(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->get(route('register-via-invite', $invite->token));

        $response->assertStatus(410);
    }

    public function test_invalid_invite_token_shows_error(): void
    {
        $response = $this->get(route('register-via-invite', 'invalid-token-value'));

        $response->assertStatus(404);
    }

    public function test_guest_cannot_access_invite_admin_routes(): void
    {
        $response = $this->post(route('admin.users.invite'), [
            'email' => 'test@example.com',
            'role' => 'CASE_MANAGER',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_send_invite(): void
    {
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($caseManager)
            ->post(route('admin.users.invite'), [
                'email' => 'newuser@example.com',
                'role' => 'CASE_MANAGER',
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertForbidden();
    }
}
