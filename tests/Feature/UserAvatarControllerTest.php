<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAvatarControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'ADMIN']);
        Role::create(['name' => 'CASE_MANAGER']);
        Role::create(['name' => 'AGENCY_FOCAL_PERSON']);
    }

    public function test_user_cannot_change_others_avatar(): void
    {
        Storage::fake('public');

        $userA = User::factory()->create(['role' => 'CASE_MANAGER']);
        $userB = User::factory()->create(['role' => 'CASE_MANAGER']);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($userA)->post("/users/{$userB->id}/avatar", [
            'avatar' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_change_own_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($user)->post("/users/{$user->id}/avatar", [
            'avatar' => $file,
        ]);

        $response->assertRedirect();
    }

    public function test_admin_can_change_any_avatar(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($admin)->post("/users/{$user->id}/avatar", [
            'avatar' => $file,
        ]);

        $response->assertRedirect();
    }
}
