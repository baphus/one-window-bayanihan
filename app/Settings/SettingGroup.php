<?php

namespace App\Settings;

/**
 * UI groups on the admin settings page.
 *
 * Order here is the order they render. Groups with nothing in them are omitted
 * by the controller, so removing the last setting of a group removes the group.
 */
final class SettingGroup
{
    public const GENERAL = 'general';

    public const REFERRAL = 'referral';

    public const ACCESS = 'access';

    public const RETENTION = 'retention';

    public const SECURITY = 'security';

    public const ORDER = [
        self::GENERAL,
        self::REFERRAL,
        self::ACCESS,
        self::RETENTION,
        self::SECURITY,
    ];

    public static function label(string $group): string
    {
        return match ($group) {
            self::GENERAL => 'General',
            self::REFERRAL => 'Referral',
            self::ACCESS => 'Access',
            self::RETENTION => 'Retention',
            self::SECURITY => 'Security',
            default => ucfirst($group),
        };
    }

    public static function description(string $group): string
    {
        return match ($group) {
            self::GENERAL => 'System-wide contact details.',
            self::REFERRAL => 'How referrals behave once they leave a case manager.',
            self::ACCESS => 'How long invitations and client links stay valid.',
            self::RETENTION => 'How long records are kept before permanent deletion. Values are read when the scheduled cleanup runs.',
            self::SECURITY => 'Authentication policy. Some values are fixed by the deployment and cannot be changed here.',
            default => '',
        };
    }
}
