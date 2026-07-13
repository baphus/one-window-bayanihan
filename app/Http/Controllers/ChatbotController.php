<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\ChatbotGuideService;
use App\Services\Chatbot\ChatbotHelpdeskService;
use App\Services\Chatbot\ChatbotIntentService;
use App\Services\Chatbot\ChatbotRetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

/**
 * Chatbot message pipeline: injection guard → heuristic intent (zero LLM) →
 * FTS5 retrieval → verbatim tier (zero LLM) or a single LLM call.
 *
 * At most one LLM request is made per message; when the model backend is
 * unavailable the bot degrades to serving the retrieved section verbatim.
 */
class ChatbotController extends Controller
{
    private const MAX_RESPONSE_LENGTH = 2000;

    private const BLOCKED_PATTERNS = [
        '/ignore\s+(?:all\s+)?(?:previous|above|below)\s+(?:instructions|directives|commands)/i',
        '/you\s+are\s+(?:not\s+)?(?:an?\s+)?(?:AI|assistant|chatbot|language\s+model|bot)/i',
        '/system\s+(?:prompt|message|instruction|directive)/i',
        '/reveal\s+(?:your\s+)?(?:prompt|instructions|system\s+message|configuration)/i',
        '/output\s+(?:your\s+|the\s+)?(?:prompt|instructions|system|internal)/i',
        '/forget\s+(?:everything|all\s+(?:previous|prior)\s+)/i',
        '/new\s+instructions/i',
        '/act\s+as\s+(?:an?\s+)?(?:AI|assistant|different|new)/i',
        '/disregard\s+(?:all\s+)?(?:previous|prior)\s+/i',
        '/print\s+(?:the\s+)?(?:prompt|instructions|system)/i',
        '/dump\s+(?:the\s+)?(?:prompt|system)/i',
    ];

    /** Map of user roles to the audience groups they should see. */
    private const ROLE_AUDIENCE_MAP = [
        'public' => ['OFW & Public'],
        'case_manager' => ['OFW & Public', 'Case Managers'],
        'agency' => ['OFW & Public', 'Agency Focal Persons'],
        'admin' => null, // null = show all
    ];

    private array $responsesGreeting;

    private array $responsesIdentity;

    private array $responsesIrrelevant;

    private array $responsesUnclear;

