<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->user = User::factory()->create(['role' => 'ADMIN']);

        // Clean test log files between tests
        foreach (glob(storage_path('logs/laravel-*.log')) as $file) {
            unlink($file);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('logs/laravel-*.log')) as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    #[Test]
    public function test_page_loads(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/system/logs');

        $response->assertOk();
        $this->assertSame('Admin/LogViewer/Index', $response->json('component'));
    }

    #[Test]
    public function test_entries_returns_json(): void
    {
        $path = storage_path('logs/laravel-2026-05-29.log');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "[2026-05-29 10:00:00] local.ERROR: First message\n");

        $response = $this->actingAs($this->user)
            ->getJson('/admin/system/logs/entries?per_page=10');

        $response->assertOk();
        $response->assertJsonStructure(['entries', 'total', 'per_page', 'current_page', 'last_page', 'levels']);
        $this->assertCount(1, $response->json('entries'));
        $this->assertSame('error', $response->json('entries.0.level'));
    }

    #[Test]
    public function test_log_viewer_works_with_temp_log_file(): void
    {
        $path = storage_path('logs/laravel-2026-05-28.log');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "[2026-05-28 09:15:00] local.WARNING: Temp log line\n");

        $response = $this->actingAs($this->user)
            ->getJson('/admin/system/logs/entries?level=warning&search=Temp');

        $response->assertOk();
        $this->assertCount(1, $response->json('entries'));
        $this->assertSame('Temp log line', $response->json('entries.0.message'));
    }
}
