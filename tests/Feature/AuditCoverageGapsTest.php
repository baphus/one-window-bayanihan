<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Services\CaseService;
use App\Services\MaintenanceService;
use App\Services\SecuritySettingsService;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the service-level audit writes added in T3–T6 of the
 * audit-coverage-gaps plan (SecuritySettingsService, CaseService::deleteDraft,
 * SessionService::terminate, MaintenanceService::enable/disable).
 *
 * These paths have no observable model event to rely on (draft deletion runs
 * inside withoutEvents; settings/sessions/maintenance have no audited model
 * at all), so each test calls the service directly and asserts the manual
 * audit row it must produce. Assertions compare against the raw
 * AuditAction::X->value strings — AuditLog intentionally carries no enum
 * cast so the frozen hash-chain serialisation is unaffected.
 */
class AuditCoverageGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_settings_update_writes_audit_row_with_key_names_only(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin);

        (new SecuritySettingsService)->update([
            'password_min_length' => 12,
            'ip_whitelist_ips' => '10.99.88.77',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'security_settings',
            'action' => AuditAction::UPDATE->value,
            'user_id' => $admin->id,
        ]);

        $entry = AuditLog::query()
            ->where('module', 'security_settings')
            ->where('action', AuditAction::UPDATE->value)
            ->firstOrFail();

        // Only the changed key NAMES are logged — never the values. The
        // whitelist IPs are secret-adjacent, so their absence is asserted
        // with a distinctive address that cannot collide with other output.
        $this->assertStringContainsString('password_min_length', $entry->description);
        $this->assertStringContainsString('ip_whitelist_ips', $entry->description);
        $this->assertStringNotContainsString('10.99.88.77', $entry->description);
        $this->assertNull($entry->old_value);
        $this->assertNull($entry->new_value);
    }

    public function test_delete_draft_writes_delete_audit_row(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
        ]);
        $case->client_id = $client->id;
        $case->save();

        $caseId = $case->id;
        $caseNumber = $case->case_number;

        app(CaseService::class)->deleteDraft($caseId, $user->id);

        $this->assertDatabaseMissing('cases', ['id' => $caseId]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'case',
            'action' => AuditAction::DELETE->value,
            'entity_id' => $caseId,
            'user_id' => $user->id,
        ]);

        $entry = AuditLog::query()
            ->where('module', 'case')
            ->where('action', AuditAction::DELETE->value)
            ->where('entity_id', $caseId)
            ->firstOrFail();

        // The manual audit captures the case number and client name because
        // the deletion itself runs inside withoutEvents (no observer row).
        $this->assertStringContainsString($caseNumber, $entry->description);
        $this->assertStringContainsString('Juan Dela Cruz', $entry->description);
    }

    public function test_session_terminate_writes_audit_row(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin);

        do {
            $sessionId = Str::random(40);
        } while ($sessionId === session()->getId());

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test-payload',
            'last_activity' => time(),
        ]);

        (new SessionService)->terminate($sessionId);

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'session',
            'action' => AuditAction::DELETE->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_maintenance_enable_writes_audit_row(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin);
        $this->ensureMaintenanceModeOff();

        try {
            (new MaintenanceService)->enable('test-bypass-secret');

            $this->assertDatabaseHas('audit_logs', [
                'module' => 'maintenance',
                'action' => AuditAction::UPDATE->value,
                'description' => 'Maintenance mode was enabled',
                'user_id' => $admin->id,
            ]);
        } finally {
            $this->ensureMaintenanceModeOff();
        }
    }

    public function test_maintenance_disable_writes_audit_row(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin);
        $this->ensureMaintenanceModeOff();

        try {
            // disable() throws unless maintenance mode is active, so enable
            // through the service first (which also proves the enable path).
            (new MaintenanceService)->enable('test-bypass-secret');
            (new MaintenanceService)->disable();

            $this->assertDatabaseHas('audit_logs', [
                'module' => 'maintenance',
                'action' => AuditAction::UPDATE->value,
                'description' => 'Maintenance mode was disabled',
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
