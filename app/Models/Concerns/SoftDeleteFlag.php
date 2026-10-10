<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

trait SoftDeleteFlag
{
    use SoftDeletes;

    /**
     * Soft-delete in one write instead of two.
     *
     * The framework's runSoftDelete() issues a raw UPDATE of only
     * deleted_at/updated_at, so the flag columns used to need a second write —
     * the old deleting hook did that with saveQuietly(), a save followed by
     * the framework's update. Staging the flags here and letting saveQuietly()
     * flush everything dirty (flags, deleted_at, and any attributes the caller
     * staged before delete()) in a single UPDATE keeps behavior identical with
     * half the queries. Force deletes bypass this method entirely
     * (performDeleteOnModel() branches before calling it).
     */
    protected function runSoftDelete(): void
    {
        if (! $this->is_deleted) {
            $this->is_deleted = true;

            // Auto-set deleted_by from the authenticated user when available.
            // CLI/queue jobs without auth context leave deleted_by as null.
            if (! $this->deleted_by && auth()->check()) {
                $this->deleted_by = auth()->id();
            }
        }

        $this->{$this->getDeletedAtColumn()} = $this->freshTimestamp();

        $this->saveQuietly();

        $this->fireModelEvent('trashed', false);
    }

    protected static function bootSoftDeleteFlag(): void
    {
        static::restoring(function ($model) {
            $model->is_deleted = false;
            $model->deleted_by = null;
        });
    }
}
