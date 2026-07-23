<?php

namespace App\Models\Concerns;

/**
 * Cascade soft-delete and restore to child relationships.
 *
 * Models using this trait must define a `$cascadeSoftDeletes` property
 * listing the relationship method names to cascade to. Each relationship
 * must point to a model that also uses SoftDeleteFlag.
 *
 * Example:
 *   protected array $cascadeSoftDeletes = ['referrals', 'documents'];
 */
trait CascadeSoftDeletes
{
    protected static function bootCascadeSoftDeletes(): void
    {
        static::deleting(function ($model) {
            if ($model->isForceDeleting()) {
                // Force-delete cascades are handled by the purge command
                foreach ($model->getCascadeSoftDeleteRelations() as $relation) {
                    $model->{$relation}()->withTrashed()->get()->each(function ($child) {
                        $child->forceDelete();
                    });
                }

                return;
            }

            // Soft-delete cascade
            foreach ($model->getCascadeSoftDeleteRelations() as $relation) {
                $model->{$relation}()->get()->each(function ($child) {
                    $child->delete();
                });
            }
        });

        static::restoring(function ($model) {
            foreach ($model->getCascadeSoftDeleteRelations() as $relation) {
                $model->{$relation}()->onlyTrashed()->get()->each(function ($child) {
                    $child->restore();
                });
            }
        });
    }

    /**
     * Get the relationships to cascade soft-deletes to.
     */
    public function getCascadeSoftDeleteRelations(): array
    {
        return $this->cascadeSoftDeletes ?? [];
    }
}
