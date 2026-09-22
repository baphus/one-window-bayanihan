<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
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
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filterKeys = ['search', 'client_type', 'sex', 'vulnerability_indicator', 'case_status', 'category_id', 'category_ids', 'case_issue_id', 'agcy_id', 'date_from', 'date_to', 'sort', 'direction', 'per_page'];

        $categoryFilter = CategoryFilter::fromRequest($request);
        $categoryIds = $categoryFilter->ids();

        // The client directory lists established clients. Self-filed intakes that
        // have not been accepted belong in the intake queue only, which reads
        // cases directly and is unaffected by this scope.
        $clients = Client::where('is_deleted', false)->withoutUnacceptedIntake()->with([
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
        ]);
    }

    private function getClientStats(?User $user): array
    {
        $cacheKey = 'client_stats:'.$user?->id;

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

        // Same rule as the directory listing: an unaccepted self-filed intake is
        // read through the intake queue, not the client profile.
        abort_if($client->hasOnlyUnacceptedIntake(), 404, 'Client not found.');

        $user = $request->user();
        $caseQuery = $client->caseFiles()
            ->where('cases.is_deleted', false)
            ->with(['user', 'category', 'caseIssue'])
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
        $user = $request->user();

        $filters = array_filter(array_merge($request->only([
            'search', 'sex', 'client_type', 'vulnerability_indicator', 'case_status', 'category_id', 'case_issue_id', 'agcy_id', 'date_from', 'date_to',
        ]), CategoryFilter::fromRequest($request)->toArray()));

        $queries = new DataExportQueries;
        $exportService = new DataExportService;

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
            'search', 'sex', 'client_type', 'vulnerability_indicator', 'case_status', 'category_id', 'case_issue_id', 'agcy_id', 'date_from', 'date_to',
        ]), CategoryFilter::fromRequest($request)->toArray()));

        $count = (new DataExportQueries)->countClientsExport($user, $filters);

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
        // A client the directory and profile refuse to show must not be
        // mutable either. Without this the avatar routes accepted a write and
        // then redirected to a profile that 404s.
        if ($client->hasOnlyUnacceptedIntake()) {
            abort(404, 'Client not found.');
        }

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
