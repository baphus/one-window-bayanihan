<?php

namespace App\Services\Export\Queries;

use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\NextOfKin;
use App\Models\User;
use App\Services\Export\Queries\Concerns\ScopesForExportUser;
use App\Services\PhilippineAddressService;
use Illuminate\Support\Collection;

class ClientDetailQueries
{
    use ScopesForExportUser;

    /**
     * Get next-of-kin. ADMIN/CASE_MANAGER: all. AGENCY: own referrals only.
     */
    public function getNextOfKins(?User $user = null): Collection
    {
        $query = NextOfKin::query()
            ->select([
                'id',
                'client_id',
                'first_name',
                'middle_name',
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

        $addressResolver = app(PhilippineAddressService::class);

        return $query->get()->map(function ($row) use ($addressResolver) {
            foreach (['region', 'province', 'city_municipality', 'barangay'] as $field) {
                $row->{$field} = $addressResolver->resolve($row->{$field} ?? null);
            }

            $row->full_address = $addressResolver->format(
                $row->street ?? null,
                $row->barangay ?? null,
                $row->city_municipality ?? null,
                $row->province ?? null,
                $row->region ?? null,
            ) ?: ($row->full_address ?? '');

            return $row;
        });
    }

    /**
     * Get client addresses. ADMIN/CASE_MANAGER: all. AGENCY: own referrals only.
     */
    public function getClientAddresses(?User $user = null): Collection
    {
        $query = ClientAddress::query()
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

        $addressResolver = app(PhilippineAddressService::class);

        return $query->get()->map(function ($row) use ($addressResolver) {
            foreach (['region', 'province', 'city_municipality', 'barangay'] as $field) {
                $row->{$field} = $addressResolver->resolve($row->{$field} ?? null);
            }

            return $row;
        });
    }

    /**
     * Get client employments. ADMIN/CASE_MANAGER: all. AGENCY: own referrals only.
     */
    public function getClientEmployments(?User $user = null): Collection
    {
        $query = ClientEmployment::query()
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
}
