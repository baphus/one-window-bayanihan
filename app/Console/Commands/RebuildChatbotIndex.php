<?php

namespace App\Console\Commands;

use App\Services\Chatbot\ChatbotHelpdeskService;
use Illuminate\Console\Command;

class RebuildChatbotIndex extends Command
{
    protected $signature = 'chatbot:index';

    protected $description = 'Warm parsed helpdesk cache for chatbot tools (cache-warmer, no embeddings; content-hash auto-invalidates)';

    public function handle(ChatbotHelpdeskService $helpdesk): int
    {
        $helpdesk->refreshCache();
        $this->info('Helpdesk cache refreshed: '.count($helpdesk->getAllParsedArticles()).' articles.');

        return self::SUCCESS;
    }
}
