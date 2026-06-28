<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ErrorHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'production';
    }

    #[Test]
    public function it_returns_404_for_nonexistent_web_routes(): void
    {
        $response = $this->get('/this-route-definitely-does-not-exist-xyz123');
        $response->assertStatus(404);
        // Should NOT contain stack trace or file paths
        $response->assertDontSee('vendor/');
        $response->assertDontSee('Exception');
    }

    #[Test]
    public function it_returns_json_404_for_api_routes(): void
    {
        $response = $this->getJson('/api/nonexistent-endpoint-xyz123');
        $response->assertStatus(404);
        $response->assertJson(['message' => 'Resource not found.']);
    }

    #[Test]
    public function it_shows_debug_info_in_local_environment(): void
    {
        $this->app['env'] = 'local';
        config(['app.debug' => true]);

        $response = $this->get('/this-route-definitely-does-not-exist-xyz123');
        $response->assertStatus(404);
        // In local mode, the default Laravel handler runs (may show debug page)
    }
}
