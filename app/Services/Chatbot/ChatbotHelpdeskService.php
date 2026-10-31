<?php

namespace App\Services\Chatbot;

use App\Helpers\CacheHelper;
use Illuminate\Support\Facades\Cache;

class ChatbotHelpdeskService
{
    private string $articlesPath;

    /**
     * Fully parsed helpdesk content, loaded once per content version from the
     * persistent cache (see parsed()).
     *
     * @var array{titles: array<string, string>, sections: array<string, array<string, array{heading: string, content: string}>>, tokens: array<string, array{title: list<string>, sections: array<string, array{heading: list<string>, body: list<string>}}>>}|null
     */
    private ?array $parsed = null;

    private ?string $parsedHash = null;

    /** Articles whose sections are included in the classifier-miss fallback. */
    private array $fallbackSlugs = [
        'using-public-tracking-portal',
        'troubleshooting-common-issues',
    ];

    public function __construct()
    {
        $this->articlesPath = resource_path('helpdesk/articles.json');
    }

    // ── Content versioning + persistent cache ──

    /**
     * Hash the canonical article corpus so body, metadata, and audience edits invalidate the cache.
     */
    public function contentHash(): string
    {
        return hash_file('sha256', $this->articlesPath);
    }

    /**
     * Drop the cached parse for the current content version and re-parse.
     * Returns the current content hash (used by the index rebuild command).
     */
    public function refreshCache(): string
    {
        $hash = $this->contentHash();
        Cache::forget("chatbot.helpdesk.{$hash}");
        $this->parsed = null;
        $this->parsed();

        return $hash;
    }

    /**
     * Load the fully parsed helpdesk content, from the persistent cache when warm.
     */
    private function parsed(): array
    {
        $hash = $this->contentHash();
        if ($this->parsed !== null && $this->parsedHash === $hash) {
            return $this->parsed;
        }

        $this->parsedHash = $hash;

        return $this->parsed = CacheHelper::safeRemember(
            'chatbot.helpdesk.'.$hash,
            now()->addWeek(),
            fn () => $this->parseAll(),
        );
    }

    /**
     * Parse every content file plus the article/category indexes in one pass.
     *
     * Also precomputes the per-section token sets search() scores against,
     * so a turn with up to 8 tool calls tokenizes the 238-section corpus
     * once per content version instead of once per section per search.
     */
    private function parseAll(): array
    {
        $titles = [];
        $sections = [];

        foreach ($this->discoverSlugs() as $slug) {
            $content = $this->loadContent($slug);
            if ($content === null) {
                continue;
            }
            $titles[$slug] = $this->extractTitle($content);
            $sections[$slug] = $this->splitSections($content);
        }

        $tokens = [];
        foreach ($sections as $slug => $slugSections) {
            $sectionTokens = [];
            foreach ($slugSections as $heading => $section) {
                $sectionTokens[$heading] = [
                    'heading' => ChatbotKnowledge::tokensFor($section['heading']),
                    'body' => ChatbotKnowledge::tokensFor($section['content']),
                ];
            }
            $tokens[$slug] = [
                'title' => ChatbotKnowledge::tokensFor(($titles[$slug] ?? $slug).' '.$slug),
                'sections' => $sectionTokens,
            ];
        }

        return [
            'titles' => $titles,
            'sections' => $sections,
            'tokens' => $tokens,

        ];
    }

    // ── Public API ──

