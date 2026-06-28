<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserVerifyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    public function test_admin_can_verify_unverified_user(): void
    {
        $target = User::factory()->unverified()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.verify', $target->id));

        $response->assertRedirect();
        $this->assertNotNull($target->fresh()->email_verified_at);
    }

    public function test_admin_can_unverify_verified_user(): void
    {
        $target = User::factory()->create(); // email_verified_at defaults to now()

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.verify', $target->id));

        $response->assertRedirect();
        $this->assertNull($target->fresh()->email_verified_at);
    }

    public function test_case_manager_cannot_verify(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $target = User::factory()->unverified()->create();

        $response = $this->actingAs($cm)
            ->patch(route('admin.users.verify', $target->id));

        $response->assertForbidden();
    }

    public function test_agency_focal_cannot_verify(): void
    {
        $agency = User::factory()->create(['role' => 'AGENCY']);
        $target = User::factory()->unverified()->create();

        $response = $this->actingAs($agency)
            ->patch(route('admin.users.verify', $target->id));

        $response->assertForbidden();
    }

    public function test_guest_redirected_to_login(): void
    {
        $target = User::factory()->unverified()->create();

        $response = $this->patch(route('admin.users.verify', $target->id));

        $response->assertRedirect(route('login'));
    }

    public function test_verify_nonexistent_user_returns_404(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.verify', $fakeId));

        $response->assertNotFound();
    }

    public function test_verify_inactive_user_returns_error(): void
    {
        $target = User::factory()->unverified()->create(['is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.verify', $target->id));

        $response->assertSessionHas('error');
        $this->assertNull($target->fresh()->email_verified_at); // unchanged
    }

    public function test_verify_deleted_user_returns_error(): void
    {
        $target = User::factory()->create(['is_deleted' => true]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.verify', $target->id));

        $response->assertSessionHas('error');
    }

    public function test_admin_email_change_resets_verification(): void
    {
        $target = User::factory()->create(['role' => 'CASE_MANAGER']); // email_verified_at = now() by default
        $this->assertNotNull($target->email_verified_at);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), [
                'name' => $target->name,
                'email' => 'changed-'.$target->email,
                'role' => $target->role,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $target->refresh();
        $this->assertNull($target->email_verified_at);
        $this->assertStringStartsWith('changed-', $target->email);
    }
}
