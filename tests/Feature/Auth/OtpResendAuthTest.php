<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OtpResendAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_otp_with_correct_password_succeeds(): void
    {
        $user = User::factory()->create();

        // Initiate login to set login_step in session
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'P@ssw0rd!',
        ]);

        Log::shouldReceive('withContext')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'otp_resend_successful'
                && str_contains($context['email'] ?? '', substr($user->email, 0, 2)));

        $response = $this->post('/login/resend-otp', [
            'email' => $user->email,
            'password' => 'P@ssw0rd!',
        ]);

        $response->assertStatus(200);
    }

    public function test_resend_otp_with_wrong_password_fails(): void
    {
        $user = User::factory()->create();

        // Initiate login to set login_step in session
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'P@ssw0rd!',
        ]);

        $response = $this->postJson('/login/resend-otp', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_resend_otp_without_initiation_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login/resend-otp', [
            'email' => $user->email,
            'password' => 'P@ssw0rd!',
        ]);

        $response->assertStatus(403);
    }
}
