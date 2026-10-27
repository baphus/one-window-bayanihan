<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
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
     * Period-scoped numbers for the case manager overview.
     *
     * The dashboard's headline KPIs and volume chart are relative to a window
     * (7 / 30 / 90 days), so switching the period refetches only this payload
     * instead of reloading the whole deferred page.
     */
    public function caseManagerPeriod(): JsonResponse
    {
        $user = request()->user();

        abort_unless(
            $user?->role === UserRole::CASE_MANAGER->value,
            403,
            'This summary is only available to case managers.',
        );

        $days = (int) request()->integer('days', 30);

        return response()->json(
            app(DashboardService::class)->getCaseManagerPeriodData($days),
        );
    }

    /**
     * Load all dashboard data (executed in a separate deferred request).
     */
    private function loadDashboardData(User $user): array
    {
        $dashboardService = app(DashboardService::class);

        $data = match ($user->role) {
            UserRole::AGENCY->value => $dashboardService->getAgencyData($user),
            UserRole::ADMIN->value => $dashboardService->getAdminData(),
            default => $dashboardService->getCaseManagerData($user),
        };

        $data['role'] = $user->role;

        // referralStatusDistribution comes role-scoped from DashboardService;
        // overwriting it with the global ReportsService version would leak
        // system-wide numbers onto agency dashboards.

        return $data;
    }
}
