<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackupStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    public function test_page_loads(): void
    {
        Http::fake([
            'api.supabase.com/*' => Http::response(['backups' => []], 200),
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/system/backups');

        $response->assertOk();
    }

    public function test_refresh_works(): void
    {
        Cache::put('supabase_backups', ['cached' => true], 300);

        $response = $this->actingAs($this->admin)->post('/admin/system/backups/refresh');

        $response->assertRedirect();
        $this->assertFalse(Cache::has('supabase_backups'));
    }

    public function test_service_handles_api_error(): void
    {
        Http::fake([
            'api.supabase.com/*' => Http::response(['error' => 'nope'], 500),
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/system/backups');

        $response->assertOk();
        $response->assertSee('Could not fetch backup data', false);
    }
}
