<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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
    /**
     * Half-width, in seconds, of the window around the parent's own deleted_at
     * stamp inside which a child's deleted_at counts as "written by this
     * cascade" and is therefore eligible for cascade restore.
     *
     * A cascade stamps every child before the parent is stamped (the deleting
     * hook runs before the parent's own soft delete), so a cascade child can
     * never sit meaningfully away from the parent stamp: the whole cascade is
     * one round of single-row updates. The window absorbs the two sources of
     * drift — PostgreSQL stores these columns as `timestamp(0)`, so a
     * microsecond-precision in-memory value can round up to the next second on
     * the way in or out, and a parent with a large child set takes a moment to
     * finish. Independently deleted children are identified by being far away
     * from this window (minutes, days, weeks), not by seconds.
     */
    protected const CASCADE_DELETED_AT_TOLERANCE_SECONDS = 2;

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

            // Soft-delete cascade. Only live children are stamped: a child that
            // was already trashed keeps its own deleted_at/deleted_by, which is
            // what lets the restoring hook tell the two apart later.
            foreach ($model->getCascadeSoftDeleteRelations() as $relation) {
                $model->{$relation}()
                    ->get()
                    ->reject(fn ($child) => $child->trashed())
                    ->each(fn ($child) => $child->delete());
            }
        });

        static::restoring(function ($model) {
            // SoftDeletes::restore() fires `restoring` before it nulls the
            // column, so this is still the parent's original deletion stamp —
            // the value the cascade children were written against.
            $cascadeDeletedAt = $model->{$model->getDeletedAtColumn()};

            if ($cascadeDeletedAt === null) {
                return;
            }

            $window = self::cascadeDeletedAtWindow($cascadeDeletedAt);

            foreach ($model->getCascadeSoftDeleteRelations() as $relation) {
                $query = $model->{$relation}()->onlyTrashed();

                $query->whereBetween($query->getModel()->getDeletedAtColumn(), $window)
                    ->get()
                    ->each(fn ($child) => $child->restore());
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

    /**
     * Inclusive [from, to] bounds used to recognise the children this cascade
     * soft-deleted, derived from the parent's own deletion stamp.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected static function cascadeDeletedAtWindow(mixed $deletedAt): array
    {
        $stamp = $deletedAt instanceof CarbonInterface
            ? Carbon::instance($deletedAt)
            : Carbon::parse($deletedAt);

        return [
            $stamp->copy()->subSeconds(self::CASCADE_DELETED_AT_TOLERANCE_SECONDS),
            $stamp->copy()->addSeconds(self::CASCADE_DELETED_AT_TOLERANCE_SECONDS),
        ];
    }
}
