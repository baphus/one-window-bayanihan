<?php

namespace Tests\Feature\Intake;

use App\Http\Middleware\VerifyTurnstile;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([
            VerifyTurnstile::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
        ]);
    }

    #[Test]
    public function test_verify_email_rejects_empty_email_with_422_shape(): void
    {
        $response = $this->postJson('/intake/verify-email', ['email' => '']);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['email']]);
        $response->assertJsonValidationErrors('email');
    }

    #[Test]
    public function test_verify_email_rejects_malformed_email_with_422_shape(): void
    {
        $response = $this->postJson('/intake/verify-email', ['email' => 'not-an-email']);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['email']]);
        $response->assertJsonValidationErrors('email');
    }

    #[Test]
    public function test_verify_email_accepts_valid_email_with_sent_shape(): void
    {
        $response = $this->postJson('/intake/verify-email', ['email' => 'ofw@example.com']);

        $response->assertOk();
        $response->assertJsonStructure(['sent', 'hint', 'debug_otp']);
        $response->assertJson(['sent' => true]);
        $this->assertStringContainsString('@', $response->json('hint'));
    }

    #[Test]
    public function test_check_duplicate_rejects_empty_payload_with_422_shape(): void
    {
        $response = $this->postJson('/intake/check-duplicate', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['email', 'otp']]);
        $response->assertJsonValidationErrors(['email', 'otp']);
    }

    #[Test]
    public function test_check_duplicate_rejects_short_otp_with_422_shape(): void
    {
        $response = $this->postJson('/intake/check-duplicate', [
            'email' => 'ofw@example.com',
            'otp' => '123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['otp']]);
        $response->assertJsonValidationErrors('otp');
    }

    #[Test]
    public function test_check_duplicate_accepts_valid_payload_with_verified_shape(): void
    {
        $email = 'fresh-ofw@example.com';
        Cache::put("otp:intake:{$email}", '123456', 300);

        $response = $this->postJson('/intake/check-duplicate', [
            'email' => $email,
            'otp' => '123456',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['verified', 'duplicate', 'message', 'existing_client']);
        $response->assertJson(['verified' => true, 'duplicate' => false]);
    }
}
