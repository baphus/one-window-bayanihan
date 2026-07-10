<?php

namespace App\Services\Chatbot;

/**
 * Deterministic (zero-LLM) intent detection for chatbot messages.
 *
 * Classifies into: greeting, identity, gibberish, or content_query.
 * Follow-up detection is a separate signal (isFollowUpCandidate) because it
 * also depends on stored session context and retrieval strength, which the
 * controller combines.
 */
class ChatbotIntentService
{
    public const GREETING = 'greeting';

    public const IDENTITY = 'identity';

    public const GIBBERISH = 'gibberish';

    public const CONTENT_QUERY = 'content_query';

    /** English + Filipino greeting openers. Message must also be short (see classify). */
    private const GREETING_PATTERNS = [
        '/^(hi|hii+|hello|helo|hey|heya|yo|howdy|greetings|sup)\b/iu',
        '/^good\s+(morning|afternoon|evening|day|am|pm)\b/iu',
        '/^(kumusta|kamusta|musta)\b/iu',
        '/^magandang\s+(umaga|hapon|gabi|araw)\b/iu',
    ];

    /** Questions about who/what the assistant is or can do. */
    private const IDENTITY_PATTERNS = [
        '/\bwho\s+(are|r)\s+(you|u)\b/iu',
        '/\bwhat\s+are\s+you\b/iu',
        '/\bwhat\s+(can|do)\s+you\s+(do|help)\b/iu',
        '/\bhow\s+can\s+you\s+help\b/iu',
        '/\bwhat\s+is\s+your\s+name\b/iu',
        '/\b(who|what)\s+is\s+bayani\b/iu',
        '/\bintroduce\s+yourself\b/iu',
        '/\bare\s+you\s+(a\s+|an\s+)?(bot|robot|ai|human|person|real)\b/iu',
        '/\b(sino|ano)\s+ka(\s+ba)?\b/iu',
    ];

    /** Bare requests to repeat/clarify — the user is confused, not asking content. */
    private const CLARIFICATION_PATTERNS = [
        '/^(what|huh|eh|ha|hm+|wat|wut)\??!*$/iu',
        '/^(say|repeat)\s+(that|it)(\s+again)?\??$/iu',
        '/^come\s+again\??$/iu',
        '/^(pardon|sorry)(\s*me)?\??$/iu',
        "/^i\s+(don'?t|do\s+not)\s+understand\.?$/iu",
    ];

    /** Deictic/pronoun references that only make sense with prior context. */
    private const DEICTIC_PATTERN =
        '/\b(it|that|this|those|these|them|they|the\s+same|one[s]?)\b|^(what|how)\s+about\b|^and\s+/iu';

    public function __construct(
        private readonly ChatbotRetrievalService $retrieval,
    ) {}

    /**
     * Classify a message into greeting, identity, gibberish, or content_query.
     */
    public function classify(string $message): string
    {
        $trimmed = trim($message);
        $wordCount = count(preg_split('/\s+/u', $trimmed) ?: []);

        // Greetings are short salutations with no substantive question attached.
        if ($wordCount <= 5) {
            foreach (self::GREETING_PATTERNS as $pattern) {
                if (preg_match($pattern, $trimmed)) {
                    return self::GREETING;
                }
            }
        }

        foreach (self::IDENTITY_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return self::IDENTITY;
            }
        }

        foreach (self::CLARIFICATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return self::GIBBERISH;
            }
        }

        $tokens = $this->retrieval->tokenize($trimmed);

        // Nothing meaningful left after stop-word removal → unintelligible.
        if ($tokens === []) {
            return self::GIBBERISH;
        }

        // Every token looks like keyboard mash → unintelligible.
        $realWords = array_filter($tokens, fn (string $t) => $this->looksLikeWord($t));
        if ($realWords === []) {
            return self::GIBBERISH;
        }

        return self::CONTENT_QUERY;
    }

    /**
     * Whether the message reads like a continuation of a prior topic: short
     * and/or leaning on deictic references instead of naming its subject.
     * The controller combines this with stored session context and retrieval
     * strength to decide whether to reuse the previous source.
     */
    public function isFollowUpCandidate(string $message): bool
    {
        $trimmed = trim($message);
        $wordCount = count(preg_split('/\s+/u', $trimmed) ?: []);

        if ($wordCount <= 12 && preg_match(self::DEICTIC_PATTERN, $trimmed)) {
            return true;
        }

        // Short questions rarely carry their own topic ("what documents do I need?").
        return $wordCount <= 6;
    }

    /**
     * Heuristic "is this a real word" check: must contain a vowel, must not be
     * a home-row run or a long same-character repeat.
     */
    private function looksLikeWord(string $token): bool
    {
        // Numbers (tracker numbers, years) count as meaningful.
        if (preg_match('/\d/', $token)) {
            return true;
        }

        if (! preg_match('/[aeiou]/i', $token)) {
            return false;
        }

        if (preg_match('/(.)\1{3,}/i', $token)) {
            return false;
        }

        // Home-row mash like "asdfgh", "jkl;asdf" (vowel 'a' alone doesn't redeem it).
        if (preg_match('/^[asdfghjkl]+$/i', $token) && strlen($token) >= 4) {
            return false;
        }

        return true;
    }
}
