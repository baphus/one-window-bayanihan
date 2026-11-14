<?php

namespace App\Models;

use App\Casts\EncryptedDate;
use App\Enums\UserRole;
use App\Models\Concerns\HasAvatar;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use App\Services\MfaPolicyService;
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
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'date_of_birth',
        'sex',
        'email',
        'password',
        'role',
        'agcy_id',
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
        'mfa_requirement_override',
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
            'date_of_birth' => EncryptedDate::class,
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

    protected static function booted(): void
    {
        // An explicitly assigned display name wins (legacy rows and fixtures);
        // otherwise `name` is derived from the parts here instead of at every
        // call site.
        static::saving(function (self $user): void {
            if ($user->isDirty('name') && filled($user->name)) {
                return;
            }

            $composed = self::composeName(
                $user->first_name,
                $user->middle_name,
                $user->last_name,
                $user->suffix,
            );

            if ($composed !== '') {
                $user->name = $composed;
            }
        });
    }

    /**
     * Legacy rows have only `name`; once parts exist they are the source.
     */
    public static function composeName(?string $first, ?string $middle = null, ?string $last = null, ?string $suffix = null): string
    {
        $given = trim(($first ?? '').' '.($middle ?? ''));
        $base = trim($given.' '.($last ?? ''));
        $suffix = trim((string) $suffix);

        return $suffix === '' ? $base : $base.', '.$suffix;
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

    public function hasCompleteOfwIdentity(): bool
    {
        return filled($this->first_name) && filled($this->last_name)
            && $this->date_of_birth !== null && filled($this->sex);
    }

    public function isInMfaEnforcedRole(): bool
    {
        return app(MfaPolicyService::class)->requiresEnrollment($this);
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

    public function ownedCases()
    {
        return $this->hasMany(CaseFile::class, 'ofw_user_id');
    }
}
