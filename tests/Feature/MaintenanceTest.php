<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    #[Test]
    public function test_page_loads(): void
    {
        $this->app->instance(MaintenanceService::class, new class extends MaintenanceService
        {
            public function getStatus(): array
            {
                return ['active' => false, 'secret' => null, 'retry' => null, 'since' => null];
            }

            public function enable(?string $secret = null, ?int $retryMinutes = null): void {}

            public function disable(): void {}
        });

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get(route('admin.system.maintenance'));

        $response->assertOk();
        $response->assertJsonStructure(['component', 'props' => ['status' => ['active', 'secret', 'retry', 'since']]]);
    }

    #[Test]
    public function test_toggle_maintenance_mode(): void
    {
        $this->app->instance(MaintenanceService::class, Mockery::mock(MaintenanceService::class, function ($mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn(['active' => false, 'secret' => null, 'retry' => null, 'since' => null]);
            $mock->shouldReceive('enable')->once()->with('bypass123', 60);
        }));

        $response = $this->actingAs($this->admin)->post(route('admin.system.maintenance.toggle'), [
            'secret' => 'bypass123',
            'retry_minutes' => 60,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Maintenance mode enabled.');

        $this->app->instance(MaintenanceService::class, Mockery::mock(MaintenanceService::class, function ($mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn(['active' => true, 'secret' => 'bypass123', 'retry' => 60, 'since' => now()->toDateTimeString()]);
            $mock->shouldReceive('disable')->once();
        }));

        $response = $this->actingAs($this->admin)->post(route('admin.system.maintenance.toggle'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Maintenance mode disabled.');
    }
}
