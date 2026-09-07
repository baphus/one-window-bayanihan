<?php

namespace App\Services\Chatbot;

use RuntimeException;

/** Mutable evidence and budgets belong to one turn, never a singleton/session. */
final class ChatbotTurn
{
    private float $started;

    private int $calls = 0;

    private int $characters = 0;

    private array $evidence = [];

    private bool $exhausted = false;

    public function __construct()
    {
        $this->started = microtime(true);
    }

    public function remaining(): float
    {
        $remaining = config('ai-chatbot.timeout', 45) - (microtime(true) - $this->started);
        if ($remaining <= 0 || $this->exhausted) {
            throw new RuntimeException('chatbot_deadline');
        }

        return $remaining;
    }

    public function call(): void
    {
        $this->remaining();
        if (++$this->calls > config('ai-chatbot.max_tool_calls', 8)) {
            $this->exhausted = true;
            throw new RuntimeException('chatbot_tool_limit');
        }
    }

    public function availableCharacters(): int
    {
        return max(0, config('ai-chatbot.max_context_characters', 24000) - $this->characters);
    }

    public function register(array $source, string $content): string
    {
        $this->remaining();
        $key = $source['source_type'].':'.$source['slug'];
        $distinct = array_unique(array_map(fn ($entry) => $entry['source']['source_type'].':'.$entry['source']['slug'], $this->evidence));
        if (! in_array($key, $distinct, true) && count($distinct) >= config('ai-chatbot.max_article_reads', 6)) {
            $this->exhausted = true;
            throw new RuntimeException('chatbot_read_limit');
        }
        if (mb_strlen($content) > $this->availableCharacters()) {
            $this->exhausted = true;
            throw new RuntimeException('chatbot_content_limit');
        }
        $this->characters += mb_strlen($content);
        $id = 'e'.(count($this->evidence) + 1);
        $this->evidence[$id] = ['source' => $source, 'content' => $content];

        return $id;
    }

    public function sources(array $ids): array
    {
        $sources = [];
        foreach ($ids as $id) {
            if (! is_string($id) || ! isset($this->evidence[$id])) {
                throw new RuntimeException('chatbot_invalid_evidence');
            }
            $source = $this->evidence[$id]['source'];
            $key = $source['source_type'].':'.$source['slug'];
            $source['sections'] = array_values(array_unique(array_merge($sources[$key]['sections'] ?? [], $source['sections'] ?? [])));
            $sources[$key] = $source;
        }

        return array_values($sources);
    }

    public function metrics(): array
    {
        return ['duration_ms' => (int) ((microtime(true) - $this->started) * 1000), 'tool_calls' => $this->calls, 'evidence_ids' => array_keys($this->evidence)];
    }
}
