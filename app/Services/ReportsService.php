<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientEmployment;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    public function getAll(?string $userId = null, ?string $role = null): array
    {
        return [
            'kpis' => $this->getKpis($userId, $role),
            'caseStatusDistribution' => $this->getCaseStatusDistribution($userId, $role),
            'casesOverTime' => $this->getCasesOverTime($userId, $role),
            'genderDistribution' => $this->getGenderDistribution(),
            'clientTypeDistribution' => $this->getClientTypeDistribution($userId, $role),
            'ageGroupDistribution' => $this->getAgeGroupDistribution(),
            'previousOccupations' => $this->getPreviousOccupations(),
            'lastEmploymentCountries' => $this->getLastEmploymentCountries(),
            'topOccupation' => $this->getTopOccupation(),
            'topCountry' => $this->getTopCountry(),
            'referralStatusDistribution' => $this->getReferralStatusDistribution($userId, $role),
            'referralAgencyDistribution' => $this->getReferralAgencyDistribution($userId, $role),
            'mostActiveAgency' => $this->getMostActiveAgency(),
            'avgReferralCompletion' => $this->getAvgReferralCompletionDays(),
            'mostRequestedService' => $this->getMostRequestedService(),
        ];
    }

    private function caseQuery(?string $userId = null, ?string $role = null)
    {
        $query = CaseFile::whereNotIn('status', ['DRAFT', 'ARCHIVED']);
        if ($role === 'CASE_MANAGER' && $userId) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    private function referralQuery(?string $userId = null, ?string $role = null)
    {
        $query = Referral::query();
        if ($role === 'CASE_MANAGER' && $userId) {
            $query->whereIn('case_id', CaseFile::where('user_id', $userId)->select('id'));
        }

        return $query;
    }

    public function getKpis(?string $userId = null, ?string $role = null): array
    {
        $cases = $this->caseQuery($userId, $role);
        $totalCases = (clone $cases)->count();
        $openCases = (clone $cases)->where('status', 'OPEN')->count();
        $closedCases = (clone $cases)->where('status', 'CLOSED')->count();

        $avgDaysToClosure = (clone $cases)
            ->where('status', 'CLOSED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        $referrals = $this->referralQuery($userId, $role);
        $totalReferrals = (clone $referrals)->count();

        return [
            'totalCases' => (int) $totalCases,
            'openCases' => (int) $openCases,
            'closedCases' => (int) $closedCases,
            'avgDaysToClosure' => round((float) ($avgDaysToClosure ?? 0), 1),
            'totalReferrals' => (int) $totalReferrals,
        ];
    }

    public function getCaseStatusDistribution(?string $userId = null, ?string $role = null): array
    {
        $statuses = (clone $this->caseQuery($userId, $role))
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $allStatuses = ['OPEN', 'CLOSED'];
        $colorMap = ['OPEN' => '#22c55e', 'CLOSED' => '#6366f1'];

        return [
            'labels' => $allStatuses,
            'data' => array_map(fn ($s) => (int) ($statuses[$s] ?? 0), $allStatuses),
            'colors' => array_map(fn ($s) => $colorMap[$s], $allStatuses),
        ];
    }

    public function getCasesOverTime(?string $userId = null, ?string $role = null): array
    {
        $cases = (clone $this->caseQuery($userId, $role))
            ->select(
                DB::raw("to_char(created_at, 'YYYY-MM') as month"),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'labels' => $cases->pluck('month')->toArray(),
            'datasets' => [
                [
                    'label' => 'Cases Created',
                    'data' => $cases->pluck('total')->toArray(),
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

    public function getClientTypeDistribution(?string $userId = null, ?string $role = null): array
    {
        $types = (clone $this->caseQuery($userId, $role))
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

    public function getPreviousOccupations(): array
    {
        $occupations = ClientEmployment::select('position', DB::raw('count(*) as total'))
            ->whereNotNull('position')
            ->groupBy('position')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $occupations->pluck('position')->toArray(),
            'data' => $occupations->pluck('total')->toArray(),
        ];
    }

    public function getLastEmploymentCountries(): array
    {
        $countries = ClientEmployment::select('country', DB::raw('count(*) as total'))
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $countries->pluck('country')->toArray(),
            'data' => $countries->pluck('total')->toArray(),
        ];
    }

    public function getTopOccupation(): array
    {
        $top = ClientEmployment::select('position', DB::raw('count(*) as total'))
            ->whereNotNull('position')
            ->groupBy('position')
            ->orderByDesc('total')
            ->first();

        return [
            'label' => $top?->position ?? 'N/A',
            'value' => (int) ($top?->total ?? 0),
        ];
    }

    public function getTopCountry(): array
    {
        $top = ClientEmployment::select('country', DB::raw('count(*) as total'))
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('total')
            ->first();

        return [
            'label' => $top?->country ?? 'N/A',
            'value' => (int) ($top?->total ?? 0),
        ];
    }

    public function getReferralStatusDistribution(?string $userId = null, ?string $role = null): array
    {
        $statuses = (clone $this->referralQuery($userId, $role))
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
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

    public function getReferralAgencyDistribution(?string $userId = null, ?string $role = null): array
    {
        $agencies = (clone $this->referralQuery($userId, $role))
            ->select('agcy_id', DB::raw('count(*) as total'))
            ->groupBy('agcy_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $agencyNames = Agency::whereIn('id', $agencies->pluck('agcy_id'))->pluck('name', 'id');

        return [
            'labels' => $agencies->map(fn ($r) => $agencyNames[$r->agcy_id] ?? 'Unknown')->toArray(),
            'data' => $agencies->pluck('total')->toArray(),
        ];
    }

    public function getMostActiveAgency(): array
    {
        $top = Agency::withCount('referrals')
            ->orderByDesc('referrals_count')
            ->first();

        return [
            'name' => $top?->name ?? 'N/A',
            'value' => (int) ($top?->referrals_count ?? 0),
        ];
    }

    public function getAvgReferralCompletionDays(): float
    {
        $avg = Referral::where('status', 'COMPLETED')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400) as avg_days'))
            ->value('avg_days');

        return round((float) ($avg ?? 0), 1);
    }

    public function getMostRequestedService(): array
    {
        $top = Referral::select('required_services', DB::raw('count(*) as total'))
            ->whereNotNull('required_services')
            ->groupBy('required_services')
            ->orderByDesc('total')
            ->first();

        return [
            'name' => $top?->required_services ?? 'N/A',
            'value' => (int) ($top?->total ?? 0),
        ];
    }

    public function getManagedCases(?string $userId = null, ?string $role = null)
    {
        return $this->caseQuery($userId, $role)
            ->with(['client', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function getManagedReferrals(?string $userId = null, ?string $role = null)
    {
        return $this->referralQuery($userId, $role)
            ->with(['caseFile.client', 'agency'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function getManagedClients()
    {
        return Client::with(['caseFile' => function ($q) {
            $q->with('referrals.agency');
        }])->orderBy('created_at', 'desc')->paginate(10);
    }
}
