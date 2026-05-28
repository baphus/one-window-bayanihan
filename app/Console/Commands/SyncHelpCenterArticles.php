<?php

namespace App\Console\Commands;

use App\Jobs\EmbedHelpdeskArticleChunks;
use App\Models\HelpdeskArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncHelpCenterArticles extends Command
{
    protected $signature = 'helpcenter:sync
        {--dry-run : Only show what would be synced, do not dispatch jobs}
        {--article= : Sync a specific article by UUID}';

    protected $description = 'Dispatch embedding jobs for all published public Helpdesk articles';

    public function handle(): int
    {
        $query = HelpdeskArticle::published()->where('visibility', 'public');

        if ($articleId = $this->option('article')) {
            $query->where('id', $articleId);
        }

        $articles = $query->get();

        if ($articles->isEmpty()) {
            $this->warn('No published public articles found to sync.');

            return Command::SUCCESS;
        }

        $this->info("Found {$articles->count()} article(s) to process.");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Title', 'Slug', 'Est. Chunks'],
                $articles->map(fn ($a) => [
                    $a->id,
                    mb_substr($a->title, 0, 60),
                    $a->slug,
                    $this->estimateChunkCount($a->content_markdown ?? ''),
                ])
            );

            return Command::SUCCESS;
        }

        $dispatched = 0;
        foreach ($articles as $article) {
            try {
                EmbedHelpdeskArticleChunks::dispatch($article->id);
                $dispatched++;
            } catch (\Throwable $e) {
                $this->error("Failed to dispatch job for article {$article->id}: {$e->getMessage()}");
                Log::error('SyncHelpCenterArticles: dispatch failed', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Dispatched {$dispatched} embedding job(s).");

        return Command::SUCCESS;
    }

    /**
     * Estimate chunk count for an article based on token estimation.
     */
    private function estimateChunkCount(string $content): int
    {
        $maxTokens = (int) config('ai-helpcenter.max_tokens_per_chunk', 800);
        $estimatedTokens = str_word_count($content) * 1.3;

        return max(1, (int) ceil($estimatedTokens / $maxTokens));
    }
}
