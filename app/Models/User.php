<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasAvatar;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasAvatar, HasFactory, MustVerifyEmailTrait, Notifiable, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = [
        'password', 'remember_token', 'id', 'created_at', 'updated_at',
        'email_verified_at', 'mfa_secret', 'mfa_recovery_codes', 'mfa_enabled_at',
        'mfa_last_totp_counter',
        'email', 'contact_number',
        'onboarding_step', 'seen_page_guides', 'checklist_progress',
    ];

    public function getAuditModuleName(): string
    {
        return 'user';
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'agcy_id',
        'client_id',
        'is_active',
        'email_verified_at',
        'contact_number',
        'avatar_url',
        'position',
        'department',
        'office_location',
        'bio',
        'emergency_contact',
        'notifications_config',
        'timezone',
        'mfa_enabled_at',
        'mfa_last_totp_counter',
        'onboarding_completed_at',
        'onboarding_step',
        'seen_page_guides',
        'checklist_progress',
        'profile_completed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'array',
            'emergency_contact' => 'array',
            'notifications_config' => 'array',
            'mfa_enabled_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'seen_page_guides' => 'array',
            'checklist_progress' => 'array',
            'profile_completed_at' => 'datetime',
        ];
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agcy_id');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN);
    }

    public function isCaseManager(): bool
    {
        return $this->hasRole(UserRole::CASE_MANAGER);
    }

    public function isAgency(): bool
    {
        return $this->hasRole(UserRole::AGENCY);
    }

    public function isOfw(): bool
    {
        return $this->hasRole(UserRole::OFW);
    }

    public function isInMfaEnforcedRole(): bool
    {
        if (function_exists('app')) {
            try {
                if (app()->isProduction()) {
                    return $this->role !== null && $this->role !== '';
                }
            } catch (\Throwable $e) {
                // Fall through to config-driven behaviour when app context is unavailable.
            }
        }

        return in_array($this->role, config('mfa.enrollment_enforced_roles', []), true);
    }

    /**
     * The role as an enum, or null when the column holds something this build
     * does not know. The column is not cast: users.role is compared as a plain
     * string in hundreds of places, and casting would change what serialises
     * everywhere at once. Use this at boundaries that need to exhaustively
     * match a role.
     */
    public function roleEnum(): ?UserRole
    {
        return is_string($this->role) ? UserRole::tryFrom($this->role) : null;
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->roleEnum() === $role;
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
