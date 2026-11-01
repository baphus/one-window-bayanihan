<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MFA enforcement is a deployment decision, not a setting.
 *
 * The old /admin/system/security page carried a two_factor_required toggle
 * that nothing read: enforcement is decided by User::isInMfaEnforcedRole() and
 * is unconditional in production. These tests assert the replacement — a
 * read-only status on the main settings page, and no writable flag anywhere.
 */
class SecuritySettingsTest extends TestCase
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
    public function the_separate_security_page_no_longer_exists(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/system/security')
            ->assertNotFound();
    }

    #[Test]
    public function the_settings_page_reports_mfa_status_read_only(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/system-settings');

        $response->assertOk();

        $mfa = $response->viewData('page')['props']['mfa'];

        $this->assertArrayHasKey('enforced', $mfa);
        $this->assertArrayHasKey('note', $mfa);
        $this->assertIsBool($mfa['enforced']);
    }

    /**
     * The flag must not be writable: posting it to the settings endpoint must
     * not create a row, because no setting is declared for it.
     */
    #[Test]
    public function two_factor_required_cannot_be_written_as_a_setting(): void
    {
        $this->actingAs($this->admin)->post('/admin/system-settings', [
            'two_factor_required' => true,
        ]);

        $this->assertNull(SystemSetting::getValue('two_factor_required'));
    }

    #[Test]
    public function seeding_removes_a_legacy_two_factor_required_row(): void
    {
        SystemSetting::create([
            'key' => 'two_factor_required',
            'value' => 'true',
            'category' => 'security',
        ]);

        $this->seed(SystemSettingSeeder::class);

        $this->assertNull(SystemSetting::getValue('two_factor_required'));
    }
}
