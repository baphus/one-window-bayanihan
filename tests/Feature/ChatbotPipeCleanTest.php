<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Chatbot\ChatbotHybridSearch;
use App\Services\Chatbot\ChatbotRetrievalService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HTTP-level verification of the pipeline's LLM budget: canned intents and
 * the verbatim tier make ZERO model requests; content queries make exactly ONE.
 *
 * The hybrid search and FTS5 content retrieval are mocked so the tests focus
 * purely on the controller's routing logic — not retrieval quality.
 */
class ChatbotPipeCleanTest extends TestCase
{
    private array $ollamaRequests = [];

    /** Sample helpdesk content returned by mocked contentFor(). */
    private const SAMPLE_CONTENT = <<<'MD'
## Using the Public Tracking Portal

The public tracking portal allows OFWs to check their case status in real time.

## What Different Case Statuses Mean

Once inside the tracking portal, you will see your case's current **status**.

| Status | Meaning |
|--------|---------|
| **Submitted** | The request has been received. |
| **Under Review** | A case manager is actively evaluating. |
| **Approved** | The request has been approved. |

---

## OTP Verification Process

When you enter your tracker number, the system sends a one-time password (OTP) to your registered mobile number. Enter the OTP within 5 minutes to verify your identity.
MD;

    /** Sample reference (DB) content for TUPAD. */
    private const TUPAD_CONTENT = <<<'MD'
# TUPAD Program (Department of Labor and Employment)

**DOLE**

TUPAD (Tulong Panghanapbuhay sa Ating Disadvantaged/Displaced Workers) is a community-based livelihood and emergency employment assistance program.

Processing time: 21 days

**Requirements:**
- Valid ID (required)
- Barangay certificate (required)
MD;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chatbot.provider' => 'ollama',
        ]);

        // Mock contentFor() to return known helpdesk content
        $this->mock(ChatbotRetrievalService::class, function ($mock) {
            $mock->shouldReceive('contentFor')->andReturnUsing(function (array $hits): string {
                $key = $hits[0]['source_key'] ?? $hits[0]['slug'] ?? 'unknown';

                if (str_contains($key, 'tupad') || str_contains($key, 'agency:')) {
                    return self::TUPAD_CONTENT;
                }

                return self::SAMPLE_CONTENT;
            });
            $mock->shouldReceive('tokenize')->andReturnUsing(function (string $text): array {
                return preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            });
        });
    }

    /**
     * Record outbound Ollama requests: embed → embeddingRequests, chat → ollamaRequests.
     */
    private function fakeOllama(): void
    {
        $this->ollamaRequests = [];

        Http::fake(function (Request $request) {
            $body = json_decode($request->body(), true);
            $url = $request->url();

            // Embedding requests — not counted as LLM calls
            if (str_contains($url, '/api/embed')) {
                return Http::response([
                    'embeddings' => [array_fill(0, 768, 0.1)],
                ]);
            }

            // Chat/completion requests — the actual LLM calls
            $this->ollamaRequests[] = [
                'url' => $url,
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

    /**
     * Bind a mock hybrid search that returns controlled results.
     *
     * @param  list<array{source_type: string, source_key: string, slug: string, heading: string, audience_group: string, score: float}>  $hits
     */
    private function mockHybridResults(array $hits, float $confidence, bool $clearWinner): void
    {
        $this->mock(ChatbotHybridSearch::class, function ($mock) use ($hits, $confidence, $clearWinner) {
            $mock->shouldReceive('search')->andReturn([
                'hits' => $hits,
                'confidence' => $confidence,
                'clear_winner' => $clearWinner,
                'vector_count' => count($hits),
                'fts_count' => count($hits),
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

    private static function trackingHit(): array
    {
        return [
            'source_type' => 'helpdesk',
            'source_key' => 'using-public-tracking-portal::What Different Case Statuses Mean',
            'slug' => 'using-public-tracking-portal',
            'heading' => 'What Different Case Statuses Mean',
            'audience_group' => 'OFW & Public',
            'score' => 0.05,
        ];
    }

    private static function otpHit(): array
    {
        return [
            'source_type' => 'helpdesk',
            'source_key' => 'using-public-tracking-portal::OTP Verification Process',
            'slug' => 'using-public-tracking-portal',
            'heading' => 'OTP Verification Process',
            'audience_group' => 'OFW & Public',
            'score' => 0.04,
        ];
    }

    // ──────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────

    public function test_content_query_makes_exactly_one_llm_call(): void
    {
        $this->fakeOllama();
        $this->mockHybridResults([self::trackingHit()], 0.5, true);
        config(['ai-chatbot.hybrid.llm_confidence' => 0.0]); // force LLM path

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'What do the colors mean for case status?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['reply']);

        $this->assertCount(1, $this->ollamaRequests,
            'Content queries must make exactly one LLM call (answer generation only)');

        [$system, $user] = $this->requestMessages($this->ollamaRequests[0]);

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

    public function test_high_confidence_query_reaches_llm(): void
    {
        $this->fakeOllama();
        $this->mockHybridResults(
            [self::trackingHit()],
            confidence: 0.9,
            clearWinner: true,
        );

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I use the public tracking portal',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests, 'All queries with hits should reach the LLM');
        $response->assertJsonStructure(['reply', 'sources', 'confidence']);
    }

    public function test_unambiguous_query_reaches_llm(): void
    {
        // Uses the shipped default thresholds — all queries go through the LLM now.
        $this->fakeOllama();
        $this->mockHybridResults(
            [self::otpHit()],
            confidence: 0.8,
            clearWinner: true,
        );

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how does the OTP verification work',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests,
            'All queries with hits should reach the LLM');
        $response->assertJsonStructure(['reply', 'sources', 'confidence']);
    }

    public function test_tuned_defaults_send_ambiguous_query_to_llm(): void
    {
        $this->fakeOllama();
        $this->mockHybridResults(
            [self::trackingHit(), self::otpHit()],
            confidence: 0.5,
            clearWinner: false,
        );

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'why is my OTP not arriving',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests,
            'Ambiguous multi-source query should be synthesized by the LLM under default thresholds');
    }

    public function test_follow_up_with_low_confidence_includes_previous_article(): void
    {
        $this->fakeOllama();
        $this->mockHybridResults([], 0.1, false); // low confidence, no hits

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'What documents do I need?',
            'history' => [
                ['role' => 'user', 'text' => 'How do I use the tracking portal?'],
                ['role' => 'bot', 'text' => 'You can use the tracking portal to check your case status.'],
            ],
            'lastContext' => [
                'source_type' => 'helpdesk',
                'source_label' => 'using-public-tracking-portal',
                'article_title' => 'Using the Public Tracking Portal',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests, 'Follow-up must still be a single LLM call');

        [$system, $user] = $this->requestMessages($this->ollamaRequests[0]);
        $this->assertStringContainsString('Tracking Portal', $user,
            'Low-confidence follow-up should include the previously discussed article in the prompt');
        $this->assertStringContainsString('How do I use the tracking portal?', $system,
            'Conversation history should be included in the system instructions');
    }

    public function test_unclear_followup_reaches_llm_instead_of_canned_response(): void
    {
        $this->fakeOllama();
        $this->mockHybridResults([], 0.05, false); // low confidence for vague query

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'such as?',
            'history' => [
                ['role' => 'user', 'text' => 'what are the services offered by DOLE'],
            ],
            'lastContext' => [
                'source_type' => 'helpdesk',
                'source_label' => 'using-public-tracking-portal',
                'article_title' => 'Using the Public Tracking Portal',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests,
            'UNCLEAR_FOLLOWUP should reach the LLM, not return a canned response');
        $response->assertJsonStructure(['reply', 'sources', 'confidence']);
    }

    public function test_high_confidence_includes_stored_context_as_labeled_source(): void
    {
        $this->fakeOllama();
        $this->mockHybridResults([self::trackingHit()], 0.8, true);

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I use the public tracking portal',
            'lastContext' => [
                'source_type' => 'reference',
                'source_label' => 'dole',
                'article_title' => 'TUPAD Program',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests);

        [, $user] = $this->requestMessages($this->ollamaRequests[0]);
        // Stored context should appear as a labeled source alongside fresh hits
        $this->assertStringContainsString('AVAILABLE SOURCES', $user,
            'LLM should receive all sources labeled for it to decide');
        $this->assertStringContainsString('dole', $user,
            'Stored context should be included as a labeled source');
    }

    public function test_no_previous_article_when_no_stored_context(): void
    {
        $this->fakeOllama();
        $this->mockHybridResults([self::trackingHit()], 0.8, true);

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I use the public tracking portal',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests);

        [, $user] = $this->requestMessages($this->ollamaRequests[0]);
        $this->assertStringNotContainsString('PREVIOUSLY DISCUSSED ARTICLE', $user,
            'Old fallback label should never appear');
    }

    // ──────────────────────────────────────────────
    //  Role-based user context in system prompt
    // ──────────────────────────────────────────────

    public function test_unauthenticated_user_label_is_public_ofw(): void
    {
        $this->fakeOllama();
        $this->mockHybridResults([self::trackingHit()], 0.8, true);

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I use the public tracking portal',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests);

        [$system] = $this->requestMessages($this->ollamaRequests[0]);
        $this->assertStringContainsString(
            'a public OFW (not logged in)',
            $system,
            'Unauthenticated user should be labeled as public OFW',
        );
    }

    public function test_case_manager_user_label_in_system_prompt(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->actingAs($user);

        $this->fakeOllama();
        $this->mockHybridResults([self::trackingHit()], 0.8, true);

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I manage overdue referrals',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests);

        [$system] = $this->requestMessages($this->ollamaRequests[0]);
        $this->assertStringContainsString(
            'a logged-in Case Manager',
            $system,
            'Case Manager user should be labeled correctly',
        );
    }

    public function test_agency_user_label_in_system_prompt(): void
    {
        $user = User::factory()->create(['role' => 'AGENCY']);
        $this->actingAs($user);

        $this->fakeOllama();
        $this->mockHybridResults([self::trackingHit()], 0.8, true);

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I manage my agency services profile',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests);

        [$system] = $this->requestMessages($this->ollamaRequests[0]);
        $this->assertStringContainsString(
            'a logged-in Agency Focal Person',
            $system,
            'Agency user should be labeled correctly',
        );
    }

    public function test_admin_user_label_in_system_prompt(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($user);

        $this->fakeOllama();
        $this->mockHybridResults([self::trackingHit()], 0.8, true);

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I configure the system settings',
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $this->ollamaRequests);

        [$system] = $this->requestMessages($this->ollamaRequests[0]);
        $this->assertStringContainsString(
            'a logged-in Administrator',
            $system,
            'Admin user should be labeled correctly',
        );
    }

    /**
     * Verify that the correct audience groups are passed to the hybrid
     * search based on the user's role. Uses a spy that records the
     * audience groups argument.
     */
    public function test_audience_groups_passed_for_case_manager(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->actingAs($user);

        $this->fakeOllama();

        // Spy on search to capture audience groups
        $captured = [];
        $this->mock(ChatbotHybridSearch::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('search')
                ->andReturnUsing(function ($query, $audienceGroups) use (&$captured) {
                    $captured['groups'] = $audienceGroups;

                    return [
                        'hits' => [self::trackingHit()],
                        'confidence' => 0.8,
                        'clear_winner' => true,
                        'vector_count' => 1,
                        'fts_count' => 1,
                    ];
                });
        });

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I manage overdue referrals',
        ]);

        $response->assertStatus(200);

        $this->assertArrayHasKey('groups', $captured, 'Hybrid search should have been called');

        $expected = ['OFW & Public', 'Case Managers', 'General'];
        $this->assertEquals($expected, $captured['groups'],
            'Case Manager should receive OFW & Public, Case Managers, and General audience groups');
    }

    public function test_audience_groups_passed_for_agency_user(): void
    {
        $user = User::factory()->create(['role' => 'AGENCY']);
        $this->actingAs($user);

        $this->fakeOllama();

        $captured = [];
        $this->mock(ChatbotHybridSearch::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('search')
                ->andReturnUsing(function ($query, $audienceGroups) use (&$captured) {
                    $captured['groups'] = $audienceGroups;

                    return [
                        'hits' => [self::trackingHit()],
                        'confidence' => 0.8,
                        'clear_winner' => true,
                        'vector_count' => 1,
                        'fts_count' => 1,
                    ];
                });
        });

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I manage my agency services',
        ]);

        $response->assertStatus(200);

        $this->assertArrayHasKey('groups', $captured);

        $expected = ['OFW & Public', 'Agency Focal Persons', 'General'];
        $this->assertEquals($expected, $captured['groups'],
            'Agency user should receive OFW & Public, Agency Focal Persons, and General audience groups');
    }

    public function test_audience_groups_passed_for_admin_is_null(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($user);

        $this->fakeOllama();

        $captured = [];
        $this->mock(ChatbotHybridSearch::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('search')
                ->andReturnUsing(function ($query, $audienceGroups) use (&$captured) {
                    $captured['groups'] = $audienceGroups;

                    return [
                        'hits' => [self::trackingHit()],
                        'confidence' => 0.8,
                        'clear_winner' => true,
                        'vector_count' => 1,
                        'fts_count' => 1,
                    ];
                });
        });

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I configure system settings',
        ]);

        $response->assertStatus(200);

        $this->assertArrayHasKey('groups', $captured);
        $this->assertNull($captured['groups'],
            'Admin should receive null (show all) audience groups');
    }

    public function test_audience_groups_passed_for_unauthenticated(): void
    {
        $this->fakeOllama();

        $captured = [];
        $this->mock(ChatbotHybridSearch::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('search')
                ->andReturnUsing(function ($query, $audienceGroups) use (&$captured) {
                    $captured['groups'] = $audienceGroups;

                    return [
                        'hits' => [self::trackingHit()],
                        'confidence' => 0.8,
                        'clear_winner' => true,
                        'vector_count' => 1,
                        'fts_count' => 1,
                    ];
                });
        });

        $response = $this->postJson(route('chatbot.message'), [
            'message' => 'how do I track my case',
        ]);

        $response->assertStatus(200);

        $this->assertArrayHasKey('groups', $captured);

        $expected = ['OFW & Public', 'General'];
        $this->assertEquals($expected, $captured['groups'],
            'Unauthenticated user should receive OFW & Public and General audience groups');
    }
}
