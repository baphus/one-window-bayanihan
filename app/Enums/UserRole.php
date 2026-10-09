<?php

namespace App\Enums;

/**
 * The four roles a user can hold, and the single source of truth for the
 * strings written to users.role.
 *
 * The column itself is a plain string(50) and is deliberately NOT cast to this
 * enum on the model: users.role is read in hundreds of places as a string and
 * casting it would change what serialises everywhere at once. This enum exists
 * so that the *sets* of roles — route middleware, MFA enforcement, the
 * inception of a new role — have one definition instead of the same four
 * literals retyped in 166 places across app/, routes/, config/ and
 * resources/js/.
 *
 * Use ::from() at boundaries that accept external input, and ->value when
 * writing to the column or comparing against a string.
 */
enum UserRole: string
{
    case CASE_MANAGER = 'CASE_MANAGER';
    case AGENCY = 'AGENCY';
    case ADMIN = 'ADMIN';
    case OFW = 'OFW';

    /** All backing values — convenience for validation and route definitions. */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }

    /**
     * Roles that read and write other people's OFW personal data, and so must
     * hold MFA. OFW citizens hold their own data only, and in non-production
     * environments MFA is config-driven for them.
     */
    public static function staffValues(): array
    {
        return [self::CASE_MANAGER->value, self::AGENCY->value, self::ADMIN->value];
    }

    public function isStaff(): bool
    {
        return in_array($this, [self::CASE_MANAGER, self::AGENCY, self::ADMIN], true);
    }

    /** Human label for UI lists and audit descriptions. */
    public function label(): string
    {
        return match ($this) {
            self::CASE_MANAGER => 'Case Manager',
            self::AGENCY => 'Agency Focal',
            self::ADMIN => 'Administrator',
            self::OFW => 'Overseas Filipino Worker',
        };
    }
}
