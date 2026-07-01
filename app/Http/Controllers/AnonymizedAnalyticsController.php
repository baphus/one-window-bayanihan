<?php

namespace App\Http\Controllers;

use App\Services\AnonymizedAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnonymizedAnalyticsController extends Controller
{
    public function __construct(
        private readonly AnonymizedAnalyticsService $analyticsService,
    ) {}

    public function index()
    {
        return Inertia::render('AnonymizedAnalytics/Index', [
            'pageTitle' => 'Anonymized Analytics',
            'description' => 'All data is anonymized and aggregated. No personally identifiable information is displayed.',
        ]);
    }

    public function api(Request $request)
    {
        $metric = $request->query('metric', 'summary');

        $data = match ($metric) {
            'cases-by-status' => $this->analyticsService->casesByStatus(),
            'cases-by-service' => $this->analyticsService->casesByService(),
            'cases-over-time' => $this->analyticsService->casesOverTime($request->query('year')),
            'avg-resolution' => $this->analyticsService->averageResolutionTime(),
            'referral-stats' => $this->analyticsService->referralStats(),
            'total-clients' => ['total' => $this->analyticsService->totalClients()],
            default => $this->analyticsService->summary(),
        };

        return response()->json($data);
    }
}
