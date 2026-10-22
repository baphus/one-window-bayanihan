<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends Model
{
    use UsesUuid;

    /**
     * Lifecycle
     * --------
     * The row is pre-created as `pending` by the controller, and
     * GenerateSystemReport flips it to `completed` with the object path (or
     * `failed`, in which case the object may or may not exist — the write to
     * object storage happens before the status update).
     *
     * Nothing here has a soft delete, so the nightly storage sweep retires the
     * bytes by age and flips the row to `expired` with a null path. The row is
     * deliberately kept rather than deleted: it is the only record of who asked
     * for an admin workbook, when, and of what size it was.
     */
    public const STATUS_EXPIRED = 'expired';

    /**
     * Directory prefix the job writes every object under.
     */
    public const STORAGE_PREFIX = 'generated-documents/';

    protected $fillable = [
        'user_id',
        'case_id',
        'type',
        'filename',
        'path',
        'file_size',
        'mime_type',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * The bytes behind this row were retired by the storage sweep.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * The key this document occupies in object storage.
     *
     * Preferred over the stored path because a `failed` run can leave an
     * object behind without ever writing `path` — GenerateSystemReport stores
     * the file before it records the path — and the key is deterministic from
     * the row id and filename, so it can still be recovered.
     */
    public function storagePath(): string
    {
        return $this->path ?: self::STORAGE_PREFIX.$this->id.'/'.$this->filename;
    }
}
