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

    protected $fillable = ['request_id', 'label', 'sort_order'];

    protected $casts = ['sort_order' => 'integer', 'is_deleted' => 'boolean'];

    public function getAuditModuleName(): string
    {
        return 'referral_client_request_item';
    }

    public function request()
    {
        return $this->belongsTo(ReferralClientRequest::class, 'request_id');
    }
}
