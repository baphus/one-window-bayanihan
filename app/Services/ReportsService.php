<?php

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\CaseStatus;
use App\Models\Client;
use App\Models\ClientEmployment;
use App\Models\Referral;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportsService
{
    // ── Cache Keys & TTLs ────────────────────────────────────────────────

    private const CACHE_TTL_PAYLOAD = 180;       // 3 minutes — full report payload

    private const CACHE_TTL_REFERENCE = 1800;    // 30 minutes — reference/status data

    private const CACHE_TTL_OPTIONS = 600;       // 10 minutes — filter options

    public const KEY_REFERENCE_DATA = 'reports:reference_data';

    public function getAll(
        ?string $userId = null,
        ?string $role = null,
        ?string $agencyId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
    ): array {
        // Do this before constructing or reading the cache key.  An unassigned
        // scoped user must never be able to receive a cached report generated
        // for another identity.
        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            $empty = $this->emptyPayload($role);
            $empty['role'] = $role;

            return $empty;
        }

        // Build cache key from all parameters that affect the output
        $cacheKey = 'reports:payload:'.md5(implode('|', [
            $userId ?? '', $role ?? '', $agencyId ?? '',
            $fromDate ?? '', $toDate ?? '', $dateScope,
            $province ?? '', $city ?? '',
        ]));

        return CacheHelper::safeRemember($cacheKey, self::CACHE_TTL_PAYLOAD, function () use (
            $userId, $role, $agencyId, $fromDate, $toDate, $dateScope, $province, $city
        ) {
            $data = match ($role) {
                'AGENCY' => $this->getAgencyPayload($userId, $fromDate, $toDate, $dateScope, $province, $city, $agencyId),
                'ADMIN' => $this->getAdminPayload($fromDate, $toDate, $dateScope, $province, $city, $agencyId),
                default => $this->getCaseManagerPayload($userId, $fromDate, $toDate, $dateScope, $province, $city, $agencyId),
            };

            $data['role'] = $role;

            return $data;
        });
    }

    private function getCaseManagerPayload(
        ?string $userId,
        ?string $fromDate,
        ?string $toDate,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'kpis' => $this->getReferralKpis($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralStatusDistribution' => $this->getReferralStatusDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralAgencyDistribution' => $this->getReferralAgencyDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralTrends' => $this->getReferralTrends($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'casesOverTime' => $this->getCasesOverTime($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'genderDistribution' => $this->getGenderDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'clientTypeDistribution' => $this->getClientTypeDistribution($userId, 'CASE_MANAGER', $agencyId),
            'ageGroupDistribution' => $this->getAgeGroupDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'mostRequestedService' => $this->getMostRequestedService($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'cycleTimeDistribution' => $this->getReferralCycleTimeDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralAging' => $this->getReferralAging($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyScorecard' => $this->getAgencyScorecard($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'geographicDistribution' => $this->getGeographicDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'geographicMapData' => $this->getGeographicMapData($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'categoryDistribution' => $this->categoryDistribution($userId, 'CASE_MANAGER', $agencyId),
            'employmentDistribution' => $this->getLastEmploymentDistribution($userId, 'CASE_MANAGER', $agencyId),
            'employmentPositionBreakdown' => $this->getEmploymentPositionBreakdown($userId, 'CASE_MANAGER', $agencyId),
            'caseStatusDistribution' => $this->getCaseStatusDistribution($userId, 'CASE_MANAGER', $agencyId),
            'caseIssueDistribution' => $this->getCaseIssueDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'overdueReferrals' => $this->getOverdueReferrals($userId, 'CASE_MANAGER', $province, $city, $agencyId),
            'cityDistribution' => $this->getCityDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city, $agencyId),
            'vulnerabilityDistribution' => $this->getVulnerabilityDistribution($userId, 'CASE_MANAGER', $agencyId),
        ];
    }

    private function getAgencyPayload(
        ?string $userId,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'kpis' => $this->getReferralKpis(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralStatusDistribution' => $this->getReferralStatusDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'referralTrends' => $this->getReferralTrends(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'avgReferralCompletion' => $this->getAvgReferralCompletionDays(role: 'AGENCY', agencyId: $agencyId),
            'cycleTimeDistribution' => $this->getReferralCycleTimeDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyScorecard' => $this->getAgencyScorecard(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'categoryDistribution' => $this->categoryDistribution(null, 'AGENCY', $agencyId),
            'caseStatusDistribution' => $this->getCaseStatusDistribution(null, 'AGENCY', $agencyId),
            'genderDistribution' => $this->getGenderDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'ageGroupDistribution' => $this->getAgeGroupDistribution(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
            'clientTypeDistribution' => $this->getClientTypeDistribution(null, 'AGENCY', $agencyId),
            'geographicMapData' => $this->getGeographicMapData(null, 'AGENCY', $from, $to, $dateScope, $province, $city, $agencyId),
        ];
    }

    private function getAdminPayload(?string $fromDate, ?string $toDate, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'kpis' => $this->getReferralKpis(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'overview' => $this->getOverview($from, $to, $agencyId),
            'caseTrends' => $this->getCaseTrends(agencyId: $agencyId),
            'referralStatusDistribution' => $this->getReferralStatusDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'referralTrends' => $this->getReferralTrends(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyWorkload' => $this->getAgencyWorkload($from, $to, $agencyId),
            'clientTypeDistribution' => $this->getClientTypeDistribution(null, null, $agencyId),
            'cycleTimeDistribution' => $this->getReferralCycleTimeDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'referralAging' => $this->getReferralAging(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'geographicDistribution' => $this->getGeographicDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'geographicMapData' => $this->getGeographicMapData(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'agencyScorecard' => $this->getAgencyScorecard(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'categoryDistribution' => $this->categoryDistribution(null, null, $agencyId),
            'employmentDistribution' => $this->getLastEmploymentDistribution(null, null, $agencyId),
            'employmentPositionBreakdown' => $this->getEmploymentPositionBreakdown(null, null, $agencyId),
            'caseStatusDistribution' => $this->getCaseStatusDistribution(null, null, $agencyId),
            'caseIssueDistribution' => $this->getCaseIssueDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'vulnerabilityDistribution' => $this->getVulnerabilityDistribution(null, null, $agencyId),
            'genderDistribution' => $this->getGenderDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'ageGroupDistribution' => $this->getAgeGroupDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
            'referralAgencyDistribution' => $this->getReferralAgencyDistribution(null, null, $from, $to, $dateScope, $province, $city, $agencyId),
        ];
    }

    public function getOverview(?string $fromDate = null, ?string $toDate = null, ?string $agencyId = null): array
    {
        $caseQuery = CaseFile::whereNotIn('status', ['DRAFT', 'ARCHIVED']);
        if ($fromDate) {
            $caseQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $caseQuery->whereDate('created_at', '<=', $toDate);
        }
        if ($agencyId) {
            $caseQuery->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        $caseCounts = (clone $caseQuery)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');
        $totalCases = $caseCounts->sum();

        $refQuery = Referral::query();
        if ($fromDate) {
            $refQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $refQuery->whereDate('created_at', '<=', $toDate);
        }
        if ($agencyId) {
            $refQuery->where('agcy_id', $agencyId);
        }

        $refCounts = (clone $refQuery)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'totalCases' => (int) $totalCases,
            'openCases' => (int) ($caseCounts['OPEN'] ?? 0),
            'closedCases' => (int) ($caseCounts['CLOSED'] ?? 0),
            'totalReferrals' => (int) $refCounts->sum(),
            'pendingReferrals' => (int) ($refCounts['PENDING'] ?? 0),
            'activeAgencies' => (int) Agency::count(),
        ];
    }

    public function getCaseTrends(int $months = 12, ?string $agencyId = null): array
    {
        $cases = CaseFile::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as month"),
            DB::raw('count(*) as total')
        )
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where('created_at', '>=', now()->subMonths($months));

        if ($agencyId) {
            $cases->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        $cases = $cases->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $cases->pluck('month')->toArray(),
            'data' => $cases->pluck('total')->toArray(),
        ];
    }

    public function getReferralTrends(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        $referrals = $this->referralQuery($userId, $role, $agencyId, $from, $to, $dateScope);
        $this->applyGeoFilter($referrals, $province, $city);

        $referrals = $referrals->select(
            DB::raw("to_char(referrals.created_at, 'YYYY-MM') as month"),
            DB::raw('count(*) as total')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $referrals->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Referrals Created',
                    'data' => $referrals->pluck('total')->toArray(),
                    'borderColor' => '#0b5a8c',
                    'backgroundColor' => 'rgba(11, 90, 140, 0.1)',
                ],
            ],
        ];
    }

    public function getAgencyWorkload(?string $fromDate = null, ?string $toDate = null, ?string $agencyId = null): array
    {
        $workload = Agency::withCount(['referrals' => function ($q) use ($fromDate, $toDate, $agencyId) {
            if ($fromDate) {
                $q->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $q->whereDate('created_at', '<=', $toDate);
            }
            if ($agencyId) {
                $q->where('agcy_id', $agencyId);
            }
        }])
            ->orderByDesc('referrals_count')
            ->get();

        return [
            'labels' => $workload->pluck('name')->toArray(),
            'data' => $workload->pluck('referrals_count')->toArray(),
        ];
    }

    public function getClientTypeDistribution(?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        $query = $this->caseQuery($userId, $role, $agencyId);

        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return ['labels' => [], 'data' => [], 'colors' => []];
        }

        $types = (clone $query)
            ->select('client_type', DB::raw('count(*) as total'))
            ->groupBy('client_type')
            ->pluck('total', 'client_type');

        return [
            'labels' => ['OFW', 'Next of Kin'],
            'data' => [
                (int) ($types['OFW'] ?? 0),
                (int) ($types['NEXT_OF_KIN'] ?? 0),
            ],
            'colors' => ['#6366f1', '#a5b4fc'],
        ];
    }

    public function getVulnerabilityDistribution(?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        $query = CaseFile::whereNotIn('cases.status', ['DRAFT', 'ARCHIVED']);
        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        $categories = ['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'];
        $counts = [];

        foreach ($categories as $cat) {
            $count = (clone $query)
                ->where(function ($q) use ($cat) {
                    $q->where('cases.vulnerability_indicator', 'LIKE', "%{$cat}%")
                        ->orWhere('cases.nok_vulnerability_indicator', 'LIKE', "%{$cat}%");
                })
                ->count();
            $counts[$cat] = $count;
        }

        // Count cases with no vulnerability set (or only "None")
        $noneCount = (clone $query)
            ->where(function ($q) use ($categories) {
                foreach ($categories as $cat) {
                    $q->where('cases.vulnerability_indicator', 'NOT LIKE', "%{$cat}%")
                        ->where('cases.nok_vulnerability_indicator', 'NOT LIKE', "%{$cat}%");
                }
            })
            ->count();
        $counts['None'] = $noneCount;

        $allCategories = ['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None'];
        $colors = ['#f59e0b', '#10b981', '#8b5cf6', '#06b6d4', '#cbd5e1'];

        return [
            'labels' => $allCategories,
            'data' => array_map(fn ($c) => (int) ($counts[$c] ?? 0), $allCategories),
            'colors' => $colors,
        ];
    }

    private function caseQuery(?string $userId = null, ?string $role = null, ?string $agencyId = null, string $dateScope = 'case_created_at')
    {
        $query = CaseFile::whereNotIn('cases.status', ['DRAFT', 'ARCHIVED']);
        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return $query->whereRaw('1 = 0');
        }
        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        return $query;
    }

    private function referralQuery(?string $userId = null, ?string $role = null, ?string $agencyId = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at')
    {
        $query = Referral::query();

        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return $query->whereRaw('1 = 0');
        }

        if ($agencyId) {
            $query->where('referrals.agcy_id', $agencyId);
        }

        if ($dateScope === 'case_created_at') {
            // Subquery avoids JOIN — prevents ambiguous column errors
            $query->whereIn('referrals.case_id', function ($q) use ($fromDate, $toDate) {
                $q->select('cases.id')->from('cases')
                    ->whereNull('cases.deleted_at');
                if ($fromDate) {
                    $q->whereDate('cases.created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $q->whereDate('cases.created_at', '<=', $toDate);
                }
            });
        } else {
            if ($fromDate) {
                $query->whereDate($dateScope === 'referral_created_at' ? 'referrals.created_at' : 'referrals.updated_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate($dateScope === 'referral_created_at' ? 'referrals.created_at' : 'referrals.updated_at', '<=', $toDate);
            }
        }

        return $query;
    }

    /**
     * Apply geographic filter (province/city) to a query builder.
     * For referral-based queries, uses a subquery on cases->clients->client_addresses.
     * For case-based queries, joins directly.
     */
    private function applyGeoFilter($query, ?string $province, ?string $city, string $baseTable = 'referrals'): void
    {
        if (! $province && ! $city) {
            return;
        }

        if ($baseTable === 'referrals') {
            $query->select('referrals.*')->whereIn('referrals.case_id', function ($q) use ($province, $city) {
                $q->select('cases.id')->from('cases')
                    ->join('clients', 'clients.id', '=', 'cases.client_id')
                    ->join('client_addresses', 'client_addresses.client_id', '=', 'clients.id');
                if ($province) {
                    $q->where('client_addresses.province', $province);
                }
                if ($city) {
                    $q->where('client_addresses.city_municipality', $city);
                }
            });
        } else {
            $query->join('clients', 'clients.id', '=', 'cases.client_id')
                ->join('client_addresses', 'client_addresses.client_id', '=', 'clients.id');
            if ($province) {
                $query->where('client_addresses.province', $province);
            }
            if ($city) {
                $query->where('client_addresses.city_municipality', $city);
            }
        }
    }

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

    public function getReferralKpis(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $from = Carbon::parse($fromDate ?: now()->subYear());
        $to = Carbon::parse($toDate ?: now());

        $referrals = $this->referralQuery($userId, $role, $agencyId, $from->toDateString(), $to->toDateString(), $dateScope);
        $this->applyGeoFilter($referrals, $province, $city);

        $cases = $this->caseQuery($userId, $role, $agencyId, $dateScope)
            ->whereDate('cases.created_at', '>=', $from->toDateString())
            ->whereDate('cases.created_at', '<=', $to->toDateString());
        $this->applyGeoFilter($cases, $province, $city, 'cases');

        $statusCounts = (clone $referrals)
            ->select('referrals.status', DB::raw('count(*) as cnt'))
            ->groupBy('referrals.status')
            ->pluck('cnt', 'status');
        $total = $statusCounts->sum();
        $totalCases = (clone $cases)->distinct('cases.id')->count('cases.id');
        $openCases = (clone $cases)->where('cases.status', 'OPEN')->distinct('cases.id')->count('cases.id');
        $completed = (int) ($statusCounts['COMPLETED'] ?? 0);
        $pending = (int) ($statusCounts['PENDING'] ?? 0);
        $processing = (int) ($statusCounts['PROCESSING'] ?? 0);
        $forCompliance = (int) ($statusCounts['FOR_COMPLIANCE'] ?? 0);
        $rejected = (int) ($statusCounts['REJECTED'] ?? 0);

        $avgDays = (clone $referrals)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        // Accurate case resolution time uses the real close timestamp (closed_at),
        // not updated_at which is corrupted by any later edit.
        $avgResolutionDays = (clone $cases)
            ->where('cases.status', 'CLOSED')
            ->whereNotNull('cases.closed_at')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (cases.closed_at - cases.created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        $duration = $from->diffInDays($to);
        $prevFrom = $from->copy()->subDays($duration);
        $prevTo = $from->copy()->subDay();

        $prev = $this->referralQuery($userId, $role, $agencyId, $prevFrom->toDateString(), $prevTo->toDateString(), $dateScope);
        $this->applyGeoFilter($prev, $province, $city);

        $prevStatusCounts = (clone $prev)
            ->select('referrals.status', DB::raw('count(*) as cnt'))
            ->groupBy('referrals.status')
            ->pluck('cnt', 'status');
        $prevTotal = $prevStatusCounts->sum();
        $prevCompleted = (int) ($prevStatusCounts['COMPLETED'] ?? 0);
        $prevPending = (int) ($prevStatusCounts['PENDING'] ?? 0);
        $prevAvgDays = (clone $prev)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        $pct = fn ($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

        return [
            'totalReferrals' => (int) $total,
            'totalCases' => (int) $totalCases,
            'openCases' => (int) $openCases,
            'completedReferrals' => (int) $completed,
            'pendingReferrals' => (int) $pending,
            'processingReferrals' => (int) $processing,
            'forComplianceReferrals' => (int) $forCompliance,
            'rejectedReferrals' => (int) $rejected,
            'completionRate' => $total > 0 ? round(($completed / $total) * 100) : 0,
            'avgCompletionDays' => round((float) ($avgDays ?? 0), 1),
            'avgResolutionDays' => round((float) ($avgResolutionDays ?? 0), 1),
            'kpiChanges' => [
                'totalReferrals' => $pct($total, $prevTotal),
                'completedReferrals' => $pct($completed, $prevCompleted),
                'pendingReferrals' => $pct($pending, $prevPending),
                'completionRate' => $pct(
                    $total > 0 ? ($completed / $total) * 100 : 0,
                    $prevTotal > 0 ? ($prevCompleted / $prevTotal) * 100 : 0,
                ),
                'avgCompletionDays' => $pct((float) ($avgDays ?? 0), (float) ($prevAvgDays ?? 0)),
            ],
        ];
    }

    public function getCasesOverTime(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $cases = $this->caseQuery($userId, $role, $agencyId, $dateScope);
        $this->applyGeoFilter($cases, $province, $city, 'cases');

        $result = $cases
            ->select(
                DB::raw("to_char(cases.created_at, 'YYYY-MM') as month"),
                DB::raw('count(*) as total')
            )
            ->where('cases.created_at', '>=', $fromDate ?: now()->subMonths(12))
            ->where('cases.created_at', '<=', $toDate ?: now())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $result->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Cases Created',
                    'data' => $result->pluck('total')->toArray(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                ],
            ],
        ];
    }

    /**
     * Subquery of client IDs whose cases match the active date/role/geo filters.
     * Lets client-level distributions (gender/age) respect the same filters as
     * the rest of the report instead of counting the whole clients table.
     */
    private function filteredClientIds(?string $userId, ?string $role, ?string $fromDate, ?string $toDate, ?string $province, ?string $city, ?string $agencyId = null)
    {
        $q = CaseFile::query()
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->whereNull('cases.deleted_at');

        if ($agencyId) {
            $q->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }
        if ($fromDate) {
            $q->whereDate('cases.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('cases.created_at', '<=', $toDate);
        }
        if ($province || $city) {
            $q->join('client_addresses', 'client_addresses.client_id', '=', 'cases.client_id');
            if ($province) {
                $q->where('client_addresses.province', $province);
            }
            if ($city) {
                $q->where('client_addresses.city_municipality', $city);
            }
        }

        return $q->select('cases.client_id');
    }

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
        // then calculate age groups in PHP — avoids PostgreSQL age() on text column.
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

    public function getReferralStatusDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $statuses = (clone $query)
            ->select('referrals.status', DB::raw('count(*) as total'))
            ->groupBy('referrals.status')
            ->pluck('total', 'status');

        $allStatuses = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];
        $colorMap = [
            'PENDING' => '#f59e0b',
            'PROCESSING' => '#3b82f6',
            'FOR_COMPLIANCE' => '#f97316',
            'COMPLETED' => '#22c55e',
            'REJECTED' => '#ef4444',
        ];

        return [
            'labels' => $allStatuses,
            'data' => array_map(fn ($s) => (int) ($statuses[$s] ?? 0), $allStatuses),
            'colors' => array_map(fn ($s) => $colorMap[$s], $allStatuses),
        ];
    }

    public function getReferralAgencyDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $agencies = (clone $query)
            ->select('agcy_id', DB::raw('count(*) as total'))
            ->groupBy('agcy_id')
            ->orderByDesc('total')
            ->get();

        $agencyNames = Agency::whereIn('id', $agencies->pluck('agcy_id'))->pluck('name', 'id');

        $colors = ['#1e3a8a', '#0f766e', '#ea580c', '#6d28d9', '#be123c', '#4338ca', '#0891b2', '#65a30d'];

        return [
            'labels' => $agencies->map(fn ($r) => $agencyNames[$r->agcy_id] ?? 'Unknown')->toArray(),
            'data' => $agencies->pluck('total')->toArray(),
            'colors' => array_slice($colors, 0, $agencies->count()),
        ];
    }

    public function getMostRequestedService(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $top = (clone $query)
            ->select('required_services', DB::raw('count(*) as total'))
            ->whereNotNull('required_services')
            ->groupBy('required_services')
            ->orderByDesc('total')
            ->first();

        return [
            'name' => $top?->required_services ?? 'N/A',
            'value' => (int) ($top?->total ?? 0),
        ];
    }

    public function getReferralCycleTimeDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $referrals = (clone $query)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400 as days'))
            ->get()
            ->pluck('days');

        $buckets = ['< 1 week' => 0, '1-2 weeks' => 0, '2-4 weeks' => 0, '> 1 month' => 0];
        foreach ($referrals as $days) {
            if ($days < 7) {
                $buckets['< 1 week']++;
            } elseif ($days < 14) {
                $buckets['1-2 weeks']++;
            } elseif ($days < 30) {
                $buckets['2-4 weeks']++;
            } else {
                $buckets['> 1 month']++;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'data' => array_values($buckets),
            'colors' => ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'],
        ];
    }

    public function getReferralAging(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $referrals = (clone $query)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])
            ->select(DB::raw('EXTRACT(EPOCH FROM (NOW() - created_at)) / 86400 as days'))
            ->get()
            ->pluck('days');

        $buckets = ['< 1 week' => 0, '1-2 weeks' => 0, '2-4 weeks' => 0, '> 1 month' => 0];
        foreach ($referrals as $days) {
            if ($days < 7) {
                $buckets['< 1 week']++;
            } elseif ($days < 14) {
                $buckets['1-2 weeks']++;
            } elseif ($days < 30) {
                $buckets['2-4 weeks']++;
            } else {
                $buckets['> 1 month']++;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'data' => array_values($buckets),
            'colors' => ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'],
        ];
    }

    public function getAgencyScorecard(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $query = $this->referralQuery($userId, $role, $agencyId, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        $referrals = (clone $query)
            ->select('agcy_id', 'status', DB::raw('EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400 as days'))
            ->get()
            ->groupBy('agcy_id');

        if ($referrals->isEmpty()) {
            return [];
        }

        $agencyIds = $referrals->keys();
        $agencyNames = Agency::whereIn('id', $agencyIds)->pluck('name', 'id');

        $result = [];
        foreach ($referrals as $agcyId => $rows) {
            $total = $rows->count();
            $completed = $rows->where('status', 'COMPLETED')->count();
            $pending = $rows->where('status', 'PENDING')->count();
            $avgDays = $rows->where('status', 'COMPLETED')->avg('days');

            $result[] = [
                'agency' => $agencyNames[$agcyId] ?? 'Unknown',
                'total' => $total,
                'completed' => $completed,
                'pending' => $pending,
                'completionRate' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'avgDays' => round((float) ($avgDays ?? 0), 1),
            ];
        }

        usort($result, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $result;
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

        $resolver = app(AddressNameResolver::class);
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

    public function getLastEmploymentDistribution(?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        // Use Eloquent so the EncryptedString cast decrypts last_country.
        // DB::table() bypasses casts and returns raw ciphertext for encrypted rows.
        $query = $this->employmentQuery($userId, $role, $agencyId)
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

    public function getEmploymentPositionBreakdown(?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        // last_position is encrypted, so grouping/counting must happen after
        // Eloquent hydrates the models and applies the EncryptedString cast.
        // Select only the columns needed for this metric; querying the raw
        // ciphertext would produce incorrect groups and distinct totals.
        $rows = $this->employmentQuery($userId, $role, $agencyId)
            ->whereNotNull('last_position')
            ->select(['id', 'client_id', 'last_position'])
            ->orderBy('client_id')
            ->orderBy('id')
            ->lazy(500);

        // Rows are ordered by client, so only the current client's distinct
        // positions need to remain in memory.  Counts retain one entry per
        // decoded position, not one entry per employment row or client.
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

    /**
     * Build the encrypted employment query with the same fail-closed role
     * guards used by the shared case/referral query helpers.
     */
    private function employmentQuery(?string $userId, ?string $role, ?string $agencyId)
    {
        $query = ClientEmployment::query()
            ->where('client_employments.is_deleted', false)
            ->whereNull('client_employments.deleted_at');

        if (! $this->hasRequiredRoleScope($userId, $role, $agencyId)) {
            return $query->whereRaw('1 = 0');
        }

        if ($agencyId) {
            $query->whereIn('client_id', function ($q) use ($agencyId) {
                $q->select('client_id')->from('cases')
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
                    ->whereIn('cases.id', function ($q2) use ($agencyId) {
                        $q2->select('case_id')->from('referrals')
                            ->where('agcy_id', $agencyId)
                            ->where('is_deleted', false)
                            ->whereNull('deleted_at');
                    });
            });
        }

        return $query;
    }

    /**
     * A scoped report requires the identity that defines that scope.  Keep
     * this check centralized so payloads, options, and individual metrics do
     * not accidentally fall back to an unrestricted query.
     *
     * CASE_MANAGER sees all (no userId needed for scoping).
     * AGENCY must have an agencyId.
     */
    private function hasRequiredRoleScope(?string $userId, ?string $role, ?string $agencyId): bool
    {
        return $role !== 'AGENCY' || (bool) $agencyId;
    }

    private function emptyPayload(?string $role): array
    {
        $zeroKpis = [
            'totalReferrals' => 0, 'totalCases' => 0, 'openCases' => 0,
            'completedReferrals' => 0, 'pendingReferrals' => 0,
            'processingReferrals' => 0, 'forComplianceReferrals' => 0,
            'rejectedReferrals' => 0, 'completionRate' => 0,
            'avgCompletionDays' => 0, 'avgResolutionDays' => 0,
            'kpiChanges' => [
                'totalReferrals' => 0, 'completedReferrals' => 0,
                'pendingReferrals' => 0, 'completionRate' => 0,
                'avgCompletionDays' => 0,
            ],
        ];

        if ($role === 'AGENCY') {
            return [
                'kpis' => $zeroKpis,
                'referralStatusDistribution' => ['labels' => ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'], 'data' => [0, 0, 0, 0, 0], 'colors' => []],
                'referralTrends' => ['labels' => [], 'datasets' => [['label' => 'Referrals Created', 'data' => [], 'borderColor' => '#0b5a8c', 'backgroundColor' => 'rgba(11, 90, 140, 0.1)']]],
                'avgReferralCompletion' => 0,
                'cycleTimeDistribution' => ['labels' => [], 'data' => [], 'colors' => []],
                'agencyScorecard' => [], 'categoryDistribution' => [],
                'caseStatusDistribution' => ['labels' => ['OPEN', 'CLOSED'], 'data' => [0, 0], 'colors' => []],
                'genderDistribution' => ['labels' => ['Male', 'Female', 'Unknown'], 'data' => [0, 0, 0], 'colors' => []],
                'ageGroupDistribution' => ['labels' => ['0-17', '18-25', '26-40', '41-60', '60+'], 'data' => [0, 0, 0, 0, 0], 'colors' => []],
                'clientTypeDistribution' => ['labels' => [], 'data' => [], 'colors' => []],
                'geographicMapData' => ['provinces' => []],
            ];
        }

        return [
            'kpis' => $zeroKpis, 'referralStatusDistribution' => [],
            'referralAgencyDistribution' => [], 'referralTrends' => [],
            'casesOverTime' => [], 'genderDistribution' => [],
            'clientTypeDistribution' => [], 'ageGroupDistribution' => [],
            'mostRequestedService' => ['name' => 'N/A', 'value' => 0],
            'cycleTimeDistribution' => [], 'referralAging' => [],
            'agencyScorecard' => [], 'geographicDistribution' => [],
            'geographicMapData' => ['provinces' => []], 'categoryDistribution' => [],
            'employmentDistribution' => [], 'employmentPositionBreakdown' => ['labels' => [], 'data' => [], 'total_distinct' => 0],
            'caseStatusDistribution' => [], 'caseIssueDistribution' => [],
            'overdueReferrals' => ['count' => 0, 'referrals' => []],
            'cityDistribution' => [], 'vulnerabilityDistribution' => [],
        ];
    }

    public function getCaseStatusDistribution(?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        $query = CaseFile::select('status', DB::raw('count(*) as total'))
            ->whereIn('status', ['OPEN', 'CLOSED']);

        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        $results = $query->groupBy('status')
            ->pluck('total', 'status');

        $allStatuses = ['OPEN', 'CLOSED'];
        $colors = ['#1e3a8a', '#10b981'];

        return [
            'labels' => $allStatuses,
            'data' => array_map(fn ($s) => (int) ($results[$s] ?? 0), $allStatuses),
            'colors' => $colors,
        ];
    }

    public function categoryDistribution(?string $userId = null, ?string $role = null, ?string $agencyId = null): array
    {
        // Category analytics reads the authoritative assignment table. A case
        // counts once per assigned category; deleted, draft, and archived cases
        // are excluded from both counts and percentages.
        $query = DB::table('case_category AS assignments')
            ->join('cases', 'cases.id', '=', 'assignments.case_id')
            ->join('case_categories', 'case_categories.id', '=', 'assignments.case_category_id')
            ->where('cases.is_deleted', false)
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->select('case_categories.name', 'case_categories.color', DB::raw('count(DISTINCT cases.id) as total'))
            ->groupBy('case_categories.name', 'case_categories.color')
            ->orderBy('case_categories.name');

        if ($agencyId) {
            $query->whereIn('cases.id', function ($q) use ($agencyId) {
                $q->select('case_id')->from('referrals')
                    ->where('agcy_id', $agencyId)
                    ->whereNull('deleted_at');
            });
        }

        $results = $query->get();
        $total = $results->sum('total');

        return $results->map(fn ($item) => [
            'name' => $item->name,
            'color' => $item->color,
            'count' => (int) $item->total,
            'percentage' => $total > 0 ? round(($item->total / $total) * 100, 2) : 0,
        ])->toArray();
    }

    public function getCaseIssueDistribution(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
        ?string $agencyId = null,
    ): array {
        $query = $this->caseQuery($userId, $role, $agencyId, $dateScope);
        if ($fromDate) {
            $query->whereDate('cases.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('cases.created_at', '<=', $toDate);
        }
        $this->applyGeoFilter($query, $province, $city, 'cases');

        $issues = (clone $query)
            ->join('case_issues', 'cases.case_issue_id', '=', 'case_issues.id')
            ->where('case_issues.is_deleted', false)
            ->select('case_issues.name', DB::raw('count(*) as total'))
            ->groupBy('case_issues.name', 'case_issues.sort_order')
            ->orderBy('case_issues.sort_order')
            ->orderByDesc('total')
            ->get();

        $chartColors = ['#0b5a8c', '#0b7a75', '#6366f1', '#f59e0b', '#ef4444', '#22c55e', '#8b5cf6', '#ec4899'];

        return $issues->map(fn ($item, $i) => [
            'name' => $item->name,
            'count' => (int) $item->total,
            'color' => $chartColors[$i % count($chartColors)],
        ])->toArray();
    }

    public function getAvgReferralCompletionDays(?string $role = null, ?string $agencyId = null): float
    {
        $avg = Referral::where('status', 'COMPLETED');
        if ($agencyId) {
            $avg->where('agcy_id', $agencyId);
        }
        $avg = $avg->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        return round((float) ($avg ?? 0), 1);
    }

    public function getOverdueReferrals(?string $userId = null, ?string $role = null, ?string $province = null, ?string $city = null, ?string $agencyId = null): array
    {
        $query = Referral::whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])
            ->whereRaw('EXTRACT(EPOCH FROM (NOW() - created_at)) / 86400 > 14');

        if ($agencyId) {
            $query->where('agcy_id', $agencyId);
        }

        if ($province || $city) {
            $query->whereIn('case_id', function ($q) use ($province, $city) {
                $q->select('cases.id')->from('cases')
                    ->join('clients', 'clients.id', '=', 'cases.client_id')
                    ->join('client_addresses', 'client_addresses.client_id', '=', 'clients.id');
                if ($province) {
                    $q->where('client_addresses.province', $province);
                }
                if ($city) {
                    $q->where('client_addresses.city_municipality', $city);
                }
            });
        }

        $count = (clone $query)->count();

        $referrals = $query->with(['caseFile.client', 'agency'])
            ->orderBy('created_at', 'asc')
            ->paginate(10);

        return [
            'count' => $count,
            'referrals' => $referrals,
        ];
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

        $resolver = app(AddressNameResolver::class);
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
        if ($role === 'AGENCY') {
            return [];
        }

        $cacheKey = 'reports:agency_options:'.md5(($userId ?? '').'|'.($role ?? ''));

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

        $cacheKey = 'reports:province_options:'.md5(($userId ?? '').'|'.($role ?? '').'|'.($agencyId ?? ''));

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

            $resolver = app(AddressNameResolver::class);

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

        $cacheKey = 'reports:city_options:'.md5(($province ?? '').'|'.($userId ?? '').'|'.($role ?? '').'|'.($agencyId ?? ''));

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

            $resolver = app(AddressNameResolver::class);

            return $query->pluck('city_municipality')->map(fn ($c) => [
                'value' => $c,
                'label' => $resolver->resolve($c),
            ])->values()->toArray();
        });
    }

    // ── Cache Invalidation ───────────────────────────────────────────────

    /**
     * Flush all reports caches. Called when cases/referrals/agencies change.
     */
    public static function invalidateAll(): void
    {
        // Clear reference data
        cache()->forget(self::KEY_REFERENCE_DATA);

        // Flush all payload caches (prefixed with reports:)
        // Use tag-based clearing or pattern deletion if available;
        // otherwise rely on TTL-based expiry (3 minutes max staleness).
        // For targeted invalidation, we clear reference data immediately
        // and let payloads expire naturally via their short TTL.
    }
}
