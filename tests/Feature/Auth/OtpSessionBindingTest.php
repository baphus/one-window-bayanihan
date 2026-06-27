<?php

namespace Tests\Feature\Auth;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class OtpSessionBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable debug OTP display so we can read OTP values from responses
        SystemSetting::setValue('debug_otp_enabled', true);
    }

    public function test_otp_can_be_verified_in_same_session(): void
    {
        $user = User::factory()->create();
        $sessionName = $this->app['session']->getName();

        // Initiate login — OTP is generated and bound to this session
        $initResponse = $this->withHeader('X-Inertia', 'true')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'P@ssw0rd!',
            ]);
        $initResponse->assertStatus(200);
        $otp = $initResponse->json('props.debug_otp');
        $this->assertNotNull($otp);

        $initSessionId = $this->app['session']->getId();

        // Assert the OTP was stored with a session-qualified cache key
        $this->assertTrue(
            Cache::has('otp:login:'.$user->email.':'.$initSessionId),
            'OTP must be stored with session-qualified key',
        );

        // Extract the encrypted session cookie from the response so we can
        // re-send it on the next request — this is what a real browser does.
        $encryptedCookie = collect($initResponse->headers->getCookies())
            ->first(fn (Cookie $c) => $c->getName() === $sessionName);
        $this->assertNotNull($encryptedCookie);

        // Verify OTP, sending the same session cookie to maintain session identity
        $this
            ->withUnencryptedCookie($sessionName, $encryptedCookie->getValue())
            ->withHeader('X-Inertia', 'true')
            ->post('/login/verify-otp', [
                'email' => $user->email,
                'otp' => $otp,
            ]);

        // User should now be authenticated (OTP verified in same session)
        $this->assertAuthenticatedAs($user);
    }

    public function test_otp_from_one_session_cannot_be_verified_from_another(): void
    {
        $user = User::factory()->create();

        // Initiate login in session A
        $initResponse = $this->withHeader('X-Inertia', 'true')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'P@ssw0rd!',
            ]);
        $initResponse->assertStatus(200);

        $otp = $initResponse->json('props.debug_otp');
        $this->assertNotNull($otp);

        // Verify OTP WITHOUT the session cookie — simulates a different browser
        // (StartSession generates a new session ID when no cookie is present)
        $this
            ->withHeader('X-Inertia', 'true')
            ->post('/login/verify-otp', [
                'email' => $user->email,
                'otp' => $otp,
            ]);

        // User should NOT be authenticated because the verification session
        // differs from the generation session
        $this->assertGuest();
    }

    public function test_otp_service_rejects_verify_from_different_session(): void
    {
        $email = 'test-otp-session@example.com';

        // Generate OTP and capture it — bound to session-A
        $otp = $this->app->make(OtpService::class)
            ->generate($email, 'login', 'session-A');

        // Verify with session-B — must be rejected
        $this->assertFalse(
            $this->app->make(OtpService::class)
                ->verify($email, 'login', $otp, 'session-B'),
        );

        // Verify with session-A — must succeed
        $this->assertTrue(
            $this->app->make(OtpService::class)
                ->verify($email, 'login', $otp, 'session-A'),
        );
    }
}
