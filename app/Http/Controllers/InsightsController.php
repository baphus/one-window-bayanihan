<?php

namespace App\Http\Controllers;

use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InsightsController extends Controller
{
    public function __construct(
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

        $alertData = $this->alertService->getActiveAlerts($user);

        return Inertia::render('Insights/Index', [
            'tab' => $tab,
            'from' => $fromDate->toISOString(),
            'to' => $toDate->toISOString(),
            'alert_count' => $alertData['data']->count(),
            'agency' => $request->query('agency'),
            'category' => $request->query('category'),
            'case_manager' => $request->query('case_manager'),
            'client_type' => $request->query('client_type'),
        ]);
    }
}
