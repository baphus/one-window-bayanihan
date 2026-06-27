<?php

namespace Tests\Feature\Security;

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarAccessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    // -----------------------------------------------
    //  Storage on private disk
    // -----------------------------------------------

    public function test_user_avatar_stored_on_private_disk(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $path = $file->storeAs('avatars', 'user-'.$user->id.'-test.jpg', 'private');

        $user->avatar_url = $path;
        $user->save();

        $this->assertNotNull($user->fresh()->avatar_url);
        $this->assertStringStartsWith('avatars/', $path);
        Storage::disk('private')->assertExists($path);
    }

    public function test_client_avatar_stored_on_private_disk(): void
    {
        Storage::fake('private');

        $client = Client::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $path = $file->storeAs('profile-pictures', 'client-'.$client->id.'-test.jpg', 'private');

        $client->avatar_url = $path;
        $client->save();

        $this->assertNotNull($client->fresh()->avatar_url);
        $this->assertStringStartsWith('profile-pictures/', $path);
        Storage::disk('private')->assertExists($path);
    }

    public function test_agency_logo_stored_on_private_disk(): void
    {
        Storage::fake('private');

        $agency = Agency::factory()->create();
        $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
        $path = $file->storeAs('logos', 'agency-'.$agency->id.'-test.jpg', 'private');

        $agency->logo_url = $path;
        $agency->save();

        $this->assertNotNull($agency->fresh()->logo_url);
        $this->assertStringStartsWith('logos/', $path);
        Storage::disk('private')->assertExists($path);
    }

    // -----------------------------------------------
    //  Accessor returns signed temporary URL
    // -----------------------------------------------

    public function test_user_avatar_url_returns_signed_temporary_url(): void
    {
        $expectedUrl = 'http://localhost/storage/avatars/user-test.jpg?expires=1234567890&signature=abc123';

        Storage::shouldReceive('disk->temporaryUrl')
            ->once()
            ->andReturn($expectedUrl);

        $user = User::factory()->create(['avatar_url' => 'avatars/user-test.jpg']);

        $url = $user->avatar_url;

        $this->assertNotNull($url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_client_avatar_url_returns_signed_temporary_url(): void
    {
        $expectedUrl = 'http://localhost/storage/profile-pictures/client-test.jpg?expires=1234567890&signature=abc123';

        Storage::shouldReceive('disk->temporaryUrl')
            ->once()
            ->andReturn($expectedUrl);

        $client = Client::factory()->create(['avatar_url' => 'profile-pictures/client-test.jpg']);

        $url = $client->avatar_url;

        $this->assertNotNull($url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_agency_logo_url_returns_signed_temporary_url(): void
    {
        $expectedUrl = 'http://localhost/storage/logos/agency-test.jpg?expires=1234567890&signature=abc123';

        Storage::shouldReceive('disk->temporaryUrl')
            ->once()
            ->andReturn($expectedUrl);

        $agency = Agency::factory()->create(['logo_url' => 'logos/agency-test.jpg']);

        $url = $agency->logo_url;

        $this->assertNotNull($url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    // -----------------------------------------------
    //  Null / empty value cases
    // -----------------------------------------------

    public function test_avatar_url_returns_null_when_not_set(): void
    {
        $user = User::factory()->create(['avatar_url' => null]);
        $client = Client::factory()->create(['avatar_url' => null]);
        $agency = Agency::factory()->create(['logo_url' => null]);

        $this->assertNull($user->avatar_url);
        $this->assertNull($client->avatar_url);
        $this->assertNull($agency->logo_url);
    }

    // -----------------------------------------------
    //  Legacy absolute URL passthrough
    // -----------------------------------------------

    public function test_avatar_url_returns_absolute_url_as_is(): void
    {
        $legacyUrl = 'http://example.com/storage/logos/legacy-logo.png';
        $agency = Agency::factory()->create(['logo_url' => $legacyUrl]);

        $this->assertEquals($legacyUrl, $agency->logo_url);
    }

    public function test_avatar_url_strips_storage_prefix(): void
    {
        // Legacy data stored as '/storage/logos/...' before the private disk migration
        $legacyPath = '/storage/logos/legacy-logo.png';
        $expectedUrl = 'http://localhost/storage/logos/legacy-logo.png?expires=1234567890&signature=abc123';

        Storage::shouldReceive('disk->temporaryUrl')
            ->once()
            ->with('logos/legacy-logo.png', \Mockery::any())
            ->andReturn($expectedUrl);

        $agency = Agency::factory()->create(['logo_url' => $legacyPath]);

        $url = $agency->logo_url;

        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);
    }
}
