<?php

namespace App\Services\Ai;

class AiService
{
    private ?AiProvider $provider = null;

    public function getSendMessageProvider(): ?AiProvider
    {
        $enabled = config('ai-chatbot.enabled', 'false');
        if (! $enabled) {
            return null;
        }

        $providerName = config('ai-chatbot.provider', 'openai');
        $apiKey = config('ai-chatbot.api_key', '');
        $model = config('ai-chatbot.model', 'gpt-4o-mini');
        $systemPrompt = config('ai-chatbot.system_prompt', '');
        $temperature = (float) config('ai-chatbot.temperature', '0.7');
        $maxTokens = (int) config('ai-chatbot.max_tokens', '500');

        return match ($providerName) {
            'anthropic' => new AnthropicProvider($apiKey, $model, $systemPrompt, $temperature, $maxTokens),
            'gemini' => new GeminiProvider($apiKey, $model, $systemPrompt, $temperature, $maxTokens),

            default => new OpenAiProvider($apiKey, $model, $systemPrompt, $temperature, $maxTokens),
        };
    }

    public function getProvider(): ?AiProvider
    {
        if ($this->provider) {
            return $this->provider;
        }

        $enabled = config('ai-chatbot.enabled', 'false');
        if (! $enabled) {
            return null;
        }

        $providerName = config('ai-chatbot.provider', 'openai');
        $apiKey = config('ai-chatbot.api_key', '');
        $model = config('ai-chatbot.model', 'gpt-4o-mini');
        $systemPrompt = config('ai-chatbot.system_prompt', '');
        $temperature = (float) config('ai-chatbot.temperature', '0.7');
        $maxTokens = (int) config('ai-chatbot.max_tokens', '500');

        $this->provider = match ($providerName) {
            'anthropic' => new AnthropicProvider($apiKey, $model, $systemPrompt, $temperature, $maxTokens),
            'gemini' => new GeminiProvider($apiKey, $model, $systemPrompt, $temperature, $maxTokens),

            default => new OpenAiProvider($apiKey, $model, $systemPrompt, $temperature, $maxTokens),
        };

        return $this->provider;
    }

    public function sendMessage(string $message): string
    {
        $provider = $this->getProvider();
        if (! $provider || ! $provider->isConfigured()) {
            return '';
        }

        return $provider->sendMessage($message, [
            'system_prompt' => config('ai-chatbot.system_prompt', ''),
            'temperature' => (float) config('ai-chatbot.temperature', '0.7'),
            'max_tokens' => (int) config('ai-chatbot.max_tokens', '500'),
        ]);
    }
}
