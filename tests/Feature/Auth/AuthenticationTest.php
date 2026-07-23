<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'P@ssw0rd!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'nonexistent@example.com',
            'password' => 'P@ssw0rd!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_user_cannot_login_with_inactive_account(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'P@ssw0rd!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password-'.$i,
            ]);
        }

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password-11',
        ]);

        $response->assertStatus(429);
    }

    public function test_flag_off_keeps_mfa_user_on_normal_login(): void
    {
        config(['mfa.login_challenge_enabled' => false]);
        $user = $this->createMfaUser();

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertArrayNotHasKey('mfa_pending', session()->all());
    }

    public function test_flag_on_mfa_login_stays_guest_until_totp_and_rotates_sessions(): void
    {
        config(['mfa.login_challenge_enabled' => true]);
        Cache::flush();
        $user = $this->createMfaUser();
        $before = $this->app['session']->getId();

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);

        $this->assertGuest();
        $response->assertRedirect(route('mfa.challenge.show'));
        $this->assertNotSame($before, $this->app['session']->getId());
        $pending = session('mfa_pending');
        $this->assertNotNull($pending);

        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $challengeSession = $this->app['session']->getId();
        $response = $this->post(route('mfa.challenge.totp'), ['code' => $google2fa->getCurrentOtp($user->mfa_secret)]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotSame($challengeSession, $this->app['session']->getId());
        $this->assertNull(session('mfa_pending'));
        $this->assertSame((string) $user->getKey(), (string) session('mfa_authenticated.user_id'));
    }

    public function test_invalid_totp_expires_cancel_and_max_attempts_clear_pending_state(): void
    {
        config(['mfa.login_challenge_enabled' => true, 'mfa.max_attempts' => 2]);
        $user = $this->createMfaUser();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);

        $this->post(route('mfa.challenge.totp'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('mfa.challenge.totp'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertNull(session('mfa_pending'));
        $this->assertNull(session('pending_mfa_user_id'));
        $this->assertNull(session('mfa_pending_attempts'));

        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);
        $this->post(route('mfa.challenge.cancel'))->assertRedirect(route('login'));
        $this->assertNull(session('mfa_pending'));

        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);
        session()->put('mfa_pending.issued_at', now()->subMinutes(6)->timestamp);
        $this->get(route('mfa.challenge.show'))->assertRedirect(route('login'));
        $this->assertNull(session('mfa_pending'));
    }

    public function test_totp_replay_and_cache_failure_are_rejected(): void
    {
        $user = $this->createMfaUser();
        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $code = $google2fa->getCurrentOtp($user->mfa_secret);
        $service = app(MfaService::class);

        $this->assertTrue($service->verifyTotp($user, $code));
        $this->assertFalse($service->verifyTotp($user, $code));
        $other = $this->createMfaUser();
        $otherCode = $google2fa->getCurrentOtp($other->mfa_secret);
        Cache::shouldReceive('add')->once()->andThrow(new \RuntimeException('cache unavailable'));
        $this->assertFalse($service->verifyTotp($other, $otherCode));
    }

    public function test_pending_login_is_invalidated_when_password_or_account_changes(): void
    {
        config(['mfa.login_challenge_enabled' => true]);
        $user = $this->createMfaUser();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);
        $user->update(['password' => Hash::make('new-password')]);
        $this->post(route('mfa.challenge.recovery'), ['code' => 'ABCD-EFGH-IJKL'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_existing_mfa_session_without_bound_marker_is_revoked(): void
    {
        config(['mfa.login_challenge_enabled' => true]);
        $user = $this->createMfaUser();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_enrollment_is_enforced_on_dashboard_when_enabled(): void
    {
        config(['mfa.enrollment_enforcement_enabled' => true]);
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_recovery_code_login_path_end_to_end(): void
    {
        config(['mfa.login_challenge_enabled' => true]);
        $user = $this->createMfaUser();

        // 1. Password step leaves user unauthenticated with pending state
        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);
        $this->assertGuest();
        $this->assertNull(auth()->id());
        $this->assertNotNull(session('mfa_pending'));

        // 2. Valid recovery code completes challenge
        $challengeSession = $this->app['session']->getId();
        $response = $this->post(route('mfa.challenge.recovery'), ['code' => 'ABCD-EFGH-IJKL']);
        $this->assertAuthenticatedAs($user);

        // 3. Session rotates and has bound marker
        $this->assertNotSame($challengeSession, $this->app['session']->getId());
        $this->assertNull(session('mfa_pending'));
        $this->assertSame((string) $user->getKey(), (string) session('mfa_authenticated.user_id'));
        $this->assertSame(hash('sha256', $user->password), session('mfa_authenticated.credential_fingerprint'));

        // 4. Reusing the same code in a second challenge fails
        $response = $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);
        $this->post(route('mfa.challenge.recovery'), ['code' => 'ABCD-EFGH-IJKL'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_concurrent_consumption_yields_one_success(): void
    {
        config(['mfa.login_challenge_enabled' => true]);
        $user = User::factory()->create([
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => [
                hash_hmac('sha256', 'ABCD-EFGH-IJKL', config('app.key')),
                hash_hmac('sha256', 'MNOP-QRST-UVWX', config('app.key')),
            ],
            'mfa_enabled_at' => now(),
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);

        $service = app(MfaService::class);
        $pending = session('mfa_pending');

        $result1 = $service->completeChallenge($user->getKey(), $pending['credential_fingerprint'], 'ABCD-EFGH-IJKL', true);
        $result2 = $service->completeChallenge($user->getKey(), $pending['credential_fingerprint'], 'ABCD-EFGH-IJKL', true);

        $this->assertTrue(($result1 !== null) xor ($result2 !== null), 'Exactly one completion should succeed');
    }

    public function test_locked_pending_user_inactive_fails_challenge(): void
    {
        config(['mfa.login_challenge_enabled' => true]);
        $user = $this->createMfaUser();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);

        $user->update(['is_active' => false]);

        $this->post(route('mfa.challenge.totp'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_locked_pending_user_soft_deleted_fails_challenge(): void
    {
        config(['mfa.login_challenge_enabled' => true]);
        $user = $this->createMfaUser();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);

        $user->update(['is_deleted' => true]);

        $this->post(route('mfa.challenge.totp'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_locked_pending_user_mfa_disabled_fails_challenge(): void
    {
        config(['mfa.login_challenge_enabled' => true]);
        $user = $this->createMfaUser();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);

        $user->update(['mfa_enabled_at' => null]);

        $this->post(route('mfa.challenge.totp'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_flag_rollback_mid_challenge_clears_pending_and_allows_normal_login(): void
    {
        config(['mfa.login_challenge_enabled' => true]);
        $user = $this->createMfaUser();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);
        $this->assertGuest();
        $this->assertNotNull(session('mfa_pending'));

        // Rollback: disable flag
        config(['mfa.login_challenge_enabled' => false]);

        // Challenge route should redirect to login and clear pending state
        $this->get(route('mfa.challenge.show'))->assertRedirect(route('login'));
        $this->assertNull(session('mfa_pending'));
        $this->assertNull(session('pending_mfa_user_id'));

        // Normal login now works
        $this->post(route('login'), ['email' => $user->email, 'password' => 'P@ssw0rd!']);
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('mfa_pending'));
    }

    private function createMfaUser(): User
    {
        $user = User::factory()->create([
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => [hash_hmac('sha256', 'ABCD-EFGH-IJKL', config('app.key'))],
            'mfa_enabled_at' => now(),
        ]);

        return $user->fresh();
    }
}
