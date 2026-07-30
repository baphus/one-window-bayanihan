<?php

namespace App\Services\Contracts;

use App\Services\Chatbot\ApiEmbeddingProvider;
use App\Services\Chatbot\OllamaEmbeddingProvider;

/**
 * Contract for an embedding provider that converts text into dense vectors.
 *
 * Implementations wrap a specific embedding API (Ollama, OpenAI-compatible,
 * etc.) behind a common interface so the retrieval pipeline is decoupled
 * from the provider choice — only the .env values need to change between
 * development (Ollama) and production (hosted API).
 *
 * @see OllamaEmbeddingProvider
 * @see ApiEmbeddingProvider
 */
interface EmbeddingProvider
{
    /**
     * Embed a single text string into a dense vector.
     *
     * @return list<float> The embedding vector (dimensions determined by model).
     */
    public function embed(string $text): array;

    /**
     * Embed multiple texts in a single API call.
     *
     * @param  list<string>  $texts
     * @return list<list<float>> Embedding vectors, one per input text.
     */
    public function embedBatch(array $texts): array;

    /**
     * Check whether the embedding endpoint is reachable and the
     * configured model is available.
     */
    public function isAvailable(): bool;
}
