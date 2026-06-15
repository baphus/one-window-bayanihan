<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminAgencyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

    }

    public function test_admin_can_delete_agency(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $agency = Agency::factory()->create(['is_active' => true, 'is_deleted' => false]);

        $response = $this->actingAs($admin)->delete(route('admin.agencies.destroy', $agency->id));

        $response->assertRedirect(route('admin.agencies.index'));

        $this->assertDatabaseHas('agencies', [
            'id' => $agency->id,
            'is_deleted' => true,
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_delete_nonexistent_agency(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($admin)->delete(route('admin.agencies.destroy', $fakeId));

        $response->assertNotFound();
    }

    public function test_admin_can_upload_logo_when_creating_agency(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $file = UploadedFile::fake()->image('logo.jpg');

        $response = $this->actingAs($admin)->post(route('admin.agencies.store'), [
            'name' => 'Test Agency',
            'short' => 'Test',
            'logo_url' => $file,
        ]);

        $response->assertRedirect(route('admin.agencies.index'));

        $agency = Agency::where('name', 'Test Agency')->first();
        $this->assertNotNull($agency);
        $this->assertNotNull($agency->logo_url);
        $this->assertStringContainsString('/storage/logos/', $agency->logo_url);
    }

    public function test_logo_upload_rejects_non_image_file(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $file = UploadedFile::fake()->create('logo.txt', 100);

        $response = $this->actingAs($admin)->post(route('admin.agencies.store'), [
            'name' => 'Test Agency',
            'short' => 'Test',
            'logo_url' => $file,
        ]);

        $response->assertInvalid(['logo_url']);
    }

    public function test_logo_upload_rejects_oversized_file(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $file = UploadedFile::fake()->create('logo.jpg', 3000);

        $response = $this->actingAs($admin)->post(route('admin.agencies.store'), [
            'name' => 'Test Agency',
            'short' => 'Test',
            'logo_url' => $file,
        ]);

        $response->assertInvalid(['logo_url']);
    }

    public function test_latitude_and_longitude_are_stored_when_creating_agency(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->post(route('admin.agencies.store'), [
            'name' => 'Test Agency',
            'short' => 'Test',
            'latitude' => 10.3157,
            'longitude' => 123.8854,
        ]);

        $response->assertRedirect(route('admin.agencies.index'));

        $this->assertDatabaseHas('agencies', [
            'name' => 'Test Agency',
            'latitude' => 10.3157,
            'longitude' => 123.8854,
        ]);
    }

    public function test_map_link_is_auto_generated_from_latitude_and_longitude(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->post(route('admin.agencies.store'), [
            'name' => 'Test Agency',
            'short' => 'Test',
            'latitude' => 10.3157,
            'longitude' => 123.8854,
        ]);

        $response->assertRedirect(route('admin.agencies.index'));

        $this->assertDatabaseHas('agencies', [
            'name' => 'Test Agency',
            'map_link' => 'https://www.google.com/maps?q=10.3157,123.8854',
        ]);
    }

    public function test_map_link_remains_null_when_lat_lng_not_provided(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->post(route('admin.agencies.store'), [
            'name' => 'Test Agency',
            'short' => 'Test',
        ]);

        $response->assertRedirect(route('admin.agencies.index'));

        $agency = Agency::where('name', 'Test Agency')->first();
        $this->assertNotNull($agency);
        $this->assertNull($agency->map_link);
    }

    public function test_existing_logo_unchanged_when_updating_without_logo_file(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency = Agency::factory()->create([
            'logo_url' => 'logos/old-logo.jpg',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.agencies.update', $agency->id), [
            'name' => 'Updated Agency',
            'short' => 'Updated',
        ]);

        $response->assertRedirect(route('admin.agencies.index'));

        $this->assertDatabaseHas('agencies', [
            'id' => $agency->id,
            'logo_url' => 'logos/old-logo.jpg',
        ]);
    }
}