    /**
     * Return full article content for the given slugs, loaded from the canonical JSON corpus.
     *
     * @param  list<string>  $slugs
     */
    public function getArticles(array $slugs): string
    {
        $parts = [];
        foreach ($slugs as $slug) {
            $content = $this->loadContent($slug);
            if ($content !== null) {
                $title = $this->extractTitle($content);
                $parts[] = "# {$title}\n\n{$content}";
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * Return all chatbot-facing articles as one block.
     */
    public function getAll(): string
    {
        return $this->getArticles($this->discoverSlugs());
    }

    /**
     * Check if a slug refers to a known article.
     */
    public function has(string $slug): bool
    {
        return in_array($slug, $this->discoverSlugs(), true);
    }

    /**
     * Get the article title from its markdown content.
     */
    public function getTitle(string $slug): ?string
    {
        return $this->parsed()['titles'][$slug] ?? null;
    }

    /**
     * Return every article's title, audience group, and parsed sections —
     * the full corpus used to build the retrieval index.
     *
     * @return array<string, array{title: string, audience_group: string, sections: array<string, array{heading: string, content: string}>, tokens: array{title: list<string>, sections: array<string, array{heading: list<string>, body: list<string>}>}|null}>
     */
    public function getAllParsedArticles(): array
    {
        $parsed = $this->parsed();

        $result = [];
        $audiences = array_column($this->sourceArticles(), 'audience', 'slug');
        foreach ($parsed['sections'] as $slug => $sections) {
            $group = match ($audiences[$slug] ?? 'ADMIN') {
                'public' => 'OFW & Public',
                'staff' => 'Staff',
                'CASE_MANAGER' => 'Case Managers',
                'AGENCY' => 'Agency Focal Persons',
                default => 'System Administrators',
            };

            $result[$slug] = [
                'title' => $parsed['titles'][$slug] ?? $slug,
                'audience_group' => $group,
                'sections' => $sections,
                'tokens' => $parsed['tokens'][$slug] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Get the section headings for a specific article.
     *
     * @return list<string>
     */
    public function getArticleHeadings(string $slug): array
    {
        $sections = $this->parseSections($slug);

        return array_map(fn ($s) => $s['heading'], $sections);
    }

    // ── Section-level API ──

    /**
     * Return a classifier-friendly listing of available sections, grouped by audience.
     *
     * Format: "article-slug::Section Heading" — one per line.
     * The classifier returns these identifiers to load only matching sections.
     *
     * @param  list<string>|null  $groupNames  Only include these audience groups, or all if null
     */
    public function getSectionList(?array $groupNames = null): string
    {
        $grouped = [];
        foreach ($this->getAllParsedArticles() as $slug => $article) {
            $group = $article['audience_group'];
            if ($groupNames !== null && ! in_array($group, $groupNames, true)) {
                continue;
            }
            foreach ($article['sections'] as $section) {
                $grouped[$group][] = "- {$slug}::{$section['heading']}";
            }
        }

        $lines = [];
        foreach ($grouped as $group => $entries) {
            $lines[] = "### {$group}";
            array_push($lines, ...$entries);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Load content for specific section identifiers.
     *
     * Each identifier is "article-slug::Section Heading". Only the matched section
     * content is loaded — the AI never sees irrelevant sections.
     *
     * @param  list<string>  $identifiers
     */
    public function getSections(array $identifiers, ?ChatbotAudience $audience = null): string
    {
        $allowed = (new ChatbotKnowledge($this))->articles($audience ?? new ChatbotAudience);
        $parts = [];
        foreach ($identifiers as $id) {
            $colonPos = strpos($id, '::');
            if ($colonPos === false) {
                continue;
            }

            $slug = substr($id, 0, $colonPos);
            $heading = substr($id, $colonPos + 2);
            if (! isset($allowed[$slug])) {
                continue;
            }

            $sections = $this->parseSections($slug);
            $matched = $this->findSection($sections, $heading);

            if ($matched !== null) {
                $title = $this->getTitle($slug) ?? $slug;
                $parts[] = "# {$title} > {$matched['heading']}\n\n{$matched['content']}";
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * Return a curated subset of sections for when the classifier returns no match.
     * Keeps the LLM grounded without dumping every article into context.
     */
    public function getFallbackSections(): string
    {
        $identifiers = [];
        foreach ($this->fallbackSlugs as $slug) {
            $sections = $this->parseSections($slug);
            $count = 0;
            foreach ($sections as $section) {
                if ($count >= 3) {
                    break;
                }
                $identifiers[] = "{$slug}::{$section['heading']}";
                $count++;
            }
        }

        return $this->getSections($identifiers);
    }

    // ── Private helpers ──

    /**
     * Discover all article slugs from the canonical corpus.
     *
     * @return list<string>
     */
    private function discoverSlugs(): array
    {
        return array_column($this->sourceArticles(), 'slug');
    }

    private function sourceArticles(): array
    {
        return json_decode(file_get_contents($this->articlesPath), true, flags: JSON_THROW_ON_ERROR);
    }

    private function loadContent(string $slug): ?string
    {
        foreach ($this->sourceArticles() as $article) {
            if ($article['slug'] === $slug) {
                return $article['content'];
            }
        }

        return null;
    }

    /**
     * Extract the first H1 heading from markdown content.
     */
    private function extractTitle(string $content): string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $m)) {
            return trim($m[1]);
        }

        return 'Untitled';
    }

    // ── Section parsing ──

    /**
     * Split an article into H2-level sections.
     *
     * Returns an associative array keyed by the original heading text:
     *   ['Section Heading' => ['heading' => 'Section Heading', 'content' => '…']]
     *
     * Content before the first H2 heading becomes an "Overview" section.
     * H3 subsections are included inside their parent H2 section.
     *
     * @return array<string, array{heading: string, content: string}>
     */
    private function parseSections(string $slug): array
    {
        return $this->parsed()['sections'][$slug] ?? [];
    }

    /**
     * Split raw markdown article content into H2-level sections.
     *
     * @return array<string, array{heading: string, content: string}>
     */
    private function splitSections(string $content): array
    {
        $parts = preg_split('/^##\s+/m', $content);
        $first = trim((string) array_shift($parts));

        $sections = [];

        // Content before the first H2 (H1 title intro / overview)
        if ($first !== '') {
            $body = trim(preg_replace('/^#\s+.*$/m', '', $first));
            if ($body !== '') {
                $sections['Overview'] = [
                    'heading' => 'Overview',
                    'content' => $body,
                ];
            }
        }

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $lines = explode("\n", $part);
            $heading = trim(array_shift($lines));
            $body = trim(implode("\n", $lines));

            if ($heading !== '' && $body !== '') {
                // Preserve an introduction even if an explicit Overview follows it.
                if ($heading === 'Overview' && isset($sections['Overview'])) {
                    $body = $sections['Overview']['content']."\n\n".$body;
                }
                $sections[$heading] = [
                    'heading' => $heading,
                    'content' => $body,
                ];
            }
        }

        return $sections;
    }

    /**
     * Find a section by heading text with case-insensitive fuzzy matching.
     *
     * Matching strategy (in order):
     * 1. Exact key match
     * 2. Normalised (lowercased, punctuation-stripped) comparison
     *
     * This handles LLM output variations like "Login Problems?" → "Login Problems".
     */
    private function findSection(array $sections, string $heading): ?array
    {
        // Exact match
        if (isset($sections[$heading])) {
            return $sections[$heading];
        }

        // Normalised fuzzy match
        $needle = $this->normalizeKey($heading);
        foreach ($sections as $key => $section) {
            if ($this->normalizeKey($key) === $needle) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Normalise a heading for fuzzy comparison: lowercase, strip non-alphanumeric,
     * collapse whitespace.
     */
    private function normalizeKey(string $heading): string
    {
        $key = preg_replace('/[^a-z0-9\s]+/i', ' ', $heading);

        return strtolower(trim(preg_replace('/\s+/', ' ', $key)));
    }

    /**
     * Extract the first meaningful sentence from section content.
     * Skips H3 headings, list items, and empty lines.
     */
    private function firstSentence(string $content): string
    {
        $lines = explode("\n", trim($content));
        $result = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $result = $line;
            break;
        }

        // Clean up list prefixes and truncate
        $result = preg_replace('/^[\d]+\.\s*/', '', $result);
        $result = preg_replace('/^-\s*/', '', $result);

        if (mb_strlen($result) > 200) {
            $result = mb_substr($result, 0, 197).'...';
        }

        return $result;
    }
}
