<?php

namespace App\Services\Chatbot;

use PDO;
use RuntimeException;

/**
 * Lexical full-text retrieval over helpdesk sections and guide topics.
 *
 * Uses a standalone SQLite FTS5 index file with BM25 ranking — no vector
 * database, embeddings, or external search service. The index is disposable:
 * it is rebuilt atomically from the parsed content whenever the helpdesk
 * content hash changes (or via `php artisan chatbot:index`).
 */
class ChatbotRetrievalService
{
    /** Words carrying no retrieval signal, removed before matching. */
    private const STOP_WORDS = [
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'can', 'shall', 'to', 'of', 'in', 'for',
        'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during',
        'before', 'after', 'and', 'but', 'or', 'nor', 'not', 'so', 'yet',
        'both', 'either', 'neither', 'each', 'every', 'all', 'any', 'few',
        'more', 'most', 'other', 'some', 'such', 'no', 'only', 'own', 'same',
        'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those',
        'it', 'its', 'how', 'i', 'me', 'my', 'we', 'our', 'you', 'your',
        'he', 'him', 'his', 'she', 'her', 'they', 'them', 'their', 'about',
        'just', 'like', 'know', 'want', 'need', 'tell', 'ask', 'please', 'thanks',
    ];

    /**
     * BM25 column weights, in table column order:
     * source_type, source_key, slug, heading, audience_group, body.
     * Headings are the strongest signal; slug tokens approximate the article title.
     */
    private const BM25_WEIGHTS = '0, 0, 2.0, 3.0, 0, 1.0';

    private ?PDO $db = null;

    public function __construct(
        private readonly ChatbotHelpdeskService $helpdesk,
        private readonly ChatbotGuideService $guide,
    ) {}

    public function indexPath(): string
    {
        return config('ai-chatbot.retrieval.index_path');
    }

    // ──────────────────────────────────────────────
    //  Index lifecycle
    // ──────────────────────────────────────────────

    /**
     * Rebuild the FTS index from the parsed content, swapping it in atomically
     * (build to a temp file, then rename). Returns the number of indexed sections.
     */
    public function rebuild(): int
    {
        $this->assertFts5Available();

        $path = $this->indexPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmpPath = $path.'.tmp';
        @unlink($tmpPath);

        $db = new PDO('sqlite:'.$tmpPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec(
            "CREATE VIRTUAL TABLE sections USING fts5(
                source_type UNINDEXED,
                source_key UNINDEXED,
                slug,
                heading,
                audience_group UNINDEXED,
                body,
                tokenize = 'porter unicode61'
            )"
        );
        $db->exec('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT)');

        $insert = $db->prepare(
            'INSERT INTO sections (source_type, source_key, slug, heading, audience_group, body)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $count = 0;
        $db->beginTransaction();

        foreach ($this->helpdesk->getAllParsedArticles() as $slug => $article) {
            foreach ($article['sections'] as $section) {
                $insert->execute([
                    'helpdesk',
                    "{$slug}::{$section['heading']}",
                    $slug,
                    $section['heading'],
                    $article['audience_group'],
                    $section['content'],
                ]);
                $count++;
            }
        }

        foreach ($this->guide->getAllTopics() as $key => $topic) {
            $body = $this->guide->getSections([$key]);
            if ($body === '') {
                continue;
            }
            $insert->execute([
                'guide',
                $key,
                str_replace('_', '-', $key),
                $topic['heading'],
                'OFW & Public',
                // Description adds matching signal; answer content is loaded
                // from the source service at answer time, never from the index.
                "{$topic['description']}\n\n{$body}",
            ]);
            $count++;
        }

        $meta = $db->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        $meta->execute(['content_hash', $this->helpdesk->contentHash()]);
        $db->commit();
        // Release every handle before the swap — prepared statements keep the
        // connection (and its Windows file lock) alive until destroyed.
        unset($insert, $meta);
        $db = null;

        $this->db = null;
        @unlink($path);
        if (! @rename($tmpPath, $path)) {
            throw new RuntimeException("Failed to activate rebuilt chatbot index at {$path}");
        }

        return $count;
    }

    /**
     * Rebuild the index if it is missing or was built from older content.
     */
    public function ensureFresh(): void
    {
        if (! file_exists($this->indexPath())
            || $this->indexedContentHash() !== $this->helpdesk->contentHash()) {
            $this->rebuild();
        }
    }

    /**
     * Throw loudly when the PHP build lacks SQLite FTS5 — retrieval cannot work without it.
     */
    public function assertFts5Available(): void
    {
        try {
            $probe = new PDO('sqlite::memory:');
            $probe->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $probe->exec('CREATE VIRTUAL TABLE __fts5_probe USING fts5(x)');
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'SQLite FTS5 is not available in this PHP build — chatbot retrieval requires pdo_sqlite with FTS5. '
                .$e->getMessage()
            );
        }
    }

