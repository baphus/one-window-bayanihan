<?php

namespace App\Services\Export\Queries;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\Export\Queries\Concerns\ScopesForExportUser;
use App\Services\PhilippineAddressService;
use Illuminate\Support\Collection;

class ClientDetailQueries
{
    use ScopesForExportUser;

    private function caseQuery(?User $user)
    {
        $query = CaseFile::query()->where('cases.is_deleted', false);
        if (! $this->isAdmin($user)) {
            if ($user->role !== UserRole::AGENCY->value || ! $user->agcy_id) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('referrals', fn ($q) => $q->where('agcy_id', $user->agcy_id));
            }
        }

        return $query;
    }

    public function getNextOfKins(?User $user = null): Collection
    {
        return $this->caseQuery($user)->whereNotNull('nok_name')->get()->map(fn ($case) => (object) [
            'case_id' => $case->id,
            'name' => $case->nok_name,
            'relationship' => $case->nok_relationship,
            'phone_number' => $case->nok_contact_number,
            'email' => $case->nok_email,
        ]);
    }

    public function getClientAddresses(?User $user = null): Collection
    {
        $resolver = app(PhilippineAddressService::class);

        return $this->caseQuery($user)->get()->map(fn ($case) => (object) [
            'case_id' => $case->id,
            'region' => $resolver->resolve($case->address_region),
            'province' => $resolver->resolve($case->address_province),
            'city_municipality' => $resolver->resolve($case->address_city_municipality),
            'barangay' => $resolver->resolve($case->address_barangay),
            'street' => $case->address_street,
        ]);
    }

    public function getClientEmployments(?User $user = null): Collection
    {
        return $this->caseQuery($user)->get()->map(function ($case) {
            $row = ['case_id' => $case->id];
            foreach (['employer_name', 'employer_address', 'position', 'last_position', 'country', 'last_country', 'start_date', 'end_date', 'date_of_arrival'] as $field) {
                $value = $case->{'employment_'.$field};
                $row[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
            }

            return (object) $row;
        });
    }
}
