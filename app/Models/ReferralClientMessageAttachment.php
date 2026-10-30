<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralClientMessageAttachment extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'message_id', 'file_name', 'file_path', 'file_type', 'size'];

    protected $fillable = ['message_id', 'request_item_id', 'file_name', 'file_path', 'file_type', 'size', 'review_status', 'visibility', 'sha256'];

    protected $casts = ['size' => 'integer', 'is_deleted' => 'boolean'];

    public function agencies()
    {
        return $this->belongsToMany(Agency::class, 'referral_client_attachment_agencies', 'attachment_id', 'agency_id')->withTimestamps();
    }

    public function requestItem()
    {
        return $this->belongsTo(ReferralClientRequestItem::class, 'request_item_id');
    }

    public function getAuditModuleName(): string
    {
        return 'referral_client_message_attachment';
    }

    public function message()
    {
        return $this->belongsTo(ReferralClientMessage::class, 'message_id');
    }
}
