<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use App\Services\CloudinaryAvatarService;
use Cloudinary\Api\Exception\AuthorizationRequired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Profile picture upload/removal for the two self-service profile pages:
 *  - POST/DELETE profile/avatar (staff)             → ProfileController
 *  - POST/DELETE my-cases/profile/avatar (OFW)      → OfwProfileController
 *
 * tests/TestCase binds a CloudinaryAvatarService double that returns a fake
 * secure_url, so the happy paths need no network. Failure paths rebind a
 * throwing double, mirroring CloudinaryFailureHandlingTest.
 */
class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_AVATAR = 'https://res.cloudinary.com/test-cloud/image/upload/v1750000000/avatars/user-old.jpg';

    #[Test]
    public function staff_can_upload_a_profile_picture(): void
    {
        $staff = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($staff)->post(route('profile.avatar.store'), [
            'profile_picture' => UploadedFile::fake()->image('me.jpg', 160, 160),
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success', 'Profile picture updated successfully.');

        $staff->refresh();
        $this->assertNotNull($staff->getRawOriginal('avatar_url'));
        $this->assertStringContainsString('res.cloudinary.com', (string) $staff->getRawOriginal('avatar_url'));
    }

    #[Test]
    public function staff_can_remove_their_profile_picture(): void
    {
        $staff = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'avatar_url' => self::OLD_AVATAR,
        ]);

        $response = $this->actingAs($staff)->delete(route('profile.avatar.destroy'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success', 'Profile picture removed.');

        $this->assertNull($staff->fresh()->getRawOriginal('avatar_url'));
    }

    #[Test]
    public function staff_upload_rejects_a_spoofed_non_image_file(): void
    {
        $staff = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($staff)->post(route('profile.avatar.store'), [
            // A .jpg name over plain-text content must be refused.
            'profile_picture' => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image'),
        ]);

        $response->assertSessionHasErrors('profile_picture');
        $this->assertNull($staff->fresh()->getRawOriginal('avatar_url'), 'A rejected file must not set an avatar.');
    }

    #[Test]
    public function staff_upload_without_a_file_reports_a_field_error(): void
    {
        $staff = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($staff)->post(route('profile.avatar.store'), []);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('profile_picture');
        $this->assertNull($staff->fresh()->getRawOriginal('avatar_url'));
    }

    #[Test]
    public function staff_upload_redirects_gracefully_when_cloudinary_fails(): void
    {
        $this->bindAvatarServiceRejectingUploads();

        $staff = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'avatar_url' => self::OLD_AVATAR,
        ]);

        $response = $this->actingAs($staff)->post(route('profile.avatar.store'), [
            'profile_picture' => UploadedFile::fake()->image('me.jpg', 160, 160),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('profile_picture');

        $this->assertSame(
            self::OLD_AVATAR,
            $staff->fresh()->getRawOriginal('avatar_url'),
            'A failed upload must not clear the existing avatar.'
        );
    }

    #[Test]
    public function ofw_can_upload_and_remove_a_profile_picture(): void
    {
        $ofw = $this->makeOfw();

        $upload = $this->actingAs($ofw)->post(route('ofw.profile.avatar.store'), [
            'profile_picture' => UploadedFile::fake()->image('me.jpg', 160, 160),
        ]);

        $upload->assertRedirect(route('ofw.profile.edit'));
        $upload->assertSessionHas('success', 'Profile picture updated successfully.');

        $ofw->refresh();
        $this->assertNotNull($ofw->getRawOriginal('avatar_url'));

        $remove = $this->actingAs($ofw)->delete(route('ofw.profile.avatar.destroy'));

        $remove->assertRedirect(route('ofw.profile.edit'));
        $remove->assertSessionHas('success', 'Profile picture removed.');
        $this->assertNull($ofw->fresh()->getRawOriginal('avatar_url'));
    }

    #[Test]
    public function ofw_profile_page_exposes_the_user_avatar_over_the_legacy_client_one(): void
    {
        $ofw = $this->makeOfw();

        $clientAvatar = 'https://res.cloudinary.com/test-cloud/image/upload/v1750000000/client-profile-pictures/client-legacy.jpg';
        $ofw->client->forceFill(['avatar_url' => $clientAvatar])->saveQuietly();
        $ofw->forceFill(['avatar_url' => self::OLD_AVATAR])->saveQuietly();

        $response = $this->actingAs($ofw)->get(route('ofw.profile.edit'));

        $response->assertOk();
        $page = $response->viewData('page');
        $props = $page['props'] ?? [];

        $this->assertSame(self::OLD_AVATAR, $props['user']['avatar_url'] ?? null, 'user.avatar_url must be exposed to the page.');
        $this->assertSame(self::OLD_AVATAR, $props['client']['avatar_url'] ?? null, 'The user avatar must win over the legacy client avatar.');
    }

    #[Test]
    public function guests_cannot_touch_either_avatar_endpoint(): void
    {
        $this->post(route('profile.avatar.store'))->assertRedirect(route('login'));
        $this->delete(route('profile.avatar.destroy'))->assertRedirect(route('login'));
        $this->post(route('ofw.profile.avatar.store'))->assertRedirect(route('login'));
        $this->delete(route('ofw.profile.avatar.destroy'))->assertRedirect(route('login'));
    }

    private function makeOfw(): User
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $client = Client::factory()->create([
            'type' => 'OFW',
            'user_id' => $ofw->id,
            'first_name' => 'Test',
            'last_name' => 'Ofw',
        ]);

        $ofw->forceFill(['client_id' => $client->id])->saveQuietly();

        return $ofw->fresh();
    }

    /**
     * Rebind the avatar service to a double whose uploadImage() raises the same
     * Cloudinary exception type the real service surfaces upstream.
     */
    private function bindAvatarServiceRejectingUploads(): void
    {
        $this->app->bind(CloudinaryAvatarService::class, fn () => new class extends CloudinaryAvatarService
        {
            public function __construct() {}

            public function uploadImage(UploadedFile $file, string $folder, string $publicId): string
            {
                throw new AuthorizationRequired('Invalid api_key 158513922971517');
            }

            public function deleteByUrl(?string $url): void {}
        });
    }
}
