<?php

namespace App\Http\Controllers;

use App\Contracts\HelpCenterProviderInterface;
use App\Models\SystemSetting;
use App\Services\Ai\AiService;
use App\Services\Ai\Contracts\ToolEnabledAiProvider;
use App\Services\Ai\PromptAssemblyService;
use App\Services\Content\ContentSanitizerService;
use App\Services\Observability\RetrievalLogger;
use App\Services\Observability\UnansweredTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    private array $responses = [
        'hello' => 'Hello! Welcome to the Bayanihan One Window support. How can I assist you today?',
        'help' => 'I can help you with:<br>- Case status inquiries<br>- Service requirements<br>- Agency information<br>- Referral process guidance<br>- Document requirements',
        'case' => 'To track your case, please visit our public tracking portal at /track and enter your tracker number. You will receive an OTP to verify your identity.',
        'track' => 'To track your case, please visit our public tracking portal at /track and enter your tracker number. You will receive an OTP to verify your identity.',
        'service' => 'Our partner agencies offer various services including:<br>- OWWA: Repatriation assistance, welfare support<br>- DMW: Legal assistance, employment documentation<br>- TESDA: Skills training and assessment<br>- DSWD: Social welfare assistance<br>- DOLE: Labor law assistance',
        'agency' => 'We work with multiple government agencies to provide comprehensive support. Each agency has its own lane for processing referrals. Contact us for specific agency details.',
        'document' => 'Required documents typically include:<br>- Valid government ID<br>- Employment contract<br>- Passport<br>- Proof of engagement with agency<br>- Supporting documents for your specific concern',
        'referral' => 'A referral is sent to the appropriate agency based on your needs. The agency will process it and update the status. You can track progress through our portal.',
        'default' => "I'm not sure I understand. Please try asking about:<br>- Case tracking<br>- Required documents<br>- Agency services<br>- Referral process<br>Or type \"help\" to see all options.",
    ];

    public function __construct(
        private readonly AiService $aiService,
        private readonly HelpCenterProviderInterface $helpCenterProvider,
        private readonly PromptAssemblyService $promptAssembly,
        private readonly ContentSanitizerService $sanitizer,
        private readonly RetrievalLogger $retrievalLogger,
        private readonly UnansweredTracker $unansweredTracker,
    ) {}

    public function message(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $userMessage = $request->input('message');
        $startTime = microtime(true);

        // Try AI-first (backward-compatible flow via AiService::sendMessage)
        try {
            $aiReply = $this->aiService->sendMessage($userMessage);
            if (! empty($aiReply)) {
                $latencyMs = (microtime(true) - $startTime) * 1000;
                $this->retrievalLogger->logRetrieval($userMessage, 0, 0.0, $latencyMs);

                return response()->json([
                    'reply' => nl2br(e($aiReply)),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Chatbot AI service sendMessage failed', [
                'error' => $e->getMessage(),
                'provider' => SystemSetting::getValue('chatbot_provider', 'unknown'),
            ]);
        }

        // If sendMessage returned empty, try tool-enabled flow with Help Center retrieval
        try {
            $provider = $this->aiService->getProvider();
            if ($provider instanceof ToolEnabledAiProvider && $provider->isConfigured()) {
                $reply = $this->handleToolBasedQuery($provider, $userMessage, $startTime);
                if (! empty($reply)) {
                    return response()->json(['reply' => nl2br(e($reply))]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Chatbot AI tool provider failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: keyword matching
        $message = strtolower(trim($userMessage));
        $response = $this->getResponse($message);

        return response()->json([
            'reply' => nl2br(e($response)),
        ]);
    }

    /**
     * Handle query using a ToolEnabledAiProvider with Help Center retrieval.
     */
    private function handleToolBasedQuery(ToolEnabledAiProvider $provider, string $userMessage, float $startTime): string
    {
        $articles = $this->helpCenterProvider->search($userMessage);
        $latencyMs = (microtime(true) - $startTime) * 1000;

        $this->retrievalLogger->logRetrieval(
            $userMessage,
            $articles->count(),
            0.0,
            $latencyMs
        );

        if ($articles->isEmpty()) {
            $this->unansweredTracker->logUnanswered($userMessage, 'no articles found');
            $systemPrompt = $this->promptAssembly->buildSystemPrompt(collect(), $userMessage);
        } else {
            $systemPrompt = $this->promptAssembly->buildSystemPrompt($articles, $userMessage);

            if (str_contains($systemPrompt, 'could not find documentation')) {
                $this->unansweredTracker->logUnanswered($userMessage, 'articles below relevance threshold');

                return 'I could not find documentation for that. Please try rephrasing your question or browse our Help Center.';
            }
        }

        return $provider->sendMessageWithTools(
            $userMessage,
            $provider->getTools(),
            $this->createToolHandler(),
            [
                'system_prompt' => $systemPrompt,
                'temperature' => (float) SystemSetting::getValue('chatbot_temperature', '0.7'),
                'max_tokens' => (int) SystemSetting::getValue('chatbot_max_tokens', '500'),
            ]
        );
    }

    /**
     * Create a tool handler closure for searchHelpCenter and getArticleBySlug.
     */
    private function createToolHandler(): callable
    {
        return function (string $name, array $args) {
            return match ($name) {
                'searchHelpCenter' => $this->handleSearchHelpCenter($args),
                'getArticleBySlug' => $this->handleGetArticleBySlug($args),
                default => json_encode(['error' => "Unknown tool: {$name}"]),
            };
        };
    }

    /**
     * Handle the searchHelpCenter tool call.
     */
    private function handleSearchHelpCenter(array $args): string
    {
        $query = $args['query'] ?? '';
        $limit = min((int) ($args['limit'] ?? 5), 10);

        if (empty($query)) {
            return json_encode([]);
        }

        $articles = $this->helpCenterProvider->search($query, ['limit' => $limit]);
        $results = [];

        foreach ($articles->take($limit) as $article) {
            $results[] = [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $this->sanitizer->sanitizeForLLM(
                    $article->excerpt ?? mb_substr($article->content_markdown ?? '', 0, 300)
                ),
                'category' => $article->relationLoaded('category') && $article->category
                    ? $article->category->name
                    : null,
                'tags' => $article->relationLoaded('tags')
                    ? $article->tags->pluck('name')->toArray()
                    : [],
            ];
        }

        return json_encode($results);
    }

    /**
     * Handle the getArticleBySlug tool call.
     */
    private function handleGetArticleBySlug(array $args): string
    {
        $slug = $args['slug'] ?? '';

        if (empty($slug)) {
            return json_encode(['error' => 'Slug is required']);
        }

        $article = $this->helpCenterProvider->getBySlug($slug);

        if (! $article) {
            return json_encode(['error' => 'Article not found']);
        }

        return json_encode([
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'content' => $this->sanitizer->sanitizeForLLM($article->content_markdown ?? ''),
            'category' => $article->relationLoaded('category') && $article->category
                ? $article->category->name
                : null,
            'tags' => $article->relationLoaded('tags')
                ? $article->tags->pluck('name')->toArray()
                : [],
        ]);
    }

    private function getResponse(string $message): string
    {
        foreach ($this->responses as $keyword => $response) {
            if (str_contains($message, $keyword)) {
                return $response;
            }
        }

        return $this->responses['default'];
    }
}
