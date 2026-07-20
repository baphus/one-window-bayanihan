<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class UserInvite extends Model
{
    use UsesUuid;

    protected $fillable = [
        'email',
        'role',
        'agcy_id',
        'token',
        'expires_at',
        'created_by',
        'consumed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->whereNull('consumed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agcy_id');
    }
}
