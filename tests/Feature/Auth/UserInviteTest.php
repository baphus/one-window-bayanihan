<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Mail\UserInviteMail;
use App\Models\Agency;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\CloudinaryAvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    public function test_admin_can_send_invite(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'newuser@example.com',
                'role' => UserRole::CASE_MANAGER->value,
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Invitation sent to newuser@example.com');

        $this->assertDatabaseHas('user_invites', [
            'email' => 'newuser@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'created_by' => $this->admin->id,
            'consumed_at' => null,
            'cancelled_at' => null,
        ]);

        Mail::assertQueued(UserInviteMail::class);
    }

    public function test_invite_token_is_stored_hashed_only(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'hashed@example.com',
                'role' => UserRole::CASE_MANAGER->value,
                'agcy_id' => $this->agency->id,
            ]);

        $invite = UserInvite::where('email', 'hashed@example.com')->firstOrFail();
        $this->assertNull($invite->token);
        $this->assertNotNull($invite->token_hash);
        $this->assertSame(64, strlen($invite->token_hash));

        Mail::assertQueued(UserInviteMail::class, function (UserInviteMail $mail) use ($invite) {
            return hash('sha256', $mail->token) === $invite->token_hash;
        });
    }

    public function test_admin_cannot_invite_existing_user(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'existing@example.com',
                'role' => UserRole::CASE_MANAGER->value,
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
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', 'existing-token'),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invite'), [
                'email' => 'pending@example.com',
                'role' => UserRole::CASE_MANAGER->value,
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning');
    }

    public function test_admin_can_resend_invite(): void
    {
        $invite = UserInvite::create([
            'email' => 'resend@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', 'original-token'),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $originalHash = $invite->token_hash;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.invites.resend', $invite->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $invite->refresh();
        $this->assertNotEquals($originalHash, $invite->token_hash);
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
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', 'consumed-token'),
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
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', 'cancel-token'),
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
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', 'consumed-token-2'),
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
        $rawToken = 'registration-token-123';
        $invite = UserInvite::create([
            'email' => 'register-via@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        // Show the invite page
        $showResponse = $this->get(route('register-via-invite', $rawToken));
        $showResponse->assertStatus(200);

        // Submit registration
        $storeResponse = $this->post(route('register-via-invite.store', $rawToken), [
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
            'role' => UserRole::CASE_MANAGER->value,
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

    public function test_user_registers_via_invite_with_an_optional_profile_picture(): void
    {
        $rawToken = 'avatar-token-123';
        UserInvite::create([
            'email' => 'avatar-register@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->post(route('register-via-invite.store', $rawToken), [
            'name' => 'Avatar User',
            'password' => 'SecureP@ss1',
            'password_confirmation' => 'SecureP@ss1',
            'profile_picture' => UploadedFile::fake()->image('me.jpg', 160, 160),
        ]);

        $response->assertRedirect(route('login'));

        $user = User::where('email', 'avatar-register@example.com')->firstOrFail();
        $this->assertNotNull($user->getRawOriginal('avatar_url'), 'The optional profile picture must be stored.');
        $this->assertStringContainsString('res.cloudinary.com', (string) $user->getRawOriginal('avatar_url'));
    }

    public function test_user_registers_via_invite_without_a_profile_picture(): void
    {
        $rawToken = 'no-avatar-token';
        $invite = UserInvite::create([
            'email' => 'no-avatar@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        // The photo is optional: omitting it must not block registration.
        $response = $this->post(route('register-via-invite.store', $rawToken), [
            'name' => 'No Avatar User',
            'password' => 'SecureP@ss1',
            'password_confirmation' => 'SecureP@ss1',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'no-avatar@example.com')->firstOrFail();
        $this->assertNull($user->getRawOriginal('avatar_url'));

        $invite->refresh();
        $this->assertNotNull($invite->consumed_at, 'The invite must still be consumed.');
    }

    public function test_invite_registration_rejects_a_non_image_profile_picture_without_creating_the_user(): void
    {
        $rawToken = 'bad-avatar-token';
        UserInvite::create([
            'email' => 'bad-avatar@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->post(route('register-via-invite.store', $rawToken), [
            'name' => 'Bad Avatar User',
            'password' => 'SecureP@ss1',
            'password_confirmation' => 'SecureP@ss1',
            'profile_picture' => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image'),
        ]);

        $response->assertSessionHasErrors('profile_picture');
        $this->assertDatabaseMissing('users', ['email' => 'bad-avatar@example.com']);
    }

    public function test_invite_registration_still_succeeds_when_cloudinary_upload_fails(): void
    {
        $this->app->bind(CloudinaryAvatarService::class, fn () => new class extends CloudinaryAvatarService
        {
            public function __construct() {}

            public function uploadImage(UploadedFile $file, string $folder, string $publicId): string
            {
                throw new \RuntimeException('The image could not be uploaded. Please try again later.');
            }

            public function deleteByUrl(?string $url): void {}
        });

        $rawToken = 'cloudinary-down-token';
        UserInvite::create([
            'email' => 'cloudinary-down@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->post(route('register-via-invite.store', $rawToken), [
            'name' => 'Resilient User',
            'password' => 'SecureP@ss1',
            'password_confirmation' => 'SecureP@ss1',
            'profile_picture' => UploadedFile::fake()->image('me.jpg', 160, 160),
        ]);

        // A Cloudinary outage must never block account creation.
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'cloudinary-down@example.com')->firstOrFail();
        $this->assertNull($user->getRawOriginal('avatar_url'), 'The account is created without a photo.');
    }

    public function test_expired_invite_shows_error(): void
    {
        $rawToken = 'expired-token';
        $invite = UserInvite::create([
            'email' => 'expired@example.com',
            'role' => UserRole::CASE_MANAGER->value,
            'agcy_id' => $this->agency->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->subDay(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->get(route('register-via-invite', $rawToken));

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
            'role' => UserRole::CASE_MANAGER->value,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_send_invite(): void
    {
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($caseManager)
            ->post(route('admin.users.invite'), [
                'email' => 'newuser@example.com',
                'role' => UserRole::CASE_MANAGER->value,
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertForbidden();
    }
}
