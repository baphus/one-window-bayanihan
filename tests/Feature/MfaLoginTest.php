<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        SystemSetting::setValue('debug_otp_enabled', true);
    }

    /**
     * Make an init request and return [otp, sessionCookieName, sessionCookieValue].
     *
     * Uses X-Inertia so we can read debug_otp from the JSON response.
     * Also extracts the encrypted session cookie to maintain session identity
     * across subsequent requests (OTP is now bound to the session).
     */
    private function initLogin(User $user): array
    {
        $response = $this->withHeader('X-Inertia', 'true')
            ->post(route('login.init'), [
                'email' => $user->email,
                'password' => 'P@ssw0rd!',
            ]);

        $otp = $response->json('props.debug_otp');
        $this->assertNotNull($otp);

        $sessionName = $this->app['session']->getName();
        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $sessionName);
        $this->assertNotNull($sessionCookie);

        return [
            'otp' => $otp,
            'cookieName' => $sessionCookie->getName(),
            'cookieValue' => $sessionCookie->getValue(),
        ];
    }

    private function initMfaLogin(User $user): array
    {
        $response = $this->withHeader('X-Inertia', 'true')
            ->post(route('login.init'), [
                'email' => $user->email,
                'password' => 'P@ssw0rd!',
            ]);

        $response->assertJson([
            'component' => 'Auth/Login',
            'props' => [
                'step' => 'mfa-challenge',
                'email' => $user->email,
            ],
        ]);

        $sessionName = $this->app['session']->getName();
        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $sessionName);
        $this->assertNotNull($sessionCookie);

        return [
            'cookieName' => $sessionCookie->getName(),
            'cookieValue' => $sessionCookie->getValue(),
        ];
    }

    private function withSessionCookie(string $name, string $value): static
    {
        return $this->withUnencryptedCookie($name, $value);
    }

    /**
     * Hash recovery codes so they match what MfaService expects on verification.
     */
    private function hashedCodes(array $plainCodes): array
    {
        $key = config('app.key');

        return array_map(fn ($c) => hash_hmac('sha256', $c, $key), $plainCodes);
    }

    public function test_user_without_mfa_skips_challenge_and_logs_in(): void
    {
        $user = User::factory()->create();

        $login = $this->initLogin($user);

        // Verify without X-Inertia so the redirect (302) is preserved for assertRedirect
        $response = $this
            ->withSessionCookie($login['cookieName'], $login['cookieValue'])
            ->post(route('login.verify-otp'), [
                'email' => $user->email,
                'otp' => $login['otp'],
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_mfa_can_verify_with_totp(): void
    {
        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $this->hashedCodes(['ABCD-EFGH-IJKL', 'MNOP-QRST-UVWX']),
            'mfa_enabled_at' => now(),
        ]);

        $login = $this->initMfaLogin($user);

        $this->assertGuest();

        $validTotp = $google2fa->getCurrentOtp($secret);

        // verify-totp returns redirect — no X-Inertia to keep 302 for assertRedirect
        $response = $this
            ->withSessionCookie($login['cookieName'], $login['cookieValue'])
            ->post(route('login.verify-totp'), [
                'email' => $user->email,
                'otp' => $validTotp,
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_mfa_can_verify_with_recovery_code(): void
    {
        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $this->hashedCodes(['ABCD-EFGH-IJKL', 'MNOP-QRST-UVWX']),
            'mfa_enabled_at' => now(),
        ]);

        $login = $this->initMfaLogin($user);

        // verify-recovery-code returns redirect — no X-Inertia for assertRedirect
        $response = $this
            ->withSessionCookie($login['cookieName'], $login['cookieValue'])
            ->post(route('login.verify-recovery-code'), [
                'email' => $user->email,
                'recovery_code' => 'ABCD-EFGH-IJKL',
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_totp_rejected(): void
    {
        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $this->hashedCodes(['ABCD-EFGH-IJKL', 'MNOP-QRST-UVWX']),
            'mfa_enabled_at' => now(),
        ]);

        $login = $this->initMfaLogin($user);

        $response = $this
            ->withSessionCookie($login['cookieName'], $login['cookieValue'])
            ->post(route('login.verify-totp'), [
                'email' => $user->email,
                'otp' => '000000',
            ]);

        $response->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_invalid_recovery_code_rejected(): void
    {
        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $this->hashedCodes(['ABCD-EFGH-IJKL', 'MNOP-QRST-UVWX']),
            'mfa_enabled_at' => now(),
        ]);

        $login = $this->initMfaLogin($user);

        $response = $this
            ->withSessionCookie($login['cookieName'], $login['cookieValue'])
            ->post(route('login.verify-recovery-code'), [
                'email' => $user->email,
                'recovery_code' => 'INVALID-CODE-XXXX',
            ]);

        $response->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_recovery_code_consumed_after_use(): void
    {
        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $this->hashedCodes(['ABCD-EFGH-IJKL', 'MNOP-QRST-UVWX']),
            'mfa_enabled_at' => now(),
        ]);

        $login = $this->initMfaLogin($user);

        $this
            ->withSessionCookie($login['cookieName'], $login['cookieValue'])
            ->post(route('login.verify-recovery-code'), [
                'email' => $user->email,
                'recovery_code' => 'ABCD-EFGH-IJKL',
            ]);

        $this->assertAuthenticatedAs($user);

        $user->refresh();

        $this->assertCount(1, $user->mfa_recovery_codes);
        $this->assertNotContains($this->hashedCodes(['ABCD-EFGH-IJKL'])[0], $user->mfa_recovery_codes);
        $this->assertContains($this->hashedCodes(['MNOP-QRST-UVWX'])[0], $user->mfa_recovery_codes);
    }

    public function test_used_recovery_code_cannot_be_reused(): void
    {
        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $this->hashedCodes(['ONLY-CODE-XXXX']),
            'mfa_enabled_at' => now(),
        ]);

        // === First login — consume the only recovery code ===
        $login = $this->initMfaLogin($user);

        $this
            ->withSessionCookie($login['cookieName'], $login['cookieValue'])
            ->post(route('login.verify-recovery-code'), [
                'email' => $user->email,
                'recovery_code' => 'ONLY-CODE-XXXX',
            ]);

        $this->assertAuthenticatedAs($user);

        // Logout
        $this->post(route('logout'));

        $this->assertGuest();

        $user->refresh();
        $this->assertCount(0, $user->mfa_recovery_codes);

        // === Second login — try same code, should fail ===
        $login2 = $this->initMfaLogin($user);

        $response = $this
            ->withSessionCookie($login2['cookieName'], $login2['cookieValue'])
            ->post(route('login.verify-recovery-code'), [
                'email' => $user->email,
                'recovery_code' => 'ONLY-CODE-XXXX',
            ]);

        $response->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_expired_session_returns_error(): void
    {
        $user = User::factory()->create([
            'mfa_secret' => 'some-secret',
            'mfa_recovery_codes' => ['ABCD-EFGH-IJKL'],
            'mfa_enabled_at' => now(),
        ]);

        $response = $this->post(route('login.verify-totp'), [
            'email' => $user->email,
            'otp' => '123456',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
