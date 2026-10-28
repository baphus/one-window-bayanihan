<?php

namespace App\Services;

use App\Enums\AuditModule;
use App\Enums\UserRole;

final class ActivityVisibility
{
    public static function modulesFor(string $role): array
    {
        if ($role === UserRole::ADMIN->value) {
            return self::staffUnion('modules');
        }

        return array_values(config("audit_visibility.{$role}.modules", []));
    }

    public static function actionsFor(string $role): array
    {
        if ($role === UserRole::ADMIN->value) {
            return self::staffUnion('actions');
        }

        return array_values(config("audit_visibility.{$role}.actions", []));
    }

    public static function isVisibleToStaff(string $module, string $action): bool
    {
        $canonicalModule = AuditModule::tryFromLegacy($module)?->value;
        $canonicalAction = strtoupper($action);

        if ($canonicalModule === null) {
            return false;
        }

        foreach ([UserRole::CASE_MANAGER->value, UserRole::AGENCY->value] as $role) {
            if (in_array($canonicalModule, self::modulesFor($role), true)
                && in_array($canonicalAction, self::actionsFor($role), true)) {
                return true;
            }
        }

        return false;
    }

    private static function staffUnion(string $key): array
    {
        $values = array_values(array_unique(array_merge(
            (array) config('audit_visibility.CASE_MANAGER.'.$key, []),
            (array) config('audit_visibility.AGENCY.'.$key, []),
        )));

        sort($values);

        return $values;
    }
}
