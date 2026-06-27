<?php

namespace Tests\Feature\Auth;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();
        $sessionName = $this->app['session']->getName();

        SystemSetting::setValue('debug_otp_enabled', true);

        $initResponse = $this
            ->withHeader('X-Inertia', 'true')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'P@ssw0rd!',
            ]);

        $otp = $initResponse->json('props.debug_otp');
        $this->assertNotNull($otp, 'OTP must be present in response');

        // Persist session cookie to maintain session identity
        $encryptedCookie = collect($initResponse->headers->getCookies())
            ->first(fn (Cookie $c) => $c->getName() === $sessionName);
        $this->assertNotNull($encryptedCookie);

        $response = $this
            ->withUnencryptedCookie($sessionName, $encryptedCookie->getValue())
            ->withHeader('X-Inertia', 'true')
            ->post(route('login.verify-otp'), [
                'email' => $user->email,
                'otp' => $otp,
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
