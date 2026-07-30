<?php

namespace App\Services\Chatbot;

use App\Services\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Embedding provider that uses Ollama's HTTP API for local development.
 *
 * Calls the `/api/embed` endpoint with the model configured in
 * `ai-chatbot.embedding.model` (default: nomic-embed-text, 768 dimensions).
 *
 * This provider is suitable for local development where Ollama is running
 * on localhost. For production, swap to ApiEmbeddingProvider (or a custom
 * provider) by changing the `AI_CHATBOT_EMBEDDING_PROVIDER` env var.
 */
class OllamaEmbeddingProvider implements EmbeddingProvider
{
    /**
     * Embed a single text string via Ollama's /api/embed endpoint.
     *
     * @return list<float> 768-dimensional embedding vector
     */
    public function embed(string $text): array
    {
        $response = $this->postEmbed([$text]);
        $embeddings = $response['embeddings'] ?? [];

        if ($embeddings === []) {
            throw new RuntimeException('Ollama returned no embeddings for the given input.');
        }

        return $embeddings[0];
    }

    /**
     * Embed multiple texts in a single Ollama API call.
     *
     * @param  list<string>  $texts
     * @return list<list<float>> Embedding vectors, one per input text
     */
    public function embedBatch(array $texts): array
    {
        $response = $this->postEmbed($texts);
        $embeddings = $response['embeddings'] ?? [];

        if ($embeddings === [] || count($embeddings) !== count($texts)) {
            throw new RuntimeException(sprintf(
                'Ollama returned %d embeddings for %d inputs.',
                count($embeddings),
                count($texts),
            ));
        }

        return $embeddings;
    }

    /**
     * Check whether the Ollama embedding endpoint is reachable and the
     * configured model is available.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl().'/api/tags');

            if (! $response->successful()) {
                return false;
            }

            $models = $response->json('models', []);
            $model = $this->model();

            foreach ($models as $m) {
                if (str_starts_with($m['name'] ?? '', $model)) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    // ──────────────────────────────────────────────
    //  Ollama-specific
    // ──────────────────────────────────────────────

    /**
     * POST to Ollama's /api/embed endpoint.
     *
     * @param  list<string>  $inputs
     * @return array{embeddings: list<list<float>>}
     */
    private function postEmbed(array $inputs): array
    {
        $url = $this->baseUrl();
        $model = $this->model();

        try {
            $response = Http::timeout(30)->post("{$url}/api/embed", [
                'model' => $model,
                'input' => count($inputs) === 1 ? $inputs[0] : $inputs,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException(sprintf(
                    'Ollama embedding request failed (HTTP %d): %s',
                    $response->status(),
                    $response->body(),
                ));
            }

            return $response->json();
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Ollama embedding request failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    private function baseUrl(): string
    {
        return config('ai-chatbot.embedding.url', 'http://localhost:11434');
    }

    private function model(): string
    {
        return config('ai-chatbot.embedding.model', 'nomic-embed-text');
    }
}
