<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Log;

class RetrievalLogger
{
    public function logRetrieval(string $query, int $resultsCount, float $topScore, float $latencyMs): void
    {
        Log::channel('chatbot')->info('Helpdesk retrieval', [
            'query' => $query,
            'results_count' => $resultsCount,
            'top_score' => $topScore,
            'latency_ms' => round($latencyMs, 2),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function logTokenUsage(string $provider, string $model, int $promptTokens, int $completionTokens): void
    {
        $total = $promptTokens + $completionTokens;

        Log::channel('chatbot')->info('Token usage', [
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $total,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function logError(string $operation, string $error, array $context = []): void
    {
        Log::channel('chatbot')->warning('Operation failed', array_merge([
            'operation' => $operation,
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }
}
