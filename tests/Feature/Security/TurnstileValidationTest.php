<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnstileValidationTest extends TestCase
{
    #[Test]
    public function it_allows_request_when_turnstile_disabled(): void
    {
        config(['turnstile.enabled' => false]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Middleware is disabled — captcha error must never appear
        $response->assertSessionDoesntHaveErrors('captcha');
    }

    #[Test]
    public function it_rejects_request_when_turnstile_enabled_and_token_missing(): void
    {
        config(['turnstile.enabled' => true]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('captcha');
    }

    #[Test]
    public function it_allows_request_when_turnstile_enabled_and_verification_succeeds(): void
    {
        config([
            'turnstile.enabled' => true,
            'turnstile.secret_key' => 'test-secret',
        ]);

        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'cf_turnstile_response' => 'fake-token',
        ]);

        // Verification passed — any downstream error (invalid credentials etc.)
        // is fine, but there must be NO captcha error.
        $response->assertSessionDoesntHaveErrors('captcha');
    }

    #[Test]
    public function it_tells_user_to_complete_the_check_when_token_missing(): void
    {
        config(['turnstile.enabled' => true]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['captcha' => 'Please complete the security check to continue.']);
    }

    #[Test]
    public function it_reports_expired_token_message_on_timeout_or_duplicate(): void
    {
        config([
            'turnstile.enabled' => true,
            'turnstile.secret_key' => 'test-secret',
        ]);

        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['timeout-or-duplicate'],
            ], 200),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'cf_turnstile_response' => 'expired-token',
        ]);

        $response->assertSessionHasErrors(['captcha' => 'Your security check expired. Please complete it again.']);
    }

    #[Test]
    public function it_reports_generic_message_on_invalid_token(): void
    {
        config([
            'turnstile.enabled' => true,
            'turnstile.secret_key' => 'test-secret',
        ]);

        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'cf_turnstile_response' => 'invalid-token',
        ]);

        $response->assertSessionHasErrors(['captcha' => 'The security check could not be verified. Please try again.']);
    }

    #[Test]
    public function it_reports_unavailable_when_siteverify_connection_fails(): void
    {
        config([
            'turnstile.enabled' => true,
            'turnstile.secret_key' => 'test-secret',
        ]);

        Http::fake(fn () => throw new ConnectionException('connection refused'));

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'cf_turnstile_response' => 'fake-token',
        ]);

        $response->assertSessionHasErrors(['captcha' => 'The security check service is temporarily unavailable. Please try again in a moment.']);
    }

    #[Test]
    public function it_returns_turnstile_required_json_on_chatbot_when_token_missing(): void
    {
        config(['turnstile.enabled' => true]);

        $response = $this->post(route('chatbot.message'), [
            'message' => 'hi',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'turnstile_required']);
    }
}
