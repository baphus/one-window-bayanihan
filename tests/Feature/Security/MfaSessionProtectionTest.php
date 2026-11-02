<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Http\Middleware\CheckMfaEnrolled;
use App\Http\Middleware\EnsureMfaSession;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MfaSessionProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const ROLE_KEYS = [
        'mfa_role_case_manager_required',
        'mfa_role_agency_required',
        'mfa_role_ofw_required',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withMiddleware([CheckMfaEnrolled::class, EnsureMfaSession::class]);
    }

    private function setRoleDefaults(bool $required): void
    {
        foreach (self::ROLE_KEYS as $key) {
            SystemSetting::setValue($key, $required);
        }
    }

    #[Test]
    public function enrolled_optional_user_without_a_marker_is_logged_out(): void
    {
        $this->setRoleDefaults(false);
        $user = User::factory()->mfaEnabled()->create(['role' => UserRole::OFW->value]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    public function required_unenrolled_user_can_enrol_and_recover_but_not_edit_the_account(): void
    {
        $this->setRoleDefaults(true);
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('profile.edit'));
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Changed',
            'email' => $user->email,
        ])->assertRedirect(route('profile.edit'));
        $this->assertSame($user->name, $user->fresh()->name);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $this->actingAs($user)->getJson(route('profile.mfa.status'))->assertOk();
        $this->actingAs($user)->get(route('profile.email-change.init'))->assertRedirect(route('profile.edit'));
        $this->actingAs($user)->delete(route('profile.destroy'))->assertRedirect(route('profile.edit'));
        $this->assertNotNull($user->fresh());
    }

    #[Test]
    public function optional_unenrolled_user_keeps_business_access(): void
    {
        $this->setRoleDefaults(false);
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    #[Test]
    public function admin_stays_required_even_when_every_role_default_is_optional(): void
    {
        $this->setRoleDefaults(false);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('profile.edit'));
    }

    #[Test]
    public function policy_tightening_applies_on_the_next_request(): void
    {
        $this->setRoleDefaults(false);
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->setRoleDefaults(true);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('profile.edit'));
    }

    #[Test]
    public function enrolled_optional_ofw_is_challenged_at_login(): void
    {
        $this->setRoleDefaults(false);
        $user = User::factory()->mfaEnabled()->create(['role' => UserRole::OFW->value]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'P@ssw0rd!',
        ])->assertRedirect(route('mfa.challenge.show'));

        $this->assertGuest();
    }
}
