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
}
