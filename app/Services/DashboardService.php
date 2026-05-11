<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Models\Agency;
use App\Models\AuditLog;

class DashboardService
{
    public function getCaseManagerData(): array
    {
        $totalCases = CaseFile::count();
        $openCases = CaseFile::where('status', 'OPEN')->count();
        $pendingReferrals = Referral::where('status', 'PENDING')->count();
        $activeAgencies = Agency::where('is_active', true)->count();

        $recentCases = CaseFile::with(['client', 'user'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        $recentReferrals = Referral::with(['caseFile.client', 'agency'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        return [
            'totalCases' => $totalCases,
            'openCases' => $openCases,
            'pendingReferrals' => $pendingReferrals,
            'activeAgencies' => $activeAgencies,
            'recentCases' => $recentCases,
            'recentReferrals' => $recentReferrals,
        ];
    }

    public function getAgencyData(string $agencyId): array
    {
        $totalReferrals = Referral::where('agcy_id', $agencyId)->count();
        $pendingReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'PENDING')->count();
        $processingReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'PROCESSING')->count();
        $completedReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'COMPLETED')->count();

        $recentReferrals = Referral::with(['caseFile.client', 'agency'])
            ->where('agcy_id', $agencyId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        return [
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'completedReferrals' => $completedReferrals,
            'recentReferrals' => $recentReferrals,
        ];
    }

    public function getAdminData(): array
    {
        $totalCases = CaseFile::count();
        $totalReferrals = Referral::count();
        $totalUsers = User::count();
        $totalAgencies = Agency::count();

        $recentCases = CaseFile::with(['client', 'user'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        $recentLogs = AuditLog::with('user')
            ->orderBy('timestamp', 'desc')
            ->take(10)
            ->get()
            ->toArray();

        return [
            'totalCases' => $totalCases,
            'totalReferrals' => $totalReferrals,
            'totalUsers' => $totalUsers,
            'totalAgencies' => $totalAgencies,
            'recentCases' => $recentCases,
            'recentLogs' => $recentLogs,
        ];
    }
}
