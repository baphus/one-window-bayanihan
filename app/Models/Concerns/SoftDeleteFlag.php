<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

trait SoftDeleteFlag
{
    use SoftDeletes;

    protected static function bootSoftDeleteFlag(): void
    {
        static::deleting(function ($model) {
            if ($model->isForceDeleting()) {
                return;
            }

            if (! $model->is_deleted) {
                $model->is_deleted = true;

                // Auto-set deleted_by from the authenticated user when available.
                // CLI/queue jobs without auth context leave deleted_by as null.
                if (! $model->deleted_by && auth()->check()) {
                    $model->deleted_by = auth()->id();
                }

                $model->saveQuietly();
            }
        });

        static::restoring(function ($model) {
            $model->is_deleted = false;
            $model->deleted_by = null;
        });
    }
}
