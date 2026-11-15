<?php

namespace Tests\Feature;

use App\Ai\Agents\HelpdeskAgent;
use App\Http\Middleware\CheckMfaEnrolled;
use App\Http\Middleware\CheckUserActive;
use App\Http\Middleware\EnsureMfaSession;
use App\Models\User;
use App\Services\Chatbot\ChatbotDailyQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['turnstile.enabled' => false, 'ai-chatbot.enabled' => true, 'cache.default' => 'database']);
        $this->withoutMiddleware([CheckUserActive::class, EnsureMfaSession::class, CheckMfaEnrolled::class]);
        HelpdeskAgent::fake([])->preventStrayPrompts();
    }

    public function test_five_sends_admitted_and_sixth_denied_with_quota_metadata(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->getJson(route('chatbot.quota'))->assertOk();
            $this->actingAs($user)->postJson(route('chatbot.message'), ['message' => 'hello'])
                ->assertOk()->assertJsonPath('quota.limit', 5)->assertJsonPath('quota.remaining', 5 - $i);
        }
        $this->actingAs($user)->postJson(route('chatbot.message'), ['message' => 'hello'])
            ->assertStatus(429)->assertJsonPath('status', 'quota_exceeded')->assertJsonPath('quota.remaining', 0)->assertHeader('Retry-After');
    }

    public function test_validation_is_free_and_provider_failure_consumes_admission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('chatbot.quota'))->assertOk();
        $this->actingAs($user)->postJson(route('chatbot.message'), ['message' => ''])->assertUnprocessable();
        $this->actingAs($user)->getJson(route('chatbot.quota'))->assertJsonPath('quota.remaining', 5);
        $this->actingAs($user)->postJson(route('chatbot.message'), ['message' => 'How do I track my case?'])
            ->assertOk()->assertJsonPath('quota.remaining', 4);
    }

    public function test_account_quota_follows_uuid_across_sessions_and_guest_identities_are_independent(): void
    {
        $quota = app(ChatbotDailyQuota::class);
        $user = User::factory()->create();
        $this->assertSame('string', $user->getKeyType());
        $this->assertSame(36, strlen($user->getKey()));
        $this->actingAs($user)->getJson(route('chatbot.quota'))->assertOk();
        $this->actingAs($user)->postJson(route('chatbot.message'), ['message' => 'hello'])->assertOk()->assertJsonPath('quota.remaining', 4);
        $this->flushSession();
        $this->actingAs($user)->getJson(route('chatbot.quota'))->assertJsonPath('quota.remaining', 4);
        $this->assertSame(4, $quota->quota($quota->identity($user->getKey(), null))['remaining']);
        $this->assertNotSame($quota->identity(null, str_repeat('a', 64)), $quota->identity(null, str_repeat('b', 64)));
    }

    public function test_guest_cookie_preserves_allowance_and_separates_browsers(): void
    {
        $this->disableCookieEncryption();
        $this->withCredentials();
        $first = $this->getJson(route('chatbot.quota'))->assertOk()->assertCookie('chatbot_guest');
        $guest = $first->getCookie('chatbot_guest', false)->getValue();
        $this->withCookie('chatbot_guest', $guest);
        foreach (range(1, 5) as $i) {
            $this->postJson(route('chatbot.message'), ['message' => 'hello'])
                ->assertOk()->assertJsonPath('quota.remaining', 5 - $i);
        }
        $this->getJson(route('chatbot.quota'))->assertJsonPath('quota.remaining', 0);
        $this->postJson(route('chatbot.message'), ['message' => 'hello'])->assertStatus(429);
        $this->withCookie('chatbot_guest', str_repeat('b', 64));
        $this->getJson(route('chatbot.quota'))->assertJsonPath('quota.remaining', 5);
    }

    public function test_quota_resets_at_manila_midnight(): void
    {
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 15)->setTime(23, 59, 0));
        $quota = app(ChatbotDailyQuota::class);
        $identity = $quota->identity(null, str_repeat('c', 64));
        $quota->admit($identity);
        $this->assertSame(4, $quota->quota($identity)['remaining']);
        $this->travel(2)->minutes();
        $this->assertSame(5, $quota->quota($identity)['remaining']);
        $this->travelBack();
    }

    public function test_admission_uses_configured_shared_lock_store(): void
    {
        $this->assertTrue(app(ChatbotDailyQuota::class)->admit('lock-check')['admitted']);
    }
}
