<?php

namespace App\Services\Ai;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class AiService
{
    private ?AiProvider $provider = null;

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
            'custom' => new CustomProvider(
                SystemSetting::getValue('chatbot_custom_endpoint', ''),
                $apiKey,
                $model,
                $systemPrompt,
            ),
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

        try {
            return $provider->sendMessage($message, [
                'system_prompt' => SystemSetting::getValue('chatbot_system_prompt', ''),
                'temperature' => (float) SystemSetting::getValue('chatbot_temperature', '0.7'),
                'max_tokens' => (int) SystemSetting::getValue('chatbot_max_tokens', '500'),
            ]);
        } catch (\Exception $e) {
            Log::warning('Chatbot AI service failed', [
                'error' => $e->getMessage(),
                'provider' => SystemSetting::getValue('chatbot_provider', 'unknown'),
            ]);

            return '';
        }
    }
}
