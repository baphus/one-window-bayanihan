<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IpWhitelist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlertConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(IpWhitelist::class);
        Config::set('auth.ip_whitelist.enabled', false);

        Role::create(['name' => 'ADMIN']);
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
        $this->admin->assignRole('ADMIN');
    }

    #[Test]
    public function test_page_loads(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/system/alerts');

        $response->assertOk();
        $response->assertJsonStructure(['component', 'props' => ['configs', 'alertLogs']]);
    }

    #[Test]
    public function test_update_alert_config(): void
    {
        $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/system/alerts');

        $configId = DB::table('alert_configs')->where('alert_type', 'low_storage')->value('id');

        $response = $this->actingAs($this->admin)->post('/admin/system/alerts', [
            'id' => $configId,
            'enabled' => false,
            'threshold_value' => 85,
            'email_recipients' => ['ops@example.com'],
            'notify_in_app' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('alert_configs', [
            'id' => $configId,
            'enabled' => false,
            'threshold_value' => 85,
            'notify_in_app' => false,
        ]);
    }

    #[Test]
    public function test_test_email_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/system/alerts/test-email', [
            'recipient' => 'recipient@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('system_alert_logs', [
            'alert_type' => 'test',
            'severity' => 'info',
            'sent_email' => true,
        ]);
    }
}
