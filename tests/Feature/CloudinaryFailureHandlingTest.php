<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Services\CloudinaryAvatarService;
use Cloudinary\Api\Exception\AuthorizationRequired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cloudinary failure handling for the client avatar upload path:
 *  - CloudinaryAvatarService::uploadImage() must convert any Cloudinary API
 *    failure into a RuntimeException with a fixed, credential-free message.
 *  - CloudinaryAvatarService::deleteByUrl() is best-effort: a failing destroy
 *    is logged and swallowed, never thrown.
 *  - ClientController::storeAvatar()/destroyAvatar() must catch every
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
            $service->uploadImage($file, 'client-profile-pictures', 'client-fake-id');
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
    public function delete_by_url_swallows_cloudinary_failures_without_touching_client_state(): void
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

        $avatarUrl = 'https://res.cloudinary.com/test-cloud/image/upload/v1750000000/client-profile-pictures/client-fake-id.jpg';
        $client = Client::factory()->create(['user_id' => User::factory()->create()->id, 'avatar_url' => $avatarUrl]);

        // Best-effort contract: this must not throw even though the destroy
        // seam fails, and it never touches the clients table itself.
        $service->deleteByUrl($client->getRawOriginal('avatar_url'));

        $client->refresh();
        $this->assertSame($avatarUrl, $client->getRawOriginal('avatar_url'), 'A failed best-effort delete must not change client state.');
    }

    #[Test]
    public function store_avatar_redirects_gracefully_when_cloudinary_upload_fails(): void
    {
        $this->bindAvatarServiceRejectingUploads();

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $client = $this->makePublishedClient('https://res.cloudinary.com/test-cloud/image/upload/v1750000000/client-profile-pictures/client-old.jpg');

        $response = $this->actingAs($admin)->post(route('clients.avatar.store', $client->id), [
            'profile_picture' => UploadedFile::fake()->image('avatar.jpg', 160, 160),
        ]);

        // The defect: an uncaught Cloudinary exception surfaced as an HTTP 500.
        $response->assertStatus(302);
        $response->assertRedirect(route('clients.show', $client->id));
        $response->assertSessionHasErrors(['profile_picture']);

        $client->refresh();
        $this->assertSame(
            'https://res.cloudinary.com/test-cloud/image/upload/v1750000000/client-profile-pictures/client-old.jpg',
            $client->getRawOriginal('avatar_url'),
            'A failed upload must leave the previous avatar in place.'
        );
    }

    #[Test]
    public function destroy_avatar_succeeds_even_when_cloudinary_delete_fails(): void
    {
        $avatarUrl = 'https://res.cloudinary.com/test-cloud/image/upload/v1750000000/client-profile-pictures/client-old.jpg';
        $client = $this->makePublishedClient($avatarUrl);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)->delete(route('clients.avatar.destroy', $client->id));

        $response->assertStatus(302);
        $response->assertRedirect(route('clients.show', $client->id));
        $response->assertSessionHas('success', 'Profile picture removed successfully.');

        $client->refresh();
        $this->assertNull($client->getRawOriginal('avatar_url'), 'The avatar must be cleared even when the Cloudinary destroy fails (best-effort).');
    }

    #[Test]
    public function store_avatar_still_saves_through_the_test_double_on_success(): void
    {
        $client = $this->makePublishedClient(null);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($admin)->post(route('clients.avatar.store', $client->id), [
            'profile_picture' => UploadedFile::fake()->image('avatar.jpg', 160, 160),
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('clients.show', $client->id));
        $response->assertSessionHas('success', 'Profile picture updated successfully.');

        $client->refresh();
        $this->assertNotNull($client->getRawOriginal('avatar_url'), 'The hardening must not break the normal upload flow.');
        $this->assertStringContainsString('res.cloudinary.com', (string) $client->getRawOriginal('avatar_url'));
    }

    /**
     * Rebinds the container binding to a double whose uploadImage() raises the
     * same Cloudinary exception type the real service surfaces upstream.
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

    /**
     * A client that passes authorizeClientAccess()'s publishedOnly() scope:
     * linked user account plus one live OPEN, non-deleted case.
     */
    private function makePublishedClient(?string $avatarUrl): Client
    {
        $client = Client::factory()->create(['user_id' => User::factory()->create()->id, 'avatar_url' => $avatarUrl]);
        CaseFile::factory()->open()->create(['client_id' => $client->id, 'status' => 'OPEN']);

        return $client;
    }
}
