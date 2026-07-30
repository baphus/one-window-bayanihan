<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * NOTE: The $audienceGroups parameter is now typed as ?array and passed
 * through to all search backends. When a user role maps to multiple groups
 * (e.g. Case Manager → OFW & Public, Case Managers, General), all are sent
 * together so any matching group returns results.
 *
 * Fallback order:
 * 1. pgvector cosine similarity  (requires pgvector extension + embedding model)
 * 2. PostgreSQL tsvector/tsquery   (requires chatbot_embeddings table)
 * 3. Keyword search against files  (zero DB dependency — always works)
 */

/**
 * Hybrid search combining pgvector cosine similarity with PostgreSQL FTS.
 *
 * Flow:
 * 1. Run both vector search and FTS in parallel
 * 2. Score results using Reciprocal Rank Fusion (RRF)
 * 3. LLM receives the top 2 articles from the fused results
 *
 * RRF formula: score = 1 / (k + rank) where k=60 (standard constant).
 * Items found by both methods get a higher combined score.
 */
class ChatbotHybridSearch
{
    /** RRF constant — higher k means less difference between ranks. */
    private const RRF_K = 60;

    /** Weight for vector scores vs FTS scores (0.0–1.0). */
    private const VECTOR_WEIGHT = 0.6;

    /** Weight for FTS scores (1.0 - VECTOR_WEIGHT). */
    private const FTS_WEIGHT = 0.4;

    /** Minimum ratio of second-hit score to first-hit score to include it.
     *  If the second hit scores below this fraction of the top hit, it's
     *  likely noise from vocabulary overlap and gets dropped. */
    private const MIN_SCORE_RATIO = 0.6;

    public function __construct(
        private readonly ChatbotEmbeddingService $embedding,
        private readonly ChatbotHelpdeskService $helpdesk,
    ) {}

    /**
     * Run hybrid search and return ranked hits with confidence.
     *
     * @param  list<string>|null  $audienceGroups  Allowed audience groups, or null for all
     * @return array{hits: list<array{source_type: string, source_key: string, slug: string, heading: string, audience_group: string, score: float, vector_score: float|null, fts_score: float|null}>, confidence: float, clear_winner: bool, vector_count: int, fts_count: int}
     */
    public function search(
        string $query,
        ?array $audienceGroups,
        int $limit = 5,
    ): array {
        // Run both search paths
        $vectorHits = $this->vectorSearch($query, $audienceGroups, max($limit * 3, 15));
        $ftsHits = $this->ftsSearch($query, $audienceGroups, max($limit * 3, 15));

        // Last resort: plain keyword search against TypeScript content files.
        // This is the only path that requires zero database infrastructure —
        // no pgvector extension, no chatbot_embeddings table, no embedding model.
        if ($vectorHits === [] && $ftsHits === []) {
            $keywordHits = $this->helpdesk->keywordSearch($query, $audienceGroups, $limit);
            if ($keywordHits !== []) {
                $confidence = $keywordHits[0]['raw_score'] * 0.8;

                return $this->result(
                    $this->toOutput($keywordHits),
                    count($keywordHits),
                    ftsCount: 0,
                    confidence: $confidence,
                );
            }

            return $this->emptyResult();
        }

        // Fuse results using Reciprocal Rank Fusion
        $fused = $this->rrfFuse($vectorHits, $ftsHits);

        $confidence = $fused !== [] ? $fused[0]['score'] : 0.0;

        // Drop the second hit if its score is too far below the first —
        // vocabulary overlap (e.g. "OWWA" mentioned in passing) produces
        // low-quality second results that confuse the LLM.
        $topScore = $fused[0]['score'] ?? 0;
        $maxHits = 1;
        if (count($fused) >= 2 && $topScore > 0) {
            $ratio = $fused[1]['score'] / $topScore;
            if ($ratio >= self::MIN_SCORE_RATIO) {
                $maxHits = 2;
            }
        }

        $outputHits = $this->toOutput(array_slice($fused, 0, $maxHits));

        return $this->result($outputHits, count($vectorHits), count($ftsHits), $confidence);
    }

    // ──────────────────────────────────────────────
    //  Search backends
    // ──────────────────────────────────────────────

