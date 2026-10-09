<?php

namespace App\Services\Reports;

use App\Models\CaseFile;
use App\Models\Client;
use App\Services\PhilippineAddressService;
use App\Services\Reports\Concerns\ScopesReportQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientMetrics
{
    use ScopesReportQueries;

    public function getGenderDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        // DB CHECK constraint (clients_sex_check) permits only MALE/FEMALE; a
        // null value is surfaced as "Unknown" rather than an always-empty "Other".
        $known = Client::whereIn('id', $this->filteredClientIds($userId, $role, $fromDate, $toDate, $province, $city, $agencyId))
            ->whereNotNull('sex')
            ->select('sex', DB::raw('count(*) as total'))
            ->groupBy('sex')
            ->pluck('total', 'sex');

        $unknown = Client::whereIn('id', $this->filteredClientIds($userId, $role, $fromDate, $toDate, $province, $city, $agencyId))
            ->whereNull('sex')
            ->count();

        return [
            'labels' => ['Male', 'Female', 'Unknown'],
            'data' => [(int) ($known['MALE'] ?? 0), (int) ($known['FEMALE'] ?? 0), (int) $unknown],
            'colors' => ['#2f6fb0', '#c73e78', '#94a3b8'],
        ];
    }

    public function getAgeGroupDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $groups = ['0-17', '18-25', '26-40', '41-60', '60+'];
        $colors = ['#818cf8', '#6366f1', '#4f46e5', '#4338ca', '#3730a3'];

        // Use Eloquent to decrypt date_of_birth (encrypted via EncryptedDate cast),
        // then calculate age groups in PHP â€” avoids PostgreSQL age() on text column.
        $clients = Client::whereIn('id', $this->filteredClientIds($userId, $role, $fromDate, $toDate, $province, $city, $agencyId))
            ->whereNotNull('date_of_birth')
            ->get(['id', 'date_of_birth']);

        $counts = array_fill_keys($groups, 0);
        foreach ($clients as $client) {
            $dob = $client->date_of_birth;
            if ($dob === null) {
                continue;
            }
            $age = $dob->age;
            if ($age < 18) {
                $counts['0-17']++;
            } elseif ($age <= 25) {
                $counts['18-25']++;
            } elseif ($age <= 40) {
                $counts['26-40']++;
            } elseif ($age <= 60) {
                $counts['41-60']++;
            } else {
                $counts['60+']++;
            }
        }

        return [
            'labels' => $groups,
            'data' => array_map(fn ($g) => $counts[$g], $groups),
            'colors' => $colors,
        ];
    }

    public function getLastEmploymentDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        // Use Eloquent so the EncryptedString cast decrypts last_country.
        // DB::table() bypasses casts and returns raw ciphertext for encrypted rows.
        $query = $this->employmentQuery($userId, $role, $agencyId, $fromDate, $toDate, $province, $city)
            ->whereNotNull('last_country');

        // Decrypt via Eloquent, then group in PHP
        $grouped = $query->pluck('last_country')
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->groupBy(fn ($v) => $v)
            ->map(fn ($g) => $g->count())
            ->sortDesc();

        return [
            'labels' => $grouped->keys()->toArray(),
            'data' => $grouped->values()->toArray(),
        ];
    }

    public function getEmploymentOccupationBreakdown(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $province = null,
        ?string $city = null,
    ): array {
        // last_position is encrypted, so grouping/counting must happen after
        // Eloquent hydrates the models and applies the EncryptedString cast.
        // Select only the columns needed for this metric; querying the raw
        // ciphertext would produce incorrect groups and distinct totals.
        $rows = $this->employmentQuery($userId, $role, $agencyId, $fromDate, $toDate, $province, $city)
            ->whereNotNull('last_position')
            ->select(['id', 'client_id', 'last_position'])
            ->orderBy('client_id')
            ->orderBy('id')
            ->lazy(500);

        // Rows are ordered by client, so only the current client's distinct
        // occupations need to remain in memory.  Counts retain one entry per
        // decoded occupation, not one entry per employment row or client.
        $counts = [];
        $currentClientId = null;
        $clientPositions = [];
        $flushClient = function () use (&$counts, &$clientPositions): void {
            foreach (array_keys($clientPositions) as $position) {
                $counts[$position] = ($counts[$position] ?? 0) + 1;
            }
            $clientPositions = [];
        };

        foreach ($rows as $employment) {
            if ($currentClientId !== null && $currentClientId !== $employment->client_id) {
                $flushClient();
            }
            $currentClientId = $employment->client_id;

            $position = $employment->last_position;
            if (! is_string($position) || $position === '') {
                continue;
            }

            $clientPositions[$position] = true;
        }
        if ($currentClientId !== null) {
            $flushClient();
        }

        $ranked = collect($counts)
            ->map(fn (int $total, string $position) => [
                'position' => $position,
                'total' => $total,
            ])
            ->sort(function (array $a, array $b): int {
                return ($b['total'] <=> $a['total']) ?: strcmp($a['position'], $b['position']);
            })
            ->values();
        $top = $ranked->take(10);

        return [
            'labels' => $top->pluck('position')->toArray(),
            'data' => $top->pluck('total')->map(fn (int $total) => (int) $total)->toArray(),
            'total_distinct' => $ranked->count(),
        ];
    }

    public function getGeographicDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $aggregated = $this->getGeographicProvinceCounts($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId);

        return [
            'labels' => array_column($aggregated, 'name'),
            'data' => array_column($aggregated, 'total'),
        ];
    }

    public function getGeographicMapData(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $provinces = array_map(function (array $row) {
            $codes = $row['codes'];
            $provinceCode = $codes[0] ?? null;
            $id = $provinceCode ? (string) $provinceCode : Str::upper(Str::slug($row['name'], '_'));

            $province = [
                'id' => $id,
                'name' => $row['name'],
                'cases' => (int) $row['total'],
            ];

            if ($provinceCode && $provinceCode !== $id) {
                $province['value'] = (string) $provinceCode;
            }

            return $province;
        }, $this->getGeographicProvinceCounts($userId, $role, $fromDate, $toDate, $dateScope, $province, $city, $agencyId));

        return ['provinces' => $provinces];
    }

    private function getGeographicProvinceCounts(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $query = CaseFile::select('ca.province', DB::raw('count(*) as total'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->leftJoin('clients as c', 'c.id', '=', 'cases.client_id')
            ->leftJoin('client_addresses as ca', 'ca.client_id', '=', 'c.id')
            ->whereNotNull('ca.province')
            ->where('ca.province', '!=', '');

        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }
        if ($fromDate) {
            $query->whereDate('cases.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('cases.created_at', '<=', $toDate);
        }
        if ($province) {
            $query->where('ca.province', $province);
        }
        if ($city) {
            $query->where('ca.city_municipality', $city);
        }

        $rows = $query->groupBy('ca.province')
            ->orderByDesc('total')
            ->get();

        $resolver = app(PhilippineAddressService::class);
        $aggregated = [];
        foreach ($rows as $row) {
            $name = $resolver->resolve($row->province);
            $aggregated[$name] ??= ['name' => $name, 'total' => 0, 'codes' => []];
            $aggregated[$name]['total'] += (int) $row->total;
            $aggregated[$name]['codes'][] = (string) $row->province;
        }

        foreach ($aggregated as &$item) {
            $item['codes'] = array_values(array_unique($item['codes']));
        }
        unset($item);

        usort($aggregated, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $aggregated;
    }

    public function getCityDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = CaseFile::select('ca.city_municipality', DB::raw('count(*) as total'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->leftJoin('clients as c', 'c.id', '=', 'cases.client_id')
            ->leftJoin('client_addresses as ca', 'ca.client_id', '=', 'c.id')
            ->whereNotNull('ca.city_municipality')
            ->where('ca.city_municipality', '!=', '');

        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }
        if ($fromDate) {
            $query->whereDate('cases.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('cases.created_at', '<=', $toDate);
        }
        if ($province) {
            $query->where('ca.province', $province);
        }
        if ($city) {
            $query->where('ca.city_municipality', $city);
        }

        $rows = $query->groupBy('ca.city_municipality')
            ->orderByDesc('total')
            ->get();

        $resolver = app(PhilippineAddressService::class);
        $aggregated = [];
        foreach ($rows as $row) {
            $name = $resolver->resolve($row->city_municipality);
            $aggregated[$name] = ($aggregated[$name] ?? 0) + (int) $row->total;
        }
        arsort($aggregated);

        return [
            'labels' => array_keys($aggregated),
            'data' => array_values($aggregated),
        ];
    }
}
