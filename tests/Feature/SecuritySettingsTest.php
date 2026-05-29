<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IpWhitelist;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
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
            ->get('/admin/system/security');

        $response->assertOk();
        $response->assertJsonPath('component', 'Admin/Security/Index');
        $response->assertJsonStructure(['props' => ['settings' => ['password_min_length', 'ip_whitelist_ips']]]);
    }

    #[Test]
    public function test_update_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/system/security', [
                'password_min_length' => 12,
                'password_require_special' => true,
                'password_require_numbers' => false,
                'password_expiry_days' => 60,
                'session_lifetime_minutes' => 180,
                'max_login_attempts' => 7,
                'lockout_duration_minutes' => 30,
                'ip_whitelist_enabled' => true,
                'ip_whitelist_ips' => "127.0.0.1\n192.168.1.0/24",
                'two_factor_required' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Security settings updated.');

        $this->assertSame('12', SystemSetting::query()->findOrFail('password_min_length')->value);
        $this->assertTrue(SystemSetting::getValue('password_require_special'));
        $this->assertFalse(SystemSetting::getValue('password_require_numbers'));
        $this->assertSame('60', SystemSetting::query()->findOrFail('password_expiry_days')->value);
        $this->assertSame('180', SystemSetting::query()->findOrFail('session_lifetime_minutes')->value);
        $this->assertSame('7', SystemSetting::query()->findOrFail('max_login_attempts')->value);
        $this->assertSame('30', SystemSetting::query()->findOrFail('lockout_duration_minutes')->value);
        $this->assertTrue(SystemSetting::getValue('ip_whitelist_enabled'));
        $this->assertSame("127.0.0.1\n192.168.1.0/24", SystemSetting::getValue('ip_whitelist_ips'));
        $this->assertTrue(SystemSetting::getValue('two_factor_required'));
    }
}
