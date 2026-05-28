<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;
use OpenAI;

class EmbeddingService
{
    private string $apiKey;

    private string $model;

    private int $dimensions;

    private int $maxRetries = 3;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?int $dimensions = null
    ) {
        $this->apiKey = $apiKey ?? config('ai-helpcenter.embedding_api_key', config('ai-helpcenter.api_key', ''));
        $this->model = $model ?? config('ai-helpcenter.embedding_model', 'text-embedding-3-small');
        $this->dimensions = $dimensions ?? config('ai-helpcenter.embedding_dimensions', 1536);
    }

    /**
     * Generate an embedding vector for a single text string.
     *
     * @throws \RuntimeException if embedding fails after all retries.
     */
    public function embed(string $text): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                $client = OpenAI::client($this->apiKey);
                $response = $client->embeddings()->create([
                    'model' => $this->model,
                    'input' => $text,
                    'dimensions' => $this->dimensions,
                ]);

                $vector = $response->embeddings[0]->embedding;

                if (count($vector) !== $this->dimensions) {
                    throw new \RuntimeException(
                        "Expected {$this->dimensions} dimensions, got ".count($vector)
                    );
                }

                return $vector;

            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;

                Log::warning('Embedding API call failed', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'error' => $e->getMessage(),
                    'model' => $this->model,
                ]);

                if ($attempt >= $this->maxRetries) {
                    break;
                }

                // Exponential backoff: 1s, 2s, 4s
                sleep(pow(2, $attempt - 1));
            }
        }

        throw new \RuntimeException(
            'Embedding failed after '.$this->maxRetries.' attempts: '.$lastException?->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Generate embeddings for multiple texts in a single API call.
     *
     * @param  array  $texts  Array of strings to embed.
     * @return array Array of embedding vectors (index-aligned with input).
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        try {
            $client = OpenAI::client($this->apiKey);
            $response = $client->embeddings()->create([
                'model' => $this->model,
                'input' => array_values($texts),
                'dimensions' => $this->dimensions,
            ]);

            $embeddings = $response->embeddings;
            usort($embeddings, fn ($a, $b) => $a->index <=> $b->index);

            return array_map(fn ($e) => $e->embedding, $embeddings);

        } catch (\Throwable $e) {
            Log::warning('Batch embedding API call failed', [
                'count' => count($texts),
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);
            throw $e;
        }
    }

    public function getDimension(): int
    {
        return $this->dimensions;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }
}
