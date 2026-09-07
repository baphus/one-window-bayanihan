<?php

namespace App\Console\Commands;

use App\Services\Chatbot\ChatbotAudience;
use App\Services\Chatbot\ChatbotConversationService;
use App\Services\Chatbot\ChatbotKnowledge;
use Illuminate\Console\Command;

class EvaluateChatbot extends Command
{
    protected $signature = 'chatbot:evaluate {--live : Call the configured model using synthetic questions} {--case= : Run a single fixture ID}';

    protected $description = 'Evaluate helpdesk retrieval or run a synthetic model/citation smoke test';

    public function handle(ChatbotKnowledge $knowledge, ChatbotConversationService $conversation): int
    {
        $cases = json_decode(file_get_contents(base_path('tests/Fixtures/chatbot-questions.json')), true, flags: JSON_THROW_ON_ERROR);
        $passed = 0;
        $total = 0;
        $live = $this->option('live');
        if ($live) {
            $this->info('Provider: '.config('ai-chatbot.provider').' | Model: '.config('ai-chatbot.model'));
        }
        foreach ($cases as $case) {
            if ($this->option('case') && $case['id'] !== $this->option('case')) {
                continue;
            }
            if (! $live && $case['type'] !== 'retrieval') {
                continue;
            }
            $total++;
            $audience = new ChatbotAudience($case['role']);
            if ($live) {
                $result = $conversation->reply(['message' => $case['query'], 'history' => $case['history'] ?? [], 'lastContext' => $case['lastContext'] ?? null], $audience);
                $slugs = array_column($result['sources'], 'slug');
                $ok = $result['status'] === $case['expected_status'] && array_diff($case['expected_slugs'], $slugs) === [];
                $this->line(json_encode(['id' => $case['id'], 'passed' => $ok, ...$result], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                if ($result['status'] === 'unavailable') {
                    $this->error('Provider/output unavailable; evaluation stopped. Check metadata logs and retry the case when resolved.');

                    return self::FAILURE;
                }
            } else {
                $slugs = array_column($knowledge->search($case['query'], $audience), 'slug');
                $ok = array_diff($case['expected_slugs'], $slugs) === [];
                $this->line($case['id'].': '.($ok ? 'PASS' : 'FAIL').' ['.implode(', ', $slugs).']');
            }
            $passed += (int) $ok;
        }
        $this->info("{$passed}/{$total} passed. Live answers additionally require human review of factual support.");

        return $total > 0 && $passed / $total >= ($live ? 1 : 0.9) ? self::SUCCESS : self::FAILURE;
    }
}
