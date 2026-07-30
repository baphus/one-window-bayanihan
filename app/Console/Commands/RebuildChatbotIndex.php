<?php

namespace App\Console\Commands;

use App\Services\Chatbot\ChatbotEmbeddingService;
use App\Services\Chatbot\ChatbotGuideService;
use App\Services\Chatbot\ChatbotHelpdeskService;
use App\Services\Chatbot\ChatbotIndexService;
use Illuminate\Console\Command;

class RebuildChatbotIndex extends Command
{
    protected $signature = 'chatbot:index';

    protected $description = 'Rebuild pgvector embeddings for the chatbot';

    public function handle(
        ChatbotHelpdeskService $helpdesk,
        ChatbotEmbeddingService $embedding,
    ): int {
        $this->info('Rebuilding pgvector embeddings...');

        $index = new ChatbotIndexService($helpdesk, new ChatbotGuideService, $embedding);

        try {
            $result = $index->rebuild(function (string $msg) {
                $this->line("  {$msg}");
            });
        } catch (\Throwable $e) {
            $this->error("Embedding rebuild failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Done.');
        $this->line("  Embeddings: {$result['embedding_count']} chunks (helpdesk: {$result['helpdesk_embedded']}, guide: {$result['guide_embedded']}, db: {$result['db_embedded']})");

        return self::SUCCESS;
    }
}
