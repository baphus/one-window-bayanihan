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

class ScheduledTaskTest extends TestCase
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
            ->get('/admin/system/scheduled-tasks');

        $response->assertOk();
        $response->assertJsonStructure(['component', 'props' => ['tasks']]);
    }

    #[Test]
    public function test_toggle_task_disables_it(): void
    {
        SystemSetting::setValue('helpcenter:sync', json_encode(['enabled' => true]), 'scheduled_task', 'Scheduled task: helpcenter:sync');

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->post('/admin/system/scheduled-tasks/helpcenter:sync/toggle');

        $response->assertRedirect();
        $setting = SystemSetting::query()->findOrFail('helpcenter:sync');
        $this->assertFalse((bool) (json_decode($setting->value, true)['enabled'] ?? true));
    }
}
