<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralClientAccessLink extends Model
{
    use HasFactory, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'request_id', 'token_hash', 'recipient_snapshot'];

    protected $fillable = ['request_id', 'token_hash', 'expires_at', 'revoked_at', 'revoked_by', 'issued_by', 'recipient_snapshot', 'first_used_at', 'last_used_at', 'use_count'];

    protected $hidden = ['token_hash', 'recipient_snapshot'];

    protected $casts = ['recipient_snapshot' => 'encrypted:array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'first_used_at' => 'datetime', 'last_used_at' => 'datetime', 'use_count' => 'integer'];

    public function getAuditModuleName(): string
    {
        return 'referral_client_access_link';
    }

    public function request()
    {
        return $this->belongsTo(ReferralClientRequest::class, 'request_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function messages()
    {
        return $this->hasMany(ReferralClientMessage::class, 'access_link_id');
    }

    public function scopeUsable($query)
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
