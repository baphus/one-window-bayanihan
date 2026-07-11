<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class AuditChainCheckpoint extends Model
{
    use UsesUuid;

    public $timestamps = false;

    protected $fillable = [
        'anchor_hash',
        'pruned_through',
        'bundle_manifest_path',
        'created_at',
    ];

    protected $casts = [
        'pruned_through' => 'datetime',
        'created_at' => 'datetime',
    ];
}
