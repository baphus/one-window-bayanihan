<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'case_id'];

    public function getAuditModuleName(): string
    {
        return 'referral';
    }

    protected $fillable = [
        'required_services',
        'notes',
        'status',
        'decision',
        'decision_comment',
        'case_id',
        'agcy_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agcy_id');
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class, 'refr_id');
    }

    public function attachments()
    {
        return $this->hasMany(ReferralAttachment::class, 'referral_id');
    }

    public function complianceRequirements()
    {
        return $this->hasMany(ReferralComplianceRequirement::class, 'referral_id');
    }

    public function comments()
    {
        return $this->hasMany(ReferralComment::class, 'refr_id');
    }
}
