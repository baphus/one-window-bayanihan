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

    protected $fillable = ['message_id', 'file_name', 'file_path', 'file_type', 'size'];

    protected $casts = ['size' => 'integer', 'is_deleted' => 'boolean'];

    public function getAuditModuleName(): string
    {
        return 'referral_client_message_attachment';
    }

    public function message()
    {
        return $this->belongsTo(ReferralClientMessage::class, 'message_id');
    }
}
