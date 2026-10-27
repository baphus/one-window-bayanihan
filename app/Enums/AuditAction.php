<?php

namespace App\Enums;

/**
 * Canonical audit event actions.
 *
 * The backing values are the single source of truth for the verbs written to
 * the audit_logs.action column and MUST stay in lock-step with the database
 * CHECK constraint (audit_logs_action_check — see the
 * 2026_11_01_000001_expand_audit_logs_action_check_for_purposeful_verbs
 * migration). A parity test asserts the two sets are identical.
 *
 * NOTE: this enum is intentionally NOT registered as an Eloquent cast on
 * AuditLog. AuditLog::chainDigest() serialises the raw string action into the
 * frozen tamper-evidence hash chain; casting the attribute to an enum object
 * would change what implode() serialises and invalidate every existing row's
 * chain. Use ::from()/->value at write sites instead.
 */
enum AuditAction: string
{
    // Generic model lifecycle (observer-driven; kept for observer + legacy)
    case CREATE = 'CREATE';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case ARCHIVE = 'ARCHIVE';
    case UNARCHIVE = 'UNARCHIVE';
    case PUBLISH = 'PUBLISH';
    case RESTORE = 'RESTORE';
    case PURGE = 'PURGE';

    // Auth & session lifecycle
    case LOGIN = 'LOGIN';
    case LOGOUT = 'LOGOUT';
    case LOGIN_FAILED = 'LOGIN_FAILED';
    case ACCEPT_INVITE = 'ACCEPT_INVITE';
    case REQUEST_PASSWORD_RESET = 'REQUEST_PASSWORD_RESET';
    case RESET_PASSWORD = 'RESET_PASSWORD';
    case CHANGE_PASSWORD = 'CHANGE_PASSWORD';
    case VERIFY_EMAIL = 'VERIFY_EMAIL';
    case CHANGE_EMAIL = 'CHANGE_EMAIL';
    case ENABLE_MFA = 'ENABLE_MFA';
    case DISABLE_MFA = 'DISABLE_MFA';
    case REGENERATE_RECOVERY_CODES = 'REGENERATE_RECOVERY_CODES';
    case ADMIN_RESET_PASSWORD = 'ADMIN_RESET_PASSWORD';
    case ADMIN_CHANGE_EMAIL = 'ADMIN_CHANGE_EMAIL';
    case ADMIN_VERIFY_EMAIL = 'ADMIN_VERIFY_EMAIL';
    case DELETE_ACCOUNT = 'DELETE_ACCOUNT';
    case PURGE_USER = 'PURGE_USER';
    case REVOKE_SESSIONS = 'REVOKE_SESSIONS';

    // Case Manager decisions
    case APPROVE = 'APPROVE';
    case REJECT = 'REJECT';
    case SEND = 'SEND';
    case CLOSE = 'CLOSE';
    case CREATE_CASE_ISSUE = 'CREATE_CASE_ISSUE';
    case DOWNLOAD = 'DOWNLOAD';
    case EXPORT = 'EXPORT';

    // Agency Focal referral lifecycle
    case ACCEPT = 'ACCEPT';
    case REQUEST_COMPLIANCE = 'REQUEST_COMPLIANCE';
    case RESUME = 'RESUME';
    case COMPLETE = 'COMPLETE';
    case ASSIGN_SERVICE = 'ASSIGN_SERVICE';
    case UNASSIGN_SERVICE = 'UNASSIGN_SERVICE';
    case ADD_REQUIREMENT = 'ADD_REQUIREMENT';
    case UPDATE_REQUIREMENT = 'UPDATE_REQUIREMENT';
    case REMOVE_REQUIREMENT = 'REMOVE_REQUIREMENT';
    case ISSUE_CLIENT_REQUEST = 'ISSUE_CLIENT_REQUEST';
    case ISSUE_LINK = 'ISSUE_LINK';
    case REISSUE_LINK = 'REISSUE_LINK';
    case REVOKE_LINK = 'REVOKE_LINK';
    case RESOLVE_CLIENT_REQUEST = 'RESOLVE_CLIENT_REQUEST';
    case CANCEL_CLIENT_REQUEST = 'CANCEL_CLIENT_REQUEST';
    case REOPEN_CLIENT_REQUEST = 'REOPEN_CLIENT_REQUEST';

    // System Admin
    case INVITE = 'INVITE';
    case RESEND_INVITE = 'RESEND_INVITE';
    case CANCEL_INVITE = 'CANCEL_INVITE';
    case DEACTIVATE = 'DEACTIVATE';
    case REACTIVATE = 'REACTIVATE';
    case RESET_MFA = 'RESET_MFA';
    case TOGGLE_MAINTENANCE = 'TOGGLE_MAINTENANCE';
    case UPDATE_SECURITY = 'UPDATE_SECURITY';
    case SEND_REMINDER = 'SEND_REMINDER';

    // OFW / public
    case REGISTER = 'REGISTER';
    case FILE_INTAKE = 'FILE_INTAKE';
    case REPLY_CLIENT_REQUEST = 'REPLY_CLIENT_REQUEST';
    case UPLOAD_REPLY_DOC = 'UPLOAD_REPLY_DOC';
    case TRACK_CASE = 'TRACK_CASE';
    case SUBMIT_SURVEY = 'SUBMIT_SURVEY';
    case SUBMIT_CONTACT = 'SUBMIT_CONTACT';

