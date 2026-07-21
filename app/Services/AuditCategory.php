<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;

/**
 * Single source of truth for audit event categories (see design D5).
 * Stamped at write time in AuditLog::saving and reused by the
 * audit:backfill-categories command.
 *
 * Module identity, aliases and the module->category mapping now live on the
 * App\Enums\AuditModule enum; action-based security promotion lives on
 * App\Enums\AuditAction. This class composes those into the runtime and
 * backfill classification rules.
 */
final class AuditCategory
{
    public const SECURITY = 'security';

    public const DATA = 'data';

    public const ADMIN = 'admin';

    public const SYSTEM = 'system';

    public const ALL = [self::SECURITY, self::DATA, self::ADMIN, self::SYSTEM];

    public static function for(string $module, string $action, ?string $userId): string
    {
        if (self::isSecurity($module, $action)) {
            return self::SECURITY;
        }

        // Unattributed writes from the console are automated/maintenance
        // activity (seeders, scheduled jobs), whatever entity they touch.
        if ($userId === null && app()->runningInConsole()) {
            return self::SYSTEM;
        }

        return self::moduleCategory($module);
    }

    /**
     * Backfill variant: historical rows carry no runtime context, so
     * unattributed non-security rows are treated as system activity.
     */
    public static function forBackfill(string $module, string $action, ?string $userId): string
    {
        if (self::isSecurity($module, $action)) {
            return self::SECURITY;
        }

        if ($userId === null) {
            return self::SYSTEM;
        }

        return self::moduleCategory($module);
    }

    /** Security when the module is a security surface OR the action is a security event. */
    private static function isSecurity(string $module, string $action): bool
    {
        if (AuditModule::tryFromLegacy($module)?->isSecurity() === true) {
            return true;
        }

        return AuditAction::tryFrom(strtoupper($action))?->isSecurityEvent() === true;
    }

    /** Module's intrinsic category; unknown modules stay visible in the default view (data). */
    private static function moduleCategory(string $module): string
    {
        return AuditModule::tryFromLegacy($module)?->category() ?? self::DATA;
    }
}
