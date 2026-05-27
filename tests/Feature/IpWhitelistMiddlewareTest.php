<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IpWhitelistMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->user = User::factory()->create(['role' => 'ADMIN']);
        Config::set('auth.ip_whitelist.enabled', true);
        Config::set('auth.ip_whitelist.addresses', ['127.0.0.1', '192.168.1.0/24']);
    }

    #[Test]
    public function it_allows_whitelisted_ip(): void
    {
        Config::set('auth.ip_whitelist.enabled', true);
        Config::set('auth.ip_whitelist.addresses', ['127.0.0.1']);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/users');

        $response->assertStatus(200);
    }

    #[Test]
    public function it_blocks_non_whitelisted_ip(): void
    {
        Config::set('auth.ip_whitelist.enabled', true);
        Config::set('auth.ip_whitelist.addresses', ['10.0.0.1']);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/users');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_allows_all_when_disabled(): void
    {
        Config::set('auth.ip_whitelist.enabled', false);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/users');

        $response->assertStatus(200);
    }

    #[Test]
    public function it_allows_cidr_range_ip(): void
    {
        Config::set('auth.ip_whitelist.enabled', true);
        Config::set('auth.ip_whitelist.addresses', ['192.168.1.0/24']);

        // Simulate request from 192.168.1.50 (within CIDR) via server variable
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->get('/admin/users');

        $response->assertStatus(200);
    }

    #[Test]
    public function it_blocks_outside_cidr_range(): void
    {
        Config::set('auth.ip_whitelist.enabled', true);
        Config::set('auth.ip_whitelist.addresses', ['192.168.1.0/24']);

        // Simulate request from 10.0.0.99 (outside CIDR) via server variable
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->get('/admin/users');

        $response->assertStatus(403);
    }
}
