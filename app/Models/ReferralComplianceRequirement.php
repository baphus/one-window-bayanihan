<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralComplianceRequirement extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'referral_id', 'fulfilled_by'];

    public function getAuditModuleName(): string
    {
        return 'referral_compliance_requirement';
    }

    protected $fillable = [
        'referral_id',
        'service_name',
        'requirement_name',
        'status',
        'fulfilled_by',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'is_deleted' => 'boolean',
    ];

    public function referral()
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function fulfilledBy()
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }
}
