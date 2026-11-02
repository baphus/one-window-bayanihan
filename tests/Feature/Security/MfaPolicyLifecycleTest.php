<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\MfaPendingState;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaPolicyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $recoveryCodeIndex = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => app(MfaService::class)->hashRecoveryCodes([
                'ABCD-EFGH-IJKL', 'MNOP-QRST-UVWX', 'YZ12-3456-7890',
            ]),
            'mfa_enabled_at' => now(),
        ]);
    }

    private function withMfaMarker(User $user): void
    {
        $this->withSession([
            MfaPendingState::MARKER_KEY => [
                'user_id' => (string) $user->id,
                'credential_fingerprint' => hash('sha256', (string) $user->password),
            ],
        ]);
        $this->actingAs($user);
    }

    private function recoveryCode(): string
    {
        return match ($this->recoveryCodeIndex) {
            1 => 'ABCD-EFGH-IJKL',
            2 => 'MNOP-QRST-UVWX',
            default => 'YZ12-3456-7890',
        };
    }

    private function withFreshConfirmation(User $user): void
    {
        $this->withMfaMarker($user);
        $code = $this->recoveryCode();
        $this->postJson(route('profile.mfa.confirm'), ['recovery_code' => $code])->assertOk();
        $this->recoveryCodeIndex++;
    }

    #[Test]
    public function confirmation_consumes_totp_once_and_expires_after_five_minutes(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => app(MfaService::class)->hashRecoveryCodes([
                'ABCD-EFGH-IJKL', 'MNOP-QRST-UVWX', 'YZ12-3456-7890',
            ]),
            'mfa_enabled_at' => now(),
        ]);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->withMfaMarker($user);
        $this->actingAs($user)->postJson(route('profile.mfa.confirm'), ['otp' => $code])
            ->assertOk()
            ->assertJsonPath('confirmed', true)
            ->assertJsonStructure(['confirmed_until']);

        $this->postJson(route('profile.mfa.confirm'), ['otp' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mfa_confirmation', 'otp']);

        $this->travel(5)->minutes();
        $this->getJson(route('profile.mfa.status'))->assertJsonPath('confirmed_until', null);
    }

    #[Test]
    public function changed_role_default_requires_fresh_proof_reason_and_audits(): void
    {
        $key = 'mfa_role_ofw_required';
        $this->withMfaMarker($this->admin);
        $this->actingAs($this->admin)->post(route('admin.system-settings.update'), [$key => false])
            ->assertSessionHasErrors('mfa_change_reason');
        $this->assertSame(true, SystemSetting::getValue($key, true));

        $otp = app(Google2FA::class)->getCurrentOtp($this->admin->mfa_secret);
        $this->post(route('admin.system-settings.update'), [
            $key => false,
            'mfa_change_reason' => 'Approved policy exception',
            'otp' => $otp,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse((bool) SystemSetting::getValue($key));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_SECURITY',
            'module' => 'mfa',
            'user_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function override_requires_fresh_proof_and_reason_and_stays_across_role_changes(): void
    {
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $payload = ['name' => $target->name, 'email' => $target->email, 'role' => UserRole::CASE_MANAGER->value, 'mfa_requirement_override' => 'optional'];

        $this->withMfaMarker($this->admin);
        $this->actingAs($this->admin)->patch(route('admin.users.update', $target->id), $payload)
            ->assertSessionHasErrors('mfa_change_reason');
        $this->assertNull($target->fresh()->mfa_requirement_override);

        $otp = app(Google2FA::class)->getCurrentOtp($this->admin->mfa_secret);
        $this->patch(route('admin.users.update', $target->id), $payload + ['mfa_change_reason' => 'Approved exemption', 'otp' => $otp])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('optional', $target->fresh()->mfa_requirement_override);

        $this->withFreshConfirmation($this->admin);
        $this->patch(route('admin.users.update', $target->id), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::AGENCY->value,
        ])->assertRedirect();
        $this->assertSame('optional', $target->fresh()->mfa_requirement_override);
    }

    #[Test]
    public function admin_reset_requires_fresh_proof_reason_and_rejects_self_reset(): void
    {
        $target = User::factory()->create([
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => app(MfaService::class)->hashRecoveryCodes([
                'ABCD-EFGH-IJKL', 'MNOP-QRST-UVWX', 'YZ12-3456-7890',
            ]),
            'mfa_enabled_at' => now(),
        ]);
        $this->withMfaMarker($this->admin);
        $this->actingAs($this->admin)->post(route('admin.users.reset-mfa', $target->id), ['reason' => 'Lost factor'])
            ->assertSessionHasErrors('mfa_confirmation');
        $this->assertNotNull($target->fresh()->mfa_enabled_at);

        $otp = app(Google2FA::class)->getCurrentOtp($this->admin->mfa_secret);
        $this->post(route('admin.users.reset-mfa', $target->id), ['reason' => 'Lost factor', 'otp' => $otp])->assertRedirect();
        $this->assertNull($target->fresh()->mfa_enabled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'RESET_MFA', 'entity_id' => $target->id, 'user_id' => $this->admin->id]);

        $this->withFreshConfirmation($this->admin);
        $this->post(route('admin.users.reset-mfa', $this->admin->id), ['reason' => 'No self reset'])
            ->assertSessionHasErrors('mfa_reset');
    }
}
