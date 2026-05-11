<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function index()
    {
        return Inertia::render('Analytics/Index', [
            'overview' => $this->analyticsService->getOverview(),
            'caseTrends' => $this->analyticsService->getCaseTrends(),
            'referralStatusDistribution' => $this->analyticsService->getReferralStatusDistribution(),
            'agencyWorkload' => $this->analyticsService->getAgencyWorkload(),
            'caseTypeDistribution' => $this->analyticsService->getCaseTypeDistribution(),
        ]);
    }
}
