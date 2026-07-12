<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralComplianceRequirement extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'referral_id'];

    public function getAuditModuleName(): string
    {
        return 'referral_compliance';
    }

    protected $fillable = [
        'referral_id',
        'service_name',
        'requirement_name',
        'status',
        'fulfilled_by',
        'completed_at',
        'remark',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'is_deleted' => 'boolean',
    ];

    protected $appends = [
        'fulfilled_by_name',
    ];

    public function getFulfilledByNameAttribute(): ?string
    {
        return $this->relationLoaded('fulfilledBy') && $this->fulfilledBy
            ? $this->fulfilledBy->name
            : null;
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function fulfilledBy()
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }
}
