<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_screen_is_not_available(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_public_registration_submission_is_not_available_and_creates_no_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'P@ssw0rd!',
            'password_confirmation' => 'P@ssw0rd!',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }
}
