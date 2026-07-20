<?php

namespace Tests\Feature\Security;

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
}
