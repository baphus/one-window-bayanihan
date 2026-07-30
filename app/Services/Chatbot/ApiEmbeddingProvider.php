<?php

namespace App\Services\Chatbot;

use App\Services\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Environment-configurable embedding provider for hosted/cloud APIs.
 *
 * Speaks the OpenAI-compatible `/v1/embeddings` format, which is supported
 * by OpenAI, Together AI, Mistral, and most other hosted embedding providers.
 *
 * In production, point this at your chosen embedding API by setting just
 * three environment variables — no code changes needed:
 *
 *   AI_CHATBOT_EMBEDDING_URL=https://api.openai.com/v1/embeddings
 *   AI_CHATBOT_EMBEDDING_API_KEY=sk-...
 *   AI_CHATBOT_EMBEDDING_MODEL=text-embedding-3-small
 */
class ApiEmbeddingProvider implements EmbeddingProvider
{
    /**
     * Embed a single text string via the configured API endpoint.
     *
     * @return list<float> Embedding vector (dimensions determined by model).
     */
    public function embed(string $text): array
    {
        $response = $this->postEmbed([$text]);
        $data = $response['data'] ?? [];

        if ($data === []) {
            throw new RuntimeException('Embedding API returned no data.');
        }

        return $data[0]['embedding'] ?? throw new RuntimeException(
            'Embedding API response is missing the "embedding" field.',
        );
    }

    /**
     * Embed multiple texts in a single API call.
     *
     * @param  list<string>  $texts
     * @return list<list<float>> Embedding vectors, one per input text.
     */
    public function embedBatch(array $texts): array
    {
        $response = $this->postEmbed($texts);
        $data = $response['data'] ?? [];

        if (count($data) !== count($texts)) {
            throw new RuntimeException(sprintf(
                'Embedding API returned %d embeddings for %d inputs.',
                count($data),
                count($texts),
            ));
        }

        // Sort by index to preserve input order (the API may return them out of order).
        usort($data, fn (array $a, array $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(
            fn (array $item) => $item['embedding']
                ?? throw new RuntimeException('Embedding API response is missing the "embedding" field.'),
            $data,
        );
    }

    /**
     * Check whether the embedding API endpoint is reachable.
     *
     * Sends a lightweight model-list request rather than a costly embed call.
     */
    public function isAvailable(): bool
    {
        try {
            $url = $this->baseUrl();

            // Most OpenAI-compatible APIs expose a /v1/models endpoint for health checks.
            $modelsUrl = preg_replace('#/embeddings$#', '/models', $url) ?: $url;
            $response = Http::timeout(5)
                ->withHeaders($this->headers())
                ->get($modelsUrl);

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    // ──────────────────────────────────────────────
    //  API call
    // ──────────────────────────────────────────────

    /**
     * POST to the configured embedding API endpoint.
     *
     * Payload follows the OpenAI /v1/embeddings request format:
     *   { "input": [...], "model": "...", "dimensions": ... }
     *
     * @param  list<string>  $inputs
     * @return array{data: list<array{index: int, embedding: list<float>}>}
     */
    private function postEmbed(array $inputs): array
    {
        $url = $this->baseUrl();
        $model = $this->model();
        $dimensions = $this->dimensions();

        $payload = [
            'input' => $inputs,
            'model' => $model,
        ];

        // Only send dimensions when explicitly configured (several providers
        // reject the field if they don't support dynamic dimensions).
        if ($dimensions !== null) {
            $payload['dimensions'] = $dimensions;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders($this->headers())
                ->post($url, $payload);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Embedding API request failed (HTTP %d): %s',
                    $response->status(),
                    $response->body(),
                ));
            }

            return $response->json();
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Embedding API request failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    // ──────────────────────────────────────────────
    //  Config helpers
    // ──────────────────────────────────────────────

    /**
     * The full URL to the embeddings endpoint.
     * Defaults to OpenAI's endpoint for convenience.
     */
    private function baseUrl(): string
    {
        return rtrim(
            config('ai-chatbot.embedding.url', 'https://api.openai.com/v1/embeddings'),
            '/',
        );
    }

    /**
     * The model identifier accepted by the API provider.
     */
    private function model(): string
    {
        return config('ai-chatbot.embedding.model', 'text-embedding-3-small');
    }

    /**
     * Dynamic dimensions, or null if the provider doesn't support it.
     */
    private function dimensions(): ?int
    {
        $dimensions = config('ai-chatbot.embedding.dimensions');

        return $dimensions !== null && $dimensions !== 0 ? (int) $dimensions : null;
    }

    /**
     * Authorization header for the embedding API.
     */
    private function apiKey(): ?string
    {
        $key = config('ai-chatbot.embedding.api_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * HTTP headers for the embedding API request.
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $apiKey = $this->apiKey();
        if ($apiKey !== null) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        return $headers;
    }
}
