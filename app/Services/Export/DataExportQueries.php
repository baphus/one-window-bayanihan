<?php

namespace App\Services\Export;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DataExportQueries
{
    private function isAdmin(?User $user): bool
    {
        return $user === null || $user->role === 'ADMIN';
    }

    /**
     * Get cases. ADMIN: all. CASE_MANAGER: own cases only (via user_id).
     */
    public function getCases(?User $user = null): Collection
    {
        $query = DB::table('cases')
            ->select([
                'id',
                'case_number',
                'client_type',
                'vulnerability_indicator',
                'nok_vulnerability_indicator',
                'tracker_number',
                'summary',
                'status',
                'closed_at',
                'consent_given_at',
                'user_id',
                'client_id',
                'category_id',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->where('user_id', $user->id);
        }

        return $query->get();
    }

    /**
     * Get clients. ADMIN: all. CASE_MANAGER: clients linked to their cases (via cases.client_id).
     */
    public function getClients(?User $user = null): Collection
    {
        $query = DB::table('clients')
            ->select([
                'id',
                'first_name',
                'last_name',
                'middle_name',
                'suffix',
                'date_of_birth',
                'sex',
                'email',
                'contact_number',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('id', function ($q) use ($user) {
                $q->select('client_id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false)
                    ->whereNotNull('client_id');
            });
        }

        return $query->get();
    }

    /**
     * Get referrals. ADMIN: all. CASE_MANAGER: referrals on their cases (via case_id → cases.user_id).
     */
    public function getReferrals(?User $user = null): Collection
    {
        $query = DB::table('referrals')
            ->select([
                'id',
                'required_services',
                'notes',
                'status',
                'decision',
                'decision_comment',
                'case_id',
                'agcy_id',
                'type',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('case_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false);
            });
        }

        return $query->get();
    }

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
     * Get milestones. ADMIN: all. CASE_MANAGER: via referrals → cases → user_id.
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
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('refr_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('referrals')
                    ->where('is_deleted', false)
                    ->whereIn('case_id', function ($q2) use ($user) {
                        $q2->select('id')
                            ->from('cases')
                            ->where('user_id', $user->id)
                            ->where('is_deleted', false);
                    });
            });
        }

        return $query->get();
    }

    /**
     * Get next-of-kin. ADMIN: all. CASE_MANAGER: via clients → cases → user_id.
     */
    public function getNextOfKins(?User $user = null): Collection
    {
        $query = DB::table('next_of_kin')
            ->select([
                'id',
                'client_id',
                'first_name',
                'middle_initial',
                'last_name',
                'is_primary',
                'relationship',
                'phone_number',
                'email',
                'full_address',
                'region',
                'province',
                'city_municipality',
                'barangay',
                'street',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('client_id', function ($q) use ($user) {
                $q->select('client_id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false)
                    ->whereNotNull('client_id');
            });
        }

        return $query->get();
    }

    /**
     * Get feedback. ADMIN: all. CASE_MANAGER: via case_id → cases → user_id.
     * Note: feedback table does not use SoftDeleteFlag — no is_deleted filter.
     */
    public function getFeedbacks(?User $user = null): Collection
    {
        $query = DB::table('feedback')
            ->select([
                'id',
                'case_id',
                'agency_id',
                'service_name',
                'overall_rating',
                'comments',
                'created_at',
                'updated_at',
            ]);

        if (! $this->isAdmin($user)) {
            $query->whereIn('case_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false);
            });
        }

        return $query->get();
    }

    /**
     * Get case documents. ADMIN: all. CASE_MANAGER: via case_id → cases → user_id.
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
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('case_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false);
            });
        }

        return $query->get();
    }

    /**
     * Get client addresses. ADMIN: all. CASE_MANAGER: via client_id → cases → user_id.
     */
    public function getClientAddresses(?User $user = null): Collection
    {
        $query = DB::table('client_addresses')
            ->select([
                'id',
                'client_id',
                'region',
                'province',
                'city_municipality',
                'barangay',
                'street',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('client_id', function ($q) use ($user) {
                $q->select('client_id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false)
                    ->whereNotNull('client_id');
            });
        }

        return $query->get();
    }

    /**
     * Get client employments. ADMIN: all. CASE_MANAGER: via client_id → cases → user_id.
     */
    public function getClientEmployments(?User $user = null): Collection
    {
        $query = DB::table('client_employments')
            ->select([
                'id',
                'client_id',
                'employer_name',
                'position',
                'last_position',
                'country',
                'last_country',
                'start_date',
                'end_date',
                'date_of_arrival',
                'created_at',
                'updated_at',
            ])
            ->where('is_deleted', false);

        if (! $this->isAdmin($user)) {
            $query->whereIn('client_id', function ($q) use ($user) {
                $q->select('client_id')
                    ->from('cases')
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false)
                    ->whereNotNull('client_id');
            });
        }

        return $query->get();
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
