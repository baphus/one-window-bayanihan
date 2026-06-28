<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SetPostgresSession;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'testing';
    }

    #[Test]
    public function it_sends_security_headers_on_web_routes(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    #[Test]
    public function it_does_not_send_hsts_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    #[Test]
    public function it_sends_hsts_in_non_local_environment(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    #[Test]
    public function it_sends_security_headers_on_api_routes(): void
    {
        // Bypass SetPostgresSession: it uses a parameterized SET SESSION statement
        // that PostgreSQL rejects ($1 placeholder not valid in SET commands).
        // Our goal is only to verify SecurityHeaders runs on API routes too.
        $this->withoutMiddleware(SetPostgresSession::class);

        $user = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($user)->getJson('/api/analytics');

        $response->assertHeader('X-Frame-Options', 'DENY');
    }
}
