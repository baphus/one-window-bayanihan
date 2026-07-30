<?php

namespace App\Services\Chatbot;

/**
 * Light text preprocessing for chatbot queries.
 *
 * Applied before embedding and intent classification. Intentionally minimal —
 * no aggressive regex, no stop-word removal, no stemming. Just enough to
 * normalize user input for consistent retrieval.
 */
class ChatbotQueryNormalizer
{
    /**
     * Normalize a user query for retrieval and embedding.
     *
     * Steps:
     * 1. Trim leading/trailing whitespace
     * 2. Unicode NFC normalization (canonical composition)
     * 3. Lowercase (UTF-8 safe)
     * 4. Collapse multiple whitespace into single space
     * 5. Strip control characters (keep newlines as spaces)
     */
    public function normalize(string $input): string
    {
        $text = trim($input);

        // Unicode NFC — ensures composed characters match their decomposed forms
        $text = \normalizer_normalize($text, \Normalizer::FORM_C) ?? $text;

        // Lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Strip control characters (U+0000–U+001F, U+007F–U+009F) except newlines
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $text);

        // Collapse whitespace
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Extract search keywords: strip common filler words that add noise to
     * retrieval but keep domain-specific terms.
     *
     * This is NOT a stop-word removal — it only removes the most generic
     * English/Filipino filler that every query contains.
     */
    public function extractKeywords(string $normalizedMessage): string
    {
        $filler = [
            'ang', 'ng', 'mga', 'sa', 'na', 'pa', 'po', 'ba', 'ka',
            'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been',
            'do', 'does', 'did', 'have', 'has', 'had', 'will', 'would',
            'can', 'could', 'should', 'may', 'might', 'shall',
            'i', 'you', 'he', 'she', 'it', 'we', 'they',
            'my', 'your', 'his', 'her', 'its', 'our', 'their',
            'me', 'him', 'us', 'them',
            'this', 'that', 'these', 'those',
            'what', 'which', 'who', 'whom', 'whose',
            'how', 'when', 'where', 'why',
            'please', 'tell', 'show', 'give', 'help', 'know',
        ];

        $words = preg_split('/\s+/u', $normalizedMessage, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            return $normalizedMessage;
        }

        $keywords = array_filter($words, fn (string $w) => ! in_array($w, $filler, true));

        return $keywords !== [] ? implode(' ', $keywords) : $normalizedMessage;
    }
}
