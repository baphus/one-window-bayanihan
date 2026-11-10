<?php

namespace App\Services\Reports;

use App\Models\CaseFile;
use App\Services\PhilippineAddressService;
use App\Services\Reports\Concerns\ScopesReportQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientMetrics
{
    use ScopesReportQueries;

    public function getGenderDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        // Case intake permits MALE/FEMALE; a null value is surfaced as "Unknown"
        // rather than an always-empty "Other". Counts are per case record, not
        // per unique person.
        $known = CaseFile::whereIn('id', $this->filteredCaseIds($userId, $role, $fromDate, $toDate, $province, $city, $agencyId))
            ->whereNotNull('sex')
            ->select('sex', DB::raw('count(*) as total'))
            ->groupBy('sex')
            ->pluck('total', 'sex');

        $unknown = CaseFile::whereIn('id', $this->filteredCaseIds($userId, $role, $fromDate, $toDate, $province, $city, $agencyId))
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
        // Labels must match the comparisons below: the last bucket is 61+, not
        // 60+. The previous '60+' label counted only ages above 60, so a
        // 60-year-old was reported under the '41-60' bin while the chart header
        // said the final row started at 60 — and the "Senior Citizen"
        // vulnerability marker counts from 60, so the two disagreed.
        $groups = ['0-17', '18-25', '26-40', '41-60', '61+'];
        $colors = ['#818cf8', '#6366f1', '#4f46e5', '#4338ca', '#3730a3'];

        // Use Eloquent to decrypt date_of_birth (encrypted via EncryptedDate cast),
        // then calculate age groups in PHP, avoiding PostgreSQL age() on text column.
        $cases = CaseFile::whereIn('id', $this->filteredCaseIds($userId, $role, $fromDate, $toDate, $province, $city, $agencyId))
            ->whereNotNull('date_of_birth')
            ->whereNotNull('intake_date')
            ->get(['id', 'date_of_birth', 'intake_date']);

        $counts = array_fill_keys($groups, 0);
        foreach ($cases as $case) {
            $age = $case->age_at_intake;
            if ($age === null) {
                continue;
            }
            if ($age < 18) {
                $counts['0-17']++;
            } elseif ($age <= 25) {
                $counts['18-25']++;
            } elseif ($age <= 40) {
                $counts['26-40']++;
            } elseif ($age <= 60) {
                $counts['41-60']++;
            } else {
                $counts['61+']++;
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
            ->whereNotNull('employment_country');

        // Decrypt via Eloquent, then group in PHP
        $grouped = $query->pluck('employment_country')
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
        // Group decrypted case snapshots, never randomized ciphertext.
        $rows = $this->employmentQuery($userId, $role, $agencyId, $fromDate, $toDate, $province, $city)
            ->whereNotNull('employment_last_position')
            ->select(['id', 'employment_last_position'])->orderBy('id')->lazy(500);
        $counts = [];
        foreach ($rows as $case) {
            $position = $case->employment_last_position;
            if (is_string($position) && $position !== '') {
                $counts[$position] = ($counts[$position] ?? 0) + 1;
            }
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
        $query = $this->caseQuery($userId, $role, $agencyId, $dateScope)
            ->select('cases.address_province as province', DB::raw('count(DISTINCT cases.id) as total'))
            ->whereNotNull('cases.address_province')
            ->where('cases.address_province', '!=', '');
        $this->applyCaseWindow($query, $fromDate, $toDate, $province, $city);

        $rows = $query->groupBy('cases.address_province')
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
        $query = $this->caseQuery($userId, $role, $agencyId, $dateScope)
            ->select('cases.address_city_municipality as city_municipality', DB::raw('count(DISTINCT cases.id) as total'))
            ->whereNotNull('cases.address_city_municipality')
            ->where('cases.address_city_municipality', '!=', '');
        $this->applyCaseWindow($query, $fromDate, $toDate, $province, $city);

        $rows = $query->groupBy('cases.address_city_municipality')
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
