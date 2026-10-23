<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `PATCH /admin/users/{user}` is the one admin route that can hand over an
 * account: it can move a user's login to a new mailbox and it can set a user's
 * password outright. Both used to be reachable from a plain admin session with
 * no proof and no re-authentication, which made the three-step email-change OTP
 * flow (password-confirm -> send-otp -> verify-otp) and reset-MFA's own password
 * check theatre rather than an enforced invariant.
 */
class AdminUserCredentialGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Factory default password is 'P@ssw0rd!'.
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $target, array $overrides = []): array
    {
        return array_merge([
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
        ], $overrides);
    }

    #[Test]
    public function email_change_without_the_otp_flag_is_rejected(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $original = $target->email;

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                'email' => 'unverified-change@example.com',
            ]));

        $response->assertSessionHasErrors(['email']);
        $this->assertSame($original, $target->fresh()->email);
    }

    #[Test]
    public function email_change_with_a_matching_otp_flag_is_accepted_and_consumes_the_flag(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $newEmail = 'verified-change@example.com';
        $key = 'verified_new_email_admin_'.$target->id;

        $response = $this->actingAs($this->admin)
            ->withSession([$key => $newEmail])
            ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                'email' => $newEmail,
            ]));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasNoErrors();
        $this->assertSame($newEmail, $target->fresh()->email);
        $this->assertFalse(
            $this->app['session.store']->has($key),
            'A spent OTP verification must not authorise a second email change.'
        );
    }

    #[Test]
    public function an_otp_flag_issued_for_another_address_does_not_authorise_the_change(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $original = $target->email;

        $response = $this->actingAs($this->admin)
            ->withSession(['verified_new_email_admin_'.$target->id => 'proved-address@example.com'])
            ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                'email' => 'attacker-address@example.com',
            ]));

        $response->assertSessionHasErrors(['email']);
        $this->assertSame($original, $target->fresh()->email);
    }

    #[Test]
    public function resubmitting_the_same_email_stays_a_plain_profile_edit(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                'name' => 'Renamed Without Otp',
                'position' => 'Focal Person',
            ]));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasNoErrors();
        $this->assertSame('Renamed Without Otp', $target->fresh()->name);
    }

    #[Test]
    public function password_change_without_the_admin_current_password_is_rejected(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $originalHash = $target->password;

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                'password' => 'N3w-P@ss!word',
            ]));

        $response->assertSessionHasErrors(['current_password']);
        $this->assertSame($originalHash, $target->fresh()->password);
        $this->assertFalse(Hash::check('N3w-P@ss!word', $target->fresh()->password));
    }

    #[Test]
    public function password_change_with_a_wrong_admin_current_password_is_rejected(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                'password' => 'N3w-P@ss!word',
                'current_password' => 'not-the-admin-password',
            ]));

        $response->assertSessionHasErrors(['current_password']);
        $this->assertFalse(Hash::check('N3w-P@ss!word', $target->fresh()->password));
    }

    #[Test]
    public function password_change_with_the_admin_current_password_is_accepted(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                'password' => 'N3w-P@ss!word',
                'current_password' => 'P@ssw0rd!',
            ]));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('N3w-P@ss!word', $target->fresh()->password));
    }

    #[Test]
    public function an_admin_cannot_take_over_another_admin_without_their_own_password(): void
    {
        $otherAdmin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $otherAdmin->id), $this->payload($otherAdmin, [
                'password' => 'N3w-P@ss!word',
                'current_password' => 'guessing-wrongly',
            ]));

        $response->assertSessionHasErrors(['current_password']);
        $this->assertFalse(Hash::check('N3w-P@ss!word', $otherAdmin->fresh()->password));
    }

    /**
     * The re-auth budget sits on the credential branches only, so a hijacked
     * session still cannot guess the admin's password for free through them.
     */
    #[Test]
    public function credential_changes_are_rate_limited_to_five_per_minute(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->admin)
                ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                    'password' => 'N3w-P@ss!word',
                    'current_password' => 'wrong-guess-'.$i,
                ]))
                ->assertSessionHasErrors(['current_password']);
        }

        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                'password' => 'N3w-P@ss!word',
                'current_password' => 'wrong-guess-6',
            ]))
            ->assertStatus(429);
    }

    /**
     * Ordinary admin user management must not spend that budget: the route also
     * carries every name/role/position edit, so a plain edit storm stays free.
     */
    #[Test]
    public function ordinary_profile_edits_are_not_rate_limited(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($this->admin)
                ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                    'name' => 'Renamed '.$i,
                ]))
                ->assertRedirect(route('admin.users.index'));
        }

        $this->assertSame('Renamed 11', $target->fresh()->name);
    }

    #[Test]
    public function a_case_manager_cannot_reach_the_credential_branches(): void
    {
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($caseManager)
            ->patch(route('admin.users.update', $target->id), $this->payload($target, [
                'password' => 'N3w-P@ss!word',
                'current_password' => 'P@ssw0rd!',
            ]))
            ->assertForbidden();
    }
}
