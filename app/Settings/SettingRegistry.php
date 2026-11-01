<?php

namespace App\Settings;

use App\Models\SystemSetting;

/**
 * The one declaration of every system setting.
 *
 * Adding a setting is an entry here plus its reader. Nothing else: validation,
 * the page props, the audit hook and the seeder all derive from this array.
 *
 * Rules for this file:
 *  - Every entry names a consumer. A setting nothing reads must not exist; the
 *    codebase already had two (two_factor_required, otp_expiry_seconds).
 *  - Defaults match the behaviour the application had before the setting
 *    existed, so introducing one changes nothing until an admin edits it.
 *  - Secrets never appear here. See SystemSettingsController for the env vars
 *    the page merely names.
 */
final class SettingRegistry
{
    /**
     * @var array<string, Setting>|null
     */
    private static ?array $settings = null;

    /**
     * @return array<string, Setting>
     */
    public static function all(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $settings = [
            // --- Referral -------------------------------------------------
            Setting::int(
                key: 'referral_overdue_days',
                group: SettingGroup::REFERRAL,
                default: 7,
                min: 1,
                max: 365,
                consumer: 'Dashboard\DashboardQueries::overdueDays',
                description: 'A referral becomes overdue after this many days without a response.',
            ),

            Setting::bool(
                key: 'feedback_enabled',
                group: SettingGroup::REFERRAL,
                default: true,
                consumer: 'Listeners\SendSurveyRequest::handle',
                description: 'Send a satisfaction survey when a referral is completed.',
            ),

            Setting::int(
                key: 'survey_invitation_expiry_days',
                group: SettingGroup::REFERRAL,
                default: 30,
                min: 1,
                max: 365,
                consumer: 'SurveyInvitationService::createInvitation',
                description: 'How long a client has to complete a survey. Applies to invitations issued after the change.',
            ),

            Setting::int(
                key: 'client_link_lifetime_days',
                group: SettingGroup::REFERRAL,
                default: 7,
                min: 1,
                max: 90,
                consumer: 'ReferralClientAccessService::issue',
                description: 'How long a client access link works. Applies to links issued after the change; existing links keep their original expiry.',
            ),

            // --- Access ---------------------------------------------------
            Setting::int(
                key: 'staff_invite_expiry_days',
                group: SettingGroup::ACCESS,
                default: 7,
                min: 1,
                max: 30,
                consumer: 'UserService::inviteUser',
                description: 'How long a staff invitation stays usable. Applies to invitations issued after the change.',
            ),

            // --- Security -------------------------------------------------
            Setting::int(
                key: 'otp_expiry_seconds',
                group: SettingGroup::SECURITY,
                default: 300,
                min: 60,
                max: 1800,
                consumer: 'OtpService::ttlMinutes',
                description: 'How long a one-time passcode remains valid.',
            ),

            Setting::int(
                key: 'otp_max_attempts',
                group: SettingGroup::SECURITY,
                default: 5,
                min: 1,
                max: 10,
                consumer: 'OtpService::maxAttempts',
                description: 'How many wrong codes are accepted before the passcode is discarded.',
            ),

            // --- Retention ------------------------------------------------
            // These are read by scheduled commands at run time, never bound
            // into config at boot: a command that captured them at container
            // start would keep enforcing the old number forever.
            Setting::int(
                key: 'trash_retention_days',
                group: SettingGroup::RETENTION,
                default: 90,
                min: 1,
                max: 3650,
                consumer: 'Console\Commands\PurgeTrashedCases::handle',
                description: 'Deleted cases are permanently removed after this many days.',
                envFallback: 'APP_TRASH_RETENTION_DAYS',
                confirmRequired: true,
            ),

            Setting::int(
                key: 'audit_retention_days',
                group: SettingGroup::RETENTION,
                default: 365,
                min: 30,
                max: 3650,
                consumer: 'Console\Commands\ArchiveAuditLogs::handle',
                description: 'Audit entries are archived, then pruned, after this many days. Pruning only removes entries already covered by a verified archive.',
                envFallback: 'AUDIT_RETENTION_DAYS',
                confirmRequired: true,
            ),

            Setting::int(
                key: 'email_log_retention_days',
                group: SettingGroup::RETENTION,
                default: 90,
                min: 7,
                max: 3650,
                consumer: 'Console\Commands\PruneEmailLogs::handle',
                description: 'Email delivery records are deleted after this many days.',
                confirmRequired: true,
            ),

            Setting::int(
                key: 'helpdesk_feedback_retention_days',
                group: SettingGroup::RETENTION,
                default: 365,
                min: 30,
                max: 3650,
                consumer: 'Console\Commands\CleanupHelpdeskArticleFeedback::handle',
                description: 'Help-article votes are deleted after this many days.',
                confirmRequired: true,
            ),

            Setting::int(
                key: 'report_retention_days',
                group: SettingGroup::RETENTION,
                default: 90,
                min: 7,
                max: 3650,
                consumer: 'Console\Commands\CleanupOrphanedFiles::handle',
                description: 'Generated report files are deleted after this many days. Previously this fell back to the trash-retention value because its config key did not exist.',
                confirmRequired: true,
            ),
        ];

        self::$settings = [];

        foreach ($settings as $setting) {
            self::$settings[$setting->key] = $setting;
        }

        return self::$settings;
    }

    public static function find(string $key): ?Setting
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Settings in a group, in declaration order.
     *
     * @return array<int, Setting>
     */
    public static function inGroup(string $group): array
    {
        return array_values(array_filter(
            self::all(),
            fn (Setting $setting) => $setting->group === $group
        ));
    }

    /**
     * Validation rules for a whole update request.
     *
     * @return array<string, array<int, string>>
     */
    public static function validationRules(): array
    {
        $rules = [];

        foreach (self::all() as $key => $setting) {
            $rules[$key] = $setting->rules();
        }

        return $rules;
    }

    /**
     * The current value of a setting: the stored row, else the declared
     * default.
     */
    public static function value(string $key): mixed
    {
        $setting = self::find($key);

        if ($setting === null) {
            return null;
        }

        return $setting->cast(SystemSetting::getValue($key, $setting->default));
    }

    /**
     * A positive integer setting.
     *
     * Every consumer of a day/attempt count wants the same guard: a stored 0
     * or null must never reach a cutoff calculation (a 0-day retention flips
     * the cutoff to "now" and deletes everything). Clamping here keeps that
     * rule in one place instead of repeating `max(1, (int) ...)` at nine call
     * sites.
     */
    public static function int(string $key, int $minimum = 1): int
    {
        $value = self::value($key);

        $minimum = $minimum > 0 ? $minimum : 1;

        return $value === null ? $minimum : max($minimum, (int) $value);
    }

    /**
     * Where a value currently comes from, for the page's provenance hint.
     *
     * "environment" means no row has been saved since the value was seeded, so
     * the env fallback is still what the application is using.
     */
    public static function source(string $key): string
    {
        return SystemSetting::query()->where('key', $key)->exists() ? 'database' : 'default';
    }
}
