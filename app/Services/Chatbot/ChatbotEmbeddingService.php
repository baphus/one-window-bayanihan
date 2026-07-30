<?php

namespace App\Services\Chatbot;

use App\Services\Contracts\EmbeddingProvider;
use App\Services\VectorDb\VectorDb;
use RuntimeException;

/**
 * Orchestrates dense vector embeddings for the chatbot retrieval pipeline.
 *
 * Responsibilities:
 * 1. Normalizing text and generating embeddings via the configured provider
 * 2. Delegating storage/querying to the configured VectorDb backend
 *
 * The embedding provider is independently configurable from the generation
 * model, so retrieval and generation can be swapped separately. Switching
 * from the local Ollama provider to a hosted API is a single .env change.
 */
class ChatbotEmbeddingService
{
    public function __construct(
        private readonly EmbeddingProvider $provider,
        private readonly VectorDb $vectorDb,
    ) {}

    // ──────────────────────────────────────────────
    //  Embedding generation (delegated to provider)
    // ──────────────────────────────────────────────

    /**
     * Embed a single text string into a dense vector.
     *
     * @return list<float> Embedding vector (dimensions determined by model).
     */
    public function embed(string $text): array
    {
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            throw new RuntimeException('Cannot embed an empty string after normalization.');
        }

        return $this->provider->embed($normalized);
    }

    /**
     * Embed multiple texts in a single API call.
     *
     * @param  list<string>  $texts
     * @return list<list<float>> Embedding vectors, one per input text
     */
    public function embedBatch(array $texts): array
    {
        $normalized = array_map(fn (string $t) => $this->normalize($t), $texts);
        $normalized = array_values(array_filter($normalized, fn (string $t) => $t !== ''));

        if ($normalized === []) {
            return [];
        }

        return $this->provider->embedBatch($normalized);
    }

    /**
     * Light text preprocessing: trim, Unicode normalize, lowercase.
     *
     * No aggressive regex cleansing or keyword extraction — these risk
     * stripping semantically important words from natural queries.
     */
    public function normalize(string $text): string
    {
        $text = trim($text);
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8'); // NFC normalize
        $text = preg_replace('/\p{M}+/u', '', $text); // strip combining marks
        $text = mb_strtolower($text, 'UTF-8');

        return trim($text);
    }

    /**
     * Check whether the embedding provider endpoint is reachable.
     */
    public function isAvailable(): bool
    {
        return $this->provider->isAvailable();
    }

    // ──────────────────────────────────────────────
    //  Database operations (delegated to VectorDb)
    // ──────────────────────────────────────────────

    /**
     * Vector similarity search.
     *
     * @param  list<float>  $queryEmbedding
     * @param  list<string>|null  $sourceTypes  Filter to these source types, or null for all
     * @param  list<string>|null  $audienceGroups  Filter to these audience groups, or null for all
     * @return list<array{id: int, source_type: string, source_key: string, slug: string, heading: string, audience_group: string, distance: float}>
     */
    public function search(
        array $queryEmbedding,
        ?array $sourceTypes = null,
        ?array $audienceGroups = null,
        int $limit = 5,
    ): array {
        return $this->vectorDb->search($queryEmbedding, $sourceTypes, $audienceGroups, $limit);
    }

    /**
     * Full-text search using PostgreSQL tsvector/tsquery.
     *
     * @return list<array{id: int, source_type: string, source_key: string, slug: string, heading: string, audience_group: string, rank: float}>
     */
    public function ftsSearch(
        string $query,
        ?array $audienceGroups = null,
        int $limit = 10,
    ): array {
        return $this->vectorDb->ftsSearch($query, $audienceGroups, $limit);
    }

    /**
     * Upsert a single embedding row.
     */
    public function store(
        string $sourceType,
        string $sourceKey,
        array $embedding,
        string $slug,
        string $heading,
        string $audienceGroup,
        string $contentHash,
        string $content = '',
    ): void {
        $this->vectorDb->store($sourceType, $sourceKey, $embedding, $slug, $heading, $audienceGroup, $contentHash, $content);
    }

    /**
     * Upsert multiple embeddings in a single transaction.
     *
     * @param  array<int, array{source_type: string, source_key: string, embedding: list<float>, slug: string, heading: string, audience_group: string, content_hash: string, content?: string}>  $rows
     */
    public function storeBatch(array $rows): void
    {
        $this->vectorDb->storeBatch($rows);
    }

    /**
     * Delete all embeddings for a given source type.
     */
    public function deleteBySourceType(string $sourceType): int
    {
        return $this->vectorDb->deleteBySourceType($sourceType);
    }

    /**
     * Delete all embeddings (full re-index).
     */
    public function truncate(): int
    {
        return $this->vectorDb->truncate();
    }

    /**
     * Count embeddings, optionally filtered by source type.
     */
    public function count(?string $sourceType = null): int
    {
        return $this->vectorDb->count($sourceType);
    }

    /**
     * Get the content hashes stored with embeddings for a given source type.
     *
     * @return list<string>
     */
    public function getContentHashes(string $sourceType): array
    {
        return $this->vectorDb->getContentHashes($sourceType);
    }

    /**
     * Check whether a specific source_key + content_hash exists (skip-reindex).
     */
    public function exists(string $sourceType, string $sourceKey, string $contentHash): bool
    {
        return $this->vectorDb->exists($sourceType, $sourceKey, $contentHash);
    }

    /**
     * Does the configured backend support native full-text search?
     */
    public function hasFts(): bool
    {
        return $this->vectorDb->hasFts();
    }
}
