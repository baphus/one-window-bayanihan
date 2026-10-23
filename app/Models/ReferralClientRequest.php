<?php

namespace App\Models;

use App\Models\Concerns\CascadeSoftDeletes;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralClientRequest extends Model
{
    use CascadeSoftDeletes, HasFactory, SoftDeleteFlag, UsesUuid;

    /**
     * Relationships to cascade on soft-delete and restore.
     *
     * A client request reached from a referral is only ever deleted through its
     * parent: trashing the case cascades referrals, and the messages hanging off
     * a request carry the client's uploaded documents. Without this the chain
     * stopped one level short and left those messages live against a trashed
     * referral — and their attachment rows, which nothing else marks trashed or
     * empties from the bucket, with it.
     */
    protected array $cascadeSoftDeletes = ['messages'];

    public const TYPE_DOCUMENT_REQUEST = 'DOCUMENT_REQUEST';

    public const TYPE_QUESTION = 'QUESTION';

    public const TYPE_INFORMATION_UPDATE = 'INFORMATION_UPDATE';

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_CLIENT_RESPONDED = 'CLIENT_RESPONDED';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'referral_id', 'creator_user_id', 'instructions'];

    protected $fillable = ['referral_id', 'creator_user_id', 'type', 'title', 'instructions', 'status', 'due_at'];

    protected $casts = ['instructions' => 'encrypted', 'due_at' => 'datetime', 'is_deleted' => 'boolean'];

    public function getAuditModuleName(): string
    {
        return 'referral_client_request';
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function items()
    {
        return $this->hasMany(ReferralClientRequestItem::class, 'request_id')->orderBy('sort_order');
    }

    public function messages()
    {
        return $this->hasMany(ReferralClientMessage::class, 'request_id');
    }

    public function accessLinks()
    {
        return $this->hasMany(ReferralClientAccessLink::class, 'request_id');
    }

    public function milestone()
    {
        return $this->hasOne(Milestone::class, 'client_request_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_CLIENT_RESPONDED]);
    }
}
