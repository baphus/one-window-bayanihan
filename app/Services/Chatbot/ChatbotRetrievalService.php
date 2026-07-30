<?php

namespace App\Services\Chatbot;

use App\Models\Agency;
use App\Models\Service;

/**
 * Content loader for the chatbot: loads article content from source services
 * and tokenizes queries for intent detection.
 *
 * FTS5 search and indexing have been removed — pgvector is the sole search
 * engine. This service provides content loading (contentFor) and text
 * tokenization (tokenize) used by the intent classifier.
 */
class ChatbotRetrievalService
{
    /** Words carrying no retrieval signal, removed before tokenizing. */
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

    public function __construct(
        private readonly ChatbotHelpdeskService $helpdesk,
        private readonly ChatbotGuideService $guide,
    ) {}

    // ──────────────────────────────────────────────
    //  Content loading
    // ──────────────────────────────────────────────

    /**
     * Load the answer-time content for a list of hits from the source services.
     *
     * @param  list<array{source_type: string, source_key: string}>  $hits
     */
    public function contentFor(array $hits): string
    {
        $guideKeys = [];
        $helpdeskIds = [];
        $referenceIds = [];

        foreach ($hits as $hit) {
            match ($hit['source_type']) {
                'guide' => $guideKeys[] = $hit['source_key'],
                'reference' => $referenceIds[] = $hit['source_key'],
                default => $helpdeskIds[] = $hit['source_key'],
            };
        }

        $parts = [];
        if ($guideKeys !== []) {
            // Guide source_keys have "guide:" prefix — strip it for lookup
            $stripped = array_map(fn ($k) => str_starts_with($k, 'guide:') ? substr($k, 6) : $k, $guideKeys);
            $content = $this->guide->getSections($stripped);
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
        if ($referenceIds !== []) {
            foreach ($referenceIds as $refId) {
                $content = $this->referenceContent($refId);
                if ($content !== '') {
                    $parts[] = $content;
                }
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * Reconstruct readable content for a reference (database) hit.
     * The source_key is "agency:{slug}" — load the agency and all its services from DB.
     */
    private function referenceContent(string $sourceKey): string
    {
        if (! str_starts_with($sourceKey, 'agency:')) {
            return '';
        }

        $agencySlug = substr($sourceKey, 7); // strip "agency:" prefix
        $agency = Agency::query()
            ->where('slug', $agencySlug)
            ->where('is_deleted', false)
            ->first();

        if (! $agency) {
            return '';
        }

        $services = $agency->services()
            ->where('is_deleted', false)
            ->with('requirements', fn ($q) => $q->where('is_deleted', false))
            ->get();

        $parts = ["# {$agency->name}"];

        if ($agency->short) {
            $parts[] = '';
            $parts[] = "**{$agency->short}**";
        }

        if ($agency->description) {
            $parts[] = '';
            $parts[] = $agency->description;
        }

        if ($agency->contact_info) {
            $parts[] = '';
            $parts[] = '**Contact Info:**';
            $parts[] = $agency->contact_info;
        }

        if ($agency->location_query) {
            $parts[] = '';
            $parts[] = "**Location:** {$agency->location_query}";
        }

        if ($services->isNotEmpty()) {
            $parts[] = '';
            $parts[] = '**Services:**';

            foreach ($services as $service) {
                $parts[] = '';
                $parts[] = "## {$service->name}";

                if ($service->description) {
                    $parts[] = '';
                    $parts[] = $service->description;
                }

                if ($service->processing_days) {
                    $parts[] = '';
                    $parts[] = "Processing time: {$service->processing_days} days";
                }

                $requirements = $service->requirements->filter(fn ($r) => ! $r->is_deleted);
                if ($requirements->isNotEmpty()) {
                    $parts[] = '';
                    $parts[] = '**Requirements:**';
                    foreach ($requirements as $req) {
                        $tag = $req->is_required ? '(required)' : '(optional)';
                        $parts[] = "- {$req->name} {$tag}";
                    }
                }
            }
        }

        return implode("\n", $parts);
    }

    // ──────────────────────────────────────────────
    //  Tokenization (used by intent classifier)
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
}
