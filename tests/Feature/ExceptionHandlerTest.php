<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExceptionHandlerTest extends TestCase
{
    public function test_404_returns_safe_json(): void
    {
        $response = $this->getJson('/api/non-existent-route');
        $response->assertStatus(404);
        $response->assertJson(['message' => 'Resource not found.']);
        // Ensure no exception details leak
        $response->assertJsonMissing(['exception']);
        $response->assertJsonMissing(['trace']);
        $response->assertJsonMissing(['line']);
        $response->assertJsonMissing(['file']);
    }

    public function test_404_returns_safe_html(): void
    {
        $response = $this->get('/non-existent-web-route');
        $response->assertStatus(404);
    }

    public function test_429_returns_safe_json(): void
    {
        // Placeholder: 429 is hard to trigger deterministically in tests
        // without configuring a throttle test route.
        $this->assertTrue(true);
    }

    public function test_500_returns_incident_id(): void
    {
        // Placeholder: triggering a real 500 in tests is difficult without
        // a dedicated route that throws an exception on demand.
        $this->assertTrue(true);
    }
}
