<?php

namespace App\Enums;

/**
 * Canonical audit event actions.
 *
 * The backing values are the single source of truth for the verbs written to
 * the audit_logs.action column and MUST stay in lock-step with the database
 * CHECK constraint (audit_logs_action_check — see the
 * 2026_07_12_000003_expand_audit_logs_action_check migration). A parity test
 * asserts the two sets are identical.
 *
 * NOTE: this enum is intentionally NOT registered as an Eloquent cast on
 * AuditLog. AuditLog::chainDigest() serialises the raw string action into the
 * frozen tamper-evidence hash chain; casting the attribute to an enum object
 * would change what implode() serialises and invalidate every existing row's
 * chain. Use ::from()/->value at write sites instead.
 */
enum AuditAction: string
{
    case CREATE = 'CREATE';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case LOGIN = 'LOGIN';
    case LOGOUT = 'LOGOUT';
    case LOGIN_FAILED = 'LOGIN_FAILED';
    case EXPORT = 'EXPORT';
    case ARCHIVE = 'ARCHIVE';
    case UNARCHIVE = 'UNARCHIVE';
    case PUBLISH = 'PUBLISH';
    case RESTORE = 'RESTORE';
    case PURGE = 'PURGE';

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
        return in_array($this, [self::LOGIN, self::LOGOUT, self::LOGIN_FAILED], true);
    }

    /** Human-readable past-tense label for display surfaces. */
    public function label(): string
    {
        return match ($this) {
            self::CREATE => 'Created',
            self::UPDATE => 'Updated',
            self::DELETE => 'Deleted',
            self::LOGIN => 'Signed in',
            self::LOGOUT => 'Signed out',
            self::LOGIN_FAILED => 'Sign-in failed',
            self::EXPORT => 'Exported',
            self::ARCHIVE => 'Archived',
            self::UNARCHIVE => 'Unarchived',
            self::PUBLISH => 'Published',
            self::RESTORE => 'Restored',
            self::PURGE => 'Purged',
        };
    }
}
