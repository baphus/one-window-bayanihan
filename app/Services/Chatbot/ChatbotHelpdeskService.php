<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;

class ChatbotHelpdeskService
{
    private string $contentDir;

    private string $articlesTsPath;

    private string $categoriesTsPath;

    /**
     * Fully parsed helpdesk content, loaded once per content version from the
     * persistent cache (see parsed()).
     *
     * @var array{titles: array<string, string>, sections: array<string, array<string, array{heading: string, content: string}>>, articles: array<string, array{title: string, excerpt: string, categorySlug: string}>, groups: array<string, string>}|null
     */
    private ?array $parsed = null;

    /** Articles whose sections are included in the classifier-miss fallback. */
    private array $fallbackSlugs = [
        'using-public-tracking-portal',
        'troubleshooting-common-issues',
    ];

    public function __construct()
    {
        $this->contentDir = resource_path('js/data/helpdesk/content');
        $this->articlesTsPath = resource_path('js/data/helpdesk/articles.ts');
        $this->categoriesTsPath = resource_path('js/data/helpdesk/categories.ts');
    }

    // ── Content versioning + persistent cache ──

    /**
     * Hash of the helpdesk content files (name, mtime, size). Changes whenever
     * any article, the article index, or the category tree changes — used as
     * the cache key so edits invalidate automatically.
     */
    public function contentHash(): string
    {
        $files = glob("{$this->contentDir}/*.ts") ?: [];
        $files[] = $this->articlesTsPath;
        $files[] = $this->categoriesTsPath;

        $parts = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $parts[] = basename($file).'|'.filemtime($file).'|'.filesize($file);
            }
        }
        sort($parts);

        return md5(implode("\n", $parts));
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
        if ($this->parsed !== null) {
            return $this->parsed;
        }

        return $this->parsed = Cache::remember(
            'chatbot.helpdesk.'.$this->contentHash(),
            now()->addWeek(),
            fn () => $this->parseAll(),
        );
    }

    /**
     * Parse every content file plus the article/category indexes in one pass.
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

        return [
            'titles' => $titles,
            'sections' => $sections,
            'articles' => $this->parseArticlesTsFile(),
            'groups' => $this->buildAudienceGroupsFile(),
        ];
    }

    // ── Public API ──

    /**
     * Return full article content for the given slugs, loaded from the TypeScript source files.
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
     * Return a classifier-friendly listing of available articles, grouped by audience.
     *
     * @param  list<string>|null  $groupNames  Only include these audience groups, or all if null
     */
    public function getArticleList(?array $groupNames = null): string
    {
        $meta = $this->parseArticlesTs();
        $groups = $this->buildAudienceGroups();

        // Group slugs by audience group using their category's top-level parent
        $grouped = [];
        foreach ($meta as $slug => $info) {
            $group = $groups[$info['categorySlug']] ?? 'General';
            if ($groupNames !== null && ! in_array($group, $groupNames, true)) {
                continue;
            }
            $grouped[$group][] = $slug;
        }

        $lines = [];
        foreach ($grouped as $group => $slugs) {
            $lines[] = "### {$group}";
            foreach ($slugs as $slug) {
                $lines[] = "- {$slug}: {$meta[$slug]['excerpt']}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Check if a slug refers to a known article.
     */
    public function has(string $slug): bool
    {
        return file_exists("{$this->contentDir}/{$slug}.ts");
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
     * @return array<string, array{title: string, audience_group: string, sections: array<string, array{heading: string, content: string}>}>
     */
    public function getAllParsedArticles(): array
    {
        $parsed = $this->parsed();

        $result = [];
        foreach ($parsed['sections'] as $slug => $sections) {
            $categorySlug = $parsed['articles'][$slug]['categorySlug'] ?? null;
            $group = $categorySlug !== null
                ? ($parsed['groups'][$categorySlug] ?? 'General')
                : 'General';

            $result[$slug] = [
                'title' => $parsed['titles'][$slug] ?? $slug,
                'audience_group' => $group,
                'sections' => $sections,
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
        $slugs = $this->discoverSlugs();
        $meta = $this->parseArticlesTs();
        $groups = $this->buildAudienceGroups();

        $grouped = [];
        foreach ($slugs as $slug) {
            $group = 'General';
            if (isset($meta[$slug]) && isset($groups[$meta[$slug]['categorySlug']])) {
                $group = $groups[$meta[$slug]['categorySlug']];
            }

            if ($groupNames !== null && ! in_array($group, $groupNames, true)) {
                continue;
            }

            $sections = $this->parseSections($slug);
            foreach ($sections as $section) {
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
    public function getSections(array $identifiers): string
    {
        $parts = [];
        foreach ($identifiers as $id) {
            $colonPos = strpos($id, '::');
            if ($colonPos === false) {
                continue;
            }

            $slug = substr($id, 0, $colonPos);
            $heading = substr($id, $colonPos + 2);

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
     * Return all sections as flat key-value pairs for embedding.
     *
     * Key:  "slug::Section Heading"
     * Value: Text suitable for embedding (heading + first sentence from content).
     *
     * @return array<string, string>
     */
    public function getAllSectionTexts(): array
    {
        $result = [];
        foreach ($this->discoverSlugs() as $slug) {
            $title = $this->getTitle($slug) ?? $slug;
            $sections = $this->parseSections($slug);
            foreach ($sections as $section) {
                $id = "{$slug}::{$section['heading']}";
                $snippet = $this->firstSentence($section['content']);
                $text = $snippet !== ''
                    ? "{$section['heading']}: {$snippet}"
                    : $section['heading'];
                $result[$id] = "{$title} — {$text}";
            }
        }

        return $result;
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
     * Discover all article slugs from the content directory.
     *
     * @return list<string>
     */
    private function discoverSlugs(): array
    {
        $files = glob($this->contentDir.'/*.ts');
        $slugs = array_map(fn ($f) => pathinfo($f, PATHINFO_FILENAME), $files);
        sort($slugs);

        return $slugs;
    }

    /**
     * Extract the markdown content from a TypeScript template-literal article file.
     * Handles: const content = `...`; export default content;
     */
    private function loadContent(string $slug): ?string
    {
        $path = "{$this->contentDir}/{$slug}.ts";
        if (! file_exists($path)) {
            return null;
        }

        $ts = file_get_contents($path);
        $first = strpos($ts, '`');
        $last = strrpos($ts, '`');

        if ($first === false || $last === false || $last <= $first) {
            return null;
        }

        return substr($ts, $first + 1, $last - $first - 1);
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

    /**
     * Cached slug → title + excerpt + categorySlug mapping.
     *
     * @return array<string, array{title: string, excerpt: string, categorySlug: string}>
     */
    private function parseArticlesTs(): array
    {
        return $this->parsed()['articles'];
    }

    /**
     * Parse articles.ts to extract slug → title + excerpt + categorySlug mapping.
     *
     * @return array<string, array{title: string, excerpt: string, categorySlug: string}>
     */
    private function parseArticlesTsFile(): array
    {
        if (! file_exists($this->articlesTsPath)) {
            return [];
        }

        $ts = file_get_contents($this->articlesTsPath);
        preg_match_all(
            '/title:\s*"([^"]++)",\s*slug:\s*"([^"]++)",\s*excerpt:\s*"([^"]++)",\s*content:\s*\w+,\s*categoryId:\s*CATEGORY\["([^"]++)"\]/s',
            $ts,
            $matches,
            PREG_SET_ORDER,
        );

        $articles = [];
        foreach ($matches as $m) {
            $articles[$m[2]] = [
                'title' => $m[1],
                'excerpt' => $m[3],
                'categorySlug' => $m[4],
            ];
        }

        return $articles;
    }

    /**
     * Return structured article metadata (title + excerpt) filtered by audience group.
     *
     * @param  list<string>|null  $groupNames  Only include these audience groups, or all if null
     * @return array<string, array{title: string, excerpt: string}>
     */
    public function getArticleMeta(?array $groupNames = null): array
    {
        $meta = $this->parseArticlesTs();
        $groups = $this->buildAudienceGroups();

        $result = [];
        foreach ($meta as $slug => $info) {
            $group = $groups[$info['categorySlug']] ?? 'General';
            if ($groupNames !== null && ! in_array($group, $groupNames, true)) {
                continue;
            }
            $result[$slug] = [
                'title' => $info['title'],
                'excerpt' => $info['excerpt'],
            ];
        }

        return $result;
    }

    /**
     * Cached category slug → audience group mapping.
     *
     * @return array<string, string> category slug → audience group name
     */
    private function buildAudienceGroups(): array
    {
        return $this->parsed()['groups'];
    }

    /**
     * Build a slug → audience group mapping from the category hierarchy.
     *
     * @return array<string, string> category slug → audience group name
     */
    private function buildAudienceGroupsFile(): array
    {
        if (! file_exists($this->categoriesTsPath)) {
            return [];
        }

        $ts = file_get_contents($this->categoriesTsPath);

        // Parse all categories: id, name, slug, parentId
        preg_match_all(
            '/\{\s*id:\s*"([^"]++)",\s*name:\s*"([^"]++)",\s*slug:\s*"([^"]++)",.*?parentId:\s*(null|"[^"]++"),/s',
            $ts,
            $matches,
            PREG_SET_ORDER,
        );

        $categories = [];
        foreach ($matches as $m) {
            $categories[$m[1]] = [
                'slug' => $m[3],
                'parentId' => $m[4] === 'null' ? null : trim($m[4], '"'),
            ];
        }

        // Map top-level parent category slugs to audience group names
        $topLevelMap = [
            'ofw-assistance' => 'OFW & Public',
            'case-management' => 'Case Managers',
            'agency-partnership' => 'Agency Focal Persons',
            'system-administration' => 'Administrators',
        ];

        // Build ID → group mapping (parent categories first)
        $idToGroup = [];
        foreach ($categories as $id => $cat) {
            if ($cat['parentId'] === null && isset($topLevelMap[$cat['slug']])) {
                $idToGroup[$id] = $topLevelMap[$cat['slug']];
            }
        }
        // FAQ (cat-5) also belongs to OFW & Public
        $idToGroup['cat-5'] = 'OFW & Public';

        // Propagate group membership to child categories
        foreach ($categories as $id => $cat) {
            if ($cat['parentId'] !== null && isset($idToGroup[$cat['parentId']])) {
                $idToGroup[$id] = $idToGroup[$cat['parentId']];
            }
        }

        // Map slug → group
        $slugToGroup = [];
        foreach ($categories as $id => $cat) {
            if (isset($idToGroup[$id])) {
                $slugToGroup[$cat['slug']] = $idToGroup[$id];
            }
        }

        return $slugToGroup;
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
