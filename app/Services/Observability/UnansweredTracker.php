<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Log;

class UnansweredTracker
{
    public function logUnanswered(string $query, string $reason): void
    {
        Log::channel('chatbot')->warning('Unanswered query', [
            'query' => $query,
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
