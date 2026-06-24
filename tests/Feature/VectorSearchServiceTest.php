<?php

namespace Tests\Feature;

use App\Services\Ai\EmbeddingService;
use App\Services\HelpCenter\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class VectorSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_search_returns_empty_collection_when_not_configured(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldReceive('isConfigured')
            ->once()
            ->andReturn(false);

        $service = new VectorSearchService($embeddingMock);
        $result = $service->search('test query');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_search_returns_empty_collection_for_empty_query(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldNotReceive('isConfigured');

        $service = new VectorSearchService($embeddingMock);
        $result = $service->search('');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_search_returns_structured_results(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldReceive('isConfigured')
            ->once()
            ->andReturn(true);
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $expectedResults = [
            (object) ['article_id' => '550e8400-e29b-41d4-a716-446655440000', 'similarity' => 0.95],
            (object) ['article_id' => '550e8400-e29b-41d4-a716-446655440001', 'similarity' => 0.87],
        ];

        DB::shouldReceive('select')
            ->once()
            ->andReturn($expectedResults);

        $service = new VectorSearchService($embeddingMock);
        $result = $service->search('test query');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertIsString($first->article_id);
        $this->assertIsFloat($first->similarity);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $first->article_id);
        $this->assertSame(0.95, $first->similarity);

        $second = $result->last();
        $this->assertIsString($second->article_id);
        $this->assertIsFloat($second->similarity);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440001', $second->article_id);
        $this->assertSame(0.87, $second->similarity);
    }
}
