<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Requests\ProfilePictureRequest;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\AuditLogFormatter;
use App\Services\CloudinaryAvatarService;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\ReferenceDataService;
use App\Support\CategoryFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filterKeys = ['search', 'client_type', 'sex', 'vulnerability_indicator', 'case_status', 'category_id', 'category_ids', 'case_issue_id', 'agcy_id', 'date_from', 'date_to', 'sort', 'direction', 'per_page'];

        $categoryFilter = CategoryFilter::fromRequest($request);
        $categoryIds = $categoryFilter->ids();

        $clients = Client::where('is_deleted', false)->with([
            'caseFile' => function ($q) use ($user) {
                // ADMIN/CASE_MANAGER: all cases. AGENCY: cases with own referrals.
                if ($user?->isAgency()) {
                    $q->whereHas('referrals', fn ($referrals) => $referrals
                        ->where('agcy_id', $user->agcy_id)
                        ->where('is_deleted', false));
                }

                $q->with([
                    'referrals' => function ($referrals) use ($user) {
                        $referrals->where('is_deleted', false)->with('agency');
                        if ($user?->isAgency()) {
                            $referrals->where('agcy_id', $user->agcy_id);
                        }
                    },
                    'category', 'categories', 'caseIssue',
                ]);
            },
            'addresses',
            'employments',
            'nextOfKin',
        ]);

        // An agency user without an agency has no visible client scope.
        if ($user?->isAgency() && ! $user->agcy_id) {
            $clients->whereRaw('1 = 0');
        }

        // Role-based scoping — ADMIN/CASE_MANAGER: all. AGENCY: own referrals.
        if ($user?->isAgency() && $user->agcy_id) {
            $clients->whereHas('caseFile.referrals', function ($q) use ($user) {
                $q->where('agcy_id', $user->agcy_id);
            });
        }

        // --- Search ---
        if (! empty($request->search)) {
            $search = $request->search;
            $clients->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('middle_initial', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('contact_number', 'ilike', "%{$search}%")
                    ->orWhereHas('caseFile', function ($q) use ($search) {
                        $q->where('case_number', 'ilike', "%{$search}%")
                            ->orWhere('tracker_number', 'ilike', "%{$search}%");
                    });
            });
        }

        // --- Filters ---
        if (! empty($request->sex)) {
            $clients->where('sex', $request->sex);
        }

        if (! empty($request->client_type)) {
            $clients->whereHas('caseFile', function ($q) use ($request) {
                $q->where('client_type', $request->client_type);
            });
        }

        if (! empty($request->vulnerability_indicator)) {
            $clients->whereHas('caseFile', function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    $q2->where('vulnerability_indicator', 'LIKE', "%{$request->vulnerability_indicator}%")
                        ->orWhere('nok_vulnerability_indicator', 'LIKE', "%{$request->vulnerability_indicator}%");
                });
            });
        }

        if (! empty($request->case_status)) {
            $clients->whereHas('caseFile', function ($q) use ($request) {
                $q->where('status', $request->case_status);
            });
        }

        if ($categoryIds !== []) {
            $clients->whereHas('caseFile', function ($q) use ($categoryIds) {
                $q->where(function ($caseQuery) use ($categoryIds) {
                    $caseQuery->whereIn('category_id', $categoryIds)
                        ->orWhereHas('categories', function ($categoryQuery) use ($categoryIds) {
                            $categoryQuery->whereIn('case_categories.id', $categoryIds);
                        });
                });
            });
        }

        if (! empty($request->case_issue_id)) {
            $clients->whereHas('caseFile', function ($q) use ($request) {
                $q->where('case_issue_id', $request->case_issue_id);
            });
        }

        if (! empty($request->agcy_id)) {
            $clients->whereHas('caseFile.referrals', function ($q) use ($request) {
                $q->where('agcy_id', $request->agcy_id);
            });
        }

        if (! empty($request->date_from)) {
            $clients->whereDate('clients.created_at', '>=', $request->date_from);
        }
        if (! empty($request->date_to)) {
            $clients->whereDate('clients.created_at', '<=', $request->date_to);
        }

        // --- Sorting ---
        $sort = $request->input('sort', 'created_at');
        $sort = in_array($sort, ['created_at', 'first_name', 'last_name', 'date_of_birth', 'sex']) ? $sort : 'created_at';
        $direction = $request->input('direction', 'desc');
        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? $direction : 'desc';

        $perPage = min((int) $request->input('per_page', 15), 100);

        $clients = $clients->orderBy($sort, $direction)->paginate($perPage);

        return Inertia::render('Client/Index', [
            'clients' => $clients,
            'filters' => (object) $request->only($filterKeys),
            'stats' => $this->getClientStats($user),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
            'agencies' => app(ReferenceDataService::class)->getAgenciesDropdown(),
            'categories' => app(ReferenceDataService::class)->getActiveCategories(),
            'caseIssues' => app(ReferenceDataService::class)->getActiveIssues(),
            'exportRowCount' => (new DataExportQueries)->countClientsExport($user, array_filter(array_merge(
                $request->only(['search', 'sex', 'client_type', 'vulnerability_indicator', 'case_status', 'category_id', 'case_issue_id', 'agcy_id', 'date_from', 'date_to']),
                ['category_ids' => $categoryIds],
            ))),
        ]);
    }

    private function getClientStats(?User $user): array
    {
        $cacheKey = 'client_stats:'.$user?->id;

        return CacheHelper::safeRemember($cacheKey, 30, function () use ($user) {
            // Single query with conditional aggregation — replaces 10 separate count queries
            $totalClientsExpression = $user?->isAdmin()
                ? '(SELECT COUNT(*) FROM clients WHERE is_deleted = false)'
                : 'COUNT(DISTINCT cl.id)';
            $sql = "SELECT
                {$totalClientsExpression} AS total_clients,
                COUNT(DISTINCT CASE WHEN c.client_type = 'OFW' AND c.status NOT IN ('DRAFT','ARCHIVED') THEN cl.id END) AS ofw_clients,
                COUNT(DISTINCT CASE WHEN c.client_type = 'NOK' AND c.status NOT IN ('DRAFT','ARCHIVED') THEN cl.id END) AS nok_clients,
                COUNT(DISTINCT CASE WHEN c.status NOT IN ('DRAFT','ARCHIVED') AND (c.vulnerability_indicator LIKE '%PWD%' OR c.nok_vulnerability_indicator LIKE '%PWD%') THEN cl.id END) AS vuln_pwd,
                COUNT(DISTINCT CASE WHEN c.status NOT IN ('DRAFT','ARCHIVED') AND (c.vulnerability_indicator LIKE '%Senior Citizen%' OR c.nok_vulnerability_indicator LIKE '%Senior Citizen%') THEN cl.id END) AS vuln_senior,
                COUNT(DISTINCT CASE WHEN c.status NOT IN ('DRAFT','ARCHIVED') AND (c.vulnerability_indicator LIKE '%Solo Parent%' OR c.nok_vulnerability_indicator LIKE '%Solo Parent%') THEN cl.id END) AS vuln_solo,
                COUNT(DISTINCT CASE WHEN c.status NOT IN ('DRAFT','ARCHIVED') AND (c.vulnerability_indicator LIKE '%Indigenous Person%' OR c.nok_vulnerability_indicator LIKE '%Indigenous Person%') THEN cl.id END) AS vuln_indigenous,
                COUNT(DISTINCT CASE WHEN c.status = 'OPEN' THEN cl.id END) AS open_cases
            FROM clients cl
            JOIN cases c ON c.client_id = cl.id AND c.is_deleted = false
            WHERE cl.is_deleted = false";

            $bindings = [];

            if ($user?->isAgency() && ! $user->agcy_id) {
                return [
                    'total_clients' => 0,
                    'ofw_clients' => 0,
                    'nok_clients' => 0,
                    'vulnerability_counts' => ['PWD' => 0, 'Senior Citizen' => 0, 'Solo Parent' => 0, 'Indigenous Person' => 0],
                    'clients_with_open_cases' => 0,
                    'total_referrals' => 0,
                ];
            }

            // AGENCY users see only cases with referrals to their agency; ADMIN and CASE_MANAGER see all
            if ($user && $user->isAgency() && $user->agcy_id) {
                $sql .= ' AND c.id IN (SELECT ref.case_id FROM referrals ref WHERE ref.agcy_id = ? AND ref.is_deleted = false)';
                $bindings[] = $user->agcy_id;
            } elseif ($user && $user->isAgency() && ! $user->agcy_id) {
                $sql .= ' AND 1 = 0';
            }

            $row = DB::selectOne($sql, $bindings);

            // Referrals count (separate query — different base table)
            $refSql = 'SELECT COUNT(*) AS total FROM referrals r
                JOIN cases c ON r.case_id = c.id AND c.is_deleted = false
                WHERE r.is_deleted = false';
            $refBindings = [];

            if ($user && $user->isAgency() && $user->agcy_id) {
                $refSql .= ' AND r.agcy_id = ?';
                $refBindings[] = $user->agcy_id;
            } elseif ($user && $user->isAgency() && ! $user->agcy_id) {
                $refSql .= ' AND 1 = 0';
            }

            $refRow = DB::selectOne($refSql, $refBindings);

            return [
                'total_clients' => (int) ($row->total_clients ?? 0),
                'ofw_clients' => (int) ($row->ofw_clients ?? 0),
                'nok_clients' => (int) ($row->nok_clients ?? 0),
                'vulnerability_counts' => [
                    'PWD' => (int) ($row->vuln_pwd ?? 0),
                    'Senior Citizen' => (int) ($row->vuln_senior ?? 0),
                    'Solo Parent' => (int) ($row->vuln_solo ?? 0),
                    'Indigenous Person' => (int) ($row->vuln_indigenous ?? 0),
                ],
                'clients_with_open_cases' => (int) ($row->open_cases ?? 0),
                'total_referrals' => (int) ($refRow->total ?? 0),
            ];
        });
    }

    public function show(string $id, Request $request)
    {
        $client = Client::with([
            'addresses',
            'employments',
        ])->findOrFail($id);

        $user = $request->user();
        $caseQuery = $client->caseFiles()
            ->where('cases.is_deleted', false)
            ->with(['user']);

        // ADMIN/CASE_MANAGER: all cases. AGENCY: own referral cases only.
        if ($user?->isAgency()) {
            if (! $user->agcy_id) {
                abort(404, 'Client not found.');
            }

            $caseQuery->whereHas('referrals', fn ($q) => $q
                ->where('agcy_id', $user->agcy_id)
                ->where('is_deleted', false));
        }

        $caseQuery->with(['referrals' => function ($q) use ($user) {
            $q->where('is_deleted', false)->with(['agency', 'milestones']);

            if ($user->isAgency()) {
                $q->where('agcy_id', $user->agcy_id);
            }
        }]);

        $case = $caseQuery->latest('cases.created_at')->latest('cases.id')->first();
        // Admin may view any client even without an associated case file.
        if (! $case && ! $user->isAdmin()) {
            abort(404, 'Client not found.');
        }

        // The relationship is explicitly replaced with the authorized case;
        // never serialize Client::caseFile's global latest case here.
        $client->setRelation('caseFile', $case);

        $auditLogs = AuditLog::with('user')
            ->where(function ($q) use ($client) {
                // Direct client changes
                // Direct client changes (from AuditObserver on Client model)
                $q->whereIn('module', ['clients', 'client'])->where('entity_id', $client->id);

                // Case file changes if client has case (from AuditObserver + CaseService)
                if ($client->caseFile) {
                    $q->orWhere(function ($q2) use ($client) {
                        $q2->whereIn('module', ['CASE', 'cases', 'case_files', 'case'])->where('entity_id', $client->caseFile->id);
                    });

                    // Referral changes (from AuditObserver + ReferralService)
                    $referralIds = $client->caseFile->referrals->pluck('id');
                    if ($referralIds->isNotEmpty()) {
                        $q->orWhere(function ($q2) use ($referralIds) {
                            $q2->whereIn('module', ['REFERRAL', 'referrals', 'referral'])->whereIn('entity_id', $referralIds);
                        });

                        // Milestone changes (from ReferralService)
                        $milestoneIds = $client->caseFile->referrals->flatMap->milestones->pluck('id');
                        if ($milestoneIds->isNotEmpty()) {
                            $q->orWhere(function ($q2) use ($milestoneIds) {
                                $q2->whereIn('module', ['MILESTONE', 'milestones', 'milestone'])->whereIn('entity_id', $milestoneIds);
                            });
                        }
                    }
                }
            })
            ->orderBy('timestamp', 'desc')
            ->limit(20)
            ->get();

        // Attach the display fields onto each row, matching the contract
        // AuditLogController uses (raw action/module + formatted_module label +
        // message/changes/actor). See resources/js/lib/audit.jsx.
        $formatter = app(AuditLogFormatter::class);
        $formattedLogs = $auditLogs->map(function ($log) use ($formatter) {
            $display = $formatter->formatForDisplay($log);
            $log->message = $display['message'];
            $log->actor = $display['actor'];
            $log->hasChanges = $display['hasChanges'];
            $log->formatted_module = $display['module'];
            $log->changes = $display['changes'];

            return $log;
        });

        return Inertia::render('Client/Show', [
            'client' => $client,
            'auditLogs' => $formattedLogs,
        ]);
    }

    public function storeAvatar(ProfilePictureRequest $request, string $id)
    {
        $client = Client::findOrFail($id);
        $this->authorizeClientAccess($client, $request->user());

        $file = $request->file('profile_picture');
        try {
            app(CloudinaryAvatarService::class)->deleteByUrl($client->getRawOriginal('avatar_url'));
            $client->avatar_url = app(CloudinaryAvatarService::class)->uploadImage(
                $file,
                'client-profile-pictures',
                'client-'.$client->id,
            );
        } catch (\RuntimeException $e) {
            return redirect()->route('clients.show', $client)->withErrors(['profile_picture' => $e->getMessage()]);
        }
        $client->save();

        return redirect()->route('clients.show', $client)->with('success', 'Profile picture updated successfully.');
    }

    public function destroyAvatar(string $id, Request $request)
    {
        $client = Client::findOrFail($id);
        $this->authorizeClientAccess($client, $request->user());

        if ($client->avatar_url) {
            app(CloudinaryAvatarService::class)->deleteByUrl($client->getRawOriginal('avatar_url'));
        }

        $client->avatar_url = null;
        $client->save();

        return redirect()->route('clients.show', $client)->with('success', 'Profile picture removed successfully.');
    }

    public function exportExcel(Request $request)
    {
        $user = auth()->user();
        $queries = new DataExportQueries;

        $filters = $request->only([
            'search', 'sex', 'client_type', 'vulnerability_indicator', 'case_status', 'category_id', 'case_issue_id', 'agcy_id', 'date_from', 'date_to',
        ]);
        $filters = array_merge($filters, CategoryFilter::fromRequest($request)->toArray());

        $clients = $queries->getClientsExport($user, array_filter($filters));

        $columnMap = self::clientsExportColumnMap();

        // Tag each row with the export timestamp for provenance
        $now = now()->format('Y-m-d H:i:s');
        $clients = $clients->map(function ($row) use ($now) {
            $row->exported_at = $now;

            return $row;
        });

        $filename = 'clients-export-'.now()->format('Ymd-His').'.xlsx';

        return (new DataExportService)->generateSingleSheet('Clients', $columnMap, $clients, $filename);
    }

    /**
     * Business-export column map — no IDs or system fields.
     */
    public static function clientsExportColumnMap(): array
    {
        return [
            ['key' => 'full_name',          'label' => 'Full Name',           'type' => 'string'],
            ['key' => 'sex',                'label' => 'Sex/Gender',          'type' => 'string'],
            ['key' => 'date_of_birth',      'label' => 'Date of Birth',       'type' => 'date'],
            ['key' => 'age',                'label' => 'Age',                 'type' => 'string'],
            ['key' => 'contact_number',     'label' => 'Contact Number',      'type' => 'string'],
            ['key' => 'email',              'label' => 'Email Address',       'type' => 'string'],
            ['key' => 'full_address',       'label' => 'Full Address',        'type' => 'string'],
            ['key' => 'case_number',        'label' => 'Case Number',         'type' => 'string'],
            ['key' => 'case_status',        'label' => 'Case Status',         'type' => 'status'],
            ['key' => 'tracker_number',     'label' => 'Case Tracking ID',    'type' => 'string'],
            ['key' => 'client_type',        'label' => 'Client Type',         'type' => 'string'],
            ['key' => 'vulnerability',      'label' => 'Vulnerability',       'type' => 'string'],
            ['key' => 'issue_concern',      'label' => 'Issues/Concern',      'type' => 'string'],
            ['key' => 'receiving_parties',  'label' => 'Receiving Party/s',   'type' => 'string'],
            ['key' => 'date_of_arrival',    'label' => 'Date of Arrival in PH', 'type' => 'date'],
            ['key' => 'previous_country',   'label' => 'Previous Country',    'type' => 'string'],
            ['key' => 'work_position',      'label' => 'Work Position',       'type' => 'string'],
            ['key' => 'nok_full_name',      'label' => 'NOK Full Name',       'type' => 'string'],
            ['key' => 'nok_contact_number', 'label' => 'NOK Contact No.',     'type' => 'string'],
            ['key' => 'nok_email',          'label' => 'NOK Email',           'type' => 'string'],
            ['key' => 'exported_at',        'label' => 'Exported At',         'type' => 'string'],
        ];
    }

    /**
     * Authorize that the current user can access this client record.
     * ADMIN: all. CASE_MANAGER: client must have a case they own.
     * AGENCY: client must have a case referred to their agency.
     * Returns 404 on mismatch (no 403 — don't reveal existence).
     */
    private function authorizeClientAccess(Client $client, User $user): void
    {
        // ADMIN and CASE_MANAGER: full access to all clients
        if ($user->isAdmin() || $user->isCaseManager()) {
            return;
        }

        if ($user->isAgency() && $user->agcy_id) {
            $hasAccess = $client->caseFiles()
                ->whereHas('referrals', function ($q) use ($user) {
                    $q->where('agcy_id', $user->agcy_id);
                })
                ->exists();

            if (! $hasAccess) {
                abort(404, 'Client not found.');
            }

            return;
        }

        // Unknown role — deny
        abort(404, 'Client not found.');
    }
}