    // ──────────────────────────────────────────────
    //  Query path
    // ──────────────────────────────────────────────

    /**
     * Retrieve the top-ranked sections for a user message.
     *
     * Pipeline: tokenize → stop-word filter → synonym expansion → FTS5 MATCH
     * with BM25 → audience-group filter → top N with scores (higher = better).
     *
     * @param  list<string>|null  $groups  Allowed audience groups, or null for all (admin)
     * @return list<array{source_type: string, source_key: string, slug: string, heading: string, audience_group: string, score: float}>
     */
    public function search(string $message, ?array $groups, ?int $limit = null): array
    {
        $limit ??= (int) config('ai-chatbot.retrieval.max_results', 3);

        $terms = $this->expandTerms($this->tokenize($message));
        if ($terms === []) {
            return [];
        }

        $this->ensureFresh();

        $match = implode(' OR ', array_map(
            fn (string $t) => '"'.str_replace('"', '', $t).'"',
            $terms,
        ));

        $sql = 'SELECT source_type, source_key, slug, heading, audience_group,
                       -bm25(sections, '.self::BM25_WEIGHTS.') AS score
                FROM sections
                WHERE sections MATCH :match';
        $params = ['match' => $match];

        if ($groups !== null) {
            $placeholders = [];
            foreach (array_values($groups) as $i => $group) {
                $placeholders[] = ":g{$i}";
                $params["g{$i}"] = $group;
            }
            $sql .= ' AND audience_group IN ('.implode(', ', $placeholders).')';
        }

        $sql .= ' ORDER BY score DESC LIMIT '.max(1, (int) $limit);

        $stmt = $this->connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $minScore = (float) config('ai-chatbot.retrieval.min_score', 0.0);

        $hits = [];
        foreach ($rows as $row) {
            $score = (float) $row['score'];
            if ($score < $minScore) {
                continue;
            }
            $row['score'] = $score;
            $hits[] = $row;
        }

        return $hits;
    }

    /**
     * Load the answer-time content for a list of hits from the source services
     * (the index stores matching text only, never serves content).
     *
     * @param  list<array{source_type: string, source_key: string}>  $hits
     */
    public function contentFor(array $hits): string
    {
        $guideKeys = [];
        $helpdeskIds = [];

        foreach ($hits as $hit) {
            if ($hit['source_type'] === 'guide') {
                $guideKeys[] = $hit['source_key'];
            } else {
                $helpdeskIds[] = $hit['source_key'];
            }
        }

        $parts = [];
        if ($guideKeys !== []) {
            $content = $this->guide->getSections($guideKeys);
            if ($content !== '') {
                $parts[] = $content;
            }
        }
        if ($helpdeskIds !== []) {
            $content = $this->helpdesk->getSections($helpdeskIds);
            if ($content !== '') {
                $parts[] = $content;
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Meaningful lowercase tokens: length > 2, not a stop word, deduplicated.
     *
     * @return list<string>
     */
    public function tokenize(string $message): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($message)) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $w) => mb_strlen($w) > 2 && ! in_array($w, self::STOP_WORDS, true),
        )));
    }

    /**
     * Add configured synonyms/phrases for any token present in the query.
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function expandTerms(array $tokens): array
    {
        $synonyms = config('ai-chatbot.retrieval.synonyms', []);

        $terms = $tokens;
        foreach ($tokens as $token) {
            foreach ($synonyms[$token] ?? [] as $expansion) {
                $terms[] = mb_strtolower($expansion);
            }
        }

        return array_values(array_unique($terms));
    }

    private function indexedContentHash(): ?string
    {
        try {
            $value = $this->connection()
                ->query("SELECT value FROM meta WHERE key = 'content_hash'")
                ->fetchColumn();

            return $value === false ? null : (string) $value;
        } catch (\Throwable) {
            return null;
        }
    }

    private function connection(): PDO
    {
        if ($this->db === null) {
            $this->db = new PDO('sqlite:'.$this->indexPath());
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return $this->db;
    }
}
