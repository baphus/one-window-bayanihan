<?php

namespace App\Services\Chatbot;

final class ChatbotKnowledge
{
    public function __construct(private readonly ChatbotHelpdeskService $helpdesk) {}

    public function articles(ChatbotAudience $audience): array
    {
        return array_filter($this->helpdesk->getAllParsedArticles(), fn ($article) => $audience->allows($article['audience_group']));
    }

    public function search(string $query, ChatbotAudience $audience, int $limit = 5): array
    {
        $tokens = $this->tokens(mb_substr($query, 0, 500));
        if ($tokens === []) {
            return [];
        }
        $results = [];
        foreach ($this->articles($audience) as $slug => $article) {
            $titleTokens = $this->tokens($article['title'].' '.$slug);
            $best = null;
            foreach ($article['sections'] as $section) {
                $headingTokens = $this->tokens($section['heading']);
                $bodyTokens = $this->tokens($section['content']);
                $score = count(array_intersect($tokens, $titleTokens)) * 5
                    + count(array_intersect($tokens, $headingTokens)) * 3
                    + count(array_intersect($tokens, $bodyTokens));
                if (mb_strtolower(trim($query)) === mb_strtolower($article['title'])) {
                    $score += 100;
                }
                if ($score > 0 && ($best === null || $score > $best['score'])) {
                    $best = ['slug' => $slug, 'article_title' => $article['title'], 'section' => $section['heading'], 'excerpt' => mb_substr($section['content'], 0, 220), 'score' => $score];
                }
            }
            if ($best !== null) {
                $results[] = $best;
            }
        }
        usort($results, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['slug'], $b['slug']));

        return array_slice($results, 0, max(1, min(5, $limit)));
    }

    public function read(string $slug, array $headings, ChatbotAudience $audience, ChatbotTurn $turn): array
    {
        $article = $this->articles($audience)[$slug] ?? null;
        if ($article === null) {
            return ['status' => 'not_found'];
        }
        $selected = $headings === [] ? $article['sections'] : array_intersect_key($article['sections'], array_flip($headings));
        $missing = array_values(array_diff($headings, array_keys($article['sections'])));
        $content = '';
        $included = [];
        $omitted = [];
        $budget = min(12000, $turn->availableCharacters());
        foreach ($selected as $heading => $section) {
            $block = '## '.$heading."\n".$section['content']."\n\n";
            if (mb_strlen($content.$block) > $budget) {
                $omitted[] = $heading;

                continue;
            }
            $content .= $block;
            $included[] = $heading;
        }
        if ($content === '') {
            return ['status' => 'no_content', 'available_sections' => array_keys($article['sections']), 'missing_sections' => $missing, 'omitted_sections' => $omitted];
        }
        $source = ['source_type' => 'helpdesk', 'slug' => $slug, 'heading' => $article['title'], 'article_title' => $article['title'], 'url' => route('helpdesk.show', $slug), 'sections' => $included];

        return ['status' => 'success', 'evidence_id' => $turn->register($source, $content), 'article_title' => $article['title'], 'content' => $content, 'available_sections' => array_keys($article['sections']), 'missing_sections' => $missing, 'omitted_sections' => $omitted];
    }

    private function tokens(string $text): array
    {
        $text = mb_strtolower($text);
        foreach (['password' => 'password security', 'mfa' => 'mfa security', 'otp' => 'otp verification', 'track' => 'track tracking tracker', 'status' => 'status statuses', 'requirements' => 'requirements documents', 'paano' => 'how', 'subaybayan' => 'tracking', 'kaso' => 'case', 'unsaon' => 'how', 'subayon' => 'tracking', 'dokumento' => 'documents'] as $word => $replacement) {
            $text = preg_replace('/\b'.preg_quote($word, '/').'\b/u', $replacement, $text);
        }
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
        $stop = ['the', 'and', 'for', 'how', 'can', 'you', 'your', 'what', 'with', 'this', 'that', 'from', 'are', 'does', 'have', 'need', 'want', 'about', 'please', 'ang', 'ako', 'akong', 'aking', 'mga'];

        return array_values(array_unique(array_filter($words, fn ($word) => mb_strlen($word) > 2 && ! in_array($word, $stop, true))));
    }
}
