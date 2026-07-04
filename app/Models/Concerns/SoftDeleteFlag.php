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
                $model->saveQuietly();
            }
        });

        static::restoring(function ($model) {
            $model->is_deleted = false;
        });
    }
}
