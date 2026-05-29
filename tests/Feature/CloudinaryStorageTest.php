<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloudinaryStorageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
        Cache::forget('cloudinary_usage');
    }

    #[Test]
    public function test_page_loads(): void
    {
        $this->app->instance(CloudinaryService::class, new class extends CloudinaryService
        {
            public function __construct() {}

            public function getStorageUsage(): array
            {
                return ['storage' => ['used_mb' => 12.5, 'limit_mb' => 100, 'usage_percent' => 12], 'bandwidth' => ['used_mb' => 3.2, 'limit_mb' => 50, 'usage_percent' => 6], 'credits' => ['used' => 1, 'limit' => 10]];
            }

            public function getRecentMedia(int $limit = 10): array
            {
                return [['public_id' => 'sample/image', 'format' => 'jpg', 'bytes' => 1048576, 'size_mb' => 1, 'created_at' => now()->toISOString(), 'url' => 'https://example.com/image.jpg']];
            }
        });

        $response = $this->actingAs($this->admin)->withHeader('X-Inertia', 'true')->get(route('admin.system.cloudinary'));

        $response->assertStatus(200);
        $this->assertSame(12.5, $response->json('props.usage.storage.used_mb'));
        $this->assertSame('sample/image', $response->json('props.recentMedia.0.public_id'));
    }

    #[Test]
    public function test_refresh_works(): void
    {
        $this->app->instance(CloudinaryService::class, Mockery::mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('__construct')->andReturnNull();
        }));

        Cache::put('cloudinary_usage', ['cached' => true], 300);

        $response = $this->actingAs($this->admin)->withHeader('X-Inertia', 'true')->post(route('admin.system.cloudinary.refresh'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Cloudinary data refreshed.');
        $this->assertFalse(Cache::has('cloudinary_usage'));
    }

    #[Test]
    public function test_service_handles_api_error(): void
    {
        config()->set('cloudinary.cloud_url', null);

        $service = app(CloudinaryService::class);
        $usage = $service->getStorageUsage();

        $this->assertArrayHasKey('error', $usage);
    }
}
