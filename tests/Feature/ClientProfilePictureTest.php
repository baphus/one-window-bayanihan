<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientProfilePictureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);
        Storage::fake('public');

        $this->user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->client = Client::factory()->create();
    }

    public function test_guest_cannot_upload_profile_picture(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $response = $this->post(route('clients.avatar.store', $this->client), [
            'profile_picture' => $file,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_upload_profile_picture(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 300, 300);

        $response = $this->actingAs($this->user)
            ->post(route('clients.avatar.store', $this->client), [
                'profile_picture' => $file,
            ]);

        $response->assertSessionHas('success');
        $response->assertRedirect(route('clients.show', $this->client));

        $this->assertNotNull($this->client->fresh()->avatar_url);
    }

    public function test_upload_rejects_non_image_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user)
            ->post(route('clients.avatar.store', $this->client), [
                'profile_picture' => $file,
            ]);

        $response->assertSessionHasErrors('profile_picture');
    }

    public function test_upload_rejects_invalid_mime_type(): void
    {
        $file = UploadedFile::fake()->image('photo.gif', 100, 100);

        $response = $this->actingAs($this->user)
            ->post(route('clients.avatar.store', $this->client), [
                'profile_picture' => $file,
            ]);

        $response->assertSessionHasErrors('profile_picture');
    }

    public function test_upload_rejects_file_over_2mb(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 1000, 1000)->size(3000);

        $response = $this->actingAs($this->user)
            ->post(route('clients.avatar.store', $this->client), [
                'profile_picture' => $file,
            ]);

        $response->assertSessionHasErrors('profile_picture');
    }

    public function test_upload_replaces_existing_picture(): void
    {
        $firstFile = UploadedFile::fake()->image('first.jpg', 100, 100);
        $this->actingAs($this->user)
            ->post(route('clients.avatar.store', $this->client), [
                'profile_picture' => $firstFile,
            ]);

        $firstPath = $this->client->fresh()->avatar_url;

        $secondFile = UploadedFile::fake()->image('second.jpg', 100, 100);
        $this->actingAs($this->user)
            ->post(route('clients.avatar.store', $this->client), [
                'profile_picture' => $secondFile,
            ]);

        $secondPath = $this->client->fresh()->avatar_url;

        $this->assertNotSame($firstPath, $secondPath);
    }

    public function test_guest_cannot_delete_profile_picture(): void
    {
        $this->client->update(['avatar_url' => 'profile-pictures/test.jpg']);

        $response = $this->delete(route('clients.avatar.destroy', $this->client));

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_delete_profile_picture(): void
    {
        $this->client->update(['avatar_url' => 'profile-pictures/test.jpg']);

        $response = $this->actingAs($this->user)
            ->delete(route('clients.avatar.destroy', $this->client));

        $response->assertSessionHas('success');
        $response->assertRedirect(route('clients.show', $this->client));

        $this->assertNull($this->client->fresh()->avatar_url);
    }
}
