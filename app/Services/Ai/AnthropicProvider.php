<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

class AnthropicProvider implements AiProvider
{
    protected string $apiKey;

    protected string $model;

    protected string $systemPrompt;

    protected float $temperature;

    protected int $maxTokens;

    public function __construct(
        string $apiKey = '',
        string $model = 'claude-3-haiku-20240307',
        string $systemPrompt = '',
        float $temperature = 0.7,
        int $maxTokens = 500
    ) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->systemPrompt = $systemPrompt;
        $this->temperature = $temperature;
        $this->maxTokens = $maxTokens;
    }

    public function sendMessage(string $message, array $context = []): string
    {
        try {
            $payload = [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'messages' => [
                    ['role' => 'user', 'content' => $message],
                ],
            ];

            if ($this->systemPrompt !== '') {
                $payload['system'] = $this->systemPrompt;
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', $payload);

            $body = $response->json();

            if ($response->failed() || empty($body)) {
                throw new \RuntimeException('Anthropic API returned error: status '.$response->status());
            }

            if (isset($body['content'][0]['text'])) {
                return $body['content'][0]['text'];
            }

            return $body['content'][0] ?? '';
        } catch (\Throwable $e) {
            throw new \RuntimeException('Anthropic API call failed: '.$e->getMessage(), previous: $e);
        }
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }
}
