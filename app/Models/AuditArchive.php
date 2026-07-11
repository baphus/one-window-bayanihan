<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class AuditArchive extends Model
{
    use UsesUuid;

    protected $fillable = [
        'period',
        'path',
        'checksum',
        'row_count',
        'first_entry_at',
        'last_entry_at',
        'finalized_at',
    ];

    protected $casts = [
        'row_count' => 'integer',
        'first_entry_at' => 'datetime',
        'last_entry_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }
}
