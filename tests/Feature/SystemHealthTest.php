<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IpWhitelist;
use App\Models\User;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(IpWhitelist::class);
        Config::set('auth.ip_whitelist.enabled', false);

        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    #[Test]
    public function test_dashboard_returns_ok(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/system/health');

        $response->assertOk();
        $response->assertJsonStructure(['component', 'props' => ['overview' => ['overall', 'checks']]]);
    }

    #[Test]
    public function test_run_checks_stores_logs(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/system/health/run-checks');

        $response->assertRedirect();
        $this->assertDatabaseCount('health_check_logs', 4);
    }

    #[Test]
    public function test_service_returns_valid_structure(): void
    {
        $service = app(SystemHealthService::class);

        $overview = $service->getOverview();
        $this->assertArrayHasKey('overall', $overview);
        $this->assertArrayHasKey('checks', $overview);

        $results = $service->runAllChecks();
        $this->assertCount(4, $results);

        $overview = $service->getOverview();
        $this->assertArrayHasKey('overall', $overview);
        $this->assertCount(4, $overview['checks']);
    }
}
