<?php

namespace App\Services\HelpCenter;

use App\Models\HelpdeskArticle;
use Illuminate\Support\Collection;

class RetrievalRankingService
{
    /**
     * Vector similarity callback — injected externally, defaults to 0 (no vector).
     *
     * @var callable|null
     */
    private $vectorSimilarityCallback = null;

    /**
     * Set a vector similarity callback.
     * Callback signature: fn(HelpdeskArticle $article, string $query): float
     */
    public function setVectorSimilarityCallback(callable $callback): void
    {
        $this->vectorSimilarityCallback = $callback;
    }

    /**
     * Rank articles by relevance to the query.
     * Returns collection with 'score' attribute on each article (0-100 normalized).
     */
    public function rank(Collection $articles, string $query): Collection
    {
        if ($articles->isEmpty()) {
            return $articles;
        }

        $queryLower = mb_strtolower($query);
        $queryWords = array_filter(str_word_count($queryLower, 1), fn ($w) => mb_strlen($w) > 2);

        $maxRawScore = 0;
        $scored = $articles->map(function (HelpdeskArticle $article) use ($queryLower, $queryWords, &$maxRawScore) {
            $score = $this->calculateScore($article, $queryLower, $queryWords);
            $article->score = $score;
            $maxRawScore = max($maxRawScore, $score);

            return $article;
        });

        // Normalize to 0-100
        if ($maxRawScore > 0) {
            $scored = $scored->map(function ($article) use ($maxRawScore) {
                $article->score = round(($article->score / $maxRawScore) * 100, 1);

                return $article;
            });
        }

        return $scored->sortByDesc('score')->values();
    }

    /**
     * Calculate raw relevance score for a single article.
     */
    protected function calculateScore(HelpdeskArticle $article, string $queryLower, array $queryWords): float
    {
        $score = 0.0;
        $titleLower = mb_strtolower($article->title ?? '');
        $excerptLower = mb_strtolower($article->excerpt ?? '');

        // 1. Exact title match: +10
        if (str_contains($titleLower, $queryLower)) {
            $score += 10;
        }

        // 2. Partial title match (Jaccard similarity): +0-5
        if (! empty($queryWords)) {
            $titleWords = array_unique(array_filter(str_word_count($titleLower, 1), fn ($w) => mb_strlen($w) > 2));
            $intersection = array_intersect($queryWords, $titleWords);
            $union = array_unique(array_merge($queryWords, $titleWords));
            $titleJaccard = count($union) > 0 ? count($intersection) / count($union) : 0;
            $score += $titleJaccard * 5;
        }

        // 3. Tag match: +3 per matching tag
        if ($article->relationLoaded('tags') && $article->tags->isNotEmpty()) {
            $tagNames = $article->tags->pluck('name')->map(fn ($n) => mb_strtolower($n))->toArray();
            foreach ($queryWords as $word) {
                foreach ($tagNames as $tagName) {
                    if (str_contains($tagName, $word) || str_contains($word, $tagName)) {
                        $score += 3;
                    }
                }
            }
        }

        // 4. Keyword overlap in excerpt: +1 per query word
        foreach ($queryWords as $word) {
            if (str_contains($excerptLower, $word)) {
                $score += 1;
            }
        }

        // 5. Vector similarity: +0-8 (via callback)
        if ($this->vectorSimilarityCallback) {
            $vectorScore = call_user_func($this->vectorSimilarityCallback, $article, $queryLower);
            $score += min(8, max(0, $vectorScore * 8));
        }

        // 6. Recency bonus: +1 if updated within 30 days
        if ($article->updated_at && $article->updated_at->diffInDays(now()) <= 30) {
            $score += 1;
        }

        return $score;
    }

    public function getMinimumScoreThreshold(): float
    {
        return (float) config('ai-helpcenter.retrieval_min_score', 0.3);
    }
}
