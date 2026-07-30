<?php

namespace App\Services\VectorDb;

/**
 * Contract for a vector database backend used by the chatbot retrieval pipeline.
 *
 * Implementations translate between the application layer and a specific
 * vector-database technology (pgvector, Pinecone, Qdrant, etc.).
 *
 * Each adapter returns search results as flat associative arrays so that
 * callers are decoupled from the storage engine's native result format.
 *
 * @see PgVectorAdapter
 */
interface VectorDb
{
    // ──────────────────────────────────────────────
    //  Read
    // ──────────────────────────────────────────────

    /**
     * Cosine-similarity search.
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
    ): array;

    /**
     * Full-text search (PostgreSQL tsvector/tsquery).
     *
     * Adapters that cannot provide FTS (e.g. Pinecone) should return an empty
     * array so the caller falls through to the keyword-search fallback.
     *
     * @return list<array{id: int, source_type: string, source_key: string, slug: string, heading: string, audience_group: string, rank: float}>
     */
    public function ftsSearch(
        string $query,
        ?array $audienceGroups = null,
        int $limit = 10,
    ): array;

    /**
     * Does this backend have native full-text search?
     *
     * When false, HybridSearch skips FTS and relies solely on vector + keyword
     * fallback — no wasted queries.
     */
    public function hasFts(): bool;

    /**
     * Count embeddings, optionally filtered by source type.
     */
    public function count(?string $sourceType = null): int;

    /**
     * Get the content hashes for a given source type (change detection).
     *
     * @return list<string>
     */
    public function getContentHashes(string $sourceType): array;

    /**
     * Check whether a specific source_key + content_hash exists (skip-reindex).
     */
    public function exists(string $sourceType, string $sourceKey, string $contentHash): bool;

    // ──────────────────────────────────────────────
    //  Write
    // ──────────────────────────────────────────────

    /**
     * Upsert a single embedding row.
     *
     * The unique constraint is (source_type, source_key) — if a row with the
     * same pair exists, its embedding and metadata are replaced.
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
    ): void;

    /**
     * Upsert multiple embeddings in a single transaction.
     *
     * @param  array<int, array{source_type: string, source_key: string, embedding: list<float>, slug: string, heading: string, audience_group: string, content_hash: string, content?: string}>  $rows
     */
    public function storeBatch(array $rows): void;

    /**
     * Delete all embeddings for a given source type.
     */
    public function deleteBySourceType(string $sourceType): int;

    /**
     * Delete all embeddings (full re-index).
     */
    public function truncate(): int;
}
