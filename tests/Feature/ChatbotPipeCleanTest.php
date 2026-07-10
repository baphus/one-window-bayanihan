<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HTTP-level verification of the pipeline's LLM budget: canned intents and
 * the verbatim tier make ZERO model requests; content queries make exactly ONE.
 * Requests to the Ollama backend are recorded via Http::fake.
 */
class ChatbotPipeCleanTest extends TestCase
{
    private array $ollamaRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chatbot.retrieval.index_path' => storage_path('framework/testing/chatbot-index-pipe.sqlite'),
            'ai-chatbot.provider' => 'ollama',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('framework/testing/chatbot-index-pipe.sqlite'));

        parent::tearDown();
    }

    /**
     * Record every outbound model request and answer with a canned completion.
     */
    private function fakeOllama(): void
    {
        $this->ollamaRequests = [];

        Http::fake(function (Request $request) {
            $body = json_decode($request->body(), true);
            $this->ollamaRequests[] = [
                'url' => $request->url(),
                'body' => $body,
            ];

            return Http::response([
                'message' => ['content' => 'The case statuses use color-coded indicators to show progress.'],
                'model' => config('ai-chatbot.model'),
                'done_reason' => 'stop',
                'prompt_eval_count' => 200,
                'eval_count' => 40,
            ]);
        });
    }

    /** Extract the system and user message contents from a recorded request. */
    private function requestMessages(array $recorded): array
    {
        $system = '';
        $user = '';
        foreach ($recorded['body']['messages'] ?? [] as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $system .= $msg['content'] ?? '';
            }
            if (($msg['role'] ?? '') === 'user') {
                $user .= $msg['content'] ?? '';
            }
        }

        return [$system, $user];
    }

    // ──────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────

    public function test_content_query_makes_exactly_one_llm_call(): void
    {
        $this->fakeOllama();
        session()->forget('chatbot_last_context');
        config(['ai-chatbot.retrieval.verbatim_min_score' => 1000.0]); // force the LLM path

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'What do the colors mean for case status?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['reply']);

        $this->assertCount(1, $this->ollamaRequests,
            'Content queries must make exactly one LLM call (answer generation only)');

        [$system, $user] = $this->requestMessages($this->ollamaRequests[0]);

        // The single call is the grounded answer prompt: our rules in the system
        // message, the question plus retrieved reference content in the user message.
        $this->assertStringContainsString('CRITICAL RULES', $system);
        $this->assertStringContainsString('What do the colors mean for case status?', $user);
        $this->assertStringContainsString('---', $user, 'Retrieved reference content should be attached to the prompt');
    }

    public function test_greeting_makes_zero_llm_calls(): void
    {
        $this->fakeOllama();

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'hello',
        ]);

        $response->assertStatus(200);
        $this->assertCount(0, $this->ollamaRequests, 'Greetings must not reach the LLM');
        $this->assertStringContainsString(config('ai-chatbot.assistant_name'), $response->json('reply'));
    }

    public function test_verbatim_tier_makes_zero_llm_calls(): void
    {
        $this->fakeOllama();
        session()->forget('chatbot_last_context');
        config([
            'ai-chatbot.retrieval.verbatim_min_score' => 0.1,
            'ai-chatbot.retrieval.verbatim_gap_ratio' => 0.1,
        ]);

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I use the public tracking portal',
        ]);

        $response->assertStatus(200);
        $this->assertCount(0, $this->ollamaRequests, 'Verbatim answers must not reach the LLM');
        $this->assertStringContainsString('tracking', strtolower($response->json('reply')));
    }

    public function test_tuned_defaults_answer_unambiguous_query_verbatim(): void
    {
        // Uses the shipped default thresholds — this locks in the 3.7 tuning.
        $this->fakeOllama();
        session()->forget('chatbot_last_context');

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how does the OTP verification work',
        ]);

        $response->assertStatus(200);
        $this->assertCount(0, $this->ollamaRequests,
            'Unambiguous single-topic query should be answered verbatim under default thresholds');
        $this->assertStringContainsString('otp', strtolower($response->json('reply')));
    }

    public function test_tuned_defaults_send_ambiguous_query_to_llm(): void
    {
        $this->fakeOllama();
        session()->forget('chatbot_last_context');

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'why is my OTP not arriving',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests,
            'Ambiguous multi-source query should be synthesized by the LLM under default thresholds');
    }

    public function test_follow_up_reuses_stored_context_in_one_call(): void
    {
        $this->fakeOllama();
        config(['ai-chatbot.retrieval.verbatim_min_score' => 1000.0]);

        session()->put('chatbot_last_context', [
            'source_type' => 'helpdesk',
            'source_label' => 'using-public-tracking-portal',
            'article_title' => 'Using the Public Tracking Portal',
        ]);

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'What documents do I need?',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests, 'Follow-up must still be a single LLM call');

        [, $user] = $this->requestMessages($this->ollamaRequests[0]);
        $this->assertStringContainsString('Public Tracking Portal', $user,
            'Follow-up should be grounded in the stored article content');
    }
}
