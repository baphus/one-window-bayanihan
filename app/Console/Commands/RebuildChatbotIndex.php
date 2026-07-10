<?php

namespace App\Console\Commands;

use App\Services\Chatbot\ChatbotHelpdeskService;
use App\Services\Chatbot\ChatbotRetrievalService;
use Illuminate\Console\Command;

class RebuildChatbotIndex extends Command
{
    protected $signature = 'chatbot:index';

    protected $description = 'Rebuild the chatbot section cache and SQLite FTS5 retrieval index';

    public function handle(ChatbotHelpdeskService $helpdesk, ChatbotRetrievalService $retrieval): int
    {
        try {
            $retrieval->assertFts5Available();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $hash = $helpdesk->refreshCache();
        $this->info("Helpdesk content parsed and cached (hash {$hash}).");

        $count = $retrieval->rebuild();
        $this->info("Indexed {$count} sections into {$retrieval->indexPath()}.");

        return self::SUCCESS;
    }
}
