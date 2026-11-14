<?php

namespace App\Services\Export\Queries;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Export\Queries\Concerns\ScopesForExportUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CaseProgressQueries
{
    use ScopesForExportUser;

    /**
     * Get milestones. ADMIN/CASE_MANAGER: all. AGENCY: own referrals only.
     */
    public function getMilestones(?User $user = null): Collection
    {
        $query = DB::table('milestones')
            ->select([
                'id',
                'title',
                'description',
                'refr_id',
                'user_id',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->whereNull('deleted_at');

        if (! $this->isAdmin($user)) {
            if ($user->role !== UserRole::AGENCY->value || ! $user->agcy_id) {
                return collect();
            }
            $query->whereIn('refr_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('referrals')
                    ->where('agcy_id', $user->agcy_id)
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at');
            });
        }

        return $query->get();
    }

    /**
     * Get case documents. ADMIN/CASE_MANAGER: all. AGENCY: own referrals only.
     */
    public function getCaseDocuments(?User $user = null): Collection
    {
        $query = DB::table('case_documents')
            ->select([
                'id',
                'file_name',
                'file_path',
                'file_type',
                'case_id',
                'user_id',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false)
            ->whereNull('deleted_at');

        if (! $this->isAdmin($user)) {
            if ($user->role !== UserRole::AGENCY->value || ! $user->agcy_id) {
                return collect();
            }
            $query->whereIn('case_id', function ($q) use ($user) {
                $q->select('case_id')
                    ->from('referrals')
                    ->where('agcy_id', $user->agcy_id)
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at');
            });
        }

        return $query->get();
    }
}
