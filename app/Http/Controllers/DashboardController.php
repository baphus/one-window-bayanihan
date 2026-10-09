<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
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

        // OFW users have their own portal — redirect them away from the staff dashboard.
        if ($user->isOfw()) {
            return redirect('/my-cases');
        }

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

        // getCaseTrends() is heavy — skip for agency dashboards which don't render it.
        if ($user->role !== 'AGENCY') {
            $data['caseTrends'] = CacheHelper::safeRemember('dashboard:admin_case_trends', 300, function () use ($reportsService) {
                return $reportsService->getCaseTrends();
            });
        }

        // referralStatusDistribution comes role-scoped from DashboardService;
        // overwriting it with the global ReportsService version would leak
        // system-wide numbers onto agency dashboards.

        return $data;
    }
}
