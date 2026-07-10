<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotPipeCleanTest extends TestCase
{
    private array $ollamaRequests = [];

    /** Shared Ollama response for generic answer generation. */
    private function answerResponse(): array
    {
        return [
            'message' => ['content' => 'The case statuses use color-coded indicators: OPEN (blue) means received, PROCESSING (yellow) means being worked on, FOR COMPLIANCE (orange) means more info needed, and CLOSED (green) means resolved.'],
            'model' => 'llama3:latest',
            'done_reason' => 'stop',
            'prompt_eval_count' => 200,
            'eval_count' => 40,
        ];
    }

    /** Shared Ollama response for section picking. */
    private function sectionsResponse(): array
    {
        return [
            'message' => ['content' => "Step 5: Viewing Case Status\nWhat Each Status Means for You"],
            'model' => 'llama3:latest',
            'done_reason' => 'stop',
            'prompt_eval_count' => 60,
            'eval_count' => 15,
        ];
    }

    /**
     * Set up the Http fake that records all Ollama requests.
     *
     * @param  callable|null  $classifyDecision  Return the classify response array.
     *                                           Default returns "relevant".
     */
    private function fakeOllama(?callable $classifyDecision = null): void
    {
        $this->ollamaRequests = [];

        $classifyDecision ??= fn () => Http::response([
            'message' => ['content' => 'relevant'],
            'model' => 'llama3:latest',
            'done_reason' => 'stop',
            'prompt_eval_count' => 50,
            'eval_count' => 1,
        ]);

        Http::fake(function (Request $request) use ($classifyDecision) {
            $body = json_decode($request->body(), true);
            $this->ollamaRequests[] = [
                'url' => $request->url(),
                'method' => $request->method(),
                'body' => $body,
                'preview' => mb_substr($body['messages'][0]['content'] ?? '', 0, 120),
            ];

            $instructions = '';
            foreach ($body['messages'] ?? [] as $msg) {
                if ($msg['role'] === 'system') {
                    $instructions = $msg['content'];
                }
            }

            // classifyIntent
            if (str_contains($instructions, 'classifier for an OFW assistance chatbot')) {
                return $classifyDecision();
            }

            // pickSections
            if (str_contains($instructions, 'Select 1-3 sections')) {
                return Http::response($this->sectionsResponse());
            }

            // answerFromSections (fallback)
            return Http::response($this->answerResponse());
        });
    }

    // ──────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────

    public function test_colors_question_full_pipeline(): void
    {
        $this->fakeOllama();
        session()->forget('chatbot_last_context');

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'What do the colors mean for case status?',
        ]);

        // Assert response structure
        $response->assertStatus(200);
        $response->assertJsonStructure(['reply']);
        $reply = $response->json('reply');
        $this->assertNotNull($reply);
        $this->assertIsString($reply);
        $this->assertGreaterThan(0, strlen($reply));

        // matchArticles("What do the colors mean for case status?") returns 2 articles:
        //   1st: understanding-case-statuses-tracker-numbers (score 7: title "case"+2 "status"+2, excerpt "mean"+1 "case"+1 "status"+1)
        //   2nd: using-public-tracking-portal (score 2: excerpt "case"+1 "status"+1)
        //   ("colors" keyword does NOT substring-match in the excerpt, so it scores 0)
        // So: 1 classify + 2 pickSections + 1 answer = 4 calls
        $this->assertCount(4, $this->ollamaRequests,
            'Pipeline should make 4 LLM calls (classify + 2× pickSections + answer)'
        );

        // Call 1: classifyIntent
        $classifyBody = $this->ollamaRequests[0]['body'] ?? [];
        $this->assertStringContainsString(
            'classifier for an OFW assistance chatbot',
            $classifyBody['messages'][0]['content'] ?? '',
        );

        // Call 2: pickSections for first matched article (statuses article — higher keyword score)
        $pick1Body = $this->ollamaRequests[1]['body'] ?? [];
        $this->assertStringContainsString(
            'understanding-case-statuses-tracker-numbers',
            $pick1Body['messages'][0]['content'] ?? '',
            'First pickSections should be the statuses article (higher keyword score)',
        );

        // Call 3: pickSections for second matched article (tracking portal)
        $pick2Body = $this->ollamaRequests[2]['body'] ?? [];
        $this->assertStringContainsString(
            'using-public-tracking-portal',
            $pick2Body['messages'][0]['content'] ?? '',
            'Second pickSections should be the tracking portal article',
        );

        // Call 4: answerFromSections — reference content should include status info
        $answerBody = $this->ollamaRequests[3]['body'] ?? [];
        $answerUserMsg = '';
        foreach ($answerBody['messages'] as $msg) {
            if ($msg['role'] === 'user') {
                $answerUserMsg = $msg['content'];
                break;
            }
        }
        $this->assertStringContainsString(
            'DRAFT',
            $answerUserMsg,
            'Answer step should receive DRAFT status from case statuses section',
        );
        $this->assertStringContainsString(
            'OPEN',
            $answerUserMsg,
            'Answer step should receive OPEN status from case statuses section',
        );

        // Reply should mention status
        $this->assertStringContainsStringIgnoringCase('status', $reply,
            'Reply should mention case status in the response'
        );
    }

    public function test_follow_up_reuses_stored_context(): void
    {
        // For this test, the classify must return "follow_up" so the
        // follow-up branch is exercised. Then pickSections + answer run.
        $this->fakeOllama(classifyDecision: fn () => Http::response([
            'message' => ['content' => 'follow_up'],
            'model' => 'llama3:latest',
            'done_reason' => 'stop',
            'prompt_eval_count' => 50,
            'eval_count' => 1,
        ]));

        session()->put('chatbot_last_context', [
            'source_type' => 'helpdesk',
            'source_label' => 'using-public-tracking-portal',
            'article_title' => 'Using the Public Tracking Portal',
        ]);

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'What documents do I need?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['reply']);

        // 1 classify(follow_up) → follow-up shortcut → 1 pickSections → 1 answer = 3
        $this->assertCount(3, $this->ollamaRequests);

        // The classify call should include "Previous topic" context
        $classifyBody = $this->ollamaRequests[0]['body'] ?? [];
        $this->assertStringContainsString(
            'Previous topic: Using the Public Tracking Portal',
            $classifyBody['messages'][0]['content'] ?? '',
            'Classify should include stored context for follow-up detection',
        );

        // pickSections call should reference the stored article
        $pickBody = $this->ollamaRequests[1]['body'] ?? [];
        $this->assertStringContainsString(
            'using-public-tracking-portal',
            $pickBody['messages'][0]['content'] ?? '',
            'Follow-up should reuse the stored article slug',
        );
    }
}
