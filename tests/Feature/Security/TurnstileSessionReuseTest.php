<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnstileSessionReuseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chatbot_reuses_verification_for_configured_window_but_other_routes_do_not(): void
    {
        config([
            'turnstile.enabled' => true,
            'turnstile.secret_key' => 'test-secret',
            'turnstile.session_ttl' => 1800,
        ]);
        Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);

        $first = $this->postJson(route('chatbot.message'), ['message' => 'first', 'cf_turnstile_response' => 'token'])->assertOk();
        $verifiedUntil = $first->json('turnstile.verified_until');
        $this->assertIsInt($verifiedUntil);
        $this->assertGreaterThan(time(), $verifiedUntil);

        $this->postJson(route('chatbot.message'), ['message' => 'second'])
            ->assertOk()
            ->assertJsonPath('turnstile.verified_until', $verifiedUntil);

        Http::assertSentCount(1);

        $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('captcha');
        Http::assertSentCount(1);
    }

    #[Test]
    public function zero_ttl_keeps_chatbot_per_message_verification(): void
    {
        config([
            'turnstile.enabled' => true,
            'turnstile.secret_key' => 'test-secret',
            'turnstile.session_ttl' => 0,
        ]);
        Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200)]);

        $this->postJson(route('chatbot.message'), ['message' => 'first', 'cf_turnstile_response' => 'token'])->assertOk();
        $this->postJson(route('chatbot.message'), ['message' => 'second'])->assertStatus(422);

        Http::assertSentCount(1);
    }
}