    /** All backing values — convenience for validation and tests. */
    public static function values(): array
    {
        return array_map(fn (self $a) => $a->value, self::cases());
    }

    /** True when the given string is a recognised action verb. */
    public static function isValid(string $action): bool
    {
        return self::tryFrom($action) !== null;
    }

    /**
     * Account-security actions that force the SECURITY category regardless of
     * the module they touch (e.g. a LOGIN on the "auth" module).
     */
    public function isSecurityEvent(): bool
    {
        return in_array($this, [
            self::LOGIN,
            self::LOGOUT,
            self::LOGIN_FAILED,
            self::ACCEPT_INVITE,
            self::REQUEST_PASSWORD_RESET,
            self::RESET_PASSWORD,
            self::CHANGE_PASSWORD,
            self::VERIFY_EMAIL,
            self::CHANGE_EMAIL,
            self::ENABLE_MFA,
            self::DISABLE_MFA,
            self::REGENERATE_RECOVERY_CODES,
            self::ADMIN_RESET_PASSWORD,
            self::ADMIN_CHANGE_EMAIL,
            self::ADMIN_VERIFY_EMAIL,
            self::DELETE_ACCOUNT,
            self::PURGE_USER,
            self::REVOKE_SESSIONS,
            self::RESET_MFA,
            self::TOGGLE_MAINTENANCE,
            self::UPDATE_SECURITY,
        ], true);
    }

    /** Human-readable past-tense label for display surfaces. */
    public function label(): string
    {
        return match ($this) {
            self::CREATE => 'Created',
            self::UPDATE => 'Updated',
            self::DELETE => 'Deleted',
            self::ARCHIVE => 'Archived',
            self::UNARCHIVE => 'Unarchived',
            self::PUBLISH => 'Published',
            self::RESTORE => 'Restored',
            self::PURGE => 'Purged',
            self::LOGIN => 'Signed in',
            self::LOGOUT => 'Signed out',
            self::LOGIN_FAILED => 'Sign-in failed',
            self::ACCEPT_INVITE => 'Accepted an invitation',
            self::REQUEST_PASSWORD_RESET => 'Requested a password reset',
            self::RESET_PASSWORD => 'Reset password',
            self::CHANGE_PASSWORD => 'Changed password',
            self::VERIFY_EMAIL => 'Verified email address',
            self::CHANGE_EMAIL => 'Changed email address',
            self::ENABLE_MFA => 'Enabled two-factor authentication',
            self::DISABLE_MFA => 'Disabled two-factor authentication',
            self::REGENERATE_RECOVERY_CODES => 'Regenerated recovery codes',
            self::ADMIN_RESET_PASSWORD => 'Reset a user\'s password',
            self::ADMIN_CHANGE_EMAIL => 'Changed a user\'s email address',
            self::ADMIN_VERIFY_EMAIL => 'Verified a user\'s email address',
            self::DELETE_ACCOUNT => 'Deleted own account',
            self::PURGE_USER => 'Purged a user',
            self::REVOKE_SESSIONS => 'Revoked sessions',
            self::APPROVE => 'Approved',
            self::REJECT => 'Rejected',
            self::SEND => 'Sent',
            self::CLOSE => 'Closed',
            self::CREATE_CASE_ISSUE => 'Added a case issue',
            self::DOWNLOAD => 'Downloaded',
            self::EXPORT => 'Exported',
            self::ACCEPT => 'Accepted',
            self::REQUEST_COMPLIANCE => 'Requested compliance for',
            self::RESUME => 'Resumed',
            self::COMPLETE => 'Completed',
            self::ASSIGN_SERVICE => 'Assigned a service to',
            self::UNASSIGN_SERVICE => 'Removed a service from',
            self::ADD_REQUIREMENT => 'Added a requirement to',
            self::UPDATE_REQUIREMENT => 'Updated a requirement on',
            self::REMOVE_REQUIREMENT => 'Removed a requirement from',
            self::ISSUE_CLIENT_REQUEST => 'Issued a client request on',
            self::ISSUE_LINK => 'Issued an access link for',
            self::REISSUE_LINK => 'Reissued an access link for',
            self::REVOKE_LINK => 'Revoked an access link for',
            self::RESOLVE_CLIENT_REQUEST => 'Resolved a client request on',
            self::CANCEL_CLIENT_REQUEST => 'Cancelled a client request on',
            self::REOPEN_CLIENT_REQUEST => 'Reopened a client request on',
            self::INVITE => 'Invited',
            self::RESEND_INVITE => 'Resent an invitation to',
            self::CANCEL_INVITE => 'Cancelled an invitation to',
            self::DEACTIVATE => 'Deactivated',
            self::REACTIVATE => 'Reactivated',
            self::RESET_MFA => 'Reset MFA for',
            self::TOGGLE_MAINTENANCE => 'Toggled maintenance mode',
            self::UPDATE_SECURITY => 'Updated security settings',
            self::SEND_REMINDER => 'Sent overdue reminders',
            self::REGISTER => 'Registered',
            self::FILE_INTAKE => 'Filed',
            self::REPLY_CLIENT_REQUEST => 'Replied to a client request',
            self::UPLOAD_REPLY_DOC => 'Uploaded a reply document',
            self::TRACK_CASE => 'Tracked a case',
            self::SUBMIT_SURVEY => 'Submitted a survey',
            self::SUBMIT_CONTACT => 'Submitted a contact request',
        };
    }
}
