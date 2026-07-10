<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\ChatbotGuideService;
use App\Services\Chatbot\ChatbotHelpdeskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

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

    private array $responsesGreeting;

    private array $responsesIdentity;

    private array $responsesIrrelevant;

    private array $responsesUnclear;

    public function __construct(
        private readonly ChatbotHelpdeskService $helpdesk,
        private readonly ChatbotGuideService $guide,
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

    /** Map of user roles to the audience groups they should see. */
    private const ROLE_AUDIENCE_MAP = [
        'public' => ['OFW & Public'],
        'case_manager' => ['OFW & Public', 'Case Managers'],
        'agency' => ['OFW & Public', 'Agency Focal Persons'],
        'admin' => null, // null = show all
    ];

    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,bot'],
            'history.*.text' => ['required', 'string', 'max:1000'],
        ]);

        $userMessage = $request->input('message');
        $history = $request->input('history', []);
        $userContext = $this->resolveUserContext();

        // ── 1. Prompt-injection guard ──
        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (preg_match($pattern, $userMessage)) {
                return response()->json([
                    'reply' => $this->randomReply($this->responsesIrrelevant),
                ]);
            }
        }

        // ── 2. Classify intent (with conversation history context) ──
        try {
            $intent = $this->classifyIntent($userMessage, $history);
        } catch (\Throwable $e) {
            Log::warning('Chatbot intent classification failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackResponse();
        }

        // ── 3. Non-relevant → canned response (follow_up is handled separately) ──
        if (! in_array($intent, ['relevant', 'follow_up'], true)) {
            return response()->json([
                'reply' => $this->cannedForIntent($intent),
            ]);
        }

        // ── 3b. Follow-up with stored context → reuse previous article ──
        if ($intent === 'follow_up') {
            $lastContext = session()->get('chatbot_last_context');
            if ($lastContext && ! empty($lastContext['source_label']) && $lastContext['source_label'] !== 'multiple') {
                // Re-use the stored article directly — keyword matcher would lack
                // context for vague follow-ups like "What documents do I need?"
                $sourceType = $lastContext['source_type'] ?? 'helpdesk';
                $sourceLabel = $lastContext['source_label'];

                if ($sourceType === 'guide') {
                    return $this->answerWithSources($userMessage, [['type' => 'guide', 'key' => $sourceLabel]], $userContext);
                }

                return $this->answerWithSources($userMessage, [['type' => 'helpdesk', 'slug' => $sourceLabel]], $userContext);
            }

            // No stored context → fall through to full pipeline
            return $this->answerFromSelectedContent($userMessage, $userContext);
        }

        // ── 4. Pick the most relevant articles/guides ──
        return $this->answerFromSelectedContent($userMessage, $userContext);
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

    // ──────────────────────────────────────────────
    //  Intent classification
    // ──────────────────────────────────────────────

    /**
     * Classify the user's message intent via the LLM.
     *
     * @return string One of "greeting", "identity", "irrelevant", "gibberish", or "relevant"
     */
    private function classifyIntent(string $message, ?array $history = null): string
    {
        // Build conversation context from history and session
        $contextBlock = "\nUser's new message: {$message}\n";

        $lastContext = session()->get('chatbot_last_context');
        if ($lastContext && ! empty($lastContext['article_title'])) {
            $contextBlock .= "Previous topic: {$lastContext['article_title']}\n";
        }

        if ($history !== null && count($history) > 0) {
            $recent = array_slice($history, -5);
            $userQueries = array_map(
                fn ($msg) => 'User: '.mb_substr($msg['text'] ?? '', 0, 200),
                $recent,
            );
            $contextBlock .= 'Recent conversation:'."\n".implode("\n", $userQueries)."\n";
        }

        $instruction = <<<EOT
You are a classifier for an OFW assistance chatbot. Categorize the user's message into exactly one of these intents:

- greeting: The user is just saying hello, hi, good morning, or similar greeting
- identity: The user is asking who you are, what you are, or what you can do
- follow_up: The user is continuing a previous conversation topic — their question is vague on its own but makes sense in context of the prior discussion (e.g. "What documents do I need?" after asking about filing a complaint)
- irrelevant: The topic is completely unrelated to OFW services, case tracking, DMW, or the Bayanihan One Window system
- gibberish: The message is garbled, has heavy typos that obscure meaning, keyboard mash, or seems unintelligible as a real question (e.g. "waht", "could usay that agian", "asdfgh", "sm like that", "uoy nac dlef"). Also includes messages where the user seems confused and is just asking the bot to repeat itself (e.g. "what?", "say that again", "come again?")
- relevant: The user is asking about OFW services, case tracking, agencies, documents, or anything related to the system

Use the conversation context below to decide — especially for follow_up detection.

{$contextBlock}
Reply with ONLY the intent word (one of: greeting, identity, follow_up, irrelevant, gibberish, relevant), nothing else.
EOT;

        $response = agent(
            instructions: $instruction,
        )->prompt(
            prompt: $message,
            provider: config('ai-chatbot.provider'),
            model: config('ai-chatbot.model'),
        );

        $intent = strtolower(trim($response->text));

        return match ($intent) {
            'greeting', 'identity', 'irrelevant', 'follow_up', 'gibberish' => $intent,
            default => 'relevant',
        };
    }

    /**
     * Return a canned response for a non-relevant intent.
     */
    private function cannedForIntent(string $intent): string
    {
        return match ($intent) {
            'greeting' => $this->randomReply($this->responsesGreeting),
            'identity' => $this->randomReply($this->responsesIdentity),
            'irrelevant' => $this->randomReply($this->responsesIrrelevant),
            'gibberish' => $this->randomReply($this->responsesUnclear),
            default => $this->randomReply($this->responsesIrrelevant),
        };
    }

    // ──────────────────────────────────────────────
    //  Article / guide selection
    // ──────────────────────────────────────────────

    /**
     * Keyword-based article selection — deterministic, no LLM call.
     *
     * Tokenizes the user question and scores articles/guides by keyword overlap
     * with titles, excerpts, and descriptions. Returns the top 1-3 sources
     * sorted by relevance score.
     *
     * @return list<array{type: string, key?: string, slug?: string}>
     */
    private function matchArticles(string $message, array $userContext): array
    {
        $articles = $this->helpdesk->getArticleMeta($userContext['groups']);
        $guideTopics = $this->guide->getAllTopics();

        // Tokenize question: lowercase, split on non-word chars, remove short/stop words
        $stopWords = [
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

        $words = array_unique(array_filter(
            preg_split('/\W+/', mb_strtolower($message)),
            fn ($w) => mb_strlen($w) > 2 && ! in_array($w, $stopWords),
        ));

        if (empty($words)) {
            Log::warning('Chatbot matchArticles no meaningful keywords', ['message' => $message]);

            throw new \RuntimeException('No meaningful keywords in query');
        }

        $scored = [];

        // Score helpdesk articles by title + excerpt keyword overlap.
        // Title matches weigh 2× excerpt matches — titles are more specific.
        foreach ($articles as $slug => $meta) {
            $titleText = mb_strtolower($meta['title']);
            $excerptText = mb_strtolower($meta['excerpt']);
            $score = 0;
            foreach ($words as $word) {
                if (str_contains($titleText, $word)) {
                    $score += 2;
                } elseif (str_contains($excerptText, $word)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scored[] = ['type' => 'helpdesk', 'slug' => $slug, 'score' => $score];
            }
        }

        // Score guide topics by heading + description keyword overlap.
        // Guide topics are supplementary; their content is brief compared to
        // helpdesk articles, so all matches get +1 weight.
        foreach ($guideTopics as $key => $meta) {
            $headingText = mb_strtolower($meta['heading']);
            $descriptionText = mb_strtolower($meta['description']);
            $score = 0;
            foreach ($words as $word) {
                if (str_contains($headingText, $word) || str_contains($descriptionText, $word)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scored[] = ['type' => 'guide', 'key' => $key, 'score' => $score];
            }
        }

        if (empty($scored)) {
            Log::warning('Chatbot matchArticles no keyword matches', ['words' => $words]);

            throw new \RuntimeException('No articles matched keywords');
        }

        // Sort by score descending, tie-break: helpdesk articles before guide topics
        usort($scored, function (array $a, array $b): int {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }

            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'helpdesk' ? -1 : 1;
            }

            return 0;
        });

        // Deduplicate by slug/key (keep highest score)
        $seen = [];
        $unique = [];
        foreach ($scored as $item) {
            $id = ($item['type'] === 'guide' ? 'g:' : 'h:').($item['slug'] ?? $item['key'] ?? '');
            if (! isset($seen[$id])) {
                $seen[$id] = true;
                $unique[] = $item;
            }
        }

        // Return top 1-3, strip scores
        $top = array_slice($unique, 0, 3);

        $result = [];
        foreach ($top as $item) {
            if ($item['type'] === 'guide') {
                $result[] = ['type' => 'guide', 'key' => $item['key']];
            } else {
                $result[] = ['type' => 'helpdesk', 'slug' => $item['slug']];
            }
        }

        Log::debug('Chatbot matchArticles result', [
            'sources' => $result,
            'words' => $words,
        ]);

        return $result;
    }

    // ──────────────────────────────────────────────
    //  Section selection
    // ──────────────────────────────────────────────

    /**
     * Have the LLM pick the most relevant sections from the chosen source.
     *
     * @param  array{type: string, key?: string, slug?: string}  $source
     * @return list<string> Section identifiers
     */
    private function pickSections(string $message, array $source, array $userContext): array
    {
        // Guide topics are single-section — no need for an extra LLM call
        if ($source['type'] === 'guide') {
            return [$source['key']];
        }

        $name = config('ai-chatbot.assistant_name', 'Bayani');
        $slug = $source['slug'];
        $headings = $this->helpdesk->getArticleHeadings($slug);

        if ($headings === []) {
            throw new \RuntimeException("No sections found for article: {$slug}");
        }

        $userLabel = $userContext['label'];
        $sectionList = implode("\n", array_map(fn ($h) => "- {$h}", $headings));

        $instruction = <<<EOT
You are {$name}, an assistant for the Bayanihan One Window system.

The user ({$userLabel}) is asking: "{$message}"

The article "{$slug}" has these sections:
{$sectionList}

Select 1-3 sections most relevant to the user's question.
Reply with ONLY the section heading text, one per line, in the order they should be read.
Do NOT include the article slug or any prefix — just the heading text.
EOT;

        $response = agent(
            instructions: $instruction,
        )->prompt(
            prompt: $message,
            provider: config('ai-chatbot.provider'),
            model: config('ai-chatbot.model'),
        );

        Log::debug('Chatbot pickSections raw response', [
            'slug' => $slug,
            'response' => $response->text,
        ]);

        $lines = explode("\n", trim($response->text));

        // Build a lookup of normalized known headings for validation
        $knownNormalized = [];
        foreach ($headings as $h) {
            $normalized = $this->normalizeHeading($h);
            $knownNormalized[$normalized] = $h; // original heading as value
        }

        $selected = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Strip bullet points, numbered lists, markdown bold, and backticks
            $cleaned = preg_replace(
                ['/^[-*]\s+/', '/^\d+[.)]\s+/', '/\*\*(.+?)\*\*/', '/`(.+?)`/'],
                ['', '', '$1', '$1'],
                $line,
            );
            $cleaned = trim($cleaned);

            if ($cleaned === '') {
                continue;
            }

            // Match against known headings — exact, fuzzy, or substring
            $normalized = $this->normalizeHeading($cleaned);

            if (isset($knownNormalized[$normalized])) {
                // Exact normalized match
                $selected[] = $knownNormalized[$normalized];
            } else {
                // Substring match: LLM may say "Viewing Case Status" instead of "Step 5: Viewing Case Status"
                foreach ($knownNormalized as $normKey => $original) {
                    if (str_contains($normKey, $normalized) || str_contains($normalized, $normKey)) {
                        $selected[] = $original;
                        break;
                    }
                }
            }
        }

        Log::debug('Chatbot pickSections matched', [
            'slug' => $slug,
            'selected' => $selected,
            'count' => count($selected),
        ]);

        // If no headings matched, load ALL sections rather than returning nothing.
        // The AI gets broader content instead of hallucinating from no context.
        if ($selected === []) {
            Log::debug('Chatbot pickSections fell back to all sections', ['slug' => $slug]);

            return $this->allSectionIdsFor($source);
        }

        return array_map(
            fn ($heading) => "{$slug}::{$heading}",
            $selected,
        );
    }

    // ──────────────────────────────────────────────
    //  Answer generation
    // ──────────────────────────────────────────────

    /**
     * Load the selected sections and generate an AI answer.
     *
     * @param  list<string>  $sectionIds
     */
    private function answerFromSections(string $message, array $sectionIds, array $userContext, array $actions = []): JsonResponse
    {
        try {
            $name = config('ai-chatbot.assistant_name', 'Bayani');
            $sectionContent = $this->loadSectionContent($sectionIds);
            $userLabel = $userContext['label'];

            Log::debug('Chatbot answerFromSections section count', [
                'sectionIds' => $sectionIds,
                'content_length' => strlen($sectionContent),
                'content_preview' => mb_substr($sectionContent, 0, 200),
            ]);

            $instructions = <<<EOT
You are {$name}, a helpful and friendly virtual assistant for the Bayanihan One Window system operated by DMW Region VII. You are knowledgeable about the system and speak with confidence.

The user you are speaking with is {$userLabel}.

CRITICAL RULES — You must follow these strictly:
1. You do NOT have access to any live data, user accounts, case files, or the tracking portal. You cannot look up, check, or know any user's case status.
2. NEVER make up or imply specific information about a user's case (status, dates, documents, etc.). If a user asks about their personal case, explain how to check it through the tracking portal using their tracker number — do NOT pretend to check it yourself.
3. Answer ONLY the user's exact question. Do NOT add procedures, steps, instructions, explanations, or details the user did not ask for. If the user asks "give me the link", say "here's the link" — do NOT explain how to use it. If the user asks "what does OPEN mean", explain only OPEN — not the other statuses. Never explain a full process when the user asked a simple question.
4. Stay on topic — do not introduce procedures, services, or agencies the user didn't ask about.
5. When explaining case statuses, use general descriptions — never say "Your case is Under Review."
6. Be very concise — 1 to 3 sentences max. No fluff, no repetition, no introductory phrases. Answer the question and stop. Use markdown formatting sparingly.
7. Tailor your response to the user's role — use appropriate terminology and detail level for their context.
8. CRITICAL — Present information naturally as if it is your own knowledge. NEVER say "according to the reference", "the provided content says", "based on the documentation", "the reference material states", or any similar phrase. You know this. Just answer directly.
EOT;

            // Reference content is included in the user prompt below. Models like
            // llama3 pay better attention to content in the user message vs system
            // instructions. The prompt phrasing guides the AI to speak from knowledge,
            // not to cite a source.
            $userPrompt = $message;
            if ($sectionContent !== '') {
                $userPrompt .= "\n\n---\n\n{$sectionContent}";
            }

            $response = agent(
                instructions: $instructions,
            )->prompt(
                prompt: $userPrompt,
                provider: config('ai-chatbot.provider'),
                model: config('ai-chatbot.model'),
            );

            $reply = $response->text;

            if (mb_strlen($reply) > self::MAX_RESPONSE_LENGTH) {
                $reply = mb_substr($reply, 0, self::MAX_RESPONSE_LENGTH - 3).'...';
            }

            $response = ['reply' => $reply];
            if ($actions !== []) {
                $response['actions'] = $actions;
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::warning('Chatbot AI answer failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'reply' => null,
                'error' => 'AI service is currently unavailable. Please try again later.',
            ], 503);
        }
    }

    /**
     * Select articles + sections and generate an answer.
     *
     * matchArticles selects 1-3 sources via keyword matching (deterministic,
     * no LLM call). pickSections selects relevant headings from each source,
     * falling back to all sections if nothing matches.
     */
    private function answerFromSelectedContent(string $message, array $userContext): JsonResponse
    {
        // ── 1. Pick 1-3 most relevant articles/guides via keyword matching ──
        try {
            $sources = $this->matchArticles($message, $userContext);
        } catch (\Throwable $e) {
            Log::warning('Chatbot article selection failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->answerWithFallback($message, $userContext);
        }

        return $this->answerWithSources($message, $sources, $userContext);
    }

    /**
     * Pick sections from the given sources, store conversation context,
     * and generate an AI answer.
     *
     * @param  list<array{type: string, key?: string, slug?: string}>  $sources
     */
    private function answerWithSources(string $message, array $sources, array $userContext): JsonResponse
    {
        // ── Pick sections from each source ──
        $allSectionIds = [];
        foreach ($sources as $source) {
            try {
                $sectionIds = $this->pickSections($message, $source, $userContext);
            } catch (\Throwable $e) {
                Log::warning('Chatbot section selection failed', [
                    'error' => $e->getMessage(),
                ]);

                $sectionIds = $this->allSectionIdsFor($source);
            }

            $allSectionIds = array_merge($allSectionIds, $sectionIds);
        }

        // ── Store conversation context for follow-up detection ──
        $firstSource = $sources[0] ?? null;
        if ($firstSource && count($sources) === 1) {
            $sourceType = $firstSource['type'];
            $sourceLabel = $firstSource['slug'] ?? $firstSource['key'] ?? 'multiple';
            session()->put('chatbot_last_context', [
                'source_type' => $sourceType,
                'source_label' => $sourceLabel,
                'article_title' => $sourceType === 'helpdesk'
                    ? ($this->helpdesk->getTitle($sourceLabel) ?? 'Selected Article')
                    : ($this->guide->getAllTopics()[$sourceLabel]['heading'] ?? 'Selected Topic'),
            ]);
        } else {
            session()->put('chatbot_last_context', [
                'source_type' => 'multiple',
                'source_label' => 'multiple',
                'article_title' => 'Selected Articles',
            ]);
        }

        // ── Build action links based on matched sources ──
        $actions = [];
        foreach ($sources as $source) {
            if (($source['slug'] ?? '') === 'using-public-tracking-portal') {
                $actions[] = [
                    'label' => 'Go to Tracking Portal',
                    'url' => route('track.index'),
                    'icon' => 'track',
                ];
            }
        }

        // ── Load section content + generate answer ──
        return $this->answerFromSections($message, $allSectionIds, $userContext, $actions);
    }

    // ──────────────────────────────────────────────
    //  Fallback paths
    // ──────────────────────────────────────────────

    /**
     * Answer using curated fallback sections when article/section selection fails.
     */
    private function answerWithFallback(string $message, array $userContext): JsonResponse
    {
        return $this->answerFromSections($message, ['__fallback__'], $userContext);
    }

    /**
     * Generic fallback when even classification fails.
     */
    private function fallbackResponse(): JsonResponse
    {
        return response()->json([
            'reply' => "I'm sorry, I'm having trouble processing your request right now. Please try again later or browse our Help Center for assistance.",
        ]);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Load content from a list of section identifiers.
     *
     * Supports guide keys (plain strings) and helpdesk format ("slug::Heading").
     *
     * @param  list<string>  $sectionIds
     */
    private function loadSectionContent(array $sectionIds): string
    {
        // Special fallback sentinel
        if ($sectionIds === ['__fallback__']) {
            return $this->helpdesk->getFallbackSections();
        }

        $guideKeys = [];
        $helpdeskIds = [];

        foreach ($sectionIds as $id) {
            if (str_contains($id, '::')) {
                $helpdeskIds[] = $id;
            } else {
                $guideKeys[] = $id;
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

    /**
     * Build section identifiers for all sections of a source.
     *
     * @param  array{type: string, key?: string, slug?: string}  $source
     * @return list<string>
     */
    private function allSectionIdsFor(array $source): array
    {
        if ($source['type'] === 'guide') {
            return [$source['key']];
        }

        $slug = $source['slug'];
        $headings = $this->helpdesk->getArticleHeadings($slug);

        return array_map(fn ($h) => "{$slug}::{$h}", $headings);
    }

    /**
     * Normalise a heading for fuzzy comparison: lowercase, strip non-alphanumeric,
     * collapse whitespace. Mirrors ChatbotHelpdeskService::normalizeKey().
     */
    private function normalizeHeading(string $heading): string
    {
        $key = preg_replace('/[^a-z0-9\s]+/i', ' ', $heading);

        return strtolower(trim(preg_replace('/\s+/', ' ', $key)));
    }

    private function randomReply(array $replies): string
    {
        return $replies[array_rand($replies)];
    }
}
