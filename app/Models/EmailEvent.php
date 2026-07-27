<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single delivery event reported by the mail provider.
 *
 * Rows are append-only: they record what the provider said and when, and are
 * never rewritten. The current delivery state derived from them lives on
 * EmailLog::$status.
 */
class EmailEvent extends Model
{
    use UsesUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }
}
