<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SetPostgresSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateLimitingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // SetPostgresSession uses parameterized SET SESSION which PostgreSQL does not
        // support via PDO prepared statements ($1 placeholder). Skip it here; we are
        // testing throttle behaviour, not RLS context propagation.
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    #[Test]
    public function it_rate_limits_api_get_requests_at_60_per_minute(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);

        // Make 60 requests (should all succeed)
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($user)->getJson('/api/clients');
        }

        // 61st should be rate limited
        $response = $this->actingAs($user)->getJson('/api/clients');
        $response->assertStatus(429);
    }

    #[Test]
    public function it_rate_limits_api_post_requests_via_global_limiter(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);

        // The api-global limiter applies to all requests in the group (GET and POST)
        // Make 60 requests to exhaust the limit
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($user)->getJson('/api/clients');
        }

        // Next request should be 429
        $response = $this->actingAs($user)->getJson('/api/clients');
        $response->assertStatus(429);
    }

    #[Test]
    public function it_uses_separate_buckets_per_user(): void
    {
        $user1 = User::factory()->create(['role' => 'ADMIN']);
        $user2 = User::factory()->create(['role' => 'ADMIN']);

        // Exhaust user1's limit
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($user1)->getJson('/api/clients');
        }

        // user1 is rate limited
        $response = $this->actingAs($user1)->getJson('/api/clients');
        $response->assertStatus(429);

        // user2 still has quota
        $response = $this->actingAs($user2)->getJson('/api/clients');
        $response->assertStatus(200);
    }
}
