<?php

namespace App\Services\Chatbot;

use App\Models\Agency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the chatbot index rebuild: pgvector embedding index
 * covering all knowledge sources (helpdesk articles, guide topics,
 * and database records).
 *
 * Called by `php artisan chatbot:index` and the admin "Update Knowledge"
 * button in System Settings.
 */
class ChatbotIndexService
{
    public function __construct(
        private readonly ChatbotHelpdeskService $helpdesk,
        private readonly ChatbotGuideService $guide,
        private readonly ChatbotEmbeddingService $embedding,
    ) {}

    /**
     * Rebuild pgvector embeddings for all sources.
     *
     * @return array{embedding_count: int, helpdesk_embedded: int, guide_embedded: int, db_embedded: int}
     */
    public function rebuild(?callable $progress = null): array
    {
        $embeddingCount = $this->rebuildEmbeddings($progress);
        $this->report('Embedding index rebuilt', $progress);

        return [
            'embedding_count' => $embeddingCount['total'],
            'helpdesk_embedded' => $embeddingCount['helpdesk'],
            'guide_embedded' => $embeddingCount['guide'],
            'db_embedded' => $embeddingCount['db'],
        ];
    }

    /**
     * Rebuild only the pgvector embeddings.
     *
     * @return array{total: int, helpdesk: int, guide: int, db: int}
     */
    public function rebuildEmbeddings(?callable $progress = null): array
    {
        $contentHash = $this->computeContentHash();

        $helpdeskCount = $this->embedHelpdesk($contentHash, $progress);
        $guideCount = $this->embedGuides($contentHash, $progress);
        $dbCount = $this->embedDatabaseRecords($contentHash, $progress);

        return [
            'total' => $helpdeskCount + $guideCount + $dbCount,
            'helpdesk' => $helpdeskCount,
            'guide' => $guideCount,
            'db' => $dbCount,
        ];
    }

    // ──────────────────────────────────────────────
    //  Helpdesk articles
    // ──────────────────────────────────────────────

    private function embedHelpdesk(string $contentHash, ?callable $progress): int
    {
        $articles = $this->helpdesk->getAllParsedArticles();
        $batch = [];
        $count = 0;

        foreach ($articles as $slug => $article) {
            foreach ($article['sections'] as $section) {
                $sourceKey = "{$slug}::{$section['heading']}";

                if ($this->alreadyIndexed('helpdesk', $sourceKey, $contentHash)) {
                    continue;
                }

                $text = "{$article['title']} - {$section['heading']}\n\n{$section['content']}";
                $batch[] = [
                    'source_type' => 'helpdesk',
                    'source_key' => $sourceKey,
                    'text' => $text,
                    'slug' => $slug,
                    'heading' => $section['heading'],
                    'audience_group' => $article['audience_group'],
                ];

                if (count($batch) >= 50) {
                    $count += $this->flushEmbeddingBatch($batch, $contentHash);
                    $batch = [];
                    $this->report("Embedded {$count} helpdesk sections", $progress);
                }
            }
        }

        if ($batch !== []) {
            $count += $this->flushEmbeddingBatch($batch, $contentHash);
        }

        return $count;
    }

    // ──────────────────────────────────────────────
    //  Guide topics
    // ──────────────────────────────────────────────

