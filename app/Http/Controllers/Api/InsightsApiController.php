<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InsightsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InsightsApiController extends Controller
{
    public function __construct(
        private readonly InsightsService $insightsService,
    ) {}

    private function resolveDate(?string $date, Carbon $default): Carbon
    {
        return $date ? Carbon::parse($date) : $default;
    }

    public function kpiCards(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getKpiCards($user, $filters)
        );
    }

    public function caseTrends(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getCaseTrends($user, $filters)
        );
    }

    public function referralVolume(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getReferralVolume($user, $filters)
        );
    }

    public function slaCompliance(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getSlaCompliance($user, $filters)
        );
    }

    public function statusDistribution(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getStatusDistribution($user, $filters)
        );
    }

    public function categoryDistribution(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getCategoryDistribution($user, $filters)
        );
    }

    public function serviceDistribution(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getServiceDistribution($user, $filters)
        );
    }

    public function geographicDistribution(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getGeographicDistribution($user, $filters)
        );
    }

    public function clientTypeSplit(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getClientTypeSplit($user, $filters)
        );
    }

    public function agingCases(Request $request)
    {
        $user = $request->user();

        return response()->json(
            $this->insightsService->getAgingCases($user)
        );
    }

    public function stalledReferrals(Request $request)
    {
        $user = $request->user();

        return response()->json(
            $this->insightsService->getStalledReferrals($user)
        );
    }

    public function overloadedAgencies(Request $request)
    {
        $user = $request->user();

        return response()->json(
            $this->insightsService->getOverloadedAgencies($user)
        );
    }

    public function bottleneckAnalysis(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getBottleneckAnalysis($user, $filters)
        );
    }

    public function rejectionAnalysis(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getRejectionAnalysis($user, $filters)
        );
    }

    public function caseManagerScorecard(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getCaseManagerScorecard($user, $filters)
        );
    }

    public function agencyScorecard(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getAgencyScorecard($user, $filters)
        );
    }

    public function serviceCompletionRate(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getServiceCompletionRate($user, $filters)
        );
    }

    public function firstResponseTime(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getFirstResponseTime($user, $filters)
        );
    }

    public function satisfactionTrend(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getSatisfactionTrend($user, $filters)
        );
    }

    public function servqualScores(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getServqualScores($user, $filters)
        );
    }

    public function agencySatisfactionRanking(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getAgencySatisfactionRanking($user, $filters)
        );
    }

    public function feedbackVolume(Request $request)
    {
        $user = $request->user();
        $from = $this->resolveDate($request->query('from'), now()->subMonths(6));
        $to = $this->resolveDate($request->query('to'), now());
        $filters = ['from' => $from, 'to' => $to];

        return response()->json(
            $this->insightsService->getFeedbackVolume($user, $filters)
        );
    }

    public function caseVolumeForecast(Request $request)
    {
        $user = $request->user();

        return response()->json(
            $this->insightsService->getCaseVolumeForecast($user)
        );
    }

    public function breachProbability(Request $request)
    {
        $user = $request->user();

        return response()->json(
            $this->insightsService->getBreachProbability($user)
        );
    }

    public function peakPeriods(Request $request)
    {
        $user = $request->user();

        return response()->json(
            $this->insightsService->getPeakPeriods($user)
        );
    }

    public function capacityForecast(Request $request)
    {
        $user = $request->user();

        return response()->json(
            $this->insightsService->getCapacityForecast($user)
        );
    }
}
