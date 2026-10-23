<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordComplexityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_lowercase_only_password(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_registration_accepts_complex_password(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test2@example.com',
            'password' => 'P@ssw0rd!',
            'password_confirmation' => 'P@ssw0rd!',
        ]);

        $response->assertSessionDoesntHaveErrors('password');
    }

    public function test_registration_rejects_password_without_uppercase(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test3@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
