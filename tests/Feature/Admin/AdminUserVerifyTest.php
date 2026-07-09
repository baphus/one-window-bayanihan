<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\OtpService;
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

    public function test_admin_email_change_via_otp_flow(): void
    {
        $target = User::factory()->create(['role' => 'CASE_MANAGER']); // email_verified_at = now() by default
        $this->assertNotNull($target->email_verified_at);

        $newEmail = 'changed-'.$target->email;

        // Mock OtpService so we don't need real cache/session persistence
        $this->mock(OtpService::class, function ($mock) use ($newEmail) {
            $mock->shouldReceive('generate')
                ->once()
                ->with($newEmail, 'admin_email_change', \Mockery::any())
                ->andReturn('123456');

            $mock->shouldReceive('verify')
                ->once()
                ->with($newEmail, 'admin_email_change', '123456', \Mockery::any())
                ->andReturn(true);
        });

        // Step 1: Send OTP
        $this->actingAs($this->admin)
            ->post(route('admin.users.email-change.send-otp', $target->id), [
                'admin_password' => 'P@ssw0rd!',
                'new_email' => $newEmail,
            ]);

        // Step 2: Verify OTP (stores verified flag in session, does NOT save email yet)
        $this->actingAs($this->admin)
            ->withSession(['pending_admin_email_change_'.$target->id => $newEmail])
            ->post(route('admin.users.email-change.verify-otp', $target->id), [
                'otp' => '123456',
            ]);

        // Step 3: Submit update — should save the email now since it's verified
        $response = $this->actingAs($this->admin)
            ->withSession(['verified_new_email_admin_'.$target->id => $newEmail])
            ->patch(route('admin.users.update', $target->id), [
                'name' => $target->name,
                'email' => $newEmail,
                'role' => $target->role,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertNotNull($target->email_verified_at, 'email_verified_at should still be set (admin OTP verified the new email)');
        $this->assertEquals($newEmail, $target->email, 'Email should be updated');
    }

    public function test_admin_email_update_without_otp_is_rejected(): void
    {
        $target = User::factory()->create(['role' => 'CASE_MANAGER']);
        $originalEmail = $target->email;

        // Direct PATCH with changed email but no OTP verification
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), [
                'name' => $target->name,
                'email' => 'hacked-'.$target->email,
                'role' => $target->role,
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors();
        $target->refresh();
        $this->assertEquals($originalEmail, $target->email, 'Email should NOT change without OTP');
        $this->assertNotNull($target->email_verified_at, 'Verification should remain intact');
    }
}
