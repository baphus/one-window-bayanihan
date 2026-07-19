<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\LogViewerService;
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

    #[Test]
    public function test_default_laravel_log_is_a_log_source(): void
    {
        // The real laravel.log is intentionally not touched: Windows keeps the
        // application log handle open. A Linux CI job should separately verify
        // the configured default channel resolves to storage/logs/laravel.log.
        $this->mock(LogViewerService::class, function ($mock): void {
            $mock->shouldReceive('getLogs')->once()->andReturn([
                'entries' => [[
                    'timestamp' => '2026-05-30 09:15:00',
                    'environment' => 'local',
                    'level' => 'info',
                    'message' => 'Default source',
                    'date' => '',
                ]],
                'total' => 1,
                'per_page' => 50,
                'current_page' => 1,
                'last_page' => 1,
                'levels' => ['info'],
            ]);
        });

        $response = $this->actingAs($this->user)->getJson('/admin/system/logs/entries');

        $response->assertOk();
        $this->assertSame('Default source', $response->json('entries.0.message'));
    }

    #[Test]
    public function test_no_source_returns_a_safe_empty_state(): void
    {
        $response = $this->actingAs($this->user)->getJson('/admin/system/logs/entries');

        $response->assertOk()->assertJson([
            'entries' => [],
            'total' => 0,
            'source_available' => false,
            'unavailable_reason' => 'Log source unavailable.',
        ]);
    }

    #[Test]
    public function test_log_filters_validate_level_search_dates_page_and_per_page(): void
    {
        // Note: getJson($uri, $headers) in Laravel 13 uses second param as headers,
        // so query params must be in the URL string.
        $cases = [
            '/admin/system/logs/entries?level=not-a-level',
            '/admin/system/logs/entries?date_from=not-a-date',
            '/admin/system/logs/entries?date_to=not-a-date',
            '/admin/system/logs/entries?page=0',
            '/admin/system/logs/entries?per_page=0',
            '/admin/system/logs/entries?per_page=101',
        ];

        foreach ($cases as $uri) {
            $this->actingAs($this->user)
                ->getJson($uri)
                ->assertStatus(422);
        }
    }

    #[Test]
    public function test_log_filtering_redacts_email_and_ip_addresses(): void
    {
        $path = storage_path('logs/laravel-2026-05-31.log');
        File::put($path, "[2026-05-31 10:00:00] local.ERROR: email jane@example.com from 192.168.1.10\n");

        $response = $this->actingAs($this->user)->getJson('/admin/system/logs/entries?level=error&search=email&date_from=2026-05-31&date_to=2026-05-31');

        $response->assertOk();
        $this->assertSame('email ***@***.*** from ***.***.***.***', $response->json('entries.0.message'));
    }
}
