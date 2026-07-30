<?php

namespace Tests\Feature;

use App\Services\Chatbot\ChatbotRetrievalService;
use Tests\TestCase;

class ChatbotRetrievalTest extends TestCase
{
    private ChatbotRetrievalService $retrieval;

    protected function setUp(): void
    {
        parent::setUp();

        $this->retrieval = app(ChatbotRetrievalService::class);
    }

    // ── Tokenization ──

    public function test_tokenize_returns_meaningful_tokens(): void
    {
        $tokens = $this->retrieval->tokenize('How do I track my case?');

        $this->assertContains('track', $tokens);
        $this->assertContains('case', $tokens);
        // Stop words should be filtered out
        $this->assertNotContains('how', $tokens);
        $this->assertNotContains('do', $tokens);
        $this->assertNotContains('my', $tokens);
    }

    public function test_tokenize_empty_string(): void
    {
        $tokens = $this->retrieval->tokenize('');

        $this->assertSame([], $tokens);
    }

    public function test_tokenize_only_stop_words(): void
    {
        $tokens = $this->retrieval->tokenize('the a an of');

        $this->assertSame([], $tokens);
    }

    // ── Content loading ──

    public function test_content_for_loads_helpdesk_sections(): void
    {
        $hits = [
            [
                'source_type' => 'helpdesk',
                'source_key' => 'using-public-tracking-portal::What clients can see',
            ],
        ];

        $content = $this->retrieval->contentFor($hits);

        $this->assertNotSame('', trim($content));
    }

    public function test_content_for_loads_guide_sections(): void
    {
        $hits = [
            [
                'source_type' => 'guide',
                'source_key' => 'guide:case_tracking',
            ],
        ];

        $content = $this->retrieval->contentFor($hits);

        $this->assertNotSame('', trim($content));
    }

    public function test_content_for_empty_hits(): void
    {
        $content = $this->retrieval->contentFor([]);

        $this->assertSame('', $content);
    }
}