    private function embedGuides(string $contentHash, ?callable $progress): int
    {
        $topics = $this->guide->getAllTopics();
        $batch = [];
        $count = 0;

        foreach ($topics as $key => $topic) {
            $sourceKey = "guide:{$key}";

            if ($this->alreadyIndexed('guide', $sourceKey, $contentHash)) {
                continue;
            }

            $sections = $this->guide->getSections([$key]);
            if ($sections === '') {
                continue;
            }

            $text = "{$topic['heading']}: {$topic['description']}\n\n{$sections}";
            $batch[] = [
                'source_type' => 'guide',
                'source_key' => $sourceKey,
                'text' => $text,
                'slug' => $key,
                'heading' => $topic['heading'],
                'audience_group' => 'OFW & Public',
            ];

            if (count($batch) >= 50) {
                $count += $this->flushEmbeddingBatch($batch, $contentHash);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $count += $this->flushEmbeddingBatch($batch, $contentHash);
        }

        return $count;
    }

    // ──────────────────────────────────────────────
    //  Database records (agencies, services, requirements)
    // ──────────────────────────────────────────────

    private function embedDatabaseRecords(string $contentHash, ?callable $progress): int
    {
        $agencies = Agency::query()
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->with(['services' => function ($q) {
                $q->where('is_deleted', false)
                    ->with('requirements', function ($rq) {
                        $rq->where('is_deleted', false);
                    });
            }])
            ->get();

        $batch = [];
        $count = 0;

        foreach ($agencies as $agency) {
            if ($agency->services->isEmpty()) {
                continue;
            }

            $sourceKey = "agency:{$agency->slug}";

            if ($this->alreadyIndexed('reference', $sourceKey, $contentHash)) {
                continue;
            }

            $text = $this->denormalizeAgency($agency);

            $batch[] = [
                'source_type' => 'reference',
                'source_key' => $sourceKey,
                'text' => $text,
                'slug' => $agency->slug,
                'heading' => $agency->name,
                'audience_group' => 'OFW & Public',
            ];

            if (count($batch) >= 50) {
                $count += $this->flushEmbeddingBatch($batch, $contentHash);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $count += $this->flushEmbeddingBatch($batch, $contentHash);
        }

        return $count;
    }

    /**
     * Build a self-contained text chunk for an agency and all its services.
     * A single embedded chunk covers the full service catalog of one agency.
     */
    private function denormalizeAgency($agency): string
    {
        $parts = [
            "{$agency->name} ({$agency->short})",
        ];

        if ($agency->description) {
            $parts[] = '';
            $parts[] = $agency->description;
        }

        if ($agency->contact_info) {
            $parts[] = '';
            $parts[] = "Contact Info\n{$agency->contact_info}";
        }

        $parts[] = '';
        $parts[] = 'Services:';

        foreach ($agency->services as $service) {
            $line = "- {$service->name}";

            if ($service->description) {
                $line .= " — {$service->description}";
            }

            if ($service->processing_days) {
                $line .= " (Processing: {$service->processing_days} days)";
            }

            $requirements = $service->requirements->filter(fn ($r) => ! $r->is_deleted);
            if ($requirements->isNotEmpty()) {
                $reqText = $requirements
                    ->map(fn ($r) => $r->name.($r->is_required ? ' (required)' : ' (optional)'))
                    ->implode(', ');
                $line .= " [Requirements: {$reqText}]";
            }

            $parts[] = $line;
        }

        return implode("\n", $parts);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Compute a content hash covering all source types: file-based content
     * plus DB record state. Detects changes to agencies, services, and
     * requirements without polling file mtimes.
     */
    public function computeContentHash(): string
    {
        $fileHash = $this->helpdesk->contentHash();

        // DB record state: counts + latest update timestamp per table
        $agencyCount = Agency::where('is_active', true)->where('is_deleted', false)->count();
        $serviceMax = DB::table('services')->where('is_deleted', false)->max('updated_at');
        $reqMax = DB::table('service_requirements')->where('is_deleted', false)->max('updated_at');

        $dbPart = "{$agencyCount}:{$serviceMax}:{$reqMax}";

        return md5("{$fileHash}:{$dbPart}");
    }

    /**
     * Check if a source is already indexed with the current content hash.
     */
    private function alreadyIndexed(string $sourceType, string $sourceKey, string $contentHash): bool
    {
        return $this->embedding->exists($sourceType, $sourceKey, $contentHash);
    }

    /**
     * Generate embeddings for a batch and store them.
     *
     * @param  list<array{source_type: string, source_key: string, text: string, slug: string, heading: string, audience_group: string}>  $batch
     */
    private function flushEmbeddingBatch(array $batch, string $contentHash): int
    {
        $texts = array_map(fn ($row) => $row['text'], $batch);

        try {
            $vectors = $this->embedding->embedBatch($texts);
        } catch (\Throwable $e) {
            Log::error('Chatbot embedding batch failed', [
                'batch_size' => count($batch),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        $rows = [];
        foreach ($batch as $i => $row) {
            $rows[] = [
                'source_type' => $row['source_type'],
                'source_key' => $row['source_key'],
                'embedding' => $vectors[$i],
                'slug' => $row['slug'],
                'heading' => $row['heading'],
                'content' => $row['text'],
                'audience_group' => $row['audience_group'],
                'content_hash' => $contentHash,
            ];
        }

        $this->embedding->storeBatch($rows);

        return count($rows);
    }

    private function report(string $message, ?callable $progress): void
    {
        if ($progress !== null) {
            $progress($message);
        }

        Log::info("ChatbotIndex: {$message}");
    }
}
