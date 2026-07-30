<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Services\Chatbot\ChatbotGuideService;
use App\Services\Chatbot\ChatbotHelpdeskService;
use App\Services\Chatbot\ChatbotHybridSearch;
use App\Services\Chatbot\ChatbotIntentService;
use App\Services\Chatbot\ChatbotRetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

/**
 * Chatbot message pipeline: injection guard → heuristic intent (zero LLM) →
 * pgvector retrieval → a single LLM call.
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
        'public' => ['OFW & Public', 'General'],
        'case_manager' => ['OFW & Public', 'Case Managers', 'General'],
        'agency' => ['OFW & Public', 'Agency Focal Persons', 'General'],
        'admin' => null, // null = show all
    ];

    private array $responsesGreeting;

    private array $responsesIdentity;

    private array $responsesIrrelevant;

    private array $responsesUnclear;

    private array $responsesClarify;

    public function __construct(
        private readonly ChatbotHelpdeskService $helpdesk,
        private readonly ChatbotGuideService $guide,
        private readonly ChatbotRetrievalService $retrieval,
        private readonly ChatbotHybridSearch $hybrid,
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

        $this->responsesClarify = [
            "I'm not sure I follow — could you give me a bit more detail? I can help with case tracking, OFW services, agencies, and the Bayanihan system.",
            'Could you rephrase that? I want to make sure I give you the right information about the Bayanihan One Window system.',
            "I didn't quite catch the specifics. Could you try asking in a different way? I can help with case status, services, agencies, and referrals.",
        ];
    }

    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,bot'],
            'history.*.text' => ['required', 'string', 'max:1000'],
            'lastContext' => ['nullable', 'array'],
            'lastContext.source_type' => ['required_with:lastContext', 'string'],
            'lastContext.source_label' => ['required_with:lastContext', 'string'],
            'lastContext.article_title' => ['required_with:lastContext', 'string'],
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

        // ── 2. Heuristic intent — greetings/identity never reach the LLM ──
        // UNCLEAR_FOLLOWUP and content_query pass through: the LLM decides with context.
        $intent = $this->intent->classify($userMessage);
        if ($intent !== ChatbotIntentService::CONTENT_QUERY && $intent !== ChatbotIntentService::UNCLEAR_FOLLOWUP) {
            return response()->json([
                'reply' => $this->cannedForIntent($intent),
            ]);
        }

        // ── 3. Hybrid retrieval (pgvector semantic search) ──
        try {
            $result = $this->hybrid->search($userMessage, $userContext['groups']);
        } catch (\Throwable $e) {
            Log::warning('Chatbot hybrid search failed', ['error' => $e->getMessage()]);
            $result = ['hits' => [], 'confidence' => 0.0, 'clear_winner' => false, 'vector_count' => 0, 'fts_count' => 0];
        }

        $hits = $result['hits'];
        $confidence = $result['confidence'];

        // ── 3b. Agency-aware retrieval — when the query mentions a known
        // agency by name, slug, or short name, always include its reference
        // embedding so the LLM has the authoritative agency data regardless
        // of what the general search ranked higher.
        $agencyHit = $this->detectAgencyHit($userMessage, $userContext['groups']);
        if ($agencyHit !== null && ! $this->hitExists($hits, $agencyHit['source_key'])) {
            array_unshift($hits, $agencyHit);
            // Bump confidence — we have authoritative data.
            $confidence = max($confidence, 0.95);
        }

        // ── 4. Stored context from previous turn ──
        $stored = $request->input('lastContext');
        $hasStored = $stored && ! empty($stored['source_label']);

        $storedHits = [];
        if ($hasStored) {
            $storedHits = $this->hitsForStoredSource($stored);
        }

        // ── 5. Conversation history ──
        $history = $request->input('history', []);

        // ── 6. Answer with LLM — all sources (fresh + stored) go to the LLM
        // labeled by slug/heading. The model decides which is most relevant.
        return $this->answerWithAi(
            $userMessage,
            $hits,
            $userContext,
            storedHits: $storedHits,
            rememberContext: true,
            confidence: $confidence,
            history: $history,
        );
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
            ChatbotIntentService::UNCLEAR_FOLLOWUP => $this->randomReply($this->responsesClarify),
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
     * All available sources (fresh search hits + stored context hits) are
     * labeled by slug/heading and sent together. The LLM picks the most
     * relevant source based on the user's question and conversation history.
     *
     * @param  list<array{source_type: string, source_key: string, slug: string, heading: string}>  $hits
     * @param  list<array{source_type: string, source_key: string, slug: string, heading: string}>  $storedHits  Hits reconstructed from the previous turn's context.
     * @param  list<array{role: string, text: string}>  $history  Full conversation history for the LLM.
     */
    private function answerWithAi(
        string $message,
        array $hits,
        array $userContext,
        array $storedHits = [],
        bool $rememberContext = true,
        ?float $confidence = null,
        array $history = [],
    ): JsonResponse {
        $actions = $this->actionsFor($hits);
        $nextContext = $rememberContext ? $this->computeContext($hits) : null;

        try {
            $name = config('ai-chatbot.assistant_name', 'Bayani');
            $userLabel = $userContext['label'];

            // Build conversation history block for the system prompt.
            $historyBlock = '';
            if ($history !== []) {
                $lines = [];
                // Take the last 6 messages (3 exchanges) to keep context manageable.
                $recentHistory = array_slice($history, -6);
                foreach ($recentHistory as $msg) {
                    $role = ($msg['role'] ?? '') === 'bot' ? 'Bayani' : 'User';
                    $text = $msg['text'] ?? '';
                    $lines[] = "- {$role}: \"{$text}\"";
                }
                $historyBlock = "\n\nCONVERSATION HISTORY:\n".implode("\n", $lines)."\n";
            }

            $instructions = <<<EOT
You are {$name}, a helpful and friendly virtual assistant for the Bayanihan One Window system operated by DMW Region VII. You are knowledgeable about the system and speak with confidence.

The user you are speaking with is {$userLabel}.
{$historyBlock}
CRITICAL RULES — You must follow these strictly:
1. You have NO access to live data, user accounts, case files, or the tracking portal. You cannot look up, check, or know any user's case status. NEVER fabricate or imply specific information about a user's case (status, dates, documents). If a user asks about their personal case, explain how to check it through the tracking portal using their tracker number — do NOT pretend to check it yourself.
2. LIST EXACTLY — When the user asks about services, requirements, contact info, or any structured data, list each item on its own line using bullet points. Use the EXACT names and values from the reference content — do NOT paraphrase, summarize, abbreviate, or group items into categories. If the reference lists 8 services, output all 8 by name. Do NOT skip or truncate any items. WRONG: "OWWA offers emergency assistance, legal aid, and medical support." RIGHT: "- EDSP (Emergency Shelter Assistance)\n- Calamity Assistance\n- Repatriation Assistance" (etc.)
3. Stay on topic — Bayanihan One Window, OFW services, and the reference content only. If the question is unrelated, politely say you can only help with Bayanihan One Window and OFW-related topics.
4. Your ONLY source of truth is the reference content below. You do NOT know anything beyond what it explicitly states. Never list services, requirements, or procedures not in the provided content. Speak naturally as if it is your own knowledge. NEVER use attribution phrases like "according to the reference", "the provided content says", "based on the documentation", "based on the information provided in Source", "Source 1 states", "as mentioned in", or any variation that reveals you are reading from a reference.
5. EXACT VALUES — When citing phone numbers, email addresses, or any specific data, copy the EXACT value from the reference content character-for-character. Do NOT shorten, truncate, or paraphrase concrete data. If the reference says "(032) 232-1234", output exactly "(032) 232-1234". If the data is not in the reference, say you don't have it rather than making one up.
6. When explaining case statuses, use general descriptions — never say "Your case is Under Review."
7. SOURCE MATCHING — Multiple sources may be provided below, each labeled with [Source N: type | slug | heading]. Pick the ONE source that best answers the user's question. Use the conversation history to understand follow-ups (e.g. "their contact details" refers to whatever agency was discussed previously). If the user is asking about a specific agency, use that agency's source. Only reference one source in your answer unless the user explicitly asks to compare.
8. GIBBERISH / UNINTELLIGIBLE INPUT — If the message is nonsense or random characters, do NOT answer from the reference content. Ask for clarification briefly and naturally.
9. LINKS — You may include one markdown link at the end of your answer using the URL mapping below. Format: [Read the full guide](URL). Do NOT invent URLs. If the URL MAPPING section is not provided below, do NOT include any markdown links. NEVER include links for agency/service questions — links are only for helpdesk articles.
10. NO FILLER — End your answer directly after the information. Do NOT add closing phrases like "If you need more detailed information", "If you need further assistance", "Please let me know if you have questions", "Feel free to ask", or any similar filler. Just provide the answer and stop.
EOT;

            // ── Build labeled sources block ──
            // Deduplicate across fresh hits and stored hits by slug.
            $allHits = $this->deduplicateHits($hits, $storedHits);

            $userPrompt = $message;

            if ($allHits !== []) {
                $sourceBlocks = [];
                $urlLines = [];
                $sourceNum = 1;

                foreach ($allHits as $hit) {
                    $sourceContent = $this->retrieval->contentFor([$hit]);
                    if ($sourceContent === '') {
                        continue;
                    }

                    $sourceBlocks[] = "[Source {$sourceNum}: {$hit['source_type']} | {$hit['slug']} | {$hit['heading']}]\n\n{$sourceContent}";

                    // Collect URL mapping for helpdesk articles.
                    if (($hit['source_type'] ?? '') === 'helpdesk') {
                        $url = route('helpdesk.show', $hit['slug']);
                        $urlLines[] = "- {$hit['heading']}: {$url}";
                    }

                    $sourceNum++;
                }

                if ($sourceBlocks !== []) {
                    $userPrompt .= "\n\n---\n\nAVAILABLE SOURCES:\n\n".implode("\n\n---\n\n", $sourceBlocks);
                }

                if ($urlLines !== []) {
                    $userPrompt .= "\n\n---\n\nURL MAPPING (use these for markdown links when answering — ONLY for helpdesk articles, do NOT generate links for guide or reference topics):\n".implode("\n", $urlLines);
                }
            } elseif (($fallback = $this->helpdesk->getFallbackSections()) !== '') {
                $userPrompt .= "\n\n---\n\nHere is the SPECIFIC information I have available:\n\n{$fallback}";
            }

            $hasHelpdeskUrls = ! empty($urlLines);

            $response = agent(
                instructions: $instructions,
            )->prompt(
                prompt: $userPrompt,
                provider: config('ai-chatbot.provider'),
                model: config('ai-chatbot.model'),
                timeout: config('ai-chatbot.timeout', 180),
            );

            $reply = $response->text;

            // Safety net: strip fabricated markdown links when no helpdesk URLs
            // were available. Guide and reference sources have no standalone pages.
            if (! $hasHelpdeskUrls) {
                $reply = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $reply);
            }

            // Always strip orphaned "Read the full guide" / "Read more about"
            // plain text — the LLM appends these even when valid helpdesk URLs
            // exist, creating noise. Valid markdown links survive this pass.
            $reply = preg_replace('/\n?\s*[Rr]ead the full guide[^\n]*/m', '', $reply);
            $reply = preg_replace('/\n?\s*[Rr]ead more about[^\n]*/m', '', $reply);
            $reply = trim($reply);

            // When the LLM detects gibberish/unintelligible input it returns a
            // short clarification — strip sources and context so the UI doesn't
            // misleadingly show source chips for something it couldn't understand.
            if ($this->isClarificationResponse($reply)) {
                return $this->replyJson($reply, [], null, $confidence);
            }

            // When the LLM says the content doesn't have the answer (e.g.
            // "not explicitly mentioned", "I don't have that information"),
            // don't store context — follow-ups should not reference wrong hits.
            if ($this->isNegativeResponse($reply)) {
                return $this->replyJson($reply, [], null, $confidence);
            }

            // Send allHits (fresh + stored) so source chips reflect what the
            // LLM actually saw, not just the fresh search results. When a
            // follow-up is answered from stored context, the source chips
            // should show the stored source, not weak fresh search hits.
            return $this->replyJson($reply, $actions, $allHits, $confidence, $nextContext);
        } catch (\Throwable $e) {
            Log::warning('Chatbot AI answer failed — degrading to verbatim content', [
                'error' => $e->getMessage(),
            ]);

            // Basic mode: the model is down, but retrieval still works.
            if ($hits !== []) {
                $reply = "_I'm having trouble reaching my AI service, so here's the most relevant help content:_\n\n"
                    .$this->verbatimText($hits[0]);

                return $this->replyJson($reply, $actions, $hits, $confidence, $nextContext);
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

        if ($type === 'reference') {
            // Reference source_label is the agency slug; source_key is "agency:{slug}".
            $agency = Agency::where('slug', $label)->where('is_deleted', false)->first();

            return [[
                'source_type' => 'reference',
                'source_key' => "agency:{$label}",
                'slug' => $label,
                'heading' => $agency->name ?? ($stored['article_title'] ?? $label),
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
     * Compute conversation context from the top hit — returned in the response
     * payload so the frontend can store it in localStorage and send it back
     * on the next turn.
     *
     * @param  list<array{source_type: string, source_key: string, slug: string}>  $hits
     * @return array{source_type: string, source_label: string, article_title: string}|null
     */
    private function computeContext(array $hits): ?array
    {
        if ($hits === []) {
            return null;
        }

        $hit = $hits[0];
        $isGuide = $hit['source_type'] === 'guide';
        $label = $isGuide ? $hit['source_key'] : $hit['slug'];

        return [
            'source_type' => $hit['source_type'],
            'source_label' => $label,
            'article_title' => $isGuide
                ? ($this->guide->getAllTopics()[$label]['heading'] ?? 'Selected Topic')
                : (($hit['source_type'] ?? '') === 'reference'
                    ? ($hit['heading'] ?? 'Selected Agency')
                    : ($this->helpdesk->getTitle($label) ?? $hit['heading'] ?? 'Selected Article')),
        ];
    }

    /**
     * Build action links based on the top hit only.
     * Only the #1 result is relevant — secondary matches from unrelated
     * articles should not trigger context-specific action buttons.
     */
    private function actionsFor(array $hits): array
    {
        if ($hits === []) {
            return [];
        }

        $top = $hits[0];
        if (($top['slug'] ?? '') === 'using-public-tracking-portal') {
            return [[
                'label' => 'Go to Tracking Portal',
                'url' => route('track.index'),
                'icon' => 'track',
            ]];
        }

        return [];
    }

    /**
     * Extract unique source references from hits for display in the UI.
     * Includes a URL so source chips are clickable links.
     * Only helpdesk articles have standalone pages — guide and reference
     * sources get no URL so their chips render as non-clickable labels.
     *
     * @param  list<array{source_type: string, slug: string, heading: string}>  $hits
     * @return list<array{source_type: string, slug: string, heading: string, url: string|null}>
     */
    private function sourcesFor(array $hits): array
    {
        $seen = [];
        $sources = [];

        foreach ($hits as $hit) {
            $key = $hit['source_type'].':'.$hit['slug'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $url = ($hit['source_type'] ?? '') === 'helpdesk'
                ? route('helpdesk.show', $hit['slug'])
                : null;

            $sources[] = [
                'source_type' => $hit['source_type'],
                'slug' => $hit['slug'],
                'heading' => $hit['heading'],
                'url' => $url,
            ];
        }

        return $sources;
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
     * actions only when present, sources and confidence for UI transparency.
     * When $lastContext is provided, it is included so the frontend can store
     * it in localStorage for follow-up augmentation on the next turn.
     *
     * @param  list<array{source_type: string, source_key: string, slug: string, heading: string}>|null  $hits
     * @param  array{source_type: string, source_label: string, article_title: string}|null  $lastContext
     */
    private function replyJson(string $reply, array $actions = [], ?array $hits = null, ?float $confidence = null, ?array $lastContext = null): JsonResponse
    {
        $payload = ['reply' => $this->capLength($reply)];
        if ($actions !== []) {
            $payload['actions'] = $actions;
        }
        if ($hits !== null) {
            $payload['sources'] = $this->sourcesFor($hits);
        }
        if ($confidence !== null) {
            $payload['confidence'] = round($confidence, 2);
        }
        if ($lastContext !== null) {
            $payload['lastContext'] = $lastContext;
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

    /**
     * Detect when the LLM returns a gibberish/clarification response instead
     * of a real answer. These should not carry source chips or context.
     */
    private function isClarificationResponse(string $reply): bool
    {
        $lower = mb_strtolower($reply);

        $patterns = [
            "didn't quite catch",
            'could you rephrase',
            "couldn't understand",
            'not sure what you mean',
            "not sure what you're asking",
            'could you clarify',
            'can you clarify',
            "didn't catch that",
            'not clear what',
            'hard to understand',
            'having trouble understanding',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect when the LLM says the reference content doesn't have the answer.
     * These responses should not carry sources or context for follow-ups.
     */
    private function isNegativeResponse(string $reply): bool
    {
        $lower = mb_strtolower($reply);

        $patterns = [
            'not explicitly mentioned',
            "don't have that information",
            "don't have access to that",
            'not available in the',
            'not mentioned in the',
            'cannot provide a specific',
            'unable to find',
            'not found in the',
            "i don't have",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merge fresh hits and stored hits, deduplicating by slug.
     * Fresh hits come first (they matched the current query); stored hits
     * fill in context the user was previously discussing.
     *
     * @return list<array{source_type: string, source_key: string, slug: string, heading: string}>
     */
    private function deduplicateHits(array $fresh, array $stored): array
    {
        $seen = [];
        $merged = [];

        foreach ($fresh as $hit) {
            $key = $hit['source_type'].':'.$hit['slug'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $hit;
        }

        foreach ($stored as $hit) {
            $key = $hit['source_type'].':'.$hit['slug'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $hit;
        }

        return $merged;
    }

    // ──────────────────────────────────────────────
    //  Agency-aware retrieval
    // ──────────────────────────────────────────────

    /**
     * Check if the query mentions a known agency and return its reference hit.
     *
     * Matches against agency name, slug, and short name (e.g. "OWWA",
     * "owwa", "DSWD", "Department of Migrant Workers").
     */
    private function detectAgencyHit(string $query, ?array $audienceGroups): ?array
    {
        $lower = mb_strtolower($query);

        $agencies = Agency::query()
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->select(['slug', 'name', 'short'])
            ->get();

        foreach ($agencies as $agency) {
            // Match slug (exact word boundary)
            if (preg_match('/\b'.preg_quote($agency->slug, '/').'\b/i', $lower)) {
                return $this->makeAgencyHit($agency);
            }

            // Match full name (e.g. "department of migrant workers")
            if (mb_strlen($agency->name) > 3 && str_contains($lower, mb_strtolower($agency->name))) {
                return $this->makeAgencyHit($agency);
            }

            // Match short name (e.g. "OWWA", "TESDA") — only if >= 3 chars
            // to avoid false positives on very short abbreviations.
            if ($agency->short && mb_strlen($agency->short) >= 3 && str_contains($lower, mb_strtolower($agency->short))) {
                return $this->makeAgencyHit($agency);
            }
        }

        return null;
    }

    private function makeAgencyHit($agency): array
    {
        return [
            'source_type' => 'reference',
            'source_key' => "agency:{$agency->slug}",
            'slug' => $agency->slug,
            'heading' => $agency->name,
            'audience_group' => 'OFW & Public',
        ];
    }

    private function hitExists(array $hits, string $sourceKey): bool
    {
        foreach ($hits as $hit) {
            if (($hit['source_key'] ?? '') === $sourceKey) {
                return true;
            }
        }

        return false;
    }
}
