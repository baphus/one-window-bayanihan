<?php

namespace App\Models;

use App\Models\Concerns\HasAvatar;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    use HasAvatar, HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by'];

    protected static function booted(): void
    {
        static::deleting(function (Agency $agency) {
            if ($agency->is_default) {
                throw new \LogicException('Cannot delete the default agency.');
            }
        });
    }

    public function scopeDefault($query): void
    {
        $query->where('is_default', true);
    }

    public function isDeletable(): bool
    {
        return ! $this->is_default;
    }

    public function getAuditModuleName(): string
    {
        return 'agency';
    }

    protected $fillable = [
        'name',
        'short',
        'slug',
        'description',
        'contact_info',
        'map_link',
        'latitude',
        'longitude',
        'logo_url',
        'location_query',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function generateMapLink(): ?string
    {
        if ($this->latitude && $this->longitude) {
            return sprintf('https://www.google.com/maps?q=%s,%s', $this->latitude, $this->longitude);
        }

        return null;
    }

    public function users()
    {
        return $this->hasMany(User::class, 'agcy_id');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'agcy_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'agcy_id');
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class, 'agency_id');
    }
}
