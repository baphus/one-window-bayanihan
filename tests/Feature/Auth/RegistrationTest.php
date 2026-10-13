<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_screen_is_available(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
    }

    public function test_public_registration_submits_credentials_and_redirects_to_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'P@ssw0rd!',
            'password_confirmation' => 'P@ssw0rd!',
        ]);

        $response->assertRedirect('/register/profile');
        $response->assertSessionHas('pending_registration');

        // Step 1 does not create a user row yet — profile step does.
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
