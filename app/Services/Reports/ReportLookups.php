<?php

namespace App\Services\Reports;

use App\Enums\UserRole;
use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseStatus;
use App\Services\PhilippineAddressService;
use App\Services\Reports\Concerns\ScopesReportQueries;

class ReportLookups
{
    use ScopesReportQueries;

    // ── Cache Keys & TTLs ────────────────────────────────────────────────

    private const CACHE_TTL_REFERENCE = 1800;    // 30 minutes — reference/status data

    private const CACHE_TTL_OPTIONS = 600;       // 10 minutes — filter options

    public const KEY_REFERENCE_DATA = 'reports:reference_data:case_intake';

    /**
     * Reference rows that drive the chart toggle controls (statuses, categories,
     * case issues). Sourced from the live reference tables — active only, ordered
     * by sort_order — so toggle lists and colors never drift from hard-coded literals.
     */
    public function getReferenceData(): array
    {
        return CacheHelper::safeRemember(self::KEY_REFERENCE_DATA, self::CACHE_TTL_REFERENCE, function () {
            return [
                'referralStatuses' => CaseStatus::query()
                    ->where('type', 'referral')->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['slug', 'name', 'color'])->toArray(),
                'caseStatuses' => CaseStatus::query()
                    ->where('type', 'case')->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['slug', 'name', 'color'])->toArray(),
                'categories' => CaseCategory::query()
                    ->where('is_active', true)->orderBy('sort_order')
                    ->get(['name', 'color'])->toArray(),
                'caseIssues' => [],
            ];
        });
    }

    /**
     * Role-scoped agency options for the agency filter dropdown.
     *
     * Admin and CASE_MANAGER: all active agencies.
     * Agency: empty array — the selector is hidden for Agency users.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getAgencyOptions(?string $userId = null, ?string $role = null): array
    {
        // Never let an unassigned scoped request fall through to the admin
        // branch after the cache key is built.
        if ($role === UserRole::AGENCY->value) {
            return [];
        }

        $cacheKey = 'reports:agency_options:'.hash('sha256', ($userId ?? '').'|'.($role ?? ''));

        return CacheHelper::safeRemember($cacheKey, self::CACHE_TTL_OPTIONS, function () {
            // Admin / CASE_MANAGER: all active agencies.
            return Agency::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Agency $a) => ['value' => $a->id, 'label' => $a->name])
                ->values()
                ->toArray();
        });
    }

    public function getProvinceOptions(?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return [];
        }

        $cacheKey = 'reports:province_options:'.hash('sha256', ($userId ?? '').'|'.($role ?? '').'|'.($agencyId ?? ''));

        return CacheHelper::safeRemember($cacheKey, self::CACHE_TTL_OPTIONS, function () use ($agencyId) {
            $query = $this->caseQuery(null, null, $agencyId)
                ->select('address_province')
                ->whereNotNull('address_province')->where('address_province', '!=', '')
                ->distinct()->orderBy('address_province');

            $resolver = app(PhilippineAddressService::class);

            return $query->pluck('address_province')->map(fn ($p) => [
                'value' => $p,
                'label' => $resolver->resolve($p),
            ])->values()->toArray();
        });
    }

    public function getCityOptions(?string $province = null, ?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return [];
        }

        $cacheKey = 'reports:city_options:'.hash('sha256', ($province ?? '').'|'.($userId ?? '').'|'.($role ?? '').'|'.($agencyId ?? ''));

        return CacheHelper::safeRemember($cacheKey, self::CACHE_TTL_OPTIONS, function () use ($province, $agencyId) {
            $query = $this->caseQuery(null, null, $agencyId)
                ->select('address_city_municipality')
                ->whereNotNull('address_city_municipality')->where('address_city_municipality', '!=', '')
                ->distinct()->orderBy('address_city_municipality');
            if ($province) {
                $query->where('cases.address_province', $province);
            }

            $resolver = app(PhilippineAddressService::class);

            return $query->pluck('address_city_municipality')->map(fn ($c) => [
                'value' => $c,
                'label' => $resolver->resolve($c),
            ])->values()->toArray();
        });
    }
}
