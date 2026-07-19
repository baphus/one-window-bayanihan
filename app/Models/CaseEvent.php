<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only client-facing case history event.
 *
 * Rows are never updated or deleted; corrections are recorded as new events.
 * Every field must be safe to show to the case's client — actors are stored
 * as actor_type (agency | case_manager | system), never as names or user IDs.
 */
class CaseEvent extends Model
{
    use UsesUuid;

    public const TYPE_CASE_OPENED = 'case_opened';

    public const TYPE_REFERRAL_SENT = 'referral_sent';

    public const TYPE_REFERRAL_STATUS_CHANGED = 'referral_status_changed';

    public const TYPE_MILESTONE_ADDED = 'milestone_added';

    public const TYPE_CASE_CLOSED = 'case_closed';

    public const TYPE_CASE_REOPENED = 'case_reopened';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        // The event log is append-only by contract — corrections are new
        // events, never edits. Enforce it, don't just rely on convention.
        static::updating(function (): void {
            throw new \LogicException('Case events are append-only and cannot be updated.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Case events are append-only and cannot be deleted.');
        });
    }

    protected $fillable = [
        'case_id',
        'referral_id',
        'type',
        'title',
        'description',
        'meta',
        'actor_type',
        'occurred_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }
}
