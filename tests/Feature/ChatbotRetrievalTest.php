<?php

namespace Tests\Feature;

use App\Services\Chatbot\ChatbotRetrievalService;
use Tests\TestCase;

class ChatbotRetrievalTest extends TestCase
{
    private ChatbotRetrievalService $retrieval;

    private string $indexPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexPath = storage_path('framework/testing/chatbot-index-test.sqlite');
        config(['ai-chatbot.retrieval.index_path' => $this->indexPath]);

        $this->retrieval = app(ChatbotRetrievalService::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->indexPath);
        @unlink($this->indexPath.'.tmp');

        parent::tearDown();
    }

    // ── Index build ──

    public function test_rebuild_creates_index_with_sections(): void
    {
        $count = $this->retrieval->rebuild();

        $this->assertFileExists($this->indexPath);
        $this->assertGreaterThan(100, $count, 'Expected all helpdesk sections + guide topics to be indexed');
    }

    public function test_ensure_fresh_builds_missing_index(): void
    {
        $this->assertFileDoesNotExist($this->indexPath);

        $hits = $this->retrieval->search('how do I track my case', ['OFW & Public']);

        $this->assertFileExists($this->indexPath);
        $this->assertNotEmpty($hits);
    }

    // ── Ranking ──

    public function test_tracking_question_ranks_tracking_portal_first(): void
    {
        $hits = $this->retrieval->search('how do I track my case', ['OFW & Public']);

        $this->assertNotEmpty($hits);
        $this->assertSame('using-public-tracking-portal', $hits[0]['slug']);
    }

    public function test_results_are_ordered_by_descending_score(): void
    {
        $hits = $this->retrieval->search('why is my OTP not arriving', ['OFW & Public']);

        $this->assertGreaterThanOrEqual(2, count($hits));
        $scores = array_column($hits, 'score');
        $sorted = $scores;
        rsort($sorted);
        $this->assertSame($sorted, $scores);
    }

    public function test_login_question_finds_tracking_portal(): void
    {
        $hits = $this->retrieval->search('How do I log in to the tracking portal?', ['OFW & Public']);

        $this->assertContains('using-public-tracking-portal', array_column($hits, 'slug'));
    }

    public function test_services_question_finds_services_article(): void
    {
        $hits = $this->retrieval->search('What services are available for OFWs?', ['OFW & Public']);

        $this->assertContains('ofw-assistance-services-available', array_column($hits, 'slug'));
    }

    public function test_status_meanings_question_finds_status_content(): void
    {
        $hits = $this->retrieval->search('What does case status mean?', ['OFW & Public']);

        $slugs = array_column($hits, 'slug');
        $statusSources = array_intersect($slugs, ['understanding-case-statuses-tracker-numbers', 'statuses']);
        $this->assertNotEmpty($statusSources, 'Expected a status-related source. Got: '.json_encode($slugs));
    }

    public function test_body_only_terms_match(): void
    {
        // "color-coded status indicators" appears only in section bodies,
        // never in an article title, slug, or section heading.
        $hits = $this->retrieval->search('what do the different colors mean', ['OFW & Public']);

        $this->assertNotEmpty($hits);
        $slugs = array_column($hits, 'slug');
        $this->assertContains('using-public-tracking-portal', $slugs);
    }

    // ── Synonym expansion ──

    public function test_taglish_synonyms_bridge_vocabulary_gap(): void
    {
        // "kaso" → case, "follow" → track/status via the config synonym map
        $hits = $this->retrieval->search('paano mag follow up sa kaso ko', ['OFW & Public']);

        $this->assertNotEmpty($hits);
        $slugs = array_column($hits, 'slug');
        $this->assertContains('using-public-tracking-portal', $slugs);
    }

    // ── Audience filtering ──

    public function test_public_user_never_receives_admin_sections(): void
    {
        $hits = $this->retrieval->search('how do I manage user accounts and roles', ['OFW & Public']);

        foreach ($hits as $hit) {
            $this->assertSame('OFW & Public', $hit['audience_group']);
            $this->assertNotSame('user-management-guide', $hit['slug']);
        }
    }

    public function test_admin_receives_all_audience_groups(): void
    {
        $hits = $this->retrieval->search('how do I manage user accounts and roles', null);

        $slugs = array_column($hits, 'slug');
        $this->assertContains('user-management-guide', $slugs);
    }

    // ── No-match and thresholds ──

    public function test_no_meaningful_tokens_returns_empty(): void
    {
        $this->assertSame([], $this->retrieval->search('the a an of', ['OFW & Public']));
        $this->assertSame([], $this->retrieval->search('??', ['OFW & Public']));
    }

    public function test_unmatched_topic_returns_empty(): void
    {
        $hits = $this->retrieval->search('quantum astrophysics telescope nebula', ['OFW & Public']);

        $this->assertSame([], $hits);
    }

    public function test_min_score_threshold_filters_weak_hits(): void
    {
        config(['ai-chatbot.retrieval.min_score' => 1000.0]);

        $hits = $this->retrieval->search('how do I track my case', ['OFW & Public']);

        $this->assertSame([], $hits);
    }

    public function test_result_count_respects_max_results(): void
    {
        $hits = $this->retrieval->search('case status tracking portal documents', ['OFW & Public']);

        $this->assertLessThanOrEqual((int) config('ai-chatbot.retrieval.max_results'), count($hits));
    }

    // ── Content loading ──

    public function test_content_for_loads_section_bodies_from_sources(): void
    {
        $hits = $this->retrieval->search('why is my OTP not arriving', ['OFW & Public']);
        $this->assertNotEmpty($hits);

        $content = $this->retrieval->contentFor($hits);

        $this->assertStringContainsString('OTP', $content);
        $this->assertNotSame('', trim($content));
    }
}
