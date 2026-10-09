<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
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

            public function enable(string $secret, ?int $retryMinutes = null): void {}

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
        $mock = $this->createMock(MaintenanceService::class);
        $mock->expects($this->once())->method('getStatus')->willReturn(['active' => false, 'secret' => null, 'retry' => null, 'since' => null]);
        $mock->expects($this->once())->method('enable')->with('bypass123', 60);
        $this->app->instance(MaintenanceService::class, $mock);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->post(route('admin.system.maintenance.toggle'), [
                'secret' => 'bypass123',
                'retry_minutes' => 60,
            ]);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', '/');

        $mock = $this->createMock(MaintenanceService::class);
        $mock->expects($this->once())->method('getStatus')->willReturn(['active' => true, 'secret' => 'bypass123', 'retry' => 60, 'since' => now()->toDateTimeString()]);
        $mock->expects($this->once())->method('disable');
        $this->app->instance(MaintenanceService::class, $mock);

        $response = $this->actingAs($this->admin)->post(route('admin.system.maintenance.toggle'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Maintenance mode disabled.');
    }
}
