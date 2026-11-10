<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\CloudinaryAvatarService;
use Cloudinary\Api\Exception\AuthorizationRequired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cloudinary failure handling for the user avatar upload path:
 *  - CloudinaryAvatarService::uploadImage() must convert any Cloudinary API
 *    failure into a RuntimeException with a fixed, credential-free message.
 *  - CloudinaryAvatarService::deleteByUrl() is best-effort: a failing destroy
 *    is logged and swallowed, never thrown.
 *  - ProfileController::storeAvatar()/destroyAvatar() must catch every
 *    throwable the service can raise — a third-party failure must yield a
 *    graceful redirect, never a 500.
 * The Cloudinary API calls are faked via the protected performUpload()/
 * performDestroy() seams (no network access, no real credentials used).
 */
class CloudinaryFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Upstream message that deliberately embeds a (fake) API key: if any of it
     * ever leaks into the user-facing RuntimeException or a log record, the
     * no-leakage assertions below fail.
     */
    private const LEAKING_UPSTREAM_MESSAGE = 'Invalid api_key 158513922971517';

    private const CONFIGURED_CLOUDINARY_URL = 'cloudinary://test-key:test-secret@test-cloud';

    private const GENERIC_UPLOAD_ERROR = 'The image could not be uploaded. Please try again later.';

    #[Test]
    public function upload_image_converts_cloudinary_failure_into_credential_free_runtime_exception(): void
    {
        config(['services.cloudinary.url' => self::CONFIGURED_CLOUDINARY_URL]);

        $service = new class extends CloudinaryAvatarService
        {
            public function performUpload(string $path, array $options): array
            {
                throw new AuthorizationRequired('Invalid api_key 158513922971517');
            }

            public function performDestroy(string $publicId, array $options): void
            {
                throw new AuthorizationRequired('Invalid api_key 158513922971517');
            }
        };

        $file = UploadedFile::fake()->image('avatar.jpg', 160, 160);

        try {
            $service->uploadImage($file, 'avatars', 'user-fake-id');
            $this->fail('uploadImage() must throw when the Cloudinary upload fails.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e, 'The raw Cloudinary exception must be normalized to a RuntimeException.');
            $this->assertNotInstanceOf(AuthorizationRequired::class, $e);

            $message = $e->getMessage();
            $this->assertStringNotContainsString('158513922971517', $message, 'The api key must never leak into the user-facing message.');
            $this->assertStringNotContainsString('Invalid api_key', $message, 'The raw upstream message must never leak into the user-facing message.');
            $this->assertStringNotContainsString('test-key', $message, 'The configured key part of CLOUDINARY_URL must never leak.');
            $this->assertStringNotContainsString('test-secret', $message, 'The configured secret part of CLOUDINARY_URL must never leak.');
            $this->assertStringNotContainsString('test-cloud', $message, 'The configured cloud name must never leak.');
            $this->assertSame('The image could not be uploaded. Please try again later.', $message);

            $this->assertInstanceOf(AuthorizationRequired::class, $e->getPrevious(), 'The original Cloudinary exception must be preserved as previous.');
        }
    }

    #[Test]
    public function delete_by_url_swallows_cloudinary_failures(): void
    {
        config(['services.cloudinary.url' => self::CONFIGURED_CLOUDINARY_URL]);

        $service = new class extends CloudinaryAvatarService
        {
            public bool $destroyAttempted = false;

            public function performUpload(string $path, array $options): array
            {
                throw new AuthorizationRequired('Invalid api_key 158513922971517');
            }

            public function performDestroy(string $publicId, array $options): void
            {
                $this->destroyAttempted = true;
                throw new AuthorizationRequired('Invalid api_key 158513922971517');
            }
        };

        $service->deleteByUrl('https://res.cloudinary.com/test-cloud/image/upload/v1750000000/avatars/user-fake-id.jpg');
        $this->assertTrue($service->destroyAttempted);
    }

    #[Test]
    public function user_avatar_upload_redirects_gracefully_when_cloudinary_fails_and_preserves_old_url(): void
    {
        $this->bindAvatarServiceRejectingUploads();
        $user = User::factory()->create(['role' => UserRole::ADMIN->value, 'avatar_url' => 'https://res.cloudinary.com/test-cloud/image/upload/v1750000000/avatars/old.jpg']);

        $response = $this->actingAsWithMfa($user)->post(route('profile.avatar.store'), [
            'profile_picture' => UploadedFile::fake()->image('avatar.jpg', 160, 160),
        ]);

        $response->assertRedirect(route('profile.edit'))->assertSessionHasErrors('profile_picture');
        $this->assertSame('https://res.cloudinary.com/test-cloud/image/upload/v1750000000/avatars/old.jpg', $user->fresh()->getRawOriginal('avatar_url'));
        $this->assertSame(0, $this->app->make(CloudinaryAvatarService::class)->deleteCalls);
    }

    #[Test]
    public function removing_user_avatar_clears_database_even_when_cloudinary_delete_fails(): void
    {
        $this->app->singleton(CloudinaryAvatarService::class, fn () => new class extends CloudinaryAvatarService
        {
            public function __construct() {}

            public function uploadImage(UploadedFile $file, string $folder, string $publicId): string
            {
                throw new AuthorizationRequired(self::LEAKING_UPSTREAM_MESSAGE);
            }

            public function deleteByUrl(?string $url): void
            {
                throw new AuthorizationRequired(self::LEAKING_UPSTREAM_MESSAGE);
            }
        });
        $user = User::factory()->create(['role' => UserRole::ADMIN->value, 'avatar_url' => 'https://res.cloudinary.com/test-cloud/image/upload/v1750000000/avatars/old.jpg']);

        $this->actingAsWithMfa($user)->delete(route('profile.avatar.destroy'))
            ->assertRedirect(route('profile.edit'))->assertSessionHas('success', 'Profile picture removed.');
        $this->assertNull($user->fresh()->getRawOriginal('avatar_url'));
    }

    /**
     * Rebinds the container binding to a double whose uploadImage() raises the
     * same Cloudinary exception type the real service surfaces upstream.
     */
    private function bindAvatarServiceRejectingUploads(): void
    {
        $this->app->singleton(CloudinaryAvatarService::class, fn () => new class extends CloudinaryAvatarService
        {
            public int $deleteCalls = 0;

            public function __construct() {}

            public function uploadImage(UploadedFile $file, string $folder, string $publicId): string
            {
                throw new AuthorizationRequired('Invalid api_key 158513922971517');
            }

            public function deleteByUrl(?string $url): void
            {
                $this->deleteCalls++;
            }
        });
    }
}
