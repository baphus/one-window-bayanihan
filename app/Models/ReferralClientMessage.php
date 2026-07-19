<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralClientMessage extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public const SENDER_AGENCY_USER = 'AGENCY_USER';

    public const SENDER_CLIENT_ACCESS = 'CLIENT_ACCESS';

    public const KIND_MESSAGE = 'MESSAGE';

    public const KIND_SYSTEM = 'SYSTEM';

    public const KIND_REVISION = 'REVISION';

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'request_id', 'user_id', 'access_link_id', 'body'];

    protected $fillable = ['request_id', 'body', 'sender_kind', 'user_id', 'access_link_id', 'kind'];

    protected $casts = ['body' => 'encrypted', 'is_deleted' => 'boolean'];

    public function getAuditModuleName(): string
    {
        return 'referral_client_message';
    }

    public function request()
    {
        return $this->belongsTo(ReferralClientRequest::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accessLink()
    {
        return $this->belongsTo(ReferralClientAccessLink::class, 'access_link_id');
    }
}
