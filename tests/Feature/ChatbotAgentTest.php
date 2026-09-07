<?php

namespace Tests\Feature;

use App\Ai\Agents\HelpdeskAgent;
use App\Ai\Tools\ReadHelpdeskArticle;
use App\Ai\Tools\SearchHelpdesk;
use App\Services\Chatbot\ChatbotAudience;
use App\Services\Chatbot\ChatbotConversationService;
use App\Services\Chatbot\ChatbotHelpdeskService;
use App\Services\Chatbot\ChatbotKnowledge;
use App\Services\Chatbot\ChatbotResponse;
use App\Services\Chatbot\ChatbotTurn;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ChatbotAgentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai-chatbot.enabled' => true, 'ai-chatbot.provider' => 'gemini', 'ai-chatbot.model' => 'test-model']);
        Http::preventStrayRequests();
    }

    public function test_sdk_search_read_answer_loop_produces_verified_article_source(): void
    {
        HelpdeskAgent::fake([
            new ToolCall('search', 'SearchHelpdesk', ['query' => 'track my case']),
            new ToolCall('read', 'ReadHelpdeskArticle', ['slug' => 'using-public-tracking-portal', 'sections' => ['What you need']]),
            ['status' => 'answered', 'reply' => 'Use your tracker number.', 'evidence_ids' => ['e1']],
        ])->preventStrayPrompts();
        $factory = Http::getFacadeRoot();
        $result = app(ChatbotConversationService::class)->reply(['message' => 'How do I track my case?'], new ChatbotAudience);
        $this->assertSame('answered', $result['status']);
        $this->assertSame('using-public-tracking-portal', $result['sources'][0]['slug']);
        $this->assertSame(route('helpdesk.show', 'using-public-tracking-portal'), $result['sources'][0]['url']);
        $this->assertSame(['What you need'], $result['sources'][0]['sections']);
        $this->assertSame($factory, Http::getFacadeRoot());
    }

    public function test_unread_fabricated_evidence_is_not_published(): void
    {
        HelpdeskAgent::fake([['status' => 'answered', 'reply' => 'Your case is closed.', 'evidence_ids' => ['e999']]])->preventStrayPrompts();
        $result = app(ChatbotConversationService::class)->reply(['message' => 'Case status?'], new ChatbotAudience);
        $this->assertSame('unavailable', $result['status']);
        $this->assertSame([], $result['sources']);
        $this->assertNull($result['lastContext']);
        $this->assertStringNotContainsString('closed', $result['reply']);
    }

    public function test_search_does_not_register_evidence_and_direct_read_enforces_audience(): void
    {
        $knowledge = app(ChatbotKnowledge::class);
        $turn = new ChatbotTurn;
        $public = new ChatbotAudience;
        $search = new SearchHelpdesk($knowledge, $public, $turn);
        $this->assertNotEmpty(json_decode($search->handle(new Request(['query' => 'tracking'])), true)['results']);
        $this->assertSame([], $turn->metrics()['evidence_ids']);
        $read = new ReadHelpdeskArticle($knowledge, $public, $turn);
        foreach (['getting-started-system-admin', '../config', 'getting-started-case-managers'] as $slug) {
            $result = json_decode($read->handle(new Request(['slug' => $slug, 'sections' => [], 'audience' => 'ADMIN'])), true);
            $this->assertSame('not_found', $result['status']);
        }
        $this->assertSame([], $turn->metrics()['evidence_ids']);
    }

    public function test_role_scopes_are_independent(): void
    {
        $knowledge = app(ChatbotKnowledge::class);
        foreach (['CASE_MANAGER' => 'getting-started-case-managers', 'AGENCY' => 'getting-started-agency-focal', 'ADMIN' => 'getting-started-system-admin'] as $role => $slug) {
            $this->assertArrayHasKey($slug, $knowledge->articles(new ChatbotAudience($role)));
            $this->assertArrayNotHasKey($slug, $knowledge->articles(new ChatbotAudience('UNKNOWN')));
        }
    }

    public function test_sections_deduplicate_and_unread_search_candidates_are_not_cited(): void
    {
        $knowledge = app(ChatbotKnowledge::class);
        $turn = new ChatbotTurn;
        $first = $knowledge->read('using-public-tracking-portal', ['What you need'], new ChatbotAudience, $turn);
        $second = $knowledge->read('using-public-tracking-portal', ['What clients can see'], new ChatbotAudience, $turn);
        $result = app(ChatbotResponse::class)->assemble(['status' => 'answered', 'reply' => 'Tracking help.', 'evidence_ids' => [$first['evidence_id'], $second['evidence_id']]], $turn);
        $this->assertCount(1, $result['sources']);
        $this->assertCount(2, $result['sources'][0]['sections']);
    }

    public function test_missing_sections_and_content_budget_do_not_silently_truncate(): void
    {
        config(['ai-chatbot.max_context_characters' => 1]);
        $result = app(ChatbotKnowledge::class)->read('using-public-tracking-portal', ['What you need', 'not a heading'], new ChatbotAudience, new ChatbotTurn);
        $this->assertSame('no_content', $result['status']);
        $this->assertSame(['not a heading'], $result['missing_sections']);
        $this->assertSame(['What you need'], $result['omitted_sections']);
        $this->assertArrayNotHasKey('evidence_id', $result);
    }

    public function test_unsupported_clears_context_and_cannot_smuggle_facts(): void
    {
        HelpdeskAgent::fake([['status' => 'unsupported', 'reply' => 'Your case is approved.', 'evidence_ids' => []]])->preventStrayPrompts();
        $result = app(ChatbotConversationService::class)->reply(['message' => 'Quantum mechanics?', 'lastContext' => ['source_type' => 'helpdesk', 'source_label' => 'using-public-tracking-portal']], new ChatbotAudience);
        $this->assertSame('unsupported', $result['status']);
        $this->assertSame([], $result['sources']);
        $this->assertNull($result['lastContext']);
        $this->assertStringNotContainsString('approved', $result['reply']);
    }

    public function test_tool_budget_stops_repeated_calls(): void
    {
        config(['ai-chatbot.max_tool_calls' => 1]);
        $turn = new ChatbotTurn;
        $turn->call();
        $this->expectException(\RuntimeException::class);
        $turn->call();
    }

    public function test_deadline_applies_to_the_whole_turn(): void
    {
        config(['ai-chatbot.timeout' => -1]);
        $this->expectException(\RuntimeException::class);
        (new ChatbotTurn)->remaining();
    }

    public function test_disabled_feature_and_greeting_do_not_call_model(): void
    {
        HelpdeskAgent::fake([])->preventStrayPrompts();
        $service = app(ChatbotConversationService::class);
        $this->assertSame('greeting', $service->reply(['message' => 'hello'], new ChatbotAudience)['status']);
        config(['ai-chatbot.enabled' => false]);
        $this->assertSame('unavailable', $service->reply(['message' => 'tracking'], new ChatbotAudience)['status']);
        HelpdeskAgent::assertNeverPrompted();
    }

    public function test_followup_history_is_bounded_and_topic_switch_replaces_context(): void
    {
        HelpdeskAgent::fake([
            new ToolCall('read', 'ReadHelpdeskArticle', ['slug' => 'providing-feedback-on-your-case', 'sections' => []]),
            ['status' => 'answered', 'reply' => 'You can provide feedback.', 'evidence_ids' => ['e1']],
        ])->preventStrayPrompts();
        $result = app(ChatbotConversationService::class)->reply([
            'message' => 'How do I provide feedback?',
            'history' => array_fill(0, 12, ['role' => 'user', 'text' => 'How do I track my case?']),
            'lastContext' => ['source_type' => 'helpdesk', 'source_label' => 'using-public-tracking-portal'],
        ], new ChatbotAudience);
        $this->assertSame('providing-feedback-on-your-case', $result['lastContext']['source_label']);
        $this->assertCount(1, $result['sources']);
        HelpdeskAgent::assertPrompted(function ($prompt) {
            $this->assertCount(6, $prompt->agent->messages());
            $this->assertStringNotContainsString('How do I track my case?', $prompt->agent->instructions());

            return $prompt->contains('Previous article hint');
        });
    }

    public function test_unauthorized_context_hint_is_not_in_prompt(): void
    {
        HelpdeskAgent::fake([['status' => 'clarification', 'reply' => 'Which topic?', 'evidence_ids' => []]])->preventStrayPrompts();
        app(ChatbotConversationService::class)->reply(['message' => 'What next?', 'lastContext' => ['source_type' => 'helpdesk', 'source_label' => 'getting-started-system-admin']], new ChatbotAudience);
        HelpdeskAgent::assertPrompted(fn ($prompt) => ! $prompt->contains('getting-started-system-admin'));
    }

    public function test_content_hash_detects_same_size_edits_and_preserves_overview_intro(): void
    {
        $directory = sys_get_temp_dir().'/chatbot-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $path = $directory.'/sample.ts';
        $metadata = tempnam(sys_get_temp_dir(), 'chatbot-meta');
        $service = new ChatbotHelpdeskService;
        foreach (['contentDir' => $directory, 'articlesTsPath' => $metadata, 'categoriesTsPath' => $metadata] as $property => $value) {
            (new \ReflectionProperty($service, $property))->setValue($service, $value);
        }
        try {
            file_put_contents($path, 'const content = `# Sample'."\nIntro\n## Overview\nFirst".'`;');
            $first = $service->getAllParsedArticles()['sample'];
            $this->assertStringContainsString('Intro', $first['sections']['Overview']['content']);
            $this->assertStringContainsString('First', $first['sections']['Overview']['content']);
            file_put_contents($path, 'const content = `# Sample'."\nIntro\n## Overview\nLater".'`;');
            $this->assertStringContainsString('Later', $service->getAllParsedArticles()['sample']['sections']['Overview']['content']);
        } finally {
            unlink($path);
            unlink($metadata);
            rmdir($directory);
        }
    }

    public function test_retrieval_fixture_recall(): void
    {
        $cases = json_decode(file_get_contents(base_path('tests/Fixtures/chatbot-questions.json')), true);
        $passed = 0;
        $total = 0;
        foreach ($cases as $case) {
            if ($case['type'] !== 'retrieval') {
                continue;
            }
            $total++;
            $slugs = array_column(app(ChatbotKnowledge::class)->search($case['query'], new ChatbotAudience($case['role'])), 'slug');
            $passed += (int) (array_diff($case['expected_slugs'], $slugs) === []);
        }
        $this->assertGreaterThanOrEqual(0.9, $passed / $total);
    }
}
