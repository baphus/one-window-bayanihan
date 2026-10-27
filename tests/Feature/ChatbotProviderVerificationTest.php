<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotProviderVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai-chatbot.enabled' => true,
            'ai-chatbot.provider' => 'gemini',
            'ai-chatbot.model' => 'gemini-3.5-flash-lite',
            'ai.providers.gemini.key' => 'test-key',
            'ai.providers.gemini.url' => 'https://generativelanguage.googleapis.com/v1beta/',
        ]);
    }

    /**
     * Fake the models listing followed by a generateContent probe.
     */
    private function fakeProvider(int $probeStatus, array $probeBody): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*models?pageSize=*' => Http::response(['models' => [
                ['name' => 'models/gemini-3.5-flash-lite', 'supportedGenerationMethods' => ['generateContent']],
            ]]),
            '*:generateContent' => Http::response($probeBody, $probeStatus),
        ]);
    }

    public function test_passes_when_key_model_and_probe_succeed(): void
    {
        $this->fakeProvider(200, [
            'candidates' => [['content' => ['parts' => [['text' => 'OK']]], 'finishReason' => 'STOP']],
        ]);

        $this->artisan('chatbot:verify-provider')
            ->expectsOutputToContain('Probe OK')
            ->assertExitCode(0);
    }

    public function test_disabled_chatbot_is_not_a_failure(): void
    {
        config(['ai-chatbot.enabled' => false]);
        Http::preventStrayRequests();

        $this->artisan('chatbot:verify-provider')
            ->expectsOutputToContain('Chatbot is disabled')
            ->assertExitCode(0);
    }

    public function test_missing_api_key_fails_with_actionable_message(): void
    {
        config(['ai.providers.gemini.key' => null]);
        Http::preventStrayRequests();

        $this->artisan('chatbot:verify-provider')
            ->expectsOutputToContain('GEMINI_API_KEY')
            ->assertExitCode(1);
    }

    /**
     * The models.list endpoint advertises retired models, so a probe is required.
     * This is the regression that made --no-live report a false pass.
     */
    public function test_retired_model_fails_even_in_offline_mode(): void
    {
        $this->fakeProvider(404, ['error' => ['message' => 'This model is no longer available to new users.']]);

        $this->artisan('chatbot:verify-provider', ['--no-live' => true])
            ->expectsOutputToContain('no longer available')
            ->assertExitCode(1);
    }

    public function test_reasoning_model_that_exhausts_budget_is_reported(): void
    {
        $this->fakeProvider(200, [
            'candidates' => [['content' => ['parts' => []], 'finishReason' => 'MAX_TOKENS']],
        ]);

        $this->artisan('chatbot:verify-provider')
            ->expectsOutputToContain('MAX_TOKENS')
            ->assertExitCode(1);
    }

    public function test_unknown_provider_fails(): void
    {
        config(['ai-chatbot.provider' => 'not-a-provider']);
        Http::preventStrayRequests();

        $this->artisan('chatbot:verify-provider')
            ->expectsOutputToContain('not defined in config/ai.php')
            ->assertExitCode(1);
    }
}
