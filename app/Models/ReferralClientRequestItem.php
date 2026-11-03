<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralClientRequestItem extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'request_id'];

    protected $fillable = ['request_id', 'source_requirement_id', 'label', 'description', 'canonical_key', 'sort_order'];

    public function sourceRequirement()
    {
        return $this->belongsTo(ReferralServiceRequirement::class, 'source_requirement_id');
    }

    public function sourceRequirements()
    {
        return $this->belongsToMany(ReferralServiceRequirement::class, 'referral_client_request_item_services', 'request_item_id', 'service_requirement_id');
    }

    protected $casts = ['sort_order' => 'integer', 'is_deleted' => 'boolean'];

    public function attachments()
    {
        return $this->hasMany(ReferralClientMessageAttachment::class, 'request_item_id');
    }

    public function getAuditModuleName(): string
    {
        return 'referral_client_request_item';
    }

    public function request()
    {
        return $this->belongsTo(ReferralClientRequest::class, 'request_id');
    }
}
