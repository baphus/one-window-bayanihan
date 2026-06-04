<?php

namespace App\Http\Controllers;

use App\Services\AlertService;
use App\Services\InsightsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InsightsController extends Controller
{
    public function __construct(
        private readonly InsightsService $insightsService,
        private readonly AlertService $alertService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $from = $request->query('from', now()->subMonths(6)->toDateString());
        $to = $request->query('to', now()->toDateString());
        $tab = $request->query('tab', 'executive');

        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        $filters = ['from' => $fromDate, 'to' => $toDate];

        $alertData = $this->alertService->getActiveAlerts($user);

        $data = [
            'tab' => $tab,
            'from' => $fromDate->toISOString(),
            'to' => $toDate->toISOString(),
            'alerts' => $alertData['data'],
            'alert_count' => $alertData['data']->count(),
        ];

        match ($tab) {
            'executive' => $data += [
                'kpiCards' => $this->insightsService->getKpiCards($user, $filters),
                'caseTrends' => $this->insightsService->getCaseTrends($user, $filters),
            ],
            'trends' => $data += [
                'caseTrends' => $this->insightsService->getCaseTrends($user, $filters),
                'referralVolume' => $this->insightsService->getReferralVolume($user, $filters),
                'slaCompliance' => $this->insightsService->getSlaCompliance($user, $filters),
            ],
            'distribution' => $data += [
                'statusDistribution' => $this->insightsService->getStatusDistribution($user, $filters),
                'categoryDistribution' => $this->insightsService->getCategoryDistribution($user, $filters),
                'serviceDistribution' => $this->insightsService->getServiceDistribution($user, $filters),
                'geographicDistribution' => $this->insightsService->getGeographicDistribution($user, $filters),
                'clientTypeSplit' => $this->insightsService->getClientTypeSplit($user, $filters),
            ],
            'operational' => $data += [
                'agingCases' => $this->insightsService->getAgingCases($user, $filters),
                'stalledReferrals' => $this->insightsService->getStalledReferrals($user, $filters),
                'overloadedAgencies' => $this->insightsService->getOverloadedAgencies($user, $filters),
                'bottleneckAnalysis' => $this->insightsService->getBottleneckAnalysis($user, $filters),
                'rejectionAnalysis' => $this->insightsService->getRejectionAnalysis($user, $filters),
            ],
            'scorecards' => $data += [
                'caseManagerScorecard' => $this->insightsService->getCaseManagerScorecard($user, $filters),
                'agencyScorecard' => $this->insightsService->getAgencyScorecard($user, $filters),
                'serviceCompletionRate' => $this->insightsService->getServiceCompletionRate($user, $filters),
                'firstResponseTime' => $this->insightsService->getFirstResponseTime($user, $filters),
            ],
            'satisfaction' => $data += [
                'satisfactionTrend' => $this->insightsService->getSatisfactionTrend($user, $filters),
                'servqualScores' => $this->insightsService->getServqualScores($user, $filters),
                'agencySatisfactionRanking' => $this->insightsService->getAgencySatisfactionRanking($user, $filters),
                'feedbackVolume' => $this->insightsService->getFeedbackVolume($user, $filters),
            ],
            'predictive' => $data += [
                'caseVolumeForecast' => $this->insightsService->getCaseVolumeForecast($user)->toArray(),
                'breachProbability' => $this->insightsService->getBreachProbability($user)->toArray(),
                'peakPeriods' => $this->insightsService->getPeakPeriods($user)->toArray(),
                'capacityForecast' => $this->insightsService->getCapacityForecast($user)->toArray(),
            ],
            'alerts' => $data += [],
            default => $data += [
                'kpiCards' => $this->insightsService->getKpiCards($user, $filters),
                'caseTrends' => $this->insightsService->getCaseTrends($user, $filters),
            ],
        };

        return Inertia::render('Insights/Index', $data);
    }
}
