<?php

namespace App\Jobs;

use App\Models\HelpdeskArticle;
use App\Models\HelpdeskArticleChunk;
use App\Services\Ai\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmbedHelpdeskArticleChunks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $articleId;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(string $articleId)
    {
        $this->articleId = $articleId;
    }

    public function handle(EmbeddingService $embedder): void
    {
        $article = HelpdeskArticle::published()
            ->where('visibility', 'public')
            ->find($this->articleId);

        if (! $article) {
            Log::warning('EmbedHelpdeskArticleChunks: Article not found or not public', [
                'article_id' => $this->articleId,
            ]);

            return;
        }

        $content = $article->content_markdown ?? '';
        if (empty(trim($content))) {
            Log::info('EmbedHelpdeskArticleChunks: Article has no content', [
                'article_id' => $this->articleId,
            ]);

            return;
        }

        if (! $embedder->isConfigured()) {
            Log::warning('EmbedHelpdeskArticleChunks: Embedding service not configured', [
                'article_id' => $this->articleId,
            ]);

            return;
        }

        // Chunk the content
        $chunks = $this->chunkContent($content);

        if (empty($chunks)) {
            return;
        }

        // Generate embeddings
        $texts = array_map(fn (array $c) => $c['content'], $chunks);

        try {
            $embeddings = $embedder->embedBatch($texts);
        } catch (\Throwable $e) {
            Log::error('EmbedHelpdeskArticleChunks: embedBatch failed', [
                'article_id' => $this->articleId,
                'error' => $e->getMessage(),
            ]);
            $this->release(30);

            return;
        }

        // Delete old chunks for this article
        HelpdeskArticleChunk::where('article_id', $this->articleId)->delete();

        // Insert new chunks
        $now = now();
        $chunkIndex = 0;

        foreach ($chunks as $i => $chunk) {
            HelpdeskArticleChunk::create([
                'id' => (string) Str::uuid(),
                'article_id' => $this->articleId,
                'content' => $chunk['content'],
                'embedding' => $embeddings[$i] ?? null,
                'chunk_index' => $chunkIndex++,
                'metadata' => [
                    'title' => $article->title,
                    'updated_at' => $article->updated_at?->toIso8601String(),
                    'chunk_prefix' => $chunk['prefix'] ?? null,
                ],
            ]);
        }

        Log::info('EmbedHelpdeskArticleChunks: Completed', [
            'article_id' => $this->articleId,
            'title' => $article->title,
            'chunks' => count($chunks),
        ]);
    }

    /**
     * Chunk article content into segments of ~maxTokens with overlap.
     * Splits on paragraphs first, then sentences, then hard-splits.
     *
     * @return array<int, array{content: string, prefix?: string}>
     */
    protected function chunkContent(string $content): array
    {
        $maxTokens = 800;
        $overlapTokens = 100;

        // Split by paragraphs first
        $paragraphs = preg_split("/\n\s*\n/", $content);
        $paragraphs = array_values(array_filter($paragraphs, fn (string $p) => trim($p) !== ''));

        if (empty($paragraphs)) {
            return [];
        }

        $chunks = [];
        $currentChunk = '';
        $currentTokens = 0;

        foreach ($paragraphs as $paragraph) {
            $paraText = trim($paragraph);
            $paraTokens = $this->estimateTokens($paraText);

            if ($currentTokens + $paraTokens <= $maxTokens) {
                $currentChunk .= ($currentChunk !== '' ? "\n\n" : '').$paraText;
                $currentTokens += $paraTokens;
            } else {
                // Save current chunk if non-empty
                if ($currentChunk !== '') {
                    $chunks[] = ['content' => $currentChunk];
                    $currentChunk = '';
                    $currentTokens = 0;
                }

                // If paragraph itself exceeds maxTokens, hard-split it
                if ($paraTokens > $maxTokens) {
                    $sentences = preg_split('/(?<=[.!?])\s+/', $paraText);
                    $tempChunk = '';
                    $tempTokens = 0;

                    foreach ($sentences as $sentence) {
                        $sentTokens = $this->estimateTokens($sentence);
                        if ($tempTokens + $sentTokens > $maxTokens && $tempChunk !== '') {
                            $chunks[] = ['content' => $tempChunk];
                            $tempChunk = $sentence;
                            $tempTokens = $sentTokens;
                        } else {
                            $tempChunk .= ($tempChunk !== '' ? ' ' : '').$sentence;
                            $tempTokens += $sentTokens;
                        }
                    }
                    if ($tempChunk !== '') {
                        $currentChunk = $tempChunk;
                        $currentTokens = $tempTokens;
                    }
                } else {
                    $currentChunk = $paraText;
                    $currentTokens = $paraTokens;
                }
            }
        }

        // Save last chunk
        if ($currentChunk !== '') {
            $chunks[] = ['content' => $currentChunk];
        }

        // Apply overlap between adjacent chunks
        $chunks = $this->applyOverlap($chunks, $overlapTokens);

        return $chunks;
    }

    /**
     * Apply token overlap between adjacent chunks.
     *
     * @param  array<int, array{content: string}>  $chunks
     * @return array<int, array{content: string, prefix?: string}>
     */
    protected function applyOverlap(array $chunks, int $overlapTokens): array
    {
        if (count($chunks) <= 1) {
            return $chunks;
        }

        $result = [];
        for ($i = 0; $i < count($chunks); $i++) {
            $chunkContent = $chunks[$i]['content'];
            $prefix = null;

            if ($i > 0) {
                $prevWords = str_word_count($chunks[$i - 1]['content'], 1);
                $overlapWordCount = max(1, (int) ($overlapTokens / 1.3));

                if (count($prevWords) > $overlapWordCount) {
                    $prefix = implode(' ', array_slice($prevWords, -$overlapWordCount));
                    $chunkContent = $prefix."\n\n".$chunkContent;
                }
            }

            $result[] = [
                'content' => $chunkContent,
                'prefix' => $prefix,
            ];
        }

        return $result;
    }

    protected function estimateTokens(string $text): int
    {
        return (int) ceil(str_word_count($text) * 1.3);
    }
}
