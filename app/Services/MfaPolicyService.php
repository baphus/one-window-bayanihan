<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Settings\SettingRegistry;

/**
 * Resolves the persisted MFA enrolment policy.
 *
 * Administrator accounts are always required in every environment. Every other
 * role reads its default from system settings and an explicit per-user override
 * wins over that default. An unknown role is treated as required so a bad row
 * cannot silently exempt an account.
 */
class MfaPolicyService
{
    public const REQUIRED = 'required';

    public const OPTIONAL = 'optional';

    public const INHERIT = 'inherit';

    /** Role value => system setting key for that role's default. */
    public const ROLE_DEFAULT_KEYS = [
        UserRole::CASE_MANAGER->value => 'mfa_role_case_manager_required',
        UserRole::AGENCY->value => 'mfa_role_agency_required',
        UserRole::OFW->value => 'mfa_role_ofw_required',
    ];

    public function roleDefault(string $role): string
    {
        if ($role === UserRole::ADMIN->value) {
            return self::REQUIRED;
        }

        $key = self::ROLE_DEFAULT_KEYS[$role] ?? null;

        if ($key === null) {
            return self::REQUIRED;
        }

        return SettingRegistry::value($key) === false ? self::OPTIONAL : self::REQUIRED;
    }

    public function roleDefaultFrom(bool $required): string
    {
        return $required ? self::REQUIRED : self::OPTIONAL;
    }

    public function override(User $user): ?string
    {
        $override = $user->mfa_requirement_override;

        return in_array($override, [self::REQUIRED, self::OPTIONAL], true) ? $override : null;
    }

    public function effective(User $user): string
    {
        return $this->effectiveFor($user->role, $this->override($user));
    }

    public function effectiveFor(?string $role, ?string $override): string
    {
        if ($role === UserRole::ADMIN->value) {
            return self::REQUIRED;
        }

        if (in_array($override, [self::REQUIRED, self::OPTIONAL], true)) {
            return $override;
        }

        return $this->roleDefault((string) $role);
    }

    public function requiresEnrollment(User $user): bool
    {
        return $this->effective($user) === self::REQUIRED;
    }

    /**
     * @return array<string, string>
     */
    public function roleDefaults(): array
    {
        $defaults = [UserRole::ADMIN->value => self::REQUIRED];

        foreach (self::ROLE_DEFAULT_KEYS as $role => $key) {
            $defaults[$role] = $this->roleDefault($role);
        }

        return $defaults;
    }
}
