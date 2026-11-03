<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MfaDisablePasswordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function disable_mfa_with_correct_password_succeeds(): void
    {
        $password = 'correct-password';
        SystemSetting::setValue('mfa_role_case_manager_required', false);
        $user = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'password' => Hash::make($password),
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => app(MfaService::class)->hashRecoveryCodes(['ABCD-EFGH-IJKL']),
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAsWithMfa($user)
            ->postJson(route('profile.mfa.disable'), [
                'password' => $password,
                'recovery_code' => 'ABCD-EFGH-IJKL',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'Two-factor authentication has been disabled.',
            ]);

        $user->refresh();
        $this->assertNull($user->mfa_enabled_at);
        $this->assertNull($user->mfa_secret);
        $this->assertNull($user->mfa_recovery_codes);
    }

    #[Test]
    public function disable_mfa_without_password_returns_validation_error(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => app(MfaService::class)->hashRecoveryCodes(['ABCD-EFGH-IJKL']),
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAsWithMfa($user)
            ->postJson(route('profile.mfa.disable'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function disable_mfa_with_wrong_password_returns_validation_error(): void
    {
        SystemSetting::setValue('mfa_role_case_manager_required', false);
        $user = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'password' => Hash::make('real-password'),
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => app(MfaService::class)->hashRecoveryCodes(['ABCD-EFGH-IJKL']),
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAsWithMfa($user)
            ->postJson(route('profile.mfa.disable'), [
                'password' => 'wrong-password',
                'recovery_code' => 'ABCD-EFGH-IJKL',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
