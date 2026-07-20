<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