    public function __construct(
        private readonly ChatbotHelpdeskService $helpdesk,
        private readonly ChatbotGuideService $guide,
        private readonly ChatbotRetrievalService $retrieval,
        private readonly ChatbotIntentService $intent,
    ) {
        $name = config('ai-chatbot.assistant_name', 'Bayani');

        $this->responsesGreeting = [
            "Hello! I'm **{$name}**, your Virtual Bayanihan Assistant. How can I help you today? Feel free to ask about OFW services, agencies, case tracking, or assistance needs.",
            "Hi there! **{$name}** here, ready to help with anything about the Bayanihan One Window system. What can I do for you today?",
            "Good day! I'm **{$name}**, your DMW virtual assistant. I can help you with case tracking, agency info, and OFW support. How may I assist you?",
            "Hello! Need help with OFW services or case concerns? I'm **{$name}**, and I'm here to guide you through the Bayanihan One Window system. What's on your mind?",
        ];

        $this->responsesIdentity = [
            "I'm **{$name}**, the Virtual Bayanihan Assistant for the **One Window Bayanihan** system operated by the **Department of Migrant Workers (DMW) Region VII**. I can help with OFW case tracking, agency information (OWWA, TESDA, DSWD, DOLE), service inquiries, and referral guidance. How can I assist you today?",
            "I'm **{$name}** — think of me as your guide to the Bayanihan One Window system. I can explain how to track cases, what services are available, which agencies to contact, and how referrals work. What would you like to know?",
            "I'm **{$name}**, your DMW virtual assistant for the One Window Bayanihan platform. I can walk you through case tracking, agency contacts, document requirements, and the referral process. Just ask!",
        ];

        $this->responsesIrrelevant = [
            "I'm sorry, I can only assist with the Bayanihan One Window system and OFW-related services. Please ask me about case tracking, agency information, OFW support, or available services.",
            "That's outside what I can help with. I'm limited to the Bayanihan One Window system — case tracking, OFW services, agency contacts, and referrals. Try asking me about those!",
            "I can't answer that, sorry! I'm only trained on the Bayanihan One Window system for OFW assistance. Feel free to ask about case tracking, agencies, documents, or how referrals work.",
        ];

        $this->responsesUnclear = [
            "I'm sorry, I didn't quite catch that. Could you rephrase your question? I'm here to help with case tracking, OFW services, agencies, and the Bayanihan system.",
            "Sorry, I didn't understand that. Could you try saying it another way? I can answer questions about case status, OFW assistance, agency contacts, and how the system works.",
            "Hmm, I'm not sure I followed that. Mind rephrasing? I'm happy to help with case tracking, services, or anything about the Bayanihan One Window system.",
        ];
    }

    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,bot'],
            'history.*.text' => ['required', 'string', 'max:1000'],
        ]);

        $userMessage = $request->input('message');
        $userContext = $this->resolveUserContext();

        // ── 1. Prompt-injection guard ──
        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (preg_match($pattern, $userMessage)) {
                return response()->json([
                    'reply' => $this->randomReply($this->responsesIrrelevant),
                ]);
            }
        }

        // ── 2. Heuristic intent — greetings/identity/gibberish never reach the LLM ──
        $intent = $this->intent->classify($userMessage);
        if ($intent !== ChatbotIntentService::CONTENT_QUERY) {
            return response()->json([
                'reply' => $this->cannedForIntent($intent),
            ]);
        }

        // ── 3. Lexical retrieval (FTS5 + BM25, audience-filtered) ──
        try {
            $hits = $this->retrieval->search($userMessage, $userContext['groups']);
        } catch (\Throwable $e) {
            Log::warning('Chatbot retrieval failed', ['error' => $e->getMessage()]);
            $hits = [];
        }

        $verbatimMin = (float) config('ai-chatbot.retrieval.verbatim_min_score');

        // ── 4. Follow-up: vague continuation of a stored topic → reuse that source ──
        $stored = session()->get('chatbot_last_context');
        $hasStored = $stored
            && ! empty($stored['source_label'])
            && ($stored['source_label'] ?? '') !== 'multiple';

        if ($hasStored) {
            $weakOwnMatch = $hits === [] || $hits[0]['score'] < $verbatimMin;
            if ($hits === [] || ($weakOwnMatch && $this->intent->isFollowUpCandidate($userMessage))) {
                $storedHits = $this->hitsForStoredSource($stored);
                if ($storedHits !== []) {
                    return $this->answerWithAi($userMessage, $storedHits, $userContext, rememberContext: false);
                }
            }
        }

        // ── 5. No match at all → answer from curated fallback sections ──
        if ($hits === []) {
            return $this->answerWithAi($userMessage, [], $userContext, rememberContext: false);
        }

        // ── 6. Verbatim tier: unambiguous single-section match needs no LLM ──
        // Ambiguity is measured against the best hit from a DIFFERENT source —
        // runner-up sections of the same article mean the topic is unambiguous.
        $gapRatio = (float) config('ai-chatbot.retrieval.verbatim_gap_ratio');
        $topScore = $hits[0]['score'];
        $topSource = $hits[0]['source_type'].':'.$hits[0]['slug'];
        $rival = null;
        foreach ($hits as $hit) {
            if ($hit['source_type'].':'.$hit['slug'] !== $topSource) {
                $rival = $hit;
                break;
            }
        }
        $clearWinner = $rival === null || $topScore >= $gapRatio * $rival['score'];

        if ($topScore >= $verbatimMin && $clearWinner) {
            $this->rememberContext([$hits[0]]);

            return $this->replyJson($this->verbatimText($hits[0]), $this->actionsFor($hits));
        }

        // ── 7. Ambiguous/multi-source → the single LLM call ──
        return $this->answerWithAi($userMessage, $hits, $userContext);
    }

    /**
     * Resolve the user's context label and allowed audience groups.
     *
     * @return array{label: string, groups: string[]|null}
     */
    private function resolveUserContext(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [
                'label' => 'a public OFW (not logged in)',
                'groups' => self::ROLE_AUDIENCE_MAP['public'],
            ];
        }

        $role = match ($user->role) {
            'CASE_MANAGER' => 'case_manager',
            'AGENCY' => 'agency',
            'ADMIN' => 'admin',
            default => 'public',
        };

        $label = match ($role) {
            'case_manager' => 'a logged-in Case Manager',
            'agency' => 'a logged-in Agency Focal Person',
            'admin' => 'a logged-in Administrator',
            default => 'a public OFW',
        };

        return [
            'label' => $label,
            'groups' => self::ROLE_AUDIENCE_MAP[$role],
        ];
    }

    /**
     * Return a canned response for a non-content intent.
     */
    private function cannedForIntent(string $intent): string
    {
        return match ($intent) {
            ChatbotIntentService::GREETING => $this->randomReply($this->responsesGreeting),
            ChatbotIntentService::IDENTITY => $this->randomReply($this->responsesIdentity),
            ChatbotIntentService::GIBBERISH => $this->randomReply($this->responsesUnclear),
            default => $this->randomReply($this->responsesIrrelevant),
        };
    }

    // ──────────────────────────────────────────────
    //  Answer generation (the single LLM call)
    // ──────────────────────────────────────────────

    /**
     * Generate the answer with one LLM call over the retrieved content.
     * Empty $hits means "answer from the curated fallback sections".
     * On LLM failure, degrade to serving the top section verbatim (HTTP 200).
     *
     * @param  list<array{source_type: string, source_key: string, slug: string, heading: string}>  $hits
     */
    private function answerWithAi(string $message, array $hits, array $userContext, bool $rememberContext = true): JsonResponse
    {
        $content = $hits !== []
            ? $this->retrieval->contentFor($hits)
            : $this->helpdesk->getFallbackSections();

        $actions = $this->actionsFor($hits);

        // ── Check cache for identical queries (only when hits are specific) ──
        if ($hits !== []) {
            $normalizedMessage = mb_strtolower(trim(preg_replace('/\s+/', ' ', $message)));
            $hitKeys = implode(',', array_map(fn ($h) => $h['source_key'], $hits));
            $audienceGroup = implode(',', $userContext['groups'] ?? ['all']);
            $cacheKey = 'chatbot:response:'.md5($normalizedMessage.':'.$hitKeys.':'.$audienceGroup);

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                if ($rememberContext) {
                    $this->rememberContext($hits);
                }

                return $this->replyJson($cached, $actions);
            }
        }

        try {
            $name = config('ai-chatbot.assistant_name', 'Bayani');
            $userLabel = $userContext['label'];

            $instructions = <<<EOT
You are {$name}, a helpful and friendly virtual assistant for the Bayanihan One Window system operated by DMW Region VII. You are knowledgeable about the system and speak with confidence.

The user you are speaking with is {$userLabel}.

CRITICAL RULES — You must follow these strictly:
1. You do NOT have access to any live data, user accounts, case files, or the tracking portal. You cannot look up, check, or know any user's case status.
2. NEVER make up or imply specific information about a user's case (status, dates, documents, etc.). If a user asks about their personal case, explain how to check it through the tracking portal using their tracker number — do NOT pretend to check it yourself.
3. Answer ONLY the user's exact question. Do NOT add procedures, steps, instructions, explanations, or details the user did not ask for. If the user asks "give me the link", say "here's the link" — do NOT explain how to use it. If the user asks "what does OPEN mean", explain only OPEN — not the other statuses. Never explain a full process when the user asked a simple question.
4. Stay on topic — do not introduce procedures, services, or agencies the user didn't ask about.
5. If the user's question is unrelated to the Bayanihan One Window system, OFW services, or the reference content, politely say you can only help with Bayanihan One Window and OFW-related topics — do not answer the unrelated question.
6. When explaining case statuses, use general descriptions — never say "Your case is Under Review."
7. Be very concise — 1 to 3 sentences max. No fluff, no repetition, no introductory phrases. Answer the question and stop. Use markdown formatting sparingly.
8. Tailor your response to the user's role — use appropriate terminology and detail level for their context.
9. CRITICAL — Present information naturally as if it is your own knowledge. NEVER say "according to the reference", "the provided content says", "based on the documentation", "the reference material states", or any similar phrase. You know this. Just answer directly.
EOT;

            // Reference content goes in the user prompt — small local models pay
            // better attention to it there than in the system instructions.
            $userPrompt = $message;
            if ($content !== '') {
                $userPrompt .= "\n\n---\n\n{$content}";
            }

            $response = agent(
                instructions: $instructions,
            )->prompt(
                prompt: $userPrompt,
                provider: config('ai-chatbot.provider'),
                model: config('ai-chatbot.model'),
            );

            if ($rememberContext) {
                $this->rememberContext($hits);
            }

            // ── Cache successful response ──
            if ($hits !== [] && isset($cacheKey)) {
                Cache::put($cacheKey, $response->text, 3600);
            }

            return $this->replyJson($response->text, $actions);
        } catch (\Throwable $e) {
            Log::warning('Chatbot AI answer failed — degrading to verbatim content', [
                'error' => $e->getMessage(),
            ]);

            // Basic mode: the model is down, but retrieval still works.
            if ($hits !== []) {
                $reply = "_I'm having trouble reaching my AI service, so here's the most relevant help content:_\n\n"
                    .$this->verbatimText($hits[0]);

                return $this->replyJson($reply, $actions);
            }

            return response()->json([
                'reply' => "I'm sorry, I'm having trouble processing your request right now. Please try again later or browse our Help Center for assistance.",
            ]);
        }
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Flush all cached chatbot responses. Called when helpdesk content changes.
     */
    public static function flushResponseCache(): void
    {
        // Use Redis SCAN to find and delete chatbot:response:* keys
        // Or simpler: use a cache tag if available, otherwise rely on TTL
        // For now, we just let the 1-hour TTL handle natural expiration
        // since content changes are rare and staleness is bounded.
    }

    /**
     * Rebuild hit descriptors for every section of a previously stored source,
     * so a follow-up question is answered from the same article/guide.
     *
     * @return list<array{source_type: string, source_key: string, slug: string, heading: string}>
     */
    private function hitsForStoredSource(array $stored): array
    {
        $type = $stored['source_type'] ?? 'helpdesk';
        $label = $stored['source_label'];

        if ($type === 'guide') {
            return [[
                'source_type' => 'guide',
                'source_key' => $label,
                'slug' => $label,
                'heading' => $this->guide->getAllTopics()[$label]['heading'] ?? $label,
            ]];
        }

        $hits = [];
        foreach ($this->helpdesk->getArticleHeadings($label) as $heading) {
            $hits[] = [
                'source_type' => 'helpdesk',
                'source_key' => "{$label}::{$heading}",
                'slug' => $label,
                'heading' => $heading,
            ];
        }

        return $hits;
    }

    /**
     * Store conversation context so vague follow-ups can reuse the same source.
     *
     * @param  list<array{source_type: string, source_key: string, slug: string}>  $hits
     */
    private function rememberContext(array $hits): void
    {
        $sources = [];
        foreach ($hits as $hit) {
            $sources[$hit['source_type'].':'.$hit['slug']] = $hit;
        }

        if (count($sources) === 1) {
            $hit = reset($sources);
            $isGuide = $hit['source_type'] === 'guide';
            $label = $isGuide ? $hit['source_key'] : $hit['slug'];

            session()->put('chatbot_last_context', [
                'source_type' => $hit['source_type'],
                'source_label' => $label,
                'article_title' => $isGuide
                    ? ($this->guide->getAllTopics()[$label]['heading'] ?? 'Selected Topic')
                    : ($this->helpdesk->getTitle($label) ?? 'Selected Article'),
            ]);

            return;
        }

        session()->put('chatbot_last_context', [
            'source_type' => 'multiple',
            'source_label' => 'multiple',
            'article_title' => 'Selected Articles',
        ]);
    }

    /**
     * Build action links based on the matched sources.
     */
    private function actionsFor(array $hits): array
    {
        foreach ($hits as $hit) {
            if (($hit['slug'] ?? '') === 'using-public-tracking-portal') {
                return [[
                    'label' => 'Go to Tracking Portal',
                    'url' => route('track.index'),
                    'icon' => 'track',
                ]];
            }
        }

        return [];
    }

    /**
     * Render a hit's section content for direct display in the chat, demoting
     * the markdown header to bold so it fits a chat bubble.
     */
    private function verbatimText(array $hit): string
    {
        $content = $this->retrieval->contentFor([$hit]);

        return trim(preg_replace('/^#{1,2}\s+(.+)$/m', '**$1**', $content, 1));
    }

    /**
     * Build the standard response payload: reply capped to the length limit,
     * actions only when present.
     */
    private function replyJson(string $reply, array $actions = []): JsonResponse
    {
        $payload = ['reply' => $this->capLength($reply)];
        if ($actions !== []) {
            $payload['actions'] = $actions;
        }

        return response()->json($payload);
    }

    private function capLength(string $reply): string
    {
        if (mb_strlen($reply) > self::MAX_RESPONSE_LENGTH) {
            return mb_substr($reply, 0, self::MAX_RESPONSE_LENGTH - 3).'...';
        }

        return $reply;
    }

    private function randomReply(array $replies): string
    {
        return $replies[array_rand($replies)];
    }
}
