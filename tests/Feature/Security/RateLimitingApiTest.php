<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
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
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        // Make 60 requests (should all succeed)
        for ($i = 0; $i < 60; $i++) {
            $this->actingAsWithMfa($user)->getJson('/dashboard/case-manager-period');
        }

        // 61st should be rate limited
        $response = $this->actingAsWithMfa($user)->getJson('/dashboard/case-manager-period');
        $response->assertStatus(429);
    }

    #[Test]
    public function it_rate_limits_api_get_requests_via_global_limiter(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        // The api-global limiter applies to all requests in the group (GET and POST)
        // Make 60 requests to exhaust the limit
        for ($i = 0; $i < 60; $i++) {
            $this->actingAsWithMfa($user)->getJson('/dashboard/case-manager-period');
        }

        // Next request should be 429
        $response = $this->actingAsWithMfa($user)->getJson('/dashboard/case-manager-period');
        $response->assertStatus(429);
    }

    #[Test]
    public function it_uses_separate_buckets_per_user(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $user2 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        // Exhaust user1's limit
        for ($i = 0; $i < 60; $i++) {
            $this->actingAsWithMfa($user1)->getJson('/dashboard/case-manager-period');
        }

        // user1 is rate limited
        $response = $this->actingAsWithMfa($user1)->getJson('/dashboard/case-manager-period');
        $response->assertStatus(429);

        // user2 still has quota
        $response = $this->actingAsWithMfa($user2)->getJson('/dashboard/case-manager-period');
        $response->assertStatus(200);
    }
}
