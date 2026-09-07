<?php

namespace App\Console\Commands;

use App\Services\Chatbot\ChatbotHelpdeskService;
use Illuminate\Console\Command;

class RebuildChatbotIndex extends Command
{
    protected $signature = 'chatbot:index';

    protected $description = 'Refresh parsed helpdesk content for chatbot tools (no embeddings)';

    public function handle(ChatbotHelpdeskService $helpdesk): int
    {
        $helpdesk->refreshCache();
        $this->info('Helpdesk cache refreshed: '.count($helpdesk->getAllParsedArticles()).' articles.');

        return self::SUCCESS;
    }
}
