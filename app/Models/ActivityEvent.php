<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityEvent extends Model
{
    use HasFactory, UsesUuid;

    protected $fillable = [
        'occurred_at',
        'actor_id',
        'actor_name',
        'actor_role',
        'module',
        'action',
        'description',
        'entity_id',
        'audit_log_id',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * The audit row this mirror entry was written from. Used to render the
     * human sentence on the activity page; null once the row is pruned.
     */
    public function auditLog()
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id');
    }
}
