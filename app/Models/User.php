<?php

namespace App\Models;

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

    public static array $auditExclude = ['password', 'remember_token', 'id', 'created_at', 'updated_at', 'email_verified_at', 'mfa_secret', 'mfa_recovery_codes', 'mfa_enabled_at'];

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
        'is_active',
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
        'onboarding_completed_at',
        'onboarding_step',
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
            'mfa_recovery_codes' => 'array',
            'emergency_contact' => 'array',
            'notifications_config' => 'array',
            'mfa_enabled_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'profile_completed_at' => 'datetime',
        ];
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agcy_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isCaseManager(): bool
    {
        return $this->role === 'CASE_MANAGER';
    }

    public function isAgency(): bool
    {
        return $this->role === 'AGENCY';
    }
}
