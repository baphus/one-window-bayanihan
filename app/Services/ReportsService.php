<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    public function getAll(
        ?string $userId = null,
        ?string $role = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
    ): array {
        $data = match ($role) {
            'AGENCY' => $this->getAgencyPayload($userId, $fromDate, $toDate, $dateScope, $province, $city),
            'ADMIN' => $this->getAdminPayload($fromDate, $toDate, $dateScope, $province, $city),
            default => $this->getCaseManagerPayload($userId, $fromDate, $toDate, $dateScope, $province, $city),
        };

        $data['role'] = $role;

        return $data;
    }

    private function getCaseManagerPayload(
        ?string $userId,
        ?string $fromDate,
        ?string $toDate,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
    ): array {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'kpis' => $this->getReferralKpis($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'referralStatusDistribution' => $this->getReferralStatusDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'referralAgencyDistribution' => $this->getReferralAgencyDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'casesOverTime' => $this->getCasesOverTime($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'genderDistribution' => $this->getGenderDistribution(),
            'clientTypeDistribution' => $this->getClientTypeDistribution($userId, 'CASE_MANAGER'),
            'ageGroupDistribution' => $this->getAgeGroupDistribution(),
            'mostRequestedService' => $this->getMostRequestedService($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'cycleTimeDistribution' => $this->getReferralCycleTimeDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'referralAging' => $this->getReferralAging($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'agencyScorecard' => $this->getAgencyScorecard($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'geographicDistribution' => $this->getGeographicDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'categoryDistribution' => $this->categoryDistribution($userId, 'CASE_MANAGER'),
            'employmentDistribution' => $this->getLastEmploymentDistribution($userId, 'CASE_MANAGER'),
            'employmentPositionBreakdown' => $this->getEmploymentPositionBreakdown($userId, 'CASE_MANAGER'),
            'caseStatusDistribution' => $this->getCaseStatusDistribution($userId, 'CASE_MANAGER'),
            'caseIssueDistribution' => $this->getCaseIssueDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
            'overdueReferrals' => $this->getOverdueReferrals($userId, 'CASE_MANAGER', $province, $city),
            'cityDistribution' => $this->getCityDistribution($userId, 'CASE_MANAGER', $from, $to, $dateScope, $province, $city),
        ];
    }

    private function getAgencyPayload(
        ?string $userId,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $dateScope = 'case_created_at',
        ?string $province = null,
        ?string $city = null,
    ): array {
        $agency = User::find($userId)?->agency;
        $agcyId = $agency?->id;

        return [
            'kpis' => $this->getReferralKpis(null, 'AGENCY', $fromDate, $toDate, $dateScope, $province, $city),
            'referralStatusDistribution' => $this->getReferralStatusDistribution(null, 'AGENCY', $fromDate, $toDate, $dateScope, $province, $city),
            'referralTrends' => $this->getReferralTrends(),
            'avgReferralCompletion' => $this->getAvgReferralCompletionDays(),
            'cycleTimeDistribution' => $this->getReferralCycleTimeDistribution(null, 'AGENCY', $fromDate, $toDate, $dateScope, $province, $city),
            'agencyScorecard' => $agcyId
                ? $this->getAgencyScorecard(null, 'AGENCY', $fromDate, $toDate, $dateScope, $province, $city)
                : [],
            'categoryDistribution' => $this->categoryDistribution(),
            'caseStatusDistribution' => $this->getCaseStatusDistribution(),
        ];
    }

    private function getAdminPayload(?string $fromDate, ?string $toDate, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null): array
    {
        $from = $fromDate ?: now()->subYear()->toDateString();
        $to = $toDate ?: now()->toDateString();

        return [
            'overview' => $this->getOverview($from, $to),
            'caseTrends' => $this->getCaseTrends(),
            'referralStatusDistribution' => $this->getReferralStatusDistribution(null, null, $from, $to, $dateScope, $province, $city),
            'agencyWorkload' => $this->getAgencyWorkload($from, $to),
            'clientTypeDistribution' => $this->getClientTypeDistribution(),
            'cycleTimeDistribution' => $this->getReferralCycleTimeDistribution(null, null, $from, $to, $dateScope, $province, $city),
            'referralAging' => $this->getReferralAging(null, null, $from, $to, $dateScope, $province, $city),
            'geographicDistribution' => $this->getGeographicDistribution(null, null, $from, $to, $dateScope, $province, $city),
            'agencyScorecard' => $this->getAgencyScorecard(null, null, $from, $to, $dateScope, $province, $city),
            'categoryDistribution' => $this->categoryDistribution(),
            'employmentDistribution' => $this->getLastEmploymentDistribution(),
            'employmentPositionBreakdown' => $this->getEmploymentPositionBreakdown(),
            'caseStatusDistribution' => $this->getCaseStatusDistribution(),
            'caseIssueDistribution' => $this->getCaseIssueDistribution(null, null, $from, $to, $dateScope, $province, $city),
        ];
    }

    public function getOverview(?string $fromDate = null, ?string $toDate = null): array
    {
        $cases = CaseFile::whereNotIn('status', ['DRAFT', 'ARCHIVED']);
        if ($fromDate) {
            $cases->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $cases->whereDate('created_at', '<=', $toDate);
        }

        $referrals = Referral::query();
        if ($fromDate) {
            $referrals->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $referrals->whereDate('created_at', '<=', $toDate);
        }

        return [
            'totalCases' => (int) (clone $cases)->count(),
            'openCases' => (int) (clone $cases)->where('status', 'OPEN')->count(),
            'closedCases' => (int) (clone $cases)->where('status', 'CLOSED')->count(),
            'totalReferrals' => (int) (clone $referrals)->count(),
            'pendingReferrals' => (int) (clone $referrals)->where('status', 'PENDING')->count(),
            'activeAgencies' => (int) Agency::count(),
        ];
    }

    public function getCaseTrends(int $months = 12): array
    {
        $cases = CaseFile::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as month"),
            DB::raw('count(*) as total')
        )
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $cases->pluck('month')->toArray(),
            'data' => $cases->pluck('total')->toArray(),
        ];
    }

    public function getReferralTrends(int $months = 12): array
    {
        $referrals = Referral::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as month"),
            DB::raw('count(*) as total')
        )
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $referrals->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Referrals',
                    'data' => $referrals->pluck('total')->toArray(),
                    'borderColor' => '#0b5a8c',
                    'backgroundColor' => 'rgba(11, 90, 140, 0.1)',
                ],
            ],
        ];
    }

    public function getAgencyWorkload(?string $fromDate = null, ?string $toDate = null): array
    {
        $workload = Agency::withCount(['referrals' => function ($q) use ($fromDate, $toDate) {
            if ($fromDate) {
                $q->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $q->whereDate('created_at', '<=', $toDate);
            }
        }])
            ->orderByDesc('referrals_count')
            ->get();

        return [
            'labels' => $workload->pluck('name')->toArray(),
            'data' => $workload->pluck('referrals_count')->toArray(),
        ];
    }

    public function getClientTypeDistribution(?string $userId = null, ?string $role = null): array
    {
        $query = CaseFile::whereNotIn('cases.status', ['DRAFT', 'ARCHIVED']);
        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('cases.user_id', $userId);
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

    private function caseQuery(?string $userId = null, ?string $role = null, string $dateScope = 'case_created_at')
    {
        $query = CaseFile::whereNotIn('cases.status', ['DRAFT', 'ARCHIVED']);
        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('cases.user_id', $userId);
        }

        return $query;
    }

    private function referralQuery(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at')
    {
        $query = Referral::query();

        if ($dateScope === 'case_created_at') {
            // Subquery avoids JOIN — prevents ambiguous column errors
            $query->whereIn('referrals.case_id', function ($q) use ($userId, $role, $fromDate, $toDate) {
                $q->select('cases.id')->from('cases')
                    ->whereNull('cases.deleted_at');
                if ($role === 'CASE_MANAGER' && $userId) {
                    $q->where('cases.user_id', $userId);
                }
                if ($fromDate) {
                    $q->whereDate('cases.created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $q->whereDate('cases.created_at', '<=', $toDate);
                }
            });
        } else {
            if ($role === 'CASE_MANAGER' && $userId) {
                $query->whereIn('case_id', CaseFile::where('user_id', $userId)->select('id'));
            }
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

    public function getReferralKpis(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null): array
    {
        $from = Carbon::parse($fromDate ?: now()->subYear());
        $to = Carbon::parse($toDate ?: now());

        $referrals = $this->referralQuery($userId, $role, $from->toDateString(), $to->toDateString(), $dateScope);
        $this->applyGeoFilter($referrals, $province, $city);

        $total = (clone $referrals)->count();
        $completed = (clone $referrals)->where('status', 'COMPLETED')->count();
        $pending = (clone $referrals)->where('status', 'PENDING')->count();
        $processing = (clone $referrals)->where('status', 'PROCESSING')->count();
        $rejected = (clone $referrals)->where('status', 'REJECTED')->count();

        $avgDays = (clone $referrals)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        $duration = $from->diffInDays($to);
        $prevFrom = $from->copy()->subDays($duration);
        $prevTo = $from->copy()->subDay();

        $prev = $this->referralQuery($userId, $role, $prevFrom->toDateString(), $prevTo->toDateString(), $dateScope);
        $this->applyGeoFilter($prev, $province, $city);

        $prevTotal = (clone $prev)->count();
        $prevCompleted = (clone $prev)->where('status', 'COMPLETED')->count();
        $prevPending = (clone $prev)->where('status', 'PENDING')->count();
        $prevAvgDays = (clone $prev)
            ->where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        $pct = fn ($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

        return [
            'totalReferrals' => (int) $total,
            'completedReferrals' => (int) $completed,
            'pendingReferrals' => (int) $pending,
            'processingReferrals' => (int) $processing,
            'rejectedReferrals' => (int) $rejected,
            'completionRate' => $total > 0 ? round(($completed / $total) * 100) : 0,
            'avgCompletionDays' => round((float) ($avgDays ?? 0), 1),
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

    public function getCasesOverTime(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null): array
    {
        $cases = $this->caseQuery($userId, $role, $dateScope);
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

    public function getGenderDistribution(): array
    {
        $genders = Client::select('sex', DB::raw('count(*) as total'))
            ->whereNotNull('sex')
            ->groupBy('sex')
            ->pluck('total', 'sex');

        $labels = [];
        $data = [];
        $colors = ['#6366f1', '#ec4899', '#94a3b8'];

        foreach (['MALE', 'FEMALE', 'OTHER'] as $i => $sex) {
            $labels[] = ucfirst(strtolower($sex));
            $data[] = (int) ($genders[$sex] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data, 'colors' => $colors];
    }

    public function getAgeGroupDistribution(): array
    {
        $ages = Client::whereNotNull('date_of_birth')
            ->select(DB::raw("
                CASE
                    WHEN EXTRACT(YEAR FROM age(date_of_birth)) < 18 THEN '0-17'
                    WHEN EXTRACT(YEAR FROM age(date_of_birth)) BETWEEN 18 AND 25 THEN '18-25'
                    WHEN EXTRACT(YEAR FROM age(date_of_birth)) BETWEEN 26 AND 40 THEN '26-40'
                    WHEN EXTRACT(YEAR FROM age(date_of_birth)) BETWEEN 41 AND 60 THEN '41-60'
                    ELSE '60+'
                END as age_group
            "))
            ->selectRaw('count(*) as total')
            ->groupBy('age_group')
            ->pluck('total', 'age_group');

        $groups = ['0-17', '18-25', '26-40', '41-60', '60+'];
        $colors = ['#818cf8', '#6366f1', '#4f46e5', '#4338ca', '#3730a3'];

        return [
            'labels' => $groups,
            'data' => array_map(fn ($g) => (int) ($ages[$g] ?? 0), $groups),
            'colors' => $colors,
        ];
    }

    public function getReferralStatusDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null): array
    {
        $query = $this->referralQuery($userId, $role, $fromDate, $toDate, $dateScope);
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

    public function getReferralAgencyDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null): array
    {
        $query = $this->referralQuery($userId, $role, $fromDate, $toDate, $dateScope);
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

    public function getMostRequestedService(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null): array
    {
        $query = $this->referralQuery($userId, $role, $fromDate, $toDate, $dateScope);
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
    ): array {
        $query = $this->referralQuery($userId, $role, $fromDate, $toDate, $dateScope);
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
    ): array {
        $query = $this->referralQuery($userId, $role, $fromDate, $toDate, $dateScope);
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
    ): array {
        $query = $this->referralQuery($userId, $role, $fromDate, $toDate, $dateScope);
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
    ): array {
        $query = CaseFile::select(DB::raw('COALESCE(pa.name, ca.province) as province'), DB::raw('count(*) as total'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->leftJoin('clients as c', 'c.id', '=', 'cases.client_id')
            ->leftJoin('client_addresses as ca', 'ca.client_id', '=', 'c.id')
            ->leftJoin('philippine_addresses as pa', function ($join) {
                $join->on('pa.name', '=', 'ca.province')
                    ->where('pa.type', '=', 'province');
            })
            ->whereNotNull('ca.province')
            ->where('ca.province', '!=', '');

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('cases.user_id', $userId);
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

        $result = $query->groupBy(DB::raw('COALESCE(pa.name, ca.province)'))
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $result->pluck('province')->toArray(),
            'data' => $result->pluck('total')->toArray(),
        ];
    }

    public function getLastEmploymentDistribution(?string $userId = null, ?string $role = null): array
    {
        $query = DB::table('client_employments')
            ->select('last_country', DB::raw('count(distinct client_id) as total'))
            ->whereNotNull('last_country')
            ->where('is_deleted', false);

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->whereIn('client_id', function ($q) use ($userId) {
                $q->select('client_id')
                    ->from('cases')
                    ->where('user_id', $userId)
                    ->whereNotIn('status', ['DRAFT', 'ARCHIVED']);
            });
        }

        $results = $query->groupBy('last_country')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $results->pluck('last_country')->toArray(),
            'data' => $results->pluck('total')->toArray(),
        ];
    }

    public function getEmploymentPositionBreakdown(?string $userId = null, ?string $role = null): array
    {
        $query = DB::table('client_employments')
            ->select('last_position', DB::raw('count(distinct client_id) as total'))
            ->whereNotNull('last_position')
            ->where('is_deleted', false);

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->whereIn('client_id', function ($q) use ($userId) {
                $q->select('client_id')
                    ->from('cases')
                    ->where('user_id', $userId)
                    ->whereNotIn('status', ['DRAFT', 'ARCHIVED']);
            });
        }

        $results = $query->groupBy('last_position')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $results->pluck('last_position')->toArray(),
            'data' => $results->pluck('total')->toArray(),
        ];
    }

    public function getCaseStatusDistribution(?string $userId = null, ?string $role = null): array
    {
        $query = CaseFile::select('status', DB::raw('count(*) as total'))
            ->whereIn('status', ['OPEN', 'CLOSED']);

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('user_id', $userId);
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

    public function categoryDistribution(?string $userId = null, ?string $role = null): array
    {
        $query = CaseFile::select('case_categories.name', 'case_categories.color', DB::raw('count(*) as total'))
            ->join('case_categories', 'case_categories.id', '=', 'cases.category_id')
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->groupBy('case_categories.name', 'case_categories.color')
            ->orderBy('case_categories.name');

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('cases.user_id', $userId);
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
    ): array {
        $query = $this->caseQuery($userId, $role, $dateScope);
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

    public function getAvgReferralCompletionDays(): float
    {
        $avg = Referral::where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        return round((float) ($avg ?? 0), 1);
    }

    public function getManagedReferrals(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null)
    {
        $query = $this->referralQuery($userId, $role, $fromDate, $toDate, $dateScope);
        $this->applyGeoFilter($query, $province, $city);

        return $query
            ->with(['caseFile.client', 'agency'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function getOverdueReferrals(?string $userId = null, ?string $role = null, ?string $province = null, ?string $city = null): array
    {
        $query = Referral::whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])
            ->whereRaw('EXTRACT(EPOCH FROM (NOW() - created_at)) / 86400 > 14');

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->whereIn('case_id', CaseFile::where('user_id', $userId)->select('id'));
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

    public function getCityDistribution(?string $userId = null, ?string $role = null, ?string $fromDate = null, ?string $toDate = null, string $dateScope = 'case_created_at', ?string $province = null, ?string $city = null): array
    {
        $query = CaseFile::select(DB::raw('COALESCE(pa.name, ca.city_municipality) as city'), DB::raw('count(*) as total'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->leftJoin('clients as c', 'c.id', '=', 'cases.client_id')
            ->leftJoin('client_addresses as ca', 'ca.client_id', '=', 'c.id')
            ->leftJoin('philippine_addresses as pa', function ($join) {
                $join->on('pa.name', '=', 'ca.city_municipality')
                    ->where('pa.type', '=', 'city');
            })
            ->whereNotNull('ca.city_municipality')
            ->where('ca.city_municipality', '!=', '');

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('cases.user_id', $userId);
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

        $result = $query->groupBy(DB::raw('COALESCE(pa.name, ca.city_municipality)'))
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $result->pluck('city')->toArray(),
            'data' => $result->pluck('total')->toArray(),
        ];
    }

    public function getProvinceOptions(?string $userId = null, ?string $role = null): array
    {
        $query = DB::table('client_addresses')
            ->select('province')
            ->whereNotNull('province')
            ->where('province', '!=', '')
            ->where('is_deleted', false)
            ->distinct()
            ->orderBy('province');

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->whereIn('client_id', function ($q) use ($userId) {
                $q->select('client_id')->from('cases')
                    ->where('user_id', $userId)
                    ->whereNotIn('status', ['DRAFT', 'ARCHIVED']);
            });
        }

        return $query->pluck('province')->map(fn ($p) => ['value' => $p, 'label' => $p])->values()->toArray();
    }

    public function getCityOptions(?string $province = null, ?string $userId = null, ?string $role = null): array
    {
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

        if ($role === 'CASE_MANAGER' && $userId) {
            $query->whereIn('client_id', function ($q) use ($userId) {
                $q->select('client_id')->from('cases')
                    ->where('user_id', $userId)
                    ->whereNotIn('status', ['DRAFT', 'ARCHIVED']);
            });
        }

        return $query->pluck('city_municipality')->map(fn ($c) => ['value' => $c, 'label' => $c])->values()->toArray();
    }
}
