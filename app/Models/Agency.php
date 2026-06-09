<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by'];

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
        'logo_url',
        'location_query',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

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
