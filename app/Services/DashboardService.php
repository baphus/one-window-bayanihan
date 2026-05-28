<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getCaseManagerData(?User $user = null): array
    {
        $formatter = app(AuditLogFormatter::class);

        $totalCases = CaseFile::where('status', '!=', 'DRAFT')->count();
        $openCases = CaseFile::where('status', 'OPEN')->count();
        $closedCases = CaseFile::where('status', 'CLOSED')->count();
        $pendingReferrals = Referral::where('status', 'PENDING')->count();
        $activeAgencies = Agency::where('is_active', true)->count();

        $allReferrals = Referral::with(['caseFile.client', 'agency'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalReferrals = $allReferrals->count();
        $processingReferrals = $allReferrals->where('status', 'PROCESSING')->count();
        $completedReferrals = $allReferrals->where('status', 'COMPLETED')->count();
        $rejectedReferrals = $allReferrals->where('status', 'REJECTED')->count();

        $uniqueClientCount = $allReferrals->pluck('caseFile.client.first_name')
            ->zip($allReferrals->pluck('caseFile.client.last_name'))
            ->map(fn ($pair) => implode(' ', array_filter($pair->toArray())))
            ->unique()
            ->count();

        $ofwCount = CaseFile::where('client_type', 'OFW')->whereNotIn('status', ['DRAFT', 'ARCHIVED'])->count();
        $nokCount = CaseFile::where('client_type', 'NEXT_OF_KIN')->whereNotIn('status', ['DRAFT', 'ARCHIVED'])->count();

        $casesByProvince = CaseFile::select('ca.province', DB::raw('count(*) as total'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->leftJoin('clients as c', 'c.case_id', '=', 'cases.id')
            ->leftJoin('client_addresses as ca', 'ca.client_id', '=', 'c.id')
            ->groupBy('ca.province')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['province' => $row->province ?? 'Unknown', 'count' => (int) $row->total])
            ->toArray();

        $agencyBreakdown = Agency::withCount('referrals')
            ->orderByDesc('referrals_count')
            ->get()
            ->map(fn ($a) => [
                'agencyName' => $a->name,
                'count' => (int) $a->referrals_count,
                'logoUrl' => $a->logo_url ?? '/logo.png',
            ])
            ->toArray();

        $casesOverTime = CaseFile::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as month"),
            DB::raw('count(*) as total')
        )
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => ['key' => $row->month, 'label' => $row->month, 'count' => (int) $row->total])
            ->toArray();

        $recentActivity = AuditLog::with('user')
            ->orderBy('timestamp', 'desc')
            ->take(10)
            ->get()
            ->map(function ($log) use ($formatter) {
                try {
                    $display = $formatter->formatForDisplay($log);
                } catch (\Throwable $e) {
                    $display = [
                        'message' => $log->action.' '.$log->module,
                        'detail' => '',
                        'action' => $log->action,
                        'module' => $log->module,
                        'actor' => 'System',
                        'timestamp' => $log->timestamp?->toISOString(),
                        'hasChanges' => false,
                    ];
                }

                return [
                    'id' => $log->id,
                    'title' => $display['message'],
                    'desc' => $display['detail'] ?: $display['message'],
                    'time' => $log->timestamp?->diffForHumans() ?? 'N/A',
                    'logoSrc' => '/logo.png',
                    // enriched structured data for modern UI
                    'message' => $display['message'],
                    'detail' => $display['detail'],
                    'actionType' => $display['action'],
                    'module' => $display['module'],
                    'actor' => $display['actor'],
                    'timestamp' => $display['timestamp'],
                ];
            })
            ->toArray();

        $recentCases = CaseFile::with(['client', 'user'])
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'case_number' => $c->case_number,
                'client_name' => $c->client ? trim(($c->client->first_name ?? '').' '.($c->client->last_name ?? '')) : 'N/A',
                'client_type' => $c->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                'created_at' => $c->created_at?->toISOString() ?? now()->toISOString(),
                'status' => $c->status,
            ])
            ->values()
            ->toArray();

        $allCases = CaseFile::with(['client', 'user'])
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'caseNo' => $c->case_number,
                'clientName' => $c->client ? trim(($c->client->first_name ?? '').' '.($c->client->last_name ?? '')) : 'N/A',
                'clientType' => $c->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                'status' => $c->status,
                'createdAt' => $c->created_at?->toISOString() ?? now()->toISOString(),
                'updatedAt' => $c->updated_at?->toISOString() ?? now()->toISOString(),
                'ofwProfile' => null,
            ])
            ->values()
            ->toArray();

        $referralsData = $allReferrals->map(fn ($r) => [
            'id' => $r->id,
            'caseId' => $r->case_id,
            'caseNo' => $r->caseFile?->case_number ?? 'N/A',
            'clientName' => $r->caseFile?->client ? trim(($r->caseFile->client->first_name ?? '').' '.($r->caseFile->client->last_name ?? '')) : 'N/A',
            'service' => $r->required_services,
            'agencyId' => $r->agcy_id,
            'agencyName' => $r->agency?->name ?? 'N/A',
            'status' => $r->status,
            'createdAt' => $r->created_at?->toISOString() ?? now()->toISOString(),
            'updatedAt' => $r->updated_at?->toISOString() ?? now()->toISOString(),
        ])->values()->toArray();

        $recentNotifications = $user
            ? $user->notifications()->latest()->take(3)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['message'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'time' => $n->created_at->diffForHumans(),
                'read' => $n->read_at !== null,
                'type' => $n->data['type'] ?? 'info',
            ])
            : collect();

        $closedCaseDays = CaseFile::where('status', 'CLOSED')
            ->selectRaw('EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400 as days')
            ->get()
            ->pluck('days')
            ->filter()
            ->toArray();

        $averageCaseDaysToClose = count($closedCaseDays) > 0
            ? round(array_sum($closedCaseDays) / count($closedCaseDays), 1)
            : 0;

        return [
            'totalCases' => $totalCases,
            'openCases' => $openCases,
            'closedCases' => $closedCases,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'completedReferrals' => $completedReferrals,
            'rejectedReferrals' => $rejectedReferrals,
            'totalReferrals' => $totalReferrals,
            'activeAgencies' => $activeAgencies,
            'uniqueClientCount' => $uniqueClientCount,
            'ofwCount' => $ofwCount,
            'nokCount' => $nokCount,
            'recentCases' => $recentCases,
            'allCases' => $allCases,
            'allReferrals' => $referralsData,
            'casesByProvince' => $casesByProvince,
            'agencyBreakdown' => $agencyBreakdown,
            'casesOverTime' => $casesOverTime,
            'recentActivity' => $recentActivity,
            'dashboardNotifications' => $recentNotifications->toArray(),
            'averageCaseDaysToClose' => $averageCaseDaysToClose,
        ];
    }

    public function getAgencyData(?User $user = null): array
    {
        $formatter = app(AuditLogFormatter::class);

        $agencyId = $user?->agcy_id;

        $totalReferrals = Referral::where('agcy_id', $agencyId)->count();
        $pendingReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'PENDING')->count();
        $processingReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'PROCESSING')->count();
        $completedReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'COMPLETED')->count();
        $rejectedReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'REJECTED')->count();

        $recentReferrals = Referral::with(['caseFile.client', 'agency'])
            ->where('agcy_id', $agencyId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        $pendingNotifications = $user
            ? $user->notifications()->latest()->take(3)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['message'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'time' => $n->created_at->diffForHumans(),
                'read' => $n->read_at !== null,
                'type' => $n->data['type'] ?? 'info',
            ])
                ->toArray()
            : [];

        $referralIds = Referral::where('agcy_id', $agencyId)->pluck('id');
        $recentActivity = AuditLog::whereIn('entity_id', $referralIds)
            ->where('module', 'referrals')
            ->orderBy('timestamp', 'desc')
            ->take(10)
            ->get()
            ->map(function ($log) use ($formatter) {
                try {
                    $display = $formatter->formatForDisplay($log);
                } catch (\Throwable $e) {
                    $display = [
                        'message' => $log->action.' '.$log->module,
                        'detail' => '',
                        'action' => $log->action,
                        'module' => $log->module,
                        'actor' => 'System',
                        'timestamp' => $log->timestamp?->toISOString(),
                        'hasChanges' => false,
                    ];
                }

                return [
                    'id' => $log->id,
                    'title' => $display['message'],
                    'desc' => $display['detail'] ?: $display['message'],
                    'time' => $log->timestamp?->diffForHumans() ?? 'N/A',
                    'logoSrc' => '/logo.png',
                    'message' => $display['message'],
                    'detail' => $display['detail'],
                    'actionType' => $display['action'],
                    'module' => $display['module'],
                    'actor' => $display['actor'],
                    'timestamp' => $display['timestamp'],
                ];
            })
            ->toArray();

        return [
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'completedReferrals' => $completedReferrals,
            'rejectedReferrals' => $rejectedReferrals,
            'recentReferrals' => $recentReferrals,
            'recentActivity' => $recentActivity,
            'dashboardNotifications' => $pendingNotifications,
        ];
    }

    public function getAdminData(): array
    {
        $formatter = app(AuditLogFormatter::class);

        $totalCases = CaseFile::where('status', '!=', 'DRAFT')->count();
        $totalReferrals = Referral::count();
        $totalUsers = User::count();
        $totalAgencies = Agency::count();

        $recentCases = CaseFile::with(['client', 'user'])
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        $recentLogs = AuditLog::with('user')
            ->orderBy('timestamp', 'desc')
            ->take(10)
            ->get()
            ->map(function ($log) use ($formatter) {
                try {
                    $display = $formatter->formatForDisplay($log);
                } catch (\Throwable $e) {
                    $display = [
                        'message' => $log->action.' '.$log->module,
                        'detail' => '',
                        'action' => $log->action,
                        'module' => $log->module,
                        'actor' => $log->user?->name ?? 'System',
                        'timestamp' => $log->timestamp?->toISOString(),
                        'hasChanges' => false,
                    ];
                }

                return [
                    'id' => $log->id,
                    'action' => $display['action'],
                    'module' => $display['module'],
                    'description' => $display['message'],
                    'user' => $log->user ? ['name' => $log->user->name] : null,
                    'timestamp' => $log->timestamp,
                    // enriched structured data
                    'message' => $display['message'],
                    'detail' => $display['detail'],
                    'actor' => $display['actor'],
                    'hasChanges' => $display['hasChanges'],
                ];
            })
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
