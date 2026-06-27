<?php

namespace Tests\Feature\Security;

use App\Models\User;
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
        $user = User::factory()->create([
            'password' => Hash::make($password),
            'mfa_secret' => 'test-secret',
            'mfa_recovery_codes' => ['code1', 'code2'],
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('profile.mfa.disable'), [
                'password' => $password,
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
            'mfa_secret' => 'test-secret',
            'mfa_recovery_codes' => ['code1'],
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('profile.mfa.disable'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function disable_mfa_with_wrong_password_returns_validation_error(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('real-password'),
            'mfa_secret' => 'test-secret',
            'mfa_recovery_codes' => ['code1'],
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('profile.mfa.disable'), [
                'password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
