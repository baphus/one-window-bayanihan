<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Requests\ProfilePictureRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Services\AuditLogFormatter;
use App\Services\CaseService;
use App\Services\CloudinaryAvatarService;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\ReferenceDataService;
use App\Support\CategoryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class ClientController extends Controller
{
    use ResolvesPerPage;

    /**
     * Sortable client-directory columns, and how each maps onto the `clients`
     * table. The array keys ARE the allowlist: a key that is absent falls back
     * to `created_at` silently, exactly as before, so the two can never drift.
     *
     * `name` and `age` are the keys Client/Index.jsx actually sends — they were
     * previously not on the allowlist, so both header clicks silently sorted by
     * `created_at` instead. `name` is the directory's own sort accessor
     * ("last name, first name"), and `age` is the DOB read in reverse so the
     * arrow means what the Age column's heading says. `date_of_birth` holds
     * ciphertext (Client's EncryptedDate cast), so any entry whose columns
     * include it is ordered in PHP (paginateByDecryptedDob), never in SQL.
     *
     * @var array<string, array{columns: array<int, string>, invert?: bool}>
     */
    private const CLIENT_SORT_MAP = [
        'created_at' => ['columns' => ['created_at']],
        'first_name' => ['columns' => ['first_name']],
        'last_name' => ['columns' => ['last_name']],
        'date_of_birth' => ['columns' => ['date_of_birth']],
        'sex' => ['columns' => ['sex']],
        'name' => ['columns' => ['last_name', 'first_name']],
        'age' => ['columns' => ['date_of_birth'], 'invert' => true],
    ];

    public function __construct(
        private readonly DataExportQueries $exportQueries,
        private readonly DataExportService $exportService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filterKeys = ['search', 'account_status', 'client_type', 'sex', 'vulnerability_indicator', 'case_status', 'category_id', 'category_ids', 'case_issue_id', 'agcy_id', 'date_from', 'date_to', 'sort', 'direction', 'per_page'];

        $categoryFilter = CategoryFilter::fromRequest($request);
        $categoryIds = $categoryFilter->ids();

        // The directory includes every active client. Account and case
        // visibility are explicit filters rather than implicit exclusions.
        $clients = Client::where('is_deleted', false)
            ->with([
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
                        // client_type is derived from client.type — eager-load the
                        // back-reference so the directory listing stays at one query.
                        'client',
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
                    ->orWhere('middle_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('contact_number', 'ilike', "%{$search}%")
                    ->orWhereHas('caseFile', function ($q) use ($search) {
                        $q->where('case_number', 'ilike', "%{$search}%")
                            ->orWhere('tracker_number', 'ilike', "%{$search}%");
                    });
            });
        }

        // --- Filters ---
        if ($request->input('account_status') === 'linked') {
            $clients->whereNotNull('clients.user_id');
        } elseif ($request->input('account_status') === 'no_account') {
            $clients->whereNull('clients.user_id');
        }

        if (! empty($request->sex)) {
            $clients->where('sex', $request->sex);
        }

        if (! empty($request->client_type)) {
            $clients->where('type', $request->client_type);
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
        $requested = $request->input('sort');
        $sort = self::CLIENT_SORT_MAP[is_string($requested) ? $requested : 'created_at']
            ?? self::CLIENT_SORT_MAP['created_at'];
        $rawDirection = $request->input('direction', 'desc');
        $direction = is_string($rawDirection) && in_array(strtolower($rawDirection), ['asc', 'desc'], true)
            ? strtolower($rawDirection)
            : 'desc';

        // `age` is stored inverted: the younger the client, the larger the DOB,
        // so sorting the age column in the arrow's direction means sorting DOB
        // against it. Sorting the DOB in the arrow's own direction would put the
        // oldest client first under "Age ascending", which reads backwards.
        $order = ($sort['invert'] ?? false)
            ? ($direction === 'asc' ? 'desc' : 'asc')
            : $direction;

        $perPage = $this->perPage($request, 15);

        // Two clients can share a name or a birth date; without a tiebreak the
        // order is unstable and a row can appear on two pages or on neither.
        // date_of_birth sorts bypass SQL entirely: the column holds ciphertext,
        // so paginateByDecryptedDob() orders the rows in PHP instead.
        if (in_array('date_of_birth', $sort['columns'], true)) {
            $clients = $this->paginateByDecryptedDob($clients, $order, $perPage);
        } else {
            foreach ($sort['columns'] as $column) {
                $clients->orderBy($column, $order);
            }

            $clients->orderBy('clients.id', $order);
            $clients = $clients->paginate($perPage);
        }

        return Inertia::render('Client/Index', [
            'clients' => $clients,
            'filters' => (object) $request->only($filterKeys),
            'stats' => $this->getClientStats($user),
            'agencies' => app(ReferenceDataService::class)->getAgenciesDropdown(),
            'categories' => app(ReferenceDataService::class)->getActiveCategories(),
            'caseIssues' => app(ReferenceDataService::class)->getActiveIssues(),
        ]);
    }

    /**
     * Order the client directory by the clients' date of birth.
     *
     * clients.date_of_birth holds ciphertext (Client's EncryptedDate cast), so
     * it cannot be ordered in SQL: encrypting the same value yields a fresh
     * payload each save, and comparing those bytes is meaningless. The case
     * directory runs into the same wall (CaseService::SORT_KEYS documents it).
     * Here the matching DOBs are decrypted in PHP (the same approach
     * ClientMetrics uses for age metrics), sorted there, and only the
     * current page's rows are hydrated with their eager-loaded relations — in
     * that order. Null DOBs sort after every dated client regardless of
     * direction; the client id breaks ties.
     *
     * @param  Builder<Client>  $query
     * @param  'asc'|'desc'  $order
     * @return LengthAwarePaginator<int, Client>
     */
    private function paginateByDecryptedDob(Builder $query, string $order, int $perPage): LengthAwarePaginator
    {
        $rows = (clone $query)
            ->setEagerLoads([])
            ->get(['clients.id', 'clients.date_of_birth']);

        $hasDate = fn (Client $client) => $client->date_of_birth !== null;
        $dated = $rows->filter($hasDate)->sortBy([['date_of_birth', $order], ['id', $order]]);
        $undated = $rows->reject($hasDate)->pluck('id');
        $orderedIds = $dated->pluck('id')->merge($undated);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $pageIds = $orderedIds->forPage($page, $perPage)->values();

        $position = $pageIds->flip();
        $items = (clone $query)
            ->whereIn('clients.id', $pageIds->all())
            ->get()
            ->sortBy(fn (Client $client) => $position[$client->id])
            ->values();

        return new LengthAwarePaginator($items, $orderedIds->count(), $perPage, $page);
    }

    private function getClientStats(?User $user): array
    {
        $cacheKey = 'client_stats:v2:'.$user?->id;

        return CacheHelper::safeRemember($cacheKey, 30, function () use ($user) {
            // Task 2.2: stats query lives in CaseService (Eloquent/query-builder,
            // bound parameters). The controller only handles HTTP + caching.
            return app(CaseService::class)->getClientDirectoryStats($user);
        });
    }

    public function show(string $id, Request $request)
    {
        $client = Client::with([
            'addresses',
            'employments',
            'nextOfKin',
        ])->findOrFail($id);

        $user = $request->user();
        $caseQuery = $client->caseFiles()
            ->where('cases.is_deleted', false)
            // client_type is derived from client.type — eager-load the
            // back-reference so the case table stays at one query.
            ->with(['user', 'client', 'category', 'caseIssue'])
            ->withCount(['referrals' => fn ($q) => $q->where('is_deleted', false)]);

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

        $cases = $caseQuery->orderBy('cases.created_at', 'desc')
            ->orderBy('cases.id', 'desc')
            ->get();

        $case = $cases->first();
        // Admin and CASE_MANAGER may view any client even without an associated case file.
        // Agency access remains limited to clients with a referral to their agency.
        if (! $case && ! $user->isAdmin() && ! $user->isCaseManager()) {
            abort(404, 'Client not found.');
        }

        // The relationship is explicitly replaced with the authorized case;
        // never serialize Client::caseFile's global latest case here.
        $client->setRelation('caseFile', $case);
        $client->setRelation('cases', $cases);

        // Audit logs scoped to all authorized cases + their referrals/milestones.
        $caseIds = $cases->pluck('id');
        $allReferralIds = $cases->flatMap->referrals->pluck('id');
        $allMilestoneIds = $cases->flatMap->referrals->flatMap->milestones->pluck('id');

        $auditLogs = AuditLog::with('user')
            ->where(function ($q) use ($client, $caseIds, $allReferralIds, $allMilestoneIds) {
                // Direct client changes (from AuditObserver on Client model)
                $q->whereIn('module', ['clients', 'client'])->where('entity_id', $client->id);

                // Case file changes across all authorized cases
                if ($caseIds->isNotEmpty()) {
                    $q->orWhere(function ($q2) use ($caseIds) {
                        $q2->whereIn('module', ['CASE', 'cases', 'case_files', 'case'])->whereIn('entity_id', $caseIds);
                    });
                }

                // Referral changes (from AuditObserver + ReferralService)
                if ($allReferralIds->isNotEmpty()) {
                    $q->orWhere(function ($q2) use ($allReferralIds) {
                        $q2->whereIn('module', ['REFERRAL', 'referrals', 'referral'])->whereIn('entity_id', $allReferralIds);
                    });
                }

                // Milestone changes (from ReferralService)
                if ($allMilestoneIds->isNotEmpty()) {
                    $q->orWhere(function ($q2) use ($allMilestoneIds) {
                        $q2->whereIn('module', ['MILESTONE', 'milestones', 'milestone'])->whereIn('entity_id', $allMilestoneIds);
                    });
                }
            })
            ->orderBy('timestamp', 'desc')
            ->limit(20)
            ->get();

        // Replace database records with the same safe audit response contract
        // used by every other browser activity surface.
        $formatter = app(AuditLogFormatter::class);
        $formattedLogs = $auditLogs
            ->map(fn (AuditLog $log) => $formatter->formatForAuditResponse($log))
            ->values();

        return Inertia::render('Client/Show', [
            'client' => $client,
            'cases' => $cases,
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
        } catch (\Throwable $e) {
            // No exception type escaping from the upload path may produce a
            // 500 — the service already normalizes Cloudinary failures, this
            // covers anything else that slips through.
            return redirect()->route('clients.show', $client)->withErrors([
                'profile_picture' => 'The image could not be uploaded. Please try again later.',
            ]);
        }
        $client->save();

        return redirect()->route('clients.show', $client)->with('success', 'Profile picture updated successfully.');
    }

    public function destroyAvatar(string $id, Request $request)
    {
        $client = Client::findOrFail($id);
        $this->authorizeClientAccess($client, $request->user());

        try {
            if ($client->avatar_url) {
                app(CloudinaryAvatarService::class)->deleteByUrl($client->getRawOriginal('avatar_url'));
            }

            $client->avatar_url = null;
            $client->save();
        } catch (\RuntimeException $e) {
            return redirect()->route('clients.show', $client)->withErrors(['profile_picture' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return redirect()->route('clients.show', $client)->withErrors([
                'profile_picture' => 'The profile picture could not be removed. Please try again later.',
            ]);
        }

        return redirect()->route('clients.show', $client)->with('success', 'Profile picture removed successfully.');
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();

        $filters = array_filter(array_merge($request->only([
            'search', 'account_status', 'sex', 'client_type', 'vulnerability_indicator', 'case_status', 'category_id', 'case_issue_id', 'agcy_id', 'date_from', 'date_to',
        ]), CategoryFilter::fromRequest($request)->toArray()));

        $queries = $this->exportQueries;
        $exportService = $this->exportService;

        $data = $queries->getClientsExport($user, $filters);

        $now = now()->format('Y-m-d H:i:s');
        $data = $data->map(function ($row) use ($now) {
            $row->exported_at = $now;

            return $row;
        });

        $filename = 'clients-export-'.now()->format('Ymd-His').'.xlsx';

        return $exportService->generateSingleSheet(
            'Clients',
            self::clientsExportColumnMap(),
            $data,
            $filename
        );
    }

    /**
     * Live row count for the export dialog. Applies the exact same filters as
     * exportExcel (including the dialog's date range) so the preview always
     * matches what the download produces.
     */
    public function exportCount(Request $request)
    {
        $user = $request->user();

        $filters = array_filter(array_merge($request->only([
            'search', 'account_status', 'sex', 'client_type', 'vulnerability_indicator', 'case_status', 'category_id', 'case_issue_id', 'agcy_id', 'date_from', 'date_to',
        ]), CategoryFilter::fromRequest($request)->toArray()));

        $count = $this->exportQueries->countClientsExport($user, $filters);

        return response()->json(['count' => $count]);
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
            ['key' => 'work_position',      'label' => 'Work Occupation',       'type' => 'string'],
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
