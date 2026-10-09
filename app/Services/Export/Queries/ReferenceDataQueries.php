<?php

namespace App\Services\Export\Queries;

use App\Models\User;
use App\Services\Export\Queries\Concerns\ScopesForExportUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReferenceDataQueries
{
    use ScopesForExportUser;

    /**
     * Get users. ADMIN only — returns empty collection for any other role.
     */
    public function getUsers(?User $user = null): Collection
    {
        if (! $this->isAdmin($user)) {
            return collect();
        }

        return DB::table('users')
            ->select([
                'id',
                'name',
                'email',
                'role',
                'agcy_id',
                'is_active',
                'contact_number',
                'position',
                'department',
                'office_location',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }

    /**
     * Get agencies. All roles receive all records (reference data).
     */
    public function getAgencies(): Collection
    {
        return DB::table('agencies')
            ->select([
                'id',
                'name',
                'short',
                'slug',
                'description',
                'contact_info',
                'is_active',
                'is_default',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }

    /**
     * Get services. All roles receive all records (reference data).
     */
    public function getServices(): Collection
    {
        return DB::table('services')
            ->select([
                'id',
                'name',
                'description',
                'processing_days',
                'agcy_id',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }

    /**
     * Get case categories. All roles receive all records (reference data).
     */
    public function getCaseCategories(): Collection
    {
        return DB::table('case_categories')
            ->select([
                'id',
                'name',
                'description',
                'color',
                'sort_order',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }

    /**
     * Get case statuses. All roles receive all records (reference data).
     */
    public function getCaseStatuses(): Collection
    {
        return DB::table('case_statuses')
            ->select([
                'id',
                'name',
                'slug',
                'type',
                'color',
                'sort_order',
                'is_system',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->get();
    }
}