    /**
     * Vector search via pgvector cosine similarity.
     *
     * @return list<array{source_type: string, source_key: string, slug: string, heading: string, audience_group: string, rank: int, raw_score: float}>
     */
    private function vectorSearch(string $query, ?array $audienceGroups, int $limit): array
    {
        $vectorHits = [];

        try {
            $normalized = $this->embedding->normalize($query);
            if ($normalized === '') {
                return [];
            }

            $queryEmbedding = $this->embedding->embed($normalized);
            $vectorResults = $this->embedding->search(
                $queryEmbedding,
                audienceGroups: $audienceGroups,
                limit: $limit,
            );

            foreach ($vectorResults as $i => $row) {
                $vectorHits[] = [
                    'source_type' => $row['source_type'],
                    'source_key' => $row['source_key'],
                    'slug' => $row['slug'],
                    'heading' => $row['heading'],
                    'audience_group' => $row['audience_group'],
                    'rank' => $i + 1,
                    'raw_score' => 1.0 - (float) ($row['distance'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            Log::warning('Chatbot vector search failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $vectorHits;
    }

    /**
     * Full-text search via PostgreSQL tsvector/tsquery.
     *
     * @return list<array{source_type: string, source_key: string, slug: string, heading: string, audience_group: string, rank: int, raw_score: float}>
     */
    private function ftsSearch(string $query, ?array $audienceGroups, int $limit): array
    {
        $ftsHits = [];

        try {
            $ftsResults = $this->embedding->ftsSearch($query, $audienceGroups, $limit);

            foreach ($ftsResults as $i => $row) {
                $ftsHits[] = [
                    'source_type' => $row['source_type'],
                    'source_key' => $row['source_key'],
                    'slug' => $row['slug'],
                    'heading' => $row['heading'],
                    'audience_group' => $row['audience_group'],
                    'rank' => $i + 1,
                    'raw_score' => (float) ($row['rank'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            Log::warning('Chatbot FTS search failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $ftsHits;
    }

    // ──────────────────────────────────────────────
    //  Score fusion
    // ──────────────────────────────────────────────

    /**
     * Fuse vector and FTS results using Reciprocal Rank Fusion (RRF).
     *
     * Each hit gets: score = VECTOR_WEIGHT * rrf(vector_rank) + FTS_WEIGHT * rrf(fts_rank)
     * Hits found by both methods receive a higher combined score.
     *
     * @param  list<array{source_type: string, source_key: string, slug: string, ...}>  $vectorHits
     * @param  list<array{source_type: string, source_key: string, slug: string, ...}>  $ftsHits
     * @return list<array{source_type: string, source_key: string, slug: string, heading: string, audience_group: string, score: float}>
     */
    private function rrfFuse(array $vectorHits, array $ftsHits): array
    {
        $fused = [];

        // Index vector hits by composite key
        $vectorIndex = [];
        foreach ($vectorHits as $hit) {
            $key = $this->hitKey($hit);
            $vectorIndex[$key] = $hit;
        }

        // Index FTS hits by composite key
        $ftsIndex = [];
        foreach ($ftsHits as $hit) {
            $key = $this->hitKey($hit);
            $ftsIndex[$key] = $hit;
        }

        // Build combined index of all unique hits
        $allKeys = array_unique(array_merge(array_keys($vectorIndex), array_keys($ftsIndex)));

        foreach ($allKeys as $key) {
            $vectorScore = isset($vectorIndex[$key])
                ? self::VECTOR_WEIGHT / (self::RRF_K + $vectorIndex[$key]['rank'])
                : 0.0;

            $ftsScore = isset($ftsIndex[$key])
                ? self::FTS_WEIGHT / (self::RRF_K + $ftsIndex[$key]['rank'])
                : 0.0;

            // Prefer vector hit for metadata (more complete)
            $hit = $vectorIndex[$key] ?? $ftsIndex[$key];

            $fused[] = [
                'source_type' => $hit['source_type'],
                'source_key' => $hit['source_key'],
                'slug' => $hit['slug'],
                'heading' => $hit['heading'],
                'audience_group' => $hit['audience_group'],
                'score' => $vectorScore + $ftsScore,
            ];
        }

        // Sort by fused score descending
        usort($fused, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $fused;
    }

    /**
     * Composite key for deduplication across search backends.
     */
    private function hitKey(array $hit): string
    {
        return "{$hit['source_type']}:{$hit['source_key']}";
    }

    // ──────────────────────────────────────────────
    //  Scoring
    // ──────────────────────────────────────────────

    /**
     * Determine if the top result is an unambiguous winner.
     */
    private function computeClearWinner(array $hits): bool
    {
        if (count($hits) < 2) {
            return true;
        }

        $topScore = $hits[0]['score'];
        $topSource = $hits[0]['source_type'].':'.$hits[0]['slug'];

        foreach ($hits as $hit) {
            if ($topSource !== $hit['source_type'].':'.$hit['slug']) {
                return $topScore >= 1.5 * $hit['score'];
            }
        }

        return true;
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Convert internal hit format to the output format consumed by the controller.
     */
    private function toOutput(array $hits): array
    {
        $output = [];
        foreach ($hits as $hit) {
            $output[] = [
                'source_type' => $hit['source_type'],
                'source_key' => $hit['source_key'],
                'slug' => $hit['slug'],
                'heading' => $hit['heading'],
                'audience_group' => $hit['audience_group'],
                'score' => $hit['score'],
                'vector_score' => null,
                'fts_score' => null,
            ];
        }

        return $output;
    }

    /**
     * Build the final result array.
     */
    private function result(array $hits, int $vectorCount, int $ftsCount, float $confidence): array
    {
        return [
            'hits' => $hits,
            'confidence' => $confidence,
            'clear_winner' => $this->computeClearWinner($hits),
            'vector_count' => $vectorCount,
            'fts_count' => $ftsCount,
        ];
    }

    /**
     * Empty result when both search backends return nothing.
     */
    private function emptyResult(): array
    {
        return [
            'hits' => [],
            'confidence' => 0.0,
            'clear_winner' => false,
            'vector_count' => 0,
            'fts_count' => 0,
        ];
    }
}
