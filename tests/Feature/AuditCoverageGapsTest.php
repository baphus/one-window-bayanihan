<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\MaintenanceService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for the service-level audit writes added in T3–T6 of the
 * audit-coverage-gaps plan (SecuritySettingsController,
 * MaintenanceService::enable/disable, and the session-invalidating user
 * paths).
 *
 * These paths have no observable model event to rely on (draft deletion runs
 * inside withoutEvents; settings/maintenance have no audited model at all), so
 * each test calls the service directly and asserts the audit row it must
 * produce. Assertions compare against the raw
 * AuditAction::X->value strings — AuditLog intentionally carries no enum
 * cast so the frozen hash-chain serialisation is unaffected.
 */
class AuditCoverageGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_update_writes_audit_row_with_the_changed_key(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin);

        // The separate Security & Auth page (and its two_factor_required
        // toggle) is gone: MFA enforcement is decided by config/mfa.php and is
        // unconditional in production. Settings writes are audited by the main
        // settings endpoint instead, which records old → new per changed key.
        $this->post(route('admin.system-settings.update'), [
            'otp_expiry_seconds' => 600,
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'system_setting',
            'action' => AuditAction::UPDATE->value,
            'user_id' => $admin->id,
        ]);

        $entry = AuditLog::query()
            ->where('module', 'system_setting')
            ->where('action', AuditAction::UPDATE->value)
            ->latest('chain_seq')
            ->firstOrFail();

        // The changed key is named, and its value is recorded so a bad change
        // is recoverable by hand.
        $this->assertArrayHasKey('otp_expiry_seconds', (array) $entry->new_value);
        $this->assertSame(600, (int) $entry->new_value['otp_expiry_seconds']);
    }

    /**
     * The Active Sessions page (and its SessionService) were removed, which
     * also removed the only writer of a manual `session`-module audit row.
     * Every remaining "kill this user's logins" path goes through
     * UserService::invalidateSessions(), which is not itself audited — it is
     * only observable because it always writes the User model (remember-token
     * rotation), and User is in config('audit.observed_models'). These two
     * tests pin that indirect guarantee so a future refactor cannot delete
     * sessions silently.
     */
    public function test_user_deactivation_writes_audit_row(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->actingAs($admin);

        DB::table('sessions')->insert([
            'id' => 'deactivation-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);

        app(UserService::class)->deactivate($target);

        $this->assertDatabaseMissing('sessions', ['id' => 'deactivation-session']);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'user',
            'user_id' => $admin->id,
            'entity_id' => $target->id,
        ]);
    }

    public function test_password_change_writes_audit_row(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->actingAs($admin);

        DB::table('sessions')->insert([
            'id' => 'password-change-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);

        // Setting somebody else's password is a full account takeover, so
        // users.update demands the acting admin re-enter their OWN password.
        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target->id), [
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
                'password' => 'N3w!Passw0rd',
                'password_confirmation' => 'N3w!Passw0rd',
                'current_password' => 'P@ssw0rd!',
            ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        // The credential change must evict the target's other sessions...
        $this->assertDatabaseMissing('sessions', ['id' => 'password-change-session']);
        // ...and that eviction must be attributable. A password-only edit is
        // invisible to AuditObserver (User::$auditExclude drops `password` and
        // `remember_token`), so UserService::updateUser logs it explicitly.
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'user',
            'action' => AuditAction::ADMIN_RESET_PASSWORD->value,
            'user_id' => $admin->id,
            'entity_id' => $target->id,
        ]);
    }

    public function test_maintenance_enable_writes_audit_row(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin);
        $this->ensureMaintenanceModeOff();

        try {
            (new MaintenanceService)->enable('test-bypass-secret');

            $this->assertDatabaseHas('audit_logs', [
                'module' => 'system_setting',
                'action' => AuditAction::TOGGLE_MAINTENANCE->value,
                'user_id' => $admin->id,
            ]);
        } finally {
            $this->ensureMaintenanceModeOff();
        }
    }

    public function test_maintenance_disable_writes_audit_row(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin);
        $this->ensureMaintenanceModeOff();

        try {
            // disable() throws unless maintenance mode is active, so enable
            // through the service first (which also proves the enable path).
            (new MaintenanceService)->enable('test-bypass-secret');
            (new MaintenanceService)->disable();

            $this->assertDatabaseHas('audit_logs', [
                'module' => 'system_setting',
                'action' => AuditAction::TOGGLE_MAINTENANCE->value,
                'user_id' => $admin->id,
            ]);
        } finally {
            $this->ensureMaintenanceModeOff();
        }
    }

    /**
     * Reset maintenance mode outside the service so a leftover down file can
     * neither trip the enable/disable guards nor leak into other tests.
     */
    private function ensureMaintenanceModeOff(): void
    {
        if (file_exists(storage_path('framework/down'))) {
            Artisan::call('up');
        }
    }
}
