<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_generate_secret_returns_qr_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('profile.mfa.generate'))
            ->assertOk()
            ->assertJsonStructure(['secret', 'qr_code_url']);
    }

    public function test_verify_valid_otp_enables_mfa(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson(route('profile.mfa.generate'));
        $secret = $response->json('secret');

        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $validOtp = $google2fa->getCurrentOtp($secret);

        $this->postJson(route('profile.mfa.verify'), ['otp' => $validOtp])
            ->assertOk();

        $user->refresh();
        $this->assertNotNull($user->mfa_enabled_at);
    }

    public function test_verify_invalid_otp_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('profile.mfa.generate'));

        $this->postJson(route('profile.mfa.verify'), ['otp' => '000000'])
            ->assertStatus(422);

        $user->refresh();
        $this->assertNull($user->mfa_enabled_at);
    }

    public function test_disable_mfa_clears_fields(): void
    {
        $user = User::factory()->create([
            'mfa_secret' => 'test-secret',
            'mfa_recovery_codes' => ['code1', 'code2'],
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('profile.mfa.disable'), [
                'password' => 'P@ssw0rd!',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertNull($user->mfa_secret);
        $this->assertNull($user->mfa_recovery_codes);
        $this->assertNull($user->mfa_enabled_at);
    }

    public function test_recovery_codes_available_after_mfa_enabled(): void
    {
        $user = User::factory()->create([
            'mfa_secret' => 'test-secret',
            'mfa_recovery_codes' => ['code1', 'code2'],
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('profile.mfa.recovery-codes', ['password' => 'P@ssw0rd!']))
            ->assertOk()
            ->assertJsonStructure(['recovery_codes']);
    }

    public function test_recovery_codes_not_available_without_mfa(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('profile.mfa.recovery-codes'))
            ->assertStatus(403);
    }

    public function test_guest_cannot_access_mfa_endpoints(): void
    {
        $this->postJson(route('profile.mfa.generate'))->assertStatus(401);
        $this->postJson(route('profile.mfa.verify'), ['otp' => '000000'])->assertStatus(401);
        $this->postJson(route('profile.mfa.disable'))->assertStatus(401);
    }

    public function test_regenerate_recovery_codes(): void
    {
        $user = User::factory()->create([
            'mfa_secret' => 'test-secret',
            'mfa_recovery_codes' => ['old-code-1', 'old-code-2'],
            'mfa_enabled_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('profile.mfa.recovery-codes.regenerate'), ['password' => 'P@ssw0rd!'])
            ->assertOk();

        $this->assertNotEquals(['old-code-1', 'old-code-2'], $response->json('recovery_codes'));
        $this->assertCount(8, $response->json('recovery_codes'));
    }

    public function test_mfa_status_returns_correct_state(): void
    {
        $user = User::factory()->create();

        // MFA disabled
        $this->actingAs($user)
            ->getJson(route('profile.mfa.status'))
            ->assertOk()
            ->assertJson(['enabled' => false]);

        // MFA enabled
        $user->update([
            'mfa_secret' => 'test',
            'mfa_recovery_codes' => ['code1'],
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('profile.mfa.status'))
            ->assertOk()
            ->assertJson(['enabled' => true]);
    }
}
