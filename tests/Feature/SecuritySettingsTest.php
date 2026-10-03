<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
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
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/system/security');

        $response->assertOk();
        $response->assertJsonPath('component', 'Admin/Security/Index');
        $response->assertJsonStructure(['props' => ['settings' => ['two_factor_required']]]);
    }

    #[Test]
    public function test_update_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/system/security', [
                'two_factor_required' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Security settings updated.');

        $this->assertTrue(SystemSetting::getValue('two_factor_required'));
    }
}
