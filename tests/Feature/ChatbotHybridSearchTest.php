<?php

namespace Tests\Feature;

use App\Services\Chatbot\ChatbotEmbeddingService;
use App\Services\Chatbot\ChatbotHelpdeskService;
use App\Services\Chatbot\ChatbotHybridSearch;
use Mockery;
use Tests\TestCase;

class ChatbotHybridSearchTest extends TestCase
{
    /**
     * Verify that the vector search returns a well-structured result
     * even when embedding/retrieval backends are unavailable in test.
     */
    public function test_search_returns_valid_structure(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        $result = $hybrid->search('test query', null, 5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('hits', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('clear_winner', $result);
        $this->assertArrayHasKey('vector_count', $result);
        $this->assertArrayHasKey('fts_count', $result);

        $this->assertIsArray($result['hits']);
        $this->assertIsFloat($result['confidence']);
        $this->assertIsBool($result['clear_winner']);
        $this->assertIsInt($result['vector_count']);
        $this->assertIsInt($result['fts_count']);

        $this->assertGreaterThanOrEqual(0.0, $result['confidence']);
        $this->assertLessThanOrEqual(1.0, $result['confidence']);
    }

    /**
     * When vector search returns empty, result should have zero confidence
     * and no hits.
     */
    public function test_empty_vector_result(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        // Query that won't match anything in the embedding DB
        $result = $hybrid->search('zzz_nonexistent_xyz_query_12345', null, 5);

        $this->assertEmpty($result['hits']);
        $this->assertEquals(0.0, $result['confidence']);
        $this->assertFalse($result['clear_winner']);
        $this->assertEquals(0, $result['vector_count']);
    }

    /**
     * Verify the vector-only search pipeline returns valid confidence.
     */
    public function test_vector_search_returns_valid_confidence(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        $result = $hybrid->search('OWWA contact number', null, 5);

        if ($result['vector_count'] > 0) {
            $this->assertGreaterThanOrEqual(0.0, $result['confidence']);
            $this->assertLessThanOrEqual(1.0, $result['confidence']);
            $this->assertEquals(0, $result['fts_count']);
        }
    }

    /**
     * Verify that hits have the correct structure with vector_score and fts_score fields.
     */
    public function test_hit_structure(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        $result = $hybrid->search('case tracking', null, 5);

        if ($result['hits'] !== []) {
            $hit = $result['hits'][0];

            $this->assertArrayHasKey('source_type', $hit);
            $this->assertArrayHasKey('source_key', $hit);
            $this->assertArrayHasKey('slug', $hit);
            $this->assertArrayHasKey('heading', $hit);
            $this->assertArrayHasKey('audience_group', $hit);
            $this->assertArrayHasKey('score', $hit);
            $this->assertArrayHasKey('vector_score', $hit);
            $this->assertArrayHasKey('fts_score', $hit);

            // Hits come from either vector search (vector_score non-null)
            // or the keyword fallback (both null). FTS against the
            // chatbot_embeddings table is never the sole source.
            if ($hit['vector_score'] !== null) {
                $this->assertNotNull($hit['vector_score']);
                $this->assertNull($hit['fts_score']);
            } else {
                $this->assertNull($hit['fts_score']);
            }
        }
    }

    /**
     * Test that clear_winner logic works correctly.
     */
    public function test_clear_winner_logic(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        $result = $hybrid->search('how to track case', null, 5);

        $this->assertIsBool($result['clear_winner']);

        if (count($result['hits']) === 1) {
            $this->assertTrue($result['clear_winner']);
        }
    }

    // ──────────────────────────────────────────────
    //  Audience group filtering
    // ──────────────────────────────────────────────

    /**
     * Passing null for audience groups should not error and
     * should return all hits regardless of audience_group.
     */
    public function test_null_audience_groups_returns_all(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        $result = $hybrid->search('case tracking', null, 5);

        // Must not throw; hits may be empty if backends unavailable,
        // but each hit must have an audience_group field.
        foreach ($result['hits'] as $hit) {
            $this->assertArrayHasKey('audience_group', $hit);
        }
    }

    /**
     * Passing a single audience group should not error.
     * When hits exist, every hit must belong to the requested group.
     */
    public function test_single_audience_group_filters_correctly(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        $result = $hybrid->search('case tracking', ['Case Managers'], 5);

        // Must not throw
        $this->assertArrayHasKey('hits', $result);

        foreach ($result['hits'] as $hit) {
            $this->assertEquals('Case Managers', $hit['audience_group'],
                'Hit audience_group must match the requested filter');
        }
    }

    /**
     * Passing multiple audience groups should return hits from any
     * of the requested groups.
     */
    public function test_multiple_audience_groups_returns_union(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        $allowed = ['OFW & Public', 'Case Managers'];
        $result = $hybrid->search('case', $allowed, 5);

        foreach ($result['hits'] as $hit) {
            $this->assertContains($hit['audience_group'], $allowed,
                'Hit audience_group must be one of the requested groups');
        }
    }

    /**
     * An empty audience groups array should not error — it simply
     * matches nothing, producing no hits.
     */
    public function test_empty_audience_groups_returns_no_hits(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        $result = $hybrid->search('case tracking', [], 5);

        $this->assertEmpty($result['hits'],
            'Empty audience groups array should filter out all results');
    }

    /**
     * A non-existent audience group should produce no hits
     * (gracefully, not as an error).
     */
    public function test_nonexistent_audience_group_returns_no_hits(): void
    {
        $hybrid = app(ChatbotHybridSearch::class);

        $result = $hybrid->search('case tracking', ['NonExistentGroup'], 5);

        $this->assertEmpty($result['hits'],
            'Non-existent audience group should return no hits');
    }

    /**
     * Keyword search fallback works with a common query and
     * returns hits in the expected format.
     */
    public function test_keyword_search_fallback_returns_results(): void
    {
        $helpdesk = app(ChatbotHelpdeskService::class);

        $hits = $helpdesk->keywordSearch('how to track my case', null, 5);

        $this->assertNotEmpty($hits, 'Keyword search should find tracking articles');
        $this->assertLessThanOrEqual(5, count($hits));

        foreach ($hits as $hit) {
            $this->assertEquals('helpdesk', $hit['source_type']);
            $this->assertArrayHasKey('source_key', $hit);
            $this->assertArrayHasKey('slug', $hit);
            $this->assertArrayHasKey('heading', $hit);
            $this->assertArrayHasKey('audience_group', $hit);
            $this->assertArrayHasKey('score', $hit);
            $this->assertGreaterThan(0.0, $hit['score']);
        }
    }

    /**
     * Keyword search respects audience group filtering.
     */
    public function test_keyword_search_filters_by_audience_group(): void
    {
        $helpdesk = app(ChatbotHelpdeskService::class);

        $hits = $helpdesk->keywordSearch('case', ['Case Managers'], 5);

        $this->assertNotEmpty($hits, 'Keyword search should find case management articles');
        foreach ($hits as $hit) {
            $this->assertEquals('Case Managers', $hit['audience_group']);
        }
    }

    /**
     * When vector and FTS backends return nothing, the hybrid search
     * falls through to keyword search against the TypeScript files.
     *
     * The embedding backend is mocked to return no hits so the fallback
     * branch always runs and every assertion executes — the test never
     * depends on the runtime availability of pgvector/FTS.
     */
    public function test_hybrid_search_falls_back_to_keyword(): void
    {
        // Mock the embedding backend to return no hits, and construct the
        // hybrid service directly so the fallback branch always runs.
        $embedding = Mockery::mock(ChatbotEmbeddingService::class);
        $embedding->shouldReceive('normalize')->andReturn('how to track my case');
        $embedding->shouldReceive('embed')->andReturn([0.1, 0.2, 0.3]);
        $embedding->shouldReceive('search')->andReturn([]);
        $embedding->shouldReceive('ftsSearch')->andReturn([]);

        $hybrid = new ChatbotHybridSearch(
            $embedding,
            app(ChatbotHelpdeskService::class),
        );

        // Use a query that matches via plain keyword search (file content).
        $result = $hybrid->search('how to track my case status using a tracker number', null, 3);

        // In the fallback branch the service reports the keyword hit count as
        // vector_count and zero FTS results — neither DB-backed backend ran.
        $this->assertSame(0, $result['fts_count'],
            'FTS backend produced no hits for the mocked query');
        $this->assertNotEmpty($result['hits'],
            'Hybrid search should fall back to keyword search when vector/FTS are unavailable');
        $this->assertSame(count($result['hits']), $result['vector_count'],
            'Fallback reports the keyword hit count as vector_count');
        $this->assertGreaterThan(0.0, $result['confidence'],
            'Fallback hits should yield a positive confidence');
    }
}
