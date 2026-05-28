<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProvider
{
    protected string $apiKey;

    protected string $model;

    protected string $systemPrompt;

    protected float $temperature;

    protected int $maxTokens;

    public function __construct(
        string $apiKey = '',
        string $model = 'gemini-2.0-flash',
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
            $contents = [];

            if ($this->systemPrompt !== '') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => $this->systemPrompt]],
                ];
                $contents[] = [
                    'role' => 'model',
                    'parts' => [['text' => 'Understood. I will follow those instructions.']],
                ];
            }

            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $message]],
            ];

            $payload = [
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $this->temperature,
                    'maxOutputTokens' => $this->maxTokens,
                ],
            ];

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::withHeaders([
                'content-type' => 'application/json',
            ])->post($url, $payload);

            if ($response->failed()) {
                Log::warning('Gemini API returned error', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return '';
            }

            $body = $response->json();
            $candidate = $body['candidates'][0] ?? [];
            $parts = $candidate['content']['parts'] ?? [];

            $text = '';
            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }

            return $text;
        } catch (\Throwable $e) {
            Log::warning('Gemini API call failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            return '';
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
