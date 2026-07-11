<?php

namespace App\Services;

/**
 * Single source of truth for audit event categories (see design D5).
 * Stamped at write time in AuditLog::saving and reused by the
 * audit:backfill-categories command.
 */
final class AuditCategory
{
    public const SECURITY = 'security';

    public const DATA = 'data';

    public const ADMIN = 'admin';

    public const SYSTEM = 'system';

    public const ALL = [self::SECURITY, self::DATA, self::ADMIN, self::SYSTEM];

    /** Auth/account-security surfaces, regardless of action. */
    private const SECURITY_MODULES = ['auth', 'security', 'session', 'mfa'];

    private const SECURITY_ACTIONS = ['LOGIN', 'LOGOUT', 'LOGIN_FAILED'];

    /** Business entities (canonical names + legacy aliases found in data). */
    private const DATA_MODULES = [
        'case', 'cases', 'case_files',
        'client', 'clients',
        'client_address', 'client_addresses',
        'client_employment', 'client_employments',
        'referral', 'referrals',
        'milestone', 'milestones',
        'referral_attachment', 'referral_attachments',
        'feedback', 'feedbacks',
        'next_of_kin', 'next_of_kins',
    ];

    /** Configuration and administration surfaces. */
    private const ADMIN_MODULES = [
        'user', 'users',
        'agency', 'agencies',
        'service', 'services',
        'service_requirement', 'service_requirements',
        'case_category', 'case_categories',
        'case_issue', 'case_issues',
        'case_status', 'case_statuses',
        'helpdesk_article', 'helpdesk_articles',
        'audit', 'data_export',
        'email_log', 'email_logs',
        'system_setting', 'system_settings',
    ];

    public static function for(string $module, string $action, ?string $userId): string
    {
        $module = strtolower($module);

        if (in_array($module, self::SECURITY_MODULES, true)
            || in_array(strtoupper($action), self::SECURITY_ACTIONS, true)) {
            return self::SECURITY;
        }

        // Unattributed writes from the console are automated/maintenance
        // activity (seeders, scheduled jobs), whatever entity they touch.
        if ($userId === null && app()->runningInConsole()) {
            return self::SYSTEM;
        }

        if (in_array($module, self::DATA_MODULES, true)) {
            return self::DATA;
        }

        if (in_array($module, self::ADMIN_MODULES, true)) {
            return self::ADMIN;
        }

        // Unknown modules stay visible in the default admin view.
        return self::DATA;
    }

    /**
     * Backfill variant: historical rows carry no runtime context, so
     * unattributed non-security rows are treated as system activity.
     */
    public static function forBackfill(string $module, string $action, ?string $userId): string
    {
        $module = strtolower($module);

        if (in_array($module, self::SECURITY_MODULES, true)
            || in_array(strtoupper($action), self::SECURITY_ACTIONS, true)) {
            return self::SECURITY;
        }

        if ($userId === null) {
            return self::SYSTEM;
        }

        if (in_array($module, self::DATA_MODULES, true)) {
            return self::DATA;
        }

        if (in_array($module, self::ADMIN_MODULES, true)) {
            return self::ADMIN;
        }

        return self::DATA;
    }
}
