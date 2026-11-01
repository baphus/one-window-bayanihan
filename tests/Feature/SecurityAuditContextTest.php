<?php

namespace Tests\Feature;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * L0 fixes: security events must be attributable.
 *
 * These assert on the *content* of the audit row, because the gaps they guard
 * against were all rows that existed but said nothing useful — a failed MFA
 * recorded as an anonymous login failure, a forced session termination with no
 * row at all, an admin password reset with no values.
 */
class SecurityAuditContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_mfa_challenge_is_recorded_as_an_mfa_event_with_the_account(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'email' => 'juan@example.com',
        ]);

        Event::dispatch(new Failed('web', $user, ['mfa' => true, 'email' => $user->email]));

        $row = AuditLog::where('module', AuditModule::MFA->value)->latest('chain_seq')->first();

        $this->assertNotNull($row, 'A failed MFA challenge must write an MFA row.');
        $this->assertSame('LOGIN_FAILED', $row->action);
        $this->assertSame('mfa_challenge', $row->new_value['stage']);
        $this->assertSame($user->getKey(), $row->entity_id);

        // And it must NOT also appear as a password failure.
        $this->assertSame(
            0,
            AuditLog::where('module', AuditModule::AUTH->value)->count(),
            'A failed MFA challenge must not be logged as an AUTH login failure.'
        );
    }

    public function test_forced_session_termination_writes_a_session_row(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($admin);

        app(UserService::class)->invalidateSessions($target, 'admin_password_reset');

        $row = AuditLog::where('module', AuditModule::SESSION->value)->first();

        $this->assertNotNull($row, 'Forcing a user out must leave an audit trail.');
        $this->assertSame('REVOKE_SESSIONS', $row->action);
        $this->assertSame($admin->id, $row->user_id);
        $this->assertSame('admin_password_reset', $row->new_value['reason']);
        $this->assertSame($target->getKey(), $row->entity_id);
    }

    public function test_admin_mfa_reset_records_the_affected_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $target = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'email' => 'target@example.com',
            'mfa_secret' => 'SECRET',
            'mfa_enabled_at' => now(),
        ]);

        $this->actingAs($admin);

        app(UserService::class)->resetMfa($target, $admin);

        $row = AuditLog::where('module', AuditModule::MFA->value)
            ->where('action', 'RESET_MFA')
            ->latest('chain_seq')
            ->first();

        $this->assertNotNull($row);
        $this->assertNotNull($row->new_value, 'An admin 2FA reset must say which account it touched.');
        $this->assertSame('target@example.com', $row->new_value['email']);
        $this->assertSame($admin->id, $row->new_value['actor_id']);
    }

    /**
     * The dead class: nothing in the application may write through it, because
     * it produced description-only rows with null values.
     */
    public function test_security_audit_logger_has_been_removed(): void
    {
        $this->assertFileDoesNotExist(app_path('Services/SecurityAuditLogger.php'));
    }

    /**
     * The security module was decoration. If it returns, this fails.
     */
    public function test_there_is_no_security_audit_module(): void
    {
        $this->assertNull(
            AuditModule::tryFrom('security'),
            'AuditModule::SECURITY was removed: nothing ever wrote to it. Security events are declared in config/security_events.php.'
        );
    }

    public function test_security_events_config_declares_the_modules_that_are_actually_written(): void
    {
        $events = config('security_events.events');

        $this->assertArrayHasKey(AuditModule::AUTH->value, $events);
        $this->assertArrayHasKey(AuditModule::MFA->value, $events);
        $this->assertArrayHasKey(AuditModule::SESSION->value, $events);
    }
}
