<?php

namespace App\Services\VectorDb;

use Illuminate\Support\Facades\DB;

/**
 * pgvector adapter — stores and queries embeddings in the local PostgreSQL
 * `chatbot_embeddings` table using the pgvector extension.
 *
 * Requirements
 * ------------
 * - PostgreSQL 15+ with `CREATE EXTENSION vector;`
 * - `chatbot_embeddings` table with a `vector(768)` column
 *
 * @see VectorDb
 */
class PgVectorAdapter implements VectorDb
{
    // ──────────────────────────────────────────────
    //  Read
    // ──────────────────────────────────────────────

    public function search(
        array $queryEmbedding,
        ?array $sourceTypes = null,
        ?array $audienceGroups = null,
        int $limit = 5,
    ): array {
        $vector = $this->vectorToString($queryEmbedding);

        // pgvector requires the vector literal inline — it cannot be bound as a
        // parameter. vectorToString() already returns '[0.1,0.2,...]' format.
        $sql = "SELECT id, source_type, source_key, slug, heading, audience_group,
                       embedding <=> '{$vector}'::vector AS distance
                FROM chatbot_embeddings
                WHERE 1=1";

        $params = [];

        if ($sourceTypes !== null) {
            $placeholders = [];
            foreach ($sourceTypes as $i => $type) {
                $placeholders[] = ":st{$i}";
                $params["st{$i}"] = $type;
            }
            $sql .= ' AND source_type IN ('.implode(', ', $placeholders).')';
        }

        if ($audienceGroups !== null) {
            $placeholders = [];
            foreach ($audienceGroups as $i => $group) {
                $placeholders[] = ":ag{$i}";
                $params["ag{$i}"] = $group;
            }
            $sql .= ' AND audience_group IN ('.implode(', ', $placeholders).')';
        }

        $sql .= ' ORDER BY distance ASC LIMIT '.max(1, $limit);

        return array_map(fn (object $row) => (array) $row, DB::select($sql, $params));
    }

    public function ftsSearch(
        string $query,
        ?array $audienceGroups = null,
        int $limit = 10,
    ): array {
        $sql = "SELECT id, source_type, source_key, slug, heading, audience_group,
                       ts_rank(ts_content, plainto_tsquery('english', :query)) AS rank
                FROM chatbot_embeddings
                WHERE ts_content @@ plainto_tsquery('english', :query)";

        $params = ['query' => $query];

        if ($audienceGroups !== null) {
            $placeholders = [];
            foreach ($audienceGroups as $i => $group) {
                $placeholders[] = ":ag{$i}";
                $params["ag{$i}"] = $group;
            }
            $sql .= ' AND audience_group IN ('.implode(', ', $placeholders).')';
        }

        $sql .= ' ORDER BY rank DESC LIMIT '.max(1, $limit);

        return array_map(fn (object $row) => (array) $row, DB::select($sql, $params));
    }

    public function hasFts(): bool
    {
        return true;
    }

    public function count(?string $sourceType = null): int
    {
        $query = DB::table('chatbot_embeddings');

        if ($sourceType !== null) {
            $query->where('source_type', $sourceType);
        }

        return (int) $query->count();
    }

    /**
     * @return list<string>
     */
    public function getContentHashes(string $sourceType): array
    {
        return DB::table('chatbot_embeddings')
            ->where('source_type', $sourceType)
            ->distinct()
            ->pluck('content_hash')
            ->toArray();
    }

    public function exists(string $sourceType, string $sourceKey, string $contentHash): bool
    {
        return DB::table('chatbot_embeddings')
            ->where('source_type', $sourceType)
            ->where('source_key', $sourceKey)
            ->where('content_hash', $contentHash)
            ->exists();
    }

    // ──────────────────────────────────────────────
    //  Write
    // ──────────────────────────────────────────────

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
        $vector = $this->vectorToString($embedding);

        DB::table('chatbot_embeddings')->updateOrInsert(
            ['source_type' => $sourceType, 'source_key' => $sourceKey],
            [
                'embedding' => DB::raw("'{$vector}'::vector"),
                'slug' => $slug,
                'heading' => $heading,
                'content' => $content,
                'audience_group' => $audienceGroup,
                'content_hash' => $contentHash,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function storeBatch(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->store(
                    $row['source_type'],
                    $row['source_key'],
                    $row['embedding'],
                    $row['slug'],
                    $row['heading'],
                    $row['audience_group'],
                    $row['content_hash'],
                    $row['content'] ?? '',
                );
            }
        });
    }

    public function deleteBySourceType(string $sourceType): int
    {
        return DB::table('chatbot_embeddings')
            ->where('source_type', $sourceType)
            ->delete();
    }

    public function truncate(): int
    {
        return DB::table('chatbot_embeddings')->delete();
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Convert an embedding array to the pgvector string format: [0.1,0.2,...]
     */
    private function vectorToString(array $embedding): string
    {
        return '['.implode(',', array_map(fn (float $v) => round($v, 8), $embedding)).']';
    }
}
