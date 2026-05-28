<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CustomProvider implements AiProvider
{
    protected string $endpoint;

    protected string $apiKey;

    protected string $model;

    protected string $systemPrompt;

    public function __construct(
        string $endpoint = '',
        string $apiKey = '',
        string $model = '',
        string $systemPrompt = ''
    ) {
        $this->endpoint = $endpoint;
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->systemPrompt = $systemPrompt;
    }

    public function sendMessage(string $message, array $context = []): string
    {
        try {
            $payload = [
                'message' => $message,
                'system_prompt' => $this->systemPrompt,
                'model' => $this->model,
            ];

            $request = Http::withHeaders([
                'content-type' => 'application/json',
            ]);

            if ($this->apiKey !== '') {
                $request = $request->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                ]);
            }

            $response = $request->post($this->endpoint, $payload);

            $body = $response->json();

            if ($response->failed() || empty($body)) {
                Log::warning('Custom AI API returned error', [
                    'status' => $response->status(),
                    'endpoint' => $this->endpoint,
                ]);

                return '';
            }

            if (isset($body['reply'])) {
                return $body['reply'];
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('Custom AI API call failed', [
                'error' => $e->getMessage(),
                'endpoint' => $this->endpoint,
            ]);

            return '';
        }
    }

    public function isConfigured(): bool
    {
        return ! empty($this->endpoint);
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
