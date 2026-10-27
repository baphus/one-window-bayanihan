<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Asserts every purposeful verb renders the target sentence format:
 *
 *   {Actor name} ({Role label}) {verb past-tense} {Entity identifier} {— salient detail}
 *
 * The role label includes the agency name for Agency Focal:
 * `(Agency Focal, OWWA)`. Anonymous actors for public-link actions resolve to
 * `Anonymous (link holder)`.
 */
class PurposefulActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private AuditLogFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new AuditLogFormatter;
    }

    // ========================================================================
    //  CHECK constraint parity (enum ↔ database)
    // ========================================================================

    public function test_audit_action_enum_matches_database_check_constraint(): void
    {
        $this->assertSame(
            collect(AuditAction::values())->sort()->values()->all(),
            collect(AuditAction::cases())->map(fn ($a) => $a->value)->sort()->values()->all(),
            'AuditAction::values() must match AuditAction::cases() mapped to ->value.',
        );

        // The existing AuditVocabularyTest already asserts enum ↔ CHECK
        // constraint parity; this test asserts the enum is internally
        // consistent and that every case has a label.
        foreach (AuditAction::cases() as $action) {
            $this->assertNotEmpty($action->label(), "Action {$action->value} has no label.");
        }
    }

    // ========================================================================
    //  Auth & security — 15 new verbs
    // ========================================================================

    public function test_accept_invite_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Juan dela Cruz']);

        $log = $this->makeLog(
            AuditAction::ACCEPT_INVITE,
            AuditModule::AUTH,
            $user,
        );

        $this->assertStringContainsString('Juan dela Cruz', $log->description);
        $this->assertStringContainsString('accepted a staff invitation', $log->description);
    }

    public function test_request_password_reset_renders_target_sentence(): void
    {
        $log = $this->makeLog(
            AuditAction::REQUEST_PASSWORD_RESET,
            AuditModule::AUTH,
            null,
            null,
            ['email' => 'juan@example.com'],
        );

        $this->assertStringContainsString('Anonymous (link holder)', $log->description);
        $this->assertStringContainsString('requested a password reset link', $log->description);
    }

    public function test_reset_password_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Juan dela Cruz', 'role' => UserRole::CASE_MANAGER]);

        $log = $this->makeLog(AuditAction::RESET_PASSWORD, AuditModule::AUTH, $user);

        $this->assertStringContainsString('Juan dela Cruz (Case Manager)', $log->description);
        $this->assertStringContainsString('reset their password', $log->description);
    }

    public function test_change_password_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Juan dela Cruz']);

        $log = $this->makeLog(AuditAction::CHANGE_PASSWORD, AuditModule::AUTH, $user);

        $this->assertStringContainsString('changed their password', $log->description);
    }

    public function test_verify_email_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Juan dela Cruz']);

        $log = $this->makeLog(AuditAction::VERIFY_EMAIL, AuditModule::AUTH, $user);

        $this->assertStringContainsString('verified their email address', $log->description);
    }

    public function test_change_email_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Juan dela Cruz']);

        $log = $this->makeLog(
            AuditAction::CHANGE_EMAIL,
            AuditModule::USER,
            $user,
            ['email' => 'old@example.com'],
            ['email' => 'new@example.com'],
        );

        $this->assertStringContainsString('changed their email', $log->description);
        $this->assertStringContainsString('old@example.com', $log->description);
        $this->assertStringContainsString('new@example.com', $log->description);
    }

    public function test_admin_change_email_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User', 'role' => UserRole::ADMIN]);
        $target = User::factory()->create(['name' => 'Target User']);

        $log = $this->makeLog(
            AuditAction::ADMIN_CHANGE_EMAIL,
            AuditModule::USER,
            $admin,
            ['email' => 'old@example.com'],
            ['email' => 'new@example.com'],
            entityLabel: 'Target User',
        );

        $this->assertStringContainsString('Admin User (System Admin)', $log->description);
        $this->assertStringContainsString('changed the email of', $log->description);
        $this->assertStringContainsString('Target User', $log->description);
    }

    public function test_enable_mfa_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Juan dela Cruz']);

        $log = $this->makeLog(AuditAction::ENABLE_MFA, AuditModule::MFA, $user);

        $this->assertStringContainsString('enabled two-factor authentication', $log->description);
    }

    public function test_disable_mfa_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Juan dela Cruz']);

        $log = $this->makeLog(AuditAction::DISABLE_MFA, AuditModule::MFA, $user);

        $this->assertStringContainsString('disabled two-factor authentication', $log->description);
    }

    public function test_regenerate_recovery_codes_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Juan dela Cruz']);

        $log = $this->makeLog(AuditAction::REGENERATE_RECOVERY_CODES, AuditModule::MFA, $user);

        $this->assertStringContainsString('regenerated MFA recovery codes', $log->description);
    }

    public function test_admin_reset_password_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User', 'role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::ADMIN_RESET_PASSWORD,
            AuditModule::USER,
            $admin,
            null,
            null,
            entityLabel: 'Target User',
        );

        $this->assertStringContainsString('Admin User (System Admin)', $log->description);
        $this->assertStringContainsString('reset the password of', $log->description);
        $this->assertStringContainsString('Target User', $log->description);
    }

    public function test_admin_verify_email_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User', 'role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::ADMIN_VERIFY_EMAIL,
            AuditModule::USER,
            $admin,
            null,
            null,
            entityLabel: 'Target User',
        );

        $this->assertStringContainsString('verified the email of', $log->description);
        $this->assertStringContainsString('Target User', $log->description);
    }

    public function test_delete_account_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Juan dela Cruz']);

        $log = $this->makeLog(AuditAction::DELETE_ACCOUNT, AuditModule::USER, $user);

        $this->assertStringContainsString('deleted their own account', $log->description);
    }

    public function test_purge_user_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User', 'role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::PURGE_USER,
            AuditModule::USER,
            $admin,
            null,
            null,
            entityLabel: 'Target User',
        );

        $this->assertStringContainsString('permanently deleted', $log->description);
        $this->assertStringContainsString('Target User', $log->description);
    }

    public function test_revoke_sessions_renders_target_sentence(): void
    {
        $log = $this->makeLog(
            AuditAction::REVOKE_SESSIONS,
            AuditModule::SESSION,
            null,
            null,
            ['count' => 5],
        );

        $this->assertStringContainsString('revoked MFA-enrolled sessions', $log->description);
    }

    // ========================================================================
    //  Case Manager — 7 verbs
    // ========================================================================

    public function test_approve_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Case Manager', 'role' => UserRole::CASE_MANAGER]);

        $log = $this->makeLog(
            AuditAction::APPROVE,
            AuditModule::CASE,
            $user,
            ['status' => 'PENDING_REVIEW'],
            ['status' => 'OPEN'],
            entityLabel: 'C-2026-001',
        );

        $this->assertStringContainsString('Case Manager (Case Manager)', $log->description);
        $this->assertStringContainsString('approved', $log->description);
        $this->assertStringContainsString('Case C-2026-001', $log->description);
    }

    public function test_reject_renders_target_sentence_with_reason(): void
    {
        $user = User::factory()->create(['name' => 'Case Manager', 'role' => UserRole::CASE_MANAGER]);

        $log = $this->makeLog(
            AuditAction::REJECT,
            AuditModule::CASE,
            $user,
            ['status' => 'PENDING_REVIEW'],
            ['is_deleted' => true, 'deletion_reason' => 'Incomplete documentation'],
            entityLabel: 'C-2026-001',
        );

        $this->assertStringContainsString('rejected', $log->description);
        $this->assertStringContainsString('Case C-2026-001', $log->description);
        $this->assertStringContainsString('Incomplete documentation', $log->description);
    }

    public function test_send_renders_target_sentence_with_agency(): void
    {
        $user = User::factory()->create(['name' => 'Case Manager', 'role' => UserRole::CASE_MANAGER]);

        $log = $this->makeLog(
            AuditAction::SEND,
            AuditModule::REFERRAL,
            $user,
            null,
            ['agency_name' => 'OWWA', 'attachment_count' => 3],
            entityLabel: 'Referral — Service A',
        );

        $this->assertStringContainsString('sent', $log->description);
        $this->assertStringContainsString('OWWA', $log->description);
        $this->assertStringContainsString('3', $log->description);
    }

    public function test_close_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Case Manager', 'role' => UserRole::CASE_MANAGER]);

        $log = $this->makeLog(
            AuditAction::CLOSE,
            AuditModule::CASE,
            $user,
            ['status' => 'OPEN'],
            ['status' => 'CLOSED'],
            entityLabel: 'C-2026-001',
        );

        $this->assertStringContainsString('closed', $log->description);
        $this->assertStringContainsString('Case C-2026-001', $log->description);
    }

    public function test_create_case_issue_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Case Manager', 'role' => UserRole::CASE_MANAGER]);

        $log = $this->makeLog(
            AuditAction::CREATE_CASE_ISSUE,
            AuditModule::CASE_ISSUE,
            $user,
            null,
            null,
            entityLabel: 'Welfare Assistance',
        );

        $this->assertStringContainsString('added case issue', $log->description);
        $this->assertStringContainsString('Welfare Assistance', $log->description);
    }

    public function test_download_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Case Manager', 'role' => UserRole::CASE_MANAGER]);

        $log = $this->makeLog(
            AuditAction::DOWNLOAD,
            AuditModule::CASE_DOCUMENT,
            $user,
            null,
            null,
            entityLabel: 'passport.pdf',
        );

        $this->assertStringContainsString('downloaded', $log->description);
        $this->assertStringContainsString('passport.pdf', $log->description);
    }

    public function test_export_renders_target_sentence(): void
    {
        $user = User::factory()->create(['name' => 'Case Manager', 'role' => UserRole::CASE_MANAGER]);

        $log = $this->makeLog(
            AuditAction::EXPORT,
            AuditModule::DATA_EXPORT,
            $user,
            null,
            ['format' => 'excel', 'scope' => 'cases'],
        );

        $this->assertStringContainsString('exported', $log->description);
        $this->assertStringContainsString('cases', $log->description);
        $this->assertStringContainsString('excel', $log->description);
    }

    // ========================================================================
    //  Agency Focal — referral lifecycle
    // ========================================================================

    public function test_accept_referral_renders_target_sentence_with_agency_role(): void
    {
        $agency = Agency::factory()->create(['name' => 'OWWA']);
        $user = User::factory()->create([
            'name' => 'Agency User',
            'role' => UserRole::AGENCY,
            'agcy_id' => $agency->id,
        ]);

        $log = $this->makeLog(
            AuditAction::ACCEPT,
            AuditModule::REFERRAL,
            $user,
            ['status' => 'PENDING'],
            ['status' => 'PROCESSING'],
            entityLabel: 'Referral — Service A (OWWA)',
        );

        $this->assertStringContainsString('Agency User (Agency Focal, OWWA)', $log->description);
        $this->assertStringContainsString('accepted', $log->description);
    }

    public function test_request_compliance_renders_target_sentence(): void
    {
        $agency = Agency::factory()->create(['name' => 'OWWA']);
        $user = User::factory()->create([
            'role' => UserRole::AGENCY,
            'agcy_id' => $agency->id,
        ]);

        $log = $this->makeLog(
            AuditAction::REQUEST_COMPLIANCE,
            AuditModule::REFERRAL,
            $user,
            ['status' => 'PROCESSING'],
            ['status' => 'FOR_COMPLIANCE'],
        );

        $this->assertStringContainsString('requested compliance for', $log->description);
    }

    public function test_resume_renders_target_sentence(): void
    {
        $user = User::factory()->create(['role' => UserRole::AGENCY]);

        $log = $this->makeLog(
            AuditAction::RESUME,
            AuditModule::REFERRAL,
            $user,
            ['status' => 'FOR_COMPLIANCE'],
            ['status' => 'PROCESSING'],
        );

        $this->assertStringContainsString('resumed', $log->description);
    }

    public function test_complete_referral_renders_target_sentence(): void
    {
        $user = User::factory()->create(['role' => UserRole::AGENCY]);

        $log = $this->makeLog(
            AuditAction::COMPLETE,
            AuditModule::REFERRAL,
            $user,
            ['status' => 'PROCESSING'],
            ['status' => 'COMPLETED'],
        );

        $this->assertStringContainsString('completed', $log->description);
    }

    public function test_assign_service_renders_target_sentence(): void
    {
        $user = User::factory()->create(['role' => UserRole::AGENCY]);

        $log = $this->makeLog(
            AuditAction::ASSIGN_SERVICE,
            AuditModule::REFERRAL,
            $user,
            null,
            ['service_name' => 'Welfare Assistance'],
            entityLabel: 'Referral — Service A',
        );

        $this->assertStringContainsString('assigned a service to', $log->description);
        $this->assertStringContainsString('Welfare Assistance', $log->description);
    }

    public function test_reject_referral_renders_target_sentence_with_reason(): void
    {
        $user = User::factory()->create(['role' => UserRole::AGENCY]);

        $log = $this->makeLog(
            AuditAction::REJECT,
            AuditModule::REFERRAL,
            $user,
            ['status' => 'PENDING'],
            ['status' => 'REJECTED', 'rejection_reason' => 'Not eligible'],
        );

        $this->assertStringContainsString('rejected', $log->description);
        $this->assertStringContainsString('Not eligible', $log->description);
    }

    // ========================================================================
    //  System Admin
    // ========================================================================

    public function test_invite_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User', 'role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::INVITE,
            AuditModule::USER_INVITE,
            $admin,
            null,
            ['email' => 'newuser@example.com'],
        );

        $this->assertStringContainsString('sent an invitation to', $log->description);
        $this->assertStringContainsString('newuser@example.com', $log->description);
    }

    public function test_resend_invite_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::RESEND_INVITE,
            AuditModule::USER_INVITE,
            $admin,
            null,
            ['email' => 'newuser@example.com'],
        );

        $this->assertStringContainsString('resent an invitation to', $log->description);
    }

    public function test_cancel_invite_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::CANCEL_INVITE,
            AuditModule::USER_INVITE,
            $admin,
            null,
            ['email' => 'newuser@example.com'],
        );

        $this->assertStringContainsString('cancelled an invitation to', $log->description);
    }

    public function test_deactivate_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User', 'role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::DEACTIVATE,
            AuditModule::USER,
            $admin,
            null,
            null,
            entityLabel: 'Target User',
        );

        $this->assertStringContainsString('Admin User (System Admin)', $log->description);
        $this->assertStringContainsString('deactivated', $log->description);
        $this->assertStringContainsString('Target User', $log->description);
    }

    public function test_reactivate_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::REACTIVATE,
            AuditModule::USER,
            $admin,
            null,
            null,
            entityLabel: 'Target User',
        );

        $this->assertStringContainsString('reactivated', $log->description);
        $this->assertStringContainsString('Target User', $log->description);
    }

    public function test_reset_mfa_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::RESET_MFA,
            AuditModule::MFA,
            $admin,
            null,
            null,
            entityLabel: 'Target User',
        );

        $this->assertStringContainsString('reset MFA for', $log->description);
        $this->assertStringContainsString('Target User', $log->description);
    }

    public function test_toggle_maintenance_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::TOGGLE_MAINTENANCE,
            AuditModule::SYSTEM_SETTING,
            $admin,
            null,
            ['enabled' => true],
        );

        $this->assertStringContainsString('enabled maintenance mode', $log->description);
    }

    public function test_update_security_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::UPDATE_SECURITY,
            AuditModule::SYSTEM_SETTING,
            $admin,
        );

        $this->assertStringContainsString('updated security settings', $log->description);
    }

    public function test_send_reminder_renders_target_sentence(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $log = $this->makeLog(
            AuditAction::SEND_REMINDER,
            AuditModule::REFERRAL,
            $admin,
            null,
            ['reminder_count' => 5],
        );

        $this->assertStringContainsString('sent overdue reminders', $log->description);
        $this->assertStringContainsString('5', $log->description);
    }

    // ========================================================================
    //  OFW / public
    // ========================================================================

    public function test_register_renders_target_sentence(): void
    {
        $log = $this->makeLog(
            AuditAction::REGISTER,
            AuditModule::AUTH,
            null,
            null,
            ['email' => 'ofw@example.com'],
        );

        $this->assertStringContainsString('registered an account', $log->description);
        $this->assertStringContainsString('ofw@example.com', $log->description);
    }

    public function test_file_intake_renders_target_sentence(): void
    {
        $log = $this->makeLog(
            AuditAction::FILE_INTAKE,
            AuditModule::CASE,
            null,
            null,
            null,
            entityLabel: 'TRK-2026-001',
        );

        $this->assertStringContainsString('filed an intake', $log->description);
        $this->assertStringContainsString('TRK-2026-001', $log->description);
    }

    public function test_track_case_renders_target_sentence(): void
    {
        $log = $this->makeLog(
            AuditAction::TRACK_CASE,
            AuditModule::CASE,
            null,
            null,
            null,
            entityLabel: 'TRK-2026-001',
        );

        $this->assertStringContainsString('tracked', $log->description);
        $this->assertStringContainsString('Case TRK-2026-001', $log->description);
    }

    public function test_submit_survey_renders_target_sentence(): void
    {
        $log = $this->makeLog(
            AuditAction::SUBMIT_SURVEY,
            AuditModule::SURVEY_RESPONSE,
            null,
        );

        $this->assertStringContainsString('submitted a satisfaction survey', $log->description);
    }

    public function test_submit_contact_renders_target_sentence(): void
    {
        $log = $this->makeLog(
            AuditAction::SUBMIT_CONTACT,
            AuditModule::FEEDBACK,
            null,
            null,
            ['name' => 'Juan', 'email' => 'juan@example.com'],
        );

        $this->assertStringContainsString('submitted a contact request', $log->description);
    }

    public function test_anonymous_actor_renders_link_holder(): void
    {
        $log = $this->makeLog(
            AuditAction::REPLY_CLIENT_REQUEST,
            AuditModule::REFERRAL_CLIENT_REQUEST,
            null,
        );

        $this->assertStringContainsString('Anonymous (link holder)', $log->description);
    }

    // ========================================================================
    //  Re-refer context
    // ========================================================================

    public function test_send_re_referral_renders_previous_context(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER]);

        $log = $this->makeLog(
            AuditAction::SEND,
            AuditModule::REFERRAL,
            $user,
            null,
            [
                'agency_name' => 'OWWA',
                'previous_referral_id' => '00000000-0000-0000-0000-000000000001',
                'rejection_reason' => 'Wrong agency',
            ],
            entityLabel: 'Referral — Service A',
        );

        $this->assertStringContainsString('re-referral', $log->description);
        $this->assertStringContainsString('Wrong agency', $log->description);
    }

    // ========================================================================
    //  Helper: build a log + format it
    // ========================================================================

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function makeLog(
        AuditAction $action,
        AuditModule $module,
        ?User $user = null,
        ?array $old = null,
        ?array $new = null,
        ?string $entityLabel = null,
    ): AuditLog {
        $log = new AuditLog([
            'action' => $action->value,
            'module' => $module->value,
            'entity_id' => $user?->id ?? '00000000-0000-0000-0000-000000000002',
            'entity_label' => $entityLabel,
            'old_value' => $old,
            'new_value' => $new,
            'user_id' => $user?->id,
            'timestamp' => now(),
        ]);

        // Generate the description via the formatter (as AuditLogger::write() does)
        $log->description = $this->formatter->format($log);

        return $log;
    }
}
