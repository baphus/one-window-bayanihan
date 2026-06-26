<?php

namespace App\Services\Ai;

use OpenAI;

class OpenAiProvider implements AiProvider
{
    protected string $apiKey;

    protected string $model;

    protected string $systemPrompt;

    protected float $temperature;

    protected int $maxTokens;

    public function __construct(
        string $apiKey = '',
        string $model = 'gpt-4o-mini',
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
            $messages = [];

            if ($this->systemPrompt !== '') {
                $messages[] = [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ];
            }

            $messages[] = [
                'role' => 'user',
                'content' => $message,
            ];

            $client = OpenAI::client($this->apiKey);

            $response = $client->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            $content = $response->choices[0]->message->content ?? '';

            return $content;
        } catch (\Throwable $e) {
            throw new \RuntimeException('OpenAI API call failed: '.$e->getMessage(), previous: $e);
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
