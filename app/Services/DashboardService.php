<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getCaseManagerData(?User $user = null): array
    {
        // ── EAGER: lightweight COUNT queries only ──────────────────────
        $totalCases = CaseFile::where('status', '!=', 'DRAFT')->count();
        $openCases = CaseFile::where('status', 'OPEN')->count();
        $closedCases = CaseFile::where('status', 'CLOSED')->count();
        $activeAgencies = Agency::where('is_active', true)->count();

        $totalReferrals = Referral::count();
        $pendingReferrals = Referral::where('status', 'PENDING')->count();
        $processingReferrals = Referral::where('status', 'PROCESSING')->count();
        $completedReferrals = Referral::where('status', 'COMPLETED')->count();
        $rejectedReferrals = Referral::where('status', 'REJECTED')->count();

        $ofwCount = CaseFile::where('client_type', 'OFW')->whereNotIn('status', ['DRAFT', 'ARCHIVED'])->count();
        $nokCount = CaseFile::where('client_type', 'NEXT_OF_KIN')->whereNotIn('status', ['DRAFT', 'ARCHIVED'])->count();

        $uniqueClientCount = Referral::join('case_files', 'referrals.case_id', '=', 'case_files.id')
            ->where('case_files.status', '!=', 'DRAFT')
            ->distinct('case_files.client_id')
            ->count('case_files.client_id');

        $myDraftCount = $user ? CaseFile::where('status', 'DRAFT')->where('user_id', $user->id)->count() : 0;

        $myRecentDrafts = $user
            ? CaseFile::with('client')
                ->where('status', 'DRAFT')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'case_number' => $d->case_number,
                    'client_name' => $d->client ? trim(($d->client->first_name ?? '').' '.($d->client->last_name ?? '')) : 'N/A',
                    'created_at' => $d->created_at?->toISOString(),
                ])
                ->toArray()
            : [];

        $closedCaseDays = CaseFile::where('status', 'CLOSED')
            ->selectRaw('EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400 as days')
            ->get()
            ->pluck('days')
            ->filter()
            ->toArray();

        $averageCaseDaysToClose = count($closedCaseDays) > 0
            ? round(array_sum($closedCaseDays) / count($closedCaseDays), 1)
            : 0;

        // ── LAZY: heavy queries as closures; route wraps in Inertia::lazy() ──
        $recentCases = fn () => CaseFile::with(['client', 'user'])
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

        $allCases = fn () => CaseFile::with(['client', 'user'])
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'caseNo' => $c->case_number,
                'trackerNumber' => $c->tracker_number,
                'clientName' => $c->client ? trim(($c->client->first_name ?? '').' '.($c->client->last_name ?? '')) : 'N/A',
                'clientType' => $c->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                'status' => $c->status,
                'createdAt' => $c->created_at?->toISOString() ?? now()->toISOString(),
                'updatedAt' => $c->updated_at?->toISOString() ?? now()->toISOString(),
                'ofwProfile' => null,
            ])
            ->values()
            ->toArray();

        $allReferrals = fn () => Referral::with(['caseFile.client', 'agency'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($r) => [
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
            ])
            ->values()
            ->toArray();

        $casesByProvince = fn () => CaseFile::select('ca.province', DB::raw('count(*) as total'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->leftJoin('clients as c', 'c.id', '=', 'cases.client_id')
            ->leftJoin('client_addresses as ca', 'ca.client_id', '=', 'c.id')
            ->groupBy('ca.province')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['province' => $row->province ?? 'Unknown', 'count' => (int) $row->total])
            ->toArray();

        $agencyBreakdown = fn () => Agency::withCount('referrals')
            ->orderByDesc('referrals_count')
            ->get()
            ->map(fn ($a) => [
                'agencyName' => $a->name,
                'count' => (int) $a->referrals_count,
                'logoUrl' => $a->logo_url ?? '/logo.png',
            ])
            ->toArray();

        $casesOverTime = fn () => CaseFile::select(
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

        $recentActivity = fn () => AuditLog::with('user')
            ->whereNotIn('module', ['clients', 'client', 'client_addresses', 'client_address', 'client_employments', 'client_employment', 'milestones', 'milestone', 'referral_attachments', 'referral_attachment'])
            ->orderBy('timestamp', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($log) => $this->formatActivityLog($log))
            ->toArray();

        $dashboardNotifications = fn () => $user
            ? $user->notifications()->latest()->take(3)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['message'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'time' => $n->created_at->diffForHumans(),
                'read' => $n->read_at !== null,
                'type' => $n->data['type'] ?? 'info',
            ])->toArray()
            : [];

        $casesByCategory = fn () => CaseFile::select('case_categories.name', 'case_categories.color', DB::raw('count(*) as count'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->join('case_categories', 'cases.category_id', '=', 'case_categories.id')
            ->groupBy('case_categories.name', 'case_categories.color')
            ->orderBy('count', 'desc')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'color' => $row->color,
                'count' => (int) $row->count,
            ])
            ->toArray();

        return [
            // Eager — available immediately
            'totalCases' => $totalCases,
            'openCases' => $openCases,
            'closedCases' => $closedCases,
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'completedReferrals' => $completedReferrals,
            'rejectedReferrals' => $rejectedReferrals,
            'activeAgencies' => $activeAgencies,
            'uniqueClientCount' => $uniqueClientCount,
            'ofwCount' => $ofwCount,
            'nokCount' => $nokCount,
            'averageCaseDaysToClose' => $averageCaseDaysToClose,
            'myDraftCount' => $myDraftCount,
            'myRecentDrafts' => $myRecentDrafts,
            // Lazy — Closures, executed on first client request
            'recentCases' => $recentCases,
            'allCases' => $allCases,
            'allReferrals' => $allReferrals,
            'casesByProvince' => $casesByProvince,
            'casesByCategory' => $casesByCategory,
            'agencyBreakdown' => $agencyBreakdown,
            'casesOverTime' => $casesOverTime,
            'recentActivity' => $recentActivity,
            'dashboardNotifications' => $dashboardNotifications,
        ];
    }

    public function getAgencyData(?User $user = null): array
    {
        $agencyId = $user?->agcy_id;

        // EAGER — lightweight COUNT queries
        $totalReferrals = Referral::where('agcy_id', $agencyId)->count();
        $pendingReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'PENDING')->count();
        $processingReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'PROCESSING')->count();
        $completedReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'COMPLETED')->count();
        $rejectedReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'REJECTED')->count();

        // LAZY — closures
        $recentReferrals = fn () => Referral::with(['caseFile.client', 'agency'])
            ->where('agcy_id', $agencyId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        $dashboardNotifications = fn () => $user
            ? $user->notifications()->latest()->take(3)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['message'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'time' => $n->created_at->diffForHumans(),
                'read' => $n->read_at !== null,
                'type' => $n->data['type'] ?? 'info',
            ])->toArray()
            : [];

        $casesByCategory = fn () => CaseFile::select('case_categories.name', 'case_categories.color', DB::raw('count(*) as count'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->join('case_categories', 'cases.category_id', '=', 'case_categories.id')
            ->join('referrals', 'referrals.case_id', '=', 'cases.id')
            ->where('referrals.agcy_id', $agencyId)
            ->groupBy('case_categories.name', 'case_categories.color')
            ->orderBy('count', 'desc')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'color' => $row->color,
                'count' => (int) $row->count,
            ])
            ->toArray();

        $recentActivity = fn () => AuditLog::whereIn('entity_id', function ($q) use ($agencyId) {
            $q->select('id')->from('referrals')->where('agcy_id', $agencyId);
        })
            ->whereIn('module', ['referral', 'referrals'])
            ->orderBy('timestamp', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($log) => $this->formatActivityLog($log))
            ->toArray();

        return [
            // Eager
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'completedReferrals' => $completedReferrals,
            'rejectedReferrals' => $rejectedReferrals,
            // Lazy
            'recentReferrals' => $recentReferrals,
            'recentActivity' => $recentActivity,
            'dashboardNotifications' => $dashboardNotifications,
            'casesByCategory' => $casesByCategory,
        ];
    }

    public function getAdminData(): array
    {
        // EAGER — COUNT queries
        $totalCases = CaseFile::where('status', '!=', 'DRAFT')->count();
        $totalReferrals = Referral::count();
        $totalUsers = User::count();
        $totalAgencies = Agency::count();

        // System health — simple settings lookups
        $alertCount = DB::table('system_alert_logs')
            ->where('is_deleted', false)
            ->whereNull('read_at')
            ->count();

        $lastHealthCheck = SystemSetting::getValue('last_health_check_at', 'Never');
        $overallStatus = SystemSetting::getValue('last_health_check_status', 'unknown');

        // System health — subquery (moderate cost, but fine eager since admin is low-traffic)
        $healthStatus = DB::table('health_check_logs')
            ->select('check_type', 'status', 'metric_value', 'checked_at')
            ->fromSub(function ($q) {
                $q->select('check_type', 'status', 'metric_value', 'checked_at')
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY check_type ORDER BY checked_at DESC) as rn')
                    ->from('health_check_logs');
            }, 'latest')
            ->where('rn', 1)
            ->orderBy('checked_at', 'desc')
            ->get()
            ->toArray();

        // LAZY — closures
        $recentCases = fn () => CaseFile::with(['client', 'user'])
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        $recentLogs = fn () => AuditLog::with('user')
            ->whereNotIn('module', ['clients', 'client', 'client_addresses', 'client_address', 'client_employments', 'client_employment', 'milestones', 'milestone', 'referral_attachments', 'referral_attachment'])
            ->orderBy('timestamp', 'desc')
            ->take(10)
            ->get()
            ->map(function ($log) {
                $formatter = app(AuditLogFormatter::class);
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
                    'message' => $display['message'],
                    'detail' => $display['detail'],
                    'actor' => $display['actor'],
                    'hasChanges' => $display['hasChanges'],
                ];
            })
            ->toArray();

        $casesByCategory = fn () => CaseFile::select('case_categories.name', 'case_categories.color', DB::raw('count(*) as count'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->join('case_categories', 'cases.category_id', '=', 'case_categories.id')
            ->groupBy('case_categories.name', 'case_categories.color')
            ->orderBy('count', 'desc')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'color' => $row->color,
                'count' => (int) $row->count,
            ])
            ->toArray();

        return [
            'totalCases' => $totalCases,
            'totalReferrals' => $totalReferrals,
            'totalUsers' => $totalUsers,
            'totalAgencies' => $totalAgencies,
            'systemHealth' => [
                'checks' => $healthStatus,
                'alertCount' => $alertCount,
                'lastCheckAt' => $lastHealthCheck,
                'overallStatus' => $overallStatus,
            ],
            // Lazy
            'recentCases' => $recentCases,
            'recentLogs' => $recentLogs,
            'casesByCategory' => $casesByCategory,
        ];
    }

    /**
     * Shared audit-log formatting for dashboard activity streams.
     */
    private function formatActivityLog($log): array
    {
        $formatter = app(AuditLogFormatter::class);
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
            'desc' => $display['detail'],
            'time' => $log->timestamp?->diffForHumans() ?? 'N/A',
            'logoSrc' => '/logo.png',
            'message' => $display['message'],
            'detail' => $display['detail'],
            'actionType' => $display['action'],
            'module' => $display['module'],
            'actor' => $display['actor'],
            'timestamp' => $display['timestamp'],
        ];
    }
}
