<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\Referral;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const ACTIVE_REFERRAL_STATUSES = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];

    private const OVERDUE_DAYS = 5;

    public function getCaseManagerData(?User $user = null): array
    {
        $formatter = app(AuditLogFormatter::class);

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

        $maleCount = Client::whereHas('caseFiles', fn ($q) => $q->whereNotIn('status', ['DRAFT', 'ARCHIVED']))
            ->where('sex', 'MALE')
            ->count();

        $femaleCount = Client::whereHas('caseFiles', fn ($q) => $q->whereNotIn('status', ['DRAFT', 'ARCHIVED']))
            ->where('sex', 'FEMALE')
            ->count();

        $pwdCount = Client::whereHas('caseFiles', fn ($q) => $q
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where(fn ($q2) => $q2
                ->where('vulnerability_indicator', 'PWD')
                ->orWhere('nok_vulnerability_indicator', 'PWD')
            )
        )->count();

        $seniorCount = Client::whereHas('caseFiles', fn ($q) => $q
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where(fn ($q2) => $q2
                ->where('vulnerability_indicator', 'Senior Citizen')
                ->orWhere('nok_vulnerability_indicator', 'Senior Citizen')
            )
        )->count();

        $singleParentCount = Client::whereHas('caseFiles', fn ($q) => $q
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->where(fn ($q2) => $q2
                ->where('vulnerability_indicator', 'Solo Parent')
                ->orWhere('nok_vulnerability_indicator', 'Solo Parent')
            )
        )->count();

        $rawProvinces = CaseFile::select('ca.province', DB::raw('count(*) as total'))
            ->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED'])
            ->leftJoin('clients as c', 'c.id', '=', 'cases.client_id')
            ->leftJoin('client_addresses as ca', 'ca.client_id', '=', 'c.id')
            ->whereNotNull('ca.province')
            ->where('ca.province', '!=', '')
            ->groupBy('ca.province')
            ->orderByDesc('total')
            ->get();

        $resolver = app(AddressNameResolver::class);
        $aggregated = [];
        foreach ($rawProvinces as $row) {
            $name = $resolver->resolve($row->province);
            $aggregated[$name] = ($aggregated[$name] ?? 0) + (int) $row->total;
        }
        arsort($aggregated);

        $casesByProvince = collect($aggregated)
            ->map(fn ($count, $province) => ['province' => $province, 'count' => $count])
            ->values()
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
            ->whereNotIn('module', ['clients', 'client', 'client_addresses', 'client_address', 'client_employments', 'client_employment', 'milestones', 'milestone', 'referral_attachments', 'referral_attachment'])
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
                        'changes' => [],
                        'action' => $log->action,
                        'module' => $log->module,
                        'actor' => 'System',
                        'timestamp' => $log->timestamp?->toISOString(),
                        'hasChanges' => false,
                    ];
                }

                $changes = $display['changes'] ?? [];

                return [
                    'id' => $log->id,
                    'title' => $display['message'],
                    'desc' => $this->formatChangeSummary($changes),
                    'time' => $log->timestamp?->diffForHumans() ?? 'N/A',
                    'logoSrc' => '/logo.png',
                    // enriched structured data for modern UI
                    'message' => $display['message'],
                    'detail' => $display['detail'],
                    'changes' => $changes,
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

        $casesByCategory = CaseFile::select('case_categories.name', 'case_categories.color', DB::raw('count(*) as count'))
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

        $referralStatusDistribution = $this->buildStatusDistribution($allReferrals, ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED']);
        $referralAgingBands = $this->buildReferralAgingBands($allReferrals);
        $priorityReferrals = $this->buildPriorityReferrals($allReferrals, 8, true);
        $priorityCases = $this->buildPriorityCases($allCases, $allReferrals, 8);
        $agencyResponseScorecard = $this->buildAgencyResponseScorecard($allReferrals);
        $casesWithoutReferrals = collect($allCases)
            ->filter(fn ($case) => ($case['status'] ?? null) === 'OPEN' && $allReferrals->where('case_id', $case['id'])->isEmpty())
            ->count();

        $workQueue = [
            $this->queueItem('agingOpenCases', 'Aging open cases', collect($allCases)->filter(fn ($case) => ($case['status'] ?? null) === 'OPEN' && $this->ageInDays($case['createdAt'] ?? null) >= 7)->count(), 'Open seven days or more.', 'amber', 'folder_clock', '/cases'),
            $this->queueItem('pendingReferrals', 'Pending referrals', $pendingReferrals, 'Waiting for agency action.', 'amber', 'schedule', '/referrals'),
            $this->queueItem('rejectedReferrals', 'Returned referrals', $rejectedReferrals, 'Needs reassignment or follow-up.', 'rose', 'assignment_return', '/referrals'),
            $this->queueItem('draftCases', 'Draft cases', $myDraftCount, 'Your unfinished case drafts.', 'slate', 'edit_note', '/cases/drafts'),
            $this->queueItem('casesWithoutReferrals', 'Cases without referrals', $casesWithoutReferrals, 'Open cases that may need routing.', 'blue', 'hub', '/cases'),
        ];

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
            'maleCount' => $maleCount,
            'femaleCount' => $femaleCount,
            'pwdCount' => $pwdCount,
            'seniorCount' => $seniorCount,
            'singleParentCount' => $singleParentCount,
            'recentCases' => $recentCases,
            'allCases' => $allCases,
            'allReferrals' => $referralsData,
            'casesByProvince' => $casesByProvince,
            'casesByCategory' => $casesByCategory,
            'agencyBreakdown' => $agencyBreakdown,
            'casesOverTime' => $casesOverTime,
            'referralStatusDistribution' => $referralStatusDistribution,
            'referralAgingBands' => $referralAgingBands,
            'priorityReferrals' => $priorityReferrals,
            'priorityCases' => $priorityCases,
            'agencyResponseScorecard' => $agencyResponseScorecard,
            'workQueue' => $workQueue,
            'recentActivity' => $recentActivity,
            'dashboardNotifications' => $recentNotifications->toArray(),
            'averageCaseDaysToClose' => $averageCaseDaysToClose,
            'myDraftCount' => $myDraftCount,
            'myRecentDrafts' => $myRecentDrafts,
        ];
    }

    public function getAgencyData(?User $user = null): array
    {
        $formatter = app(AuditLogFormatter::class);

        $agencyId = $user?->agcy_id;

        $totalReferrals = Referral::where('agcy_id', $agencyId)->count();
        $pendingReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'PENDING')->count();
        $processingReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'PROCESSING')->count();
        $forComplianceReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'FOR_COMPLIANCE')->count();
        $completedReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'COMPLETED')->count();
        $rejectedReferrals = Referral::where('agcy_id', $agencyId)->where('status', 'REJECTED')->count();

        $agencyReferrals = Referral::with(['caseFile.client', 'agency'])
            ->where('agcy_id', $agencyId)
            ->orderBy('created_at', 'desc')
            ->get();

        $recentReferrals = Referral::with(['caseFile.client', 'agency'])
            ->where('agcy_id', $agencyId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        $referralStatusDistribution = $this->buildStatusDistribution($agencyReferrals, ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED']);
        $referralAgingBands = $this->buildReferralAgingBands($agencyReferrals);
        $priorityReferrals = $this->buildPriorityReferrals($agencyReferrals, 8, false);
        $serviceDemand = $this->buildAgencyServiceDemand($agencyId);
        $feedbackPulse = $this->buildFeedbackPulse($agencyId);
        $overdueReferrals = $agencyReferrals
            ->filter(fn ($referral) => in_array($referral->status, self::ACTIVE_REFERRAL_STATUSES, true) && $this->ageInDays($referral->created_at) >= self::OVERDUE_DAYS)
            ->count();

        $workQueue = [
            $this->queueItem('newReferrals', 'New referrals', $agencyReferrals->filter(fn ($referral) => $this->ageInDays($referral->created_at) <= 2)->count(), 'Received in the last two days.', 'blue', 'move_to_inbox', '/referrals'),
            $this->queueItem('pendingReferrals', 'Pending', $pendingReferrals, 'Needs acknowledgement or first action.', 'amber', 'schedule', '/referrals'),
            $this->queueItem('forComplianceReferrals', 'For compliance', $forComplianceReferrals, 'Waiting on missing requirements.', 'orange', 'fact_check', '/referrals'),
            $this->queueItem('processingReferrals', 'Processing', $processingReferrals, 'Currently being handled.', 'cyan', 'sync', '/referrals'),
            $this->queueItem('overdueReferrals', 'Overdue', $overdueReferrals, 'Active referrals older than five days.', 'rose', 'warning', '/referrals'),
            $this->queueItem('returnedReferrals', 'Returned', $rejectedReferrals, 'Needs review or clarification.', 'rose', 'assignment_return', '/referrals'),
        ];

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
        $casesByCategory = CaseFile::select('case_categories.name', 'case_categories.color', DB::raw('count(*) as count'))
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

        $recentActivity = AuditLog::whereIn('entity_id', $referralIds)
            ->whereIn('module', ['referral', 'referrals'])
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
                        'changes' => [],
                        'action' => $log->action,
                        'module' => $log->module,
                        'actor' => 'System',
                        'timestamp' => $log->timestamp?->toISOString(),
                        'hasChanges' => false,
                    ];
                }

                $changes = $display['changes'] ?? [];

                return [
                    'id' => $log->id,
                    'title' => $display['message'],
                    'desc' => $this->formatChangeSummary($changes),
                    'time' => $log->timestamp?->diffForHumans() ?? 'N/A',
                    'logoSrc' => '/logo.png',
                    'message' => $display['message'],
                    'detail' => $display['detail'],
                    'changes' => $changes,
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
            'forComplianceReferrals' => $forComplianceReferrals,
            'completedReferrals' => $completedReferrals,
            'rejectedReferrals' => $rejectedReferrals,
            'recentReferrals' => $recentReferrals,
            'recentActivity' => $recentActivity,
            'dashboardNotifications' => $pendingNotifications,
            'casesByCategory' => $casesByCategory,
            'workQueue' => $workQueue,
            'referralStatusDistribution' => $referralStatusDistribution,
            'referralAgingBands' => $referralAgingBands,
            'priorityReferrals' => $priorityReferrals,
            'serviceDemand' => $serviceDemand,
            'feedbackPulse' => $feedbackPulse,
        ];
    }

    public function getAdminData(): array
    {
        $formatter = app(AuditLogFormatter::class);

        $activeReferralStatuses = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'];
        $dashboardWindow = now()->subDays(5);

        $totalCases = CaseFile::where('status', '!=', 'DRAFT')->count();
        $openCases = CaseFile::where('status', 'OPEN')->count();
        $closedCases = CaseFile::where('status', 'CLOSED')->count();

        $totalReferrals = Referral::count();
        $pendingReferrals = Referral::where('status', 'PENDING')->count();
        $processingReferrals = Referral::where('status', 'PROCESSING')->count();
        $forComplianceReferrals = Referral::where('status', 'FOR_COMPLIANCE')->count();
        $overdueReferrals = Referral::whereIn('status', $activeReferralStatuses)
            ->where('created_at', '<', $dashboardWindow)
            ->count();

        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $inactiveUsers = max($totalUsers - $activeUsers, 0);

        $totalAgencies = Agency::count();
        $activeAgencies = Agency::where('is_active', true)->count();
        $inactiveAgencies = max($totalAgencies - $activeAgencies, 0);

        $operationalQueues = [
            [
                'key' => 'openCases',
                'label' => 'Open cases',
                'count' => $openCases,
                'note' => 'Active case files on deck.',
                'tone' => 'blue',
                'icon' => 'folder_open',
            ],
            [
                'key' => 'pendingReferrals',
                'label' => 'Pending referrals',
                'count' => $pendingReferrals,
                'note' => 'Waiting for agency action.',
                'tone' => 'amber',
                'icon' => 'schedule',
            ],
            [
                'key' => 'processingReferrals',
                'label' => 'Processing',
                'count' => $processingReferrals,
                'note' => 'Already in motion.',
                'tone' => 'cyan',
                'icon' => 'sync',
            ],
            [
                'key' => 'forComplianceReferrals',
                'label' => 'For compliance',
                'count' => $forComplianceReferrals,
                'note' => 'Needs missing documents.',
                'tone' => 'orange',
                'icon' => 'fact_check',
            ],
            [
                'key' => 'overdueReferrals',
                'label' => 'Overdue referrals',
                'count' => $overdueReferrals,
                'note' => 'Older than five days.',
                'tone' => 'rose',
                'icon' => 'warning',
            ],
        ];

        $usersByRole = User::select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'role' => $row->role,
                'label' => match ($row->role) {
                    'ADMIN' => 'Administrators',
                    'CASE_MANAGER' => 'Case managers',
                    'AGENCY' => 'Agency users',
                    default => $row->role,
                },
                'count' => (int) $row->total,
            ])
            ->toArray();

        $topAgencies = Agency::select('id', 'name', 'is_active')
            ->withCount('referrals')
            ->withCount(['referrals as active_referrals_count' => fn ($query) => $query->whereIn('status', $activeReferralStatuses)])
            ->orderByDesc('active_referrals_count')
            ->orderByDesc('referrals_count')
            ->take(5)
            ->get()
            ->map(fn ($agency) => [
                'id' => $agency->id,
                'name' => $agency->name,
                'isActive' => (bool) $agency->is_active,
                'totalReferrals' => (int) $agency->referrals_count,
                'activeReferrals' => (int) $agency->active_referrals_count,
            ])
            ->toArray();

        $recentCases = CaseFile::with(['client', 'user', 'category'])
            ->whereNotIn('status', ['DRAFT', 'ARCHIVED'])
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'case_number' => $c->case_number,
                'tracker_number' => $c->tracker_number,
                'client_name' => $c->client ? trim(($c->client->first_name ?? '').' '.($c->client->last_name ?? '')) : 'N/A',
                'client_type' => $c->client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin',
                'status' => $c->status,
                'created_at' => $c->created_at?->toISOString() ?? now()->toISOString(),
                'updated_at' => $c->updated_at?->toISOString() ?? now()->toISOString(),
                'case_owner' => $c->user?->name,
                'category' => $c->category?->name,
            ])
            ->toArray();

        $recentLogs = AuditLog::with('user')
            ->whereNotIn('module', ['clients', 'client', 'client_addresses', 'client_address', 'client_employments', 'client_employment', 'milestones', 'milestone', 'referral_attachments', 'referral_attachment'])
            ->orderBy('timestamp', 'desc')
            ->take(8)
            ->get()
            ->map(function ($log) use ($formatter) {
                try {
                    $display = $formatter->formatForDisplay($log);
                } catch (\Throwable $e) {
                    $display = [
                        'message' => $log->action.' '.$log->module,
                        'detail' => '',
                        'changes' => [],
                        'action' => $log->action,
                        'module' => $log->module,
                        'actor' => $log->user?->name ?? 'System',
                        'timestamp' => $log->timestamp?->toISOString(),
                        'hasChanges' => false,
                    ];
                }

                $changes = $display['changes'] ?? [];

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
                    'changes' => $changes,
                    'actor' => $display['actor'],
                    'hasChanges' => $display['hasChanges'],
                ];
            })
            ->toArray();

        $casesByCategory = CaseFile::select('case_categories.name', 'case_categories.color', DB::raw('count(*) as count'))
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

        $stats = [
            'totalCases' => $totalCases,
            'openCases' => $openCases,
            'closedCases' => $closedCases,
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'forComplianceReferrals' => $forComplianceReferrals,
            'overdueReferrals' => $overdueReferrals,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'verifiedUsers' => $verifiedUsers,
            'inactiveUsers' => $inactiveUsers,
            'totalAgencies' => $totalAgencies,
            'activeAgencies' => $activeAgencies,
            'inactiveAgencies' => $inactiveAgencies,
        ];

        return [
            'stats' => $stats,
            'totalCases' => $totalCases,
            'openCases' => $openCases,
            'closedCases' => $closedCases,
            'totalReferrals' => $totalReferrals,
            'pendingReferrals' => $pendingReferrals,
            'processingReferrals' => $processingReferrals,
            'forComplianceReferrals' => $forComplianceReferrals,
            'overdueReferrals' => $overdueReferrals,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'verifiedUsers' => $verifiedUsers,
            'inactiveUsers' => $inactiveUsers,
            'totalAgencies' => $totalAgencies,
            'activeAgencies' => $activeAgencies,
            'inactiveAgencies' => $inactiveAgencies,
            'operationalQueues' => $operationalQueues,
            'usersByRole' => $usersByRole,
            'topAgencies' => $topAgencies,
            'recentCases' => $recentCases,
            'recentLogs' => $recentLogs,
            'casesByCategory' => $casesByCategory,
        ];
    }

    private function formatChangeSummary(array $changes): string
    {
        if (empty($changes)) {
            return '';
        }

        $first = $changes[0];
        $summary = $first['fieldLabel'].': ';

        if ($first['old'] !== null && $first['old'] !== 'not set') {
            $summary .= $first['old'].' → ';
        }

        $summary .= $first['new'] ?? '';

        if (count($changes) > 1) {
            $summary .= ' (+'.(count($changes) - 1).' more)';
        }

        return $summary;
    }

    private function queueItem(string $key, string $label, int $count, string $note, string $tone, string $icon, string $href): array
    {
        return compact('key', 'label', 'count', 'note', 'tone', 'icon', 'href');
    }

    private function buildStatusDistribution($referrals, array $statuses): array
    {
        $total = max($referrals->count(), 1);

        $items = collect($statuses)
            ->map(function (string $status) use ($referrals, $total) {
                $count = $referrals->where('status', $status)->count();

                return [
                    'status' => $status,
                    'label' => $this->statusLabel($status),
                    'count' => $count,
                    'percent' => (int) round(($count / $total) * 100),
                    'tone' => $this->statusTone($status),
                ];
            });

        $knownCount = $items->sum('count');
        $otherCount = max($referrals->count() - $knownCount, 0);
        if ($otherCount > 0) {
            $items->push([
                'status' => 'OTHER',
                'label' => 'Other',
                'count' => $otherCount,
                'percent' => (int) round(($otherCount / $total) * 100),
                'tone' => 'slate',
            ]);
        }

        return $items->filter(fn ($item) => $item['count'] > 0)->values()->toArray();
    }

    private function buildReferralAgingBands($referrals): array
    {
        $active = $referrals->filter(fn ($referral) => in_array($referral->status, self::ACTIVE_REFERRAL_STATUSES, true));
        $bands = [
            ['key' => '0-2', 'label' => '0-2 days', 'min' => 0, 'max' => 2, 'tone' => 'emerald'],
            ['key' => '3-5', 'label' => '3-5 days', 'min' => 3, 'max' => 5, 'tone' => 'amber'],
            ['key' => '6-10', 'label' => '6-10 days', 'min' => 6, 'max' => 10, 'tone' => 'orange'],
            ['key' => '11+', 'label' => '11+ days', 'min' => 11, 'max' => null, 'tone' => 'rose'],
        ];
        $total = max($active->count(), 1);

        return collect($bands)->map(function (array $band) use ($active, $total) {
            $count = $active->filter(function ($referral) use ($band) {
                $age = $this->ageInDays($referral->created_at);

                return $age >= $band['min'] && ($band['max'] === null || $age <= $band['max']);
            })->count();

            return [
                'key' => $band['key'],
                'label' => $band['label'],
                'count' => $count,
                'percent' => (int) round(($count / $total) * 100),
                'tone' => $band['tone'],
            ];
        })->toArray();
    }

    private function buildPriorityReferrals($referrals, int $limit, bool $includeAgency): array
    {
        return $referrals
            ->map(function ($referral) use ($includeAgency) {
                $age = $this->ageInDays($referral->created_at);
                $priority = match ($referral->status) {
                    'REJECTED' => 100 + $age,
                    'FOR_COMPLIANCE' => 80 + $age,
                    'PENDING' => 60 + $age,
                    'PROCESSING' => $age >= self::OVERDUE_DAYS ? 40 + $age : 0,
                    default => 0,
                };

                if ($priority === 0 && in_array($referral->status, self::ACTIVE_REFERRAL_STATUSES, true) && $age >= self::OVERDUE_DAYS) {
                    $priority = 30 + $age;
                }

                return [
                    'id' => $referral->id,
                    'caseId' => $referral->case_id,
                    'caseNo' => $referral->caseFile?->case_number ?? 'N/A',
                    'clientName' => $this->clientName($referral->caseFile?->client),
                    'service' => $referral->required_services ?: 'Service not specified',
                    'agencyName' => $includeAgency ? ($referral->agency?->name ?? 'N/A') : null,
                    'status' => $referral->status,
                    'ageDays' => $age,
                    'href' => '/referrals/'.$referral->id,
                    'priority' => $priority,
                ];
            })
            ->filter(fn ($item) => $item['priority'] > 0)
            ->sortByDesc('priority')
            ->take($limit)
            ->map(fn ($item) => collect($item)->except('priority')->toArray())
            ->values()
            ->toArray();
    }

    private function buildPriorityCases(array $cases, $referrals, int $limit): array
    {
        $referralsByCase = $referrals->groupBy('case_id');

        return collect($cases)
            ->map(function (array $case) use ($referralsByCase) {
                $caseReferrals = $referralsByCase->get($case['id'], collect());
                $age = $this->ageInDays($case['createdAt'] ?? null);
                $latestReferral = $caseReferrals->sortByDesc('updated_at')->first();
                $hasRejected = $caseReferrals->contains(fn ($referral) => $referral->status === 'REJECTED');
                $hasNoReferral = ($case['status'] ?? null) === 'OPEN' && $caseReferrals->isEmpty();
                $isAging = ($case['status'] ?? null) === 'OPEN' && $age >= 7;
                $priority = ($hasRejected ? 100 : 0) + ($hasNoReferral ? 80 : 0) + ($isAging ? 40 + $age : 0);

                return [
                    'id' => $case['id'],
                    'caseNo' => $case['caseNo'] ?? 'N/A',
                    'trackerNumber' => $case['trackerNumber'] ?? null,
                    'clientName' => $case['clientName'] ?? 'N/A',
                    'status' => $case['status'] ?? 'UNKNOWN',
                    'latestReferralStatus' => $latestReferral?->status,
                    'ageDays' => $age,
                    'reason' => $hasRejected ? 'Returned referral' : ($hasNoReferral ? 'No referral yet' : 'Aging open case'),
                    'href' => '/cases/'.$case['id'],
                    'priority' => $priority,
                ];
            })
            ->filter(fn ($item) => $item['priority'] > 0)
            ->sortByDesc('priority')
            ->take($limit)
            ->map(fn ($item) => collect($item)->except('priority')->toArray())
            ->values()
            ->toArray();
    }

    private function buildAgencyResponseScorecard($referrals): array
    {
        return $referrals
            ->groupBy('agcy_id')
            ->map(function ($items) {
                $agency = $items->first()?->agency;
                $active = $items->whereIn('status', self::ACTIVE_REFERRAL_STATUSES);
                $completed = $items->where('status', 'COMPLETED');
                $overdue = $active->filter(fn ($referral) => $this->ageInDays($referral->created_at) >= self::OVERDUE_DAYS)->count();
                $completionDays = $completed
                    ->map(fn ($referral) => $this->daysBetween($referral->created_at, $referral->updated_at))
                    ->filter(fn ($days) => $days !== null)
                    ->values();

                return [
                    'agencyId' => $agency?->id,
                    'agencyName' => $agency?->name ?? 'Unassigned agency',
                    'activeCount' => $active->count(),
                    'overdueCount' => $overdue,
                    'completedCount' => $completed->count(),
                    'averageCompletionDays' => $completionDays->isNotEmpty() ? round($completionDays->avg(), 1) : null,
                    'completionRate' => $items->count() > 0 ? (int) round(($completed->count() / $items->count()) * 100) : 0,
                    'href' => '/referrals',
                ];
            })
            ->sortByDesc(fn ($item) => ($item['overdueCount'] * 10) + $item['activeCount'])
            ->take(6)
            ->values()
            ->toArray();
    }

    private function buildAgencyServiceDemand(?string $agencyId): array
    {
        if (! $agencyId) {
            return [];
        }

        $activeStatuses = self::ACTIVE_REFERRAL_STATUSES;

        return Service::where('agcy_id', $agencyId)
            ->where('is_deleted', false)
            ->orderBy('name')
            ->get()
            ->map(function (Service $service) use ($agencyId, $activeStatuses) {
                $base = Referral::where('agcy_id', $agencyId)->where('required_services', $service->name);
                $total = (clone $base)->count();
                $active = (clone $base)->whereIn('status', $activeStatuses)->count();
                $completed = (clone $base)->where('status', 'COMPLETED')->count();

                return [
                    'serviceId' => $service->id,
                    'serviceName' => $service->name,
                    'totalCount' => $total,
                    'activeCount' => $active,
                    'completedCount' => $completed,
                    'completionRate' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                    'href' => '/referrals',
                ];
            })
            ->filter(fn ($item) => $item['totalCount'] > 0)
            ->sortByDesc('activeCount')
            ->take(6)
            ->values()
            ->toArray();
    }

    private function buildFeedbackPulse(?string $agencyId): array
    {
        if (! $agencyId) {
            return [
                'hasData' => false,
                'totalSent' => 0,
                'totalSubmitted' => 0,
                'responseRate' => 0,
                'avgRating' => null,
                'avgServqual' => null,
                'href' => '/feedbacks',
            ];
        }

        $totalSent = FeedbackInvitation::where('agency_id', $agencyId)->count();
        $feedbackQuery = Feedback::where('agency_id', $agencyId);
        $totalSubmitted = (clone $feedbackQuery)->count();
        $avgRating = (clone $feedbackQuery)->whereNotNull('overall_rating')->avg('overall_rating');
        $avgServqual = DB::table('feedback_servqual_responses')
            ->join('feedback', 'feedback_servqual_responses.feedback_id', '=', 'feedback.id')
            ->where('feedback.agency_id', $agencyId)
            ->whereNotNull('feedback_servqual_responses.perception')
            ->avg('feedback_servqual_responses.perception');

        return [
            'hasData' => $totalSubmitted > 0 || $totalSent > 0,
            'totalSent' => $totalSent,
            'totalSubmitted' => $totalSubmitted,
            'responseRate' => $totalSent > 0 ? round(($totalSubmitted / $totalSent) * 100, 1) : 0,
            'avgRating' => $avgRating ? round((float) $avgRating, 2) : null,
            'avgServqual' => $avgServqual ? round((float) $avgServqual, 2) : null,
            'href' => '/feedbacks',
        ];
    }

    private function ageInDays($timestamp): int
    {
        if (! $timestamp) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($timestamp, false) * -1);
    }

    private function daysBetween($start, $end): ?int
    {
        if (! $start || ! $end) {
            return null;
        }

        return max(0, (int) $start->startOfDay()->diffInDays($end->startOfDay(), false));
    }

    private function clientName($client): string
    {
        if (! $client) {
            return 'N/A';
        }

        return trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: 'N/A';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'PENDING' => 'Pending',
            'PROCESSING' => 'Processing',
            'FOR_COMPLIANCE' => 'For compliance',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Returned',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'PENDING' => 'amber',
            'PROCESSING' => 'blue',
            'FOR_COMPLIANCE' => 'orange',
            'COMPLETED' => 'emerald',
            'REJECTED' => 'rose',
            default => 'slate',
        };
    }
}
