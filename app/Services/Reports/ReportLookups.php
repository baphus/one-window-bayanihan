<?php

namespace App\Services\Reports;

use App\Enums\UserRole;
use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseIssue;
use App\Models\CaseStatus;
use App\Services\PhilippineAddressService;
use App\Services\Reports\Concerns\ScopesReportQueries;
use Illuminate\Support\Facades\DB;

class ReportLookups
{
    use ScopesReportQueries;

    // ── Cache Keys & TTLs ────────────────────────────────────────────────

    private const CACHE_TTL_REFERENCE = 1800;    // 30 minutes — reference/status data

    private const CACHE_TTL_OPTIONS = 600;       // 10 minutes — filter options

    public const KEY_REFERENCE_DATA = 'reports:reference_data';

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
                'caseIssues' => CaseIssue::query()
                    ->where('is_active', true)->orderBy('sort_order')
                    ->get(['name'])->toArray(),
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
            $query = DB::table('client_addresses')
                ->select('province')
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->where('is_deleted', false)
                ->distinct()
                ->orderBy('province');

            if ($agencyId) {
                $query->whereIn('client_id', function ($q) use ($agencyId) {
                    $q->select('c.client_id')->from('cases as c')
                        ->whereIn('c.id', function ($q2) use ($agencyId) {
                            $q2->select('case_id')->from('referrals')
                                ->where('agcy_id', $agencyId)
                                ->whereNull('deleted_at');
                        })
                        ->whereNotIn('c.status', ['DRAFT', 'ARCHIVED']);
                });
            }

            $resolver = app(PhilippineAddressService::class);

            return $query->pluck('province')->map(fn ($p) => [
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
            $query = DB::table('client_addresses')
                ->select('city_municipality')
                ->whereNotNull('city_municipality')
                ->where('city_municipality', '!=', '')
                ->where('is_deleted', false)
                ->distinct()
                ->orderBy('city_municipality');

            if ($province) {
                $query->where('province', $province);
            }

            if ($agencyId) {
                $query->whereIn('client_id', function ($q) use ($agencyId) {
                    $q->select('c.client_id')->from('cases as c')
                        ->whereIn('c.id', function ($q2) use ($agencyId) {
                            $q2->select('case_id')->from('referrals')
                                ->where('agcy_id', $agencyId)
                                ->whereNull('deleted_at');
                        })
                        ->whereNotIn('c.status', ['DRAFT', 'ARCHIVED']);
                });
            }

            $resolver = app(PhilippineAddressService::class);

            return $query->pluck('city_municipality')->map(fn ($c) => [
                'value' => $c,
                'label' => $resolver->resolve($c),
            ])->values()->toArray();
        });
    }
}
