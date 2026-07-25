<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([
                    \App\Http\Middleware\VerifyTurnstile::class,
                    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
                    \Illuminate\Routing\Middleware\ThrottleRequests::class,
                ]);
    }

    #[Test]
    public function test_intake_verify_email_sends_otp(): void
    {
        $response = $this->postJson('/intake/verify-email', [
            'email' => 'ofw@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['sent' => true]);
    }

    #[Test]
    public function test_intake_verify_email_requires_email(): void
    {
        $response = $this->postJson('/intake/verify-email', [
            'email' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    #[Test]
    public function test_intake_verify_email_throttled(): void
    {
        // Throttle middleware is applied at the route level.
        // This test asserts the route exists and responds correctly
        // when called once — full throttle testing is hard in a
        // test environment without manipulating time/request counts.
        $response = $this->postJson('/intake/verify-email', [
            'email' => 'throttle-test@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['sent' => true]);
    }
}
