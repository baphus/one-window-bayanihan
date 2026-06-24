<?php

namespace App\Services\HelpCenter;

use App\Services\Ai\EmbeddingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VectorSearchService
{
    /**
     * Create a new VectorSearchService instance.
     */
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * Search for articles semantically similar to the query.
     *
     * Converts the query to an embedding vector via EmbeddingService, then
     * performs a pgvector cosine similarity search against article chunks.
     * Results are aggregated by article_id with average similarity.
     *
     * @param  string  $query  The search query text.
     * @return Collection Collection of objects with article_id (string) and similarity (float) properties.
     */
    public function search(string $query): Collection
    {
        if (empty(trim($query))) {
            return new Collection;
        }

        if (! $this->embeddingService->isConfigured()) {
            return new Collection;
        }

        $vector = $this->embeddingService->embed($query);
        $vectorString = '['.implode(',', $vector).']';
        $limit = (int) config('ai-helpcenter.vector_search_limit', 20);

        $results = DB::select('
            SELECT article_id, AVG(similarity) AS similarity
            FROM (
                SELECT article_id, 1 - (embedding <=> ?::vector) AS similarity
                FROM helpdesk_article_chunks
                ORDER BY embedding <=> ?::vector
                LIMIT ?
            ) AS ranked_chunks
            GROUP BY article_id
            ORDER BY similarity DESC
        ', [$vectorString, $vectorString, $limit]);

        return collect(array_map(function (object $row): object {
            return (object) [
                'article_id' => (string) $row->article_id,
                'similarity' => (float) $row->similarity,
            ];
        }, $results));
    }
}
