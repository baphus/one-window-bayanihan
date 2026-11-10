<?php

namespace App\Services\Export\Queries;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\Export\Queries\Concerns\FiltersCaseCategories;
use App\Services\Export\Queries\Concerns\ScopesForExportUser;
use App\Services\PhilippineAddressService;
use Illuminate\Support\Collection;

class ClientQueries
{
    use FiltersCaseCategories, ScopesForExportUser;

    private function clientsExportQuery(?User $user = null, array $filters = [])
    {
        $query = CaseFile::query()->where('cases.is_deleted', false);
        if (! $this->isAdmin($user)) {
            if ($user->role !== UserRole::AGENCY->value || ! $user->agcy_id) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('referrals', fn ($q) => $q->where('agcy_id', $user->agcy_id));
            }
        }
        foreach (['sex', 'client_type'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['account_status'] ?? null) === 'linked') {
            $query->whereNotNull('ofw_user_id');
        } elseif (($filters['account_status'] ?? null) === 'no_account') {
            $query->whereNull('ofw_user_id');
        }
        if (! empty($filters['case_status'])) {
            $query->where('status', $filters['case_status']);
        } else {
            $query->where('status', '!=', 'ARCHIVED');
        }
        if (! empty($filters['vulnerability_indicator'])) {
            $value = '%'.$filters['vulnerability_indicator'].'%';
            $query->where(fn ($q) => $q->where('vulnerability_indicator', 'LIKE', $value)->orWhere('nok_vulnerability_indicator', 'LIKE', $value));
        }
        $this->applyCategoryFilter($query, 'cases', $filters, $user);
        if (! empty($filters['agcy_id'])) {
            $query->whereHas('referrals', fn ($q) => $q->where('agcy_id', $filters['agcy_id']));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('cases.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('cases.created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $value = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($value) {
                $q->where('first_name', 'ilike', $value)->orWhere('last_name', 'ilike', $value)
                    ->orWhere('middle_name', 'ilike', $value)->orWhere('email', 'ilike', $value)
                    ->orWhere('contact_number', 'ilike', $value)->orWhere('case_number', 'ilike', $value);
            });
        }

        return $query;
    }

    public function getClients(?User $user = null): Collection
    {
        return $this->clientsExportQuery($user)->get()->map(fn ($case) => (object) array_merge(
            $case->only(['id', 'case_number', 'first_name', 'last_name', 'middle_name', 'suffix', 'sex', 'email', 'contact_number', 'ofw_user_id', 'created_at', 'updated_at']),
            ['date_of_birth' => $case->date_of_birth?->toDateString()],
        ));
    }

    public function getClientsExport(?User $user = null, array $filters = []): Collection
    {
        $resolver = app(PhilippineAddressService::class);

        $query = $this->clientsExportQuery($user, $filters)->with(['referrals' => function ($q) use ($user) {
            $q->with('agency');
            if ($user?->role === UserRole::AGENCY->value) {
                $q->where('agcy_id', $user->agcy_id);
            }
        }]);

        return $query->orderByDesc('cases.created_at')->limit(10000)->get()->map(function ($case) use ($resolver) {
            return (object) [
                'case_number' => $case->case_number,
                'case_status' => $case->status,
                'tracker_number' => $case->tracker_number,
                'client_type' => $case->client_type === 'NEXT_OF_KIN' ? 'Next of Kin' : 'OFW',
                'full_name' => trim($case->last_name.', '.$case->first_name.' '.$case->middle_name),
                'sex' => $case->sex,
                'date_of_birth' => $case->date_of_birth?->toDateString(),
                'age' => $case->age_at_intake,
                'contact_number' => $case->contact_number,
                'email' => $case->email,
                'vulnerability' => $case->vulnerability_indicator,
                'full_address' => $resolver->format($case->address_street, $case->address_barangay, $case->address_city_municipality, $case->address_province, $case->address_region),
                'date_of_arrival' => $case->employment_date_of_arrival?->toDateString(),
                'previous_country' => $case->employment_country,
                'work_position' => $case->employment_position,
                'receiving_parties' => $case->referrals->pluck('agency.name')->filter()->unique()->implode(', '),
                'nok_full_name' => $case->nok_name,
                'nok_contact_number' => $case->nok_contact_number,
                'nok_email' => $case->nok_email,
            ];
        });
    }

    public function countClientsExport(?User $user = null, array $filters = []): int
    {
        return $this->clientsExportQuery($user, $filters)->count();
    }
}
