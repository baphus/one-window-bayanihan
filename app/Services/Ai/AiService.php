<?php

namespace App\Services\Ai;

use App\Models\SystemSetting;
use App\Services\Ai\Contracts\ToolEnabledAiProvider;
use App\Services\Ai\Providers\AnthropicToolProvider;
use App\Services\Ai\Providers\GeminiToolProvider;
use App\Services\Ai\Providers\OpenAiToolProvider;

class AiService
{
    private ?AiProvider $provider = null;

    private function isToolEnabledProvider(): bool
    {
        $enabled = SystemSetting::getValue('chatbot_enabled', 'false');
        if (! $enabled) {
            return false;
        }

        $providerName = SystemSetting::getValue('chatbot_provider', 'openai');

        return in_array($providerName, ['openai', 'anthropic', 'gemini']);
    }

    public function getToolProvider(): ?ToolEnabledAiProvider
    {
        $enabled = SystemSetting::getValue('chatbot_enabled', 'false');
        if (! $enabled) {
            return null;
        }

        $providerName = SystemSetting::getValue('chatbot_provider', 'openai');
        $apiKey = SystemSetting::getValue('chatbot_api_key', '');
        $model = SystemSetting::getValue('chatbot_model', 'gpt-4o-mini');
        $systemPrompt = SystemSetting::getValue('chatbot_system_prompt', '');
        $temperature = (float) SystemSetting::getValue('chatbot_temperature', '0.7');
        $maxTokens = (int) SystemSetting::getValue('chatbot_max_tokens', '500');

        return match ($providerName) {
            'anthropic' => new AnthropicToolProvider($apiKey, $model, $systemPrompt, $temperature, $maxTokens),
            'gemini' => new GeminiToolProvider($apiKey, $model, $systemPrompt, $temperature, $maxTokens),
            default => new OpenAiToolProvider($apiKey, $model, $systemPrompt, $temperature, $maxTokens),
        };
    }

    public function getSendMessageProvider(): ?AiProvider
    {
        $enabled = SystemSetting::getValue('chatbot_enabled', 'false');
        if (! $enabled) {
            return null;
        }

        $providerName = SystemSetting::getValue('chatbot_provider', 'openai');
        $apiKey = SystemSetting::getValue('chatbot_api_key', '');
        $model = SystemSetting::getValue('chatbot_model', 'gpt-4o-mini');
        $systemPrompt = SystemSetting::getValue('chatbot_system_prompt', '');
        $temperature = (float) SystemSetting::getValue('chatbot_temperature', '0.7');
        $maxTokens = (int) SystemSetting::getValue('chatbot_max_tokens', '500');

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

        $enabled = SystemSetting::getValue('chatbot_enabled', 'false');
        if (! $enabled) {
            return null;
        }

        $providerName = SystemSetting::getValue('chatbot_provider', 'openai');
        $apiKey = SystemSetting::getValue('chatbot_api_key', '');
        $model = SystemSetting::getValue('chatbot_model', 'gpt-4o-mini');
        $systemPrompt = SystemSetting::getValue('chatbot_system_prompt', '');
        $temperature = (float) SystemSetting::getValue('chatbot_temperature', '0.7');
        $maxTokens = (int) SystemSetting::getValue('chatbot_max_tokens', '500');

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
            'system_prompt' => SystemSetting::getValue('chatbot_system_prompt', ''),
            'temperature' => (float) SystemSetting::getValue('chatbot_temperature', '0.7'),
            'max_tokens' => (int) SystemSetting::getValue('chatbot_max_tokens', '500'),
        ]);
    }
}
