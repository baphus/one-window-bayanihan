<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardService;
use App\Services\ReportsService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Show the dashboard with deferred data loading.
     */
    public function index()
    {
        $user = request()->user();

        return Inertia::render('Dashboard', [
            'role' => $user->role,
            'dashboard' => Inertia::defer(fn () => $this->loadDashboardData($user), 'dashboard'),
        ]);
    }

    /**
     * Load all dashboard data (executed in a separate deferred request).
     */
    private function loadDashboardData(User $user): array
    {
        $dashboardService = app(DashboardService::class);
        $reportsService = app(ReportsService::class);

        $data = match ($user->role) {
            'AGENCY' => $dashboardService->getAgencyData($user),
            'ADMIN' => $dashboardService->getAdminData(),
            default => $dashboardService->getCaseManagerData($user),
        };

        $data['role'] = $user->role;

        // getCaseTrends() is heavy — only fetch for CASE_MANAGER and ADMIN
        if ($user->role !== 'AGENCY') {
            $data['caseTrends'] = $reportsService->getCaseTrends();
        }

        $data['referralStatusDistribution'] = $reportsService->getReferralStatusDistribution();

        return $data;
    }
}
