<?php

namespace App\Services\Content;

class ContentSanitizerService
{
    /**
     * Sanitize article content for LLM consumption.
     * Strips HTML, removes zero-width characters, normalizes whitespace,
     * and strips prompt injection patterns.
     */
    public function sanitizeForLLM(string $content): string
    {
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);
        $content = $this->stripPromptInjection($content);

        return trim($content);
    }

    /**
     * Remove known prompt injection patterns from content.
     */
    public function stripPromptInjection(string $content): string
    {
        $patterns = config('ai-helpcenter.injection_patterns', [
            'ignore previous instructions',
            'reveal system prompt',
            'you are now',
            'forget all previous',
            'new instructions',
            'ignore all instructions',
            'you must now',
            'forget everything',
            'override instructions',
            'disregard',
        ]);

        $escaped = array_map(fn (string $p) => preg_quote($p, '/'), $patterns);
        $regex = '/('.implode('|', $escaped).')/i';

        return preg_replace($regex, '', $content);
    }

    /**
     * Truncate content to an approximate token limit.
     * Uses a rough heuristic of ~1.3 tokens per word.
     */
    public function truncateToTokens(string $content, int $maxTokens = 1000): string
    {
        $estimatedTokens = str_word_count($content) * 1.3;

        if ($estimatedTokens <= $maxTokens) {
            return $content;
        }

        $maxWords = (int) floor($maxTokens / 1.3);
        $words = str_word_count($content, 1);
        $truncated = implode(' ', array_slice($words, 0, $maxWords));

        return $truncated."\n\n[Content truncated due to length]";
    }
}
