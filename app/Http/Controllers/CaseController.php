<?php

namespace App\Http\Controllers;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\StoreIntakeRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Mail\IntakeReviewMail;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\SystemSetting;
use App\Models\User;
use App\Rules\VulnerabilityRule;
use App\Services\AuditLogger;
use App\Services\CaseService;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\OnboardingService;
use App\Services\ReferenceDataService;
use App\Services\TrackingService;
use App\Support\CategoryFilter;
use App\Support\MultiSelectFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CaseController extends Controller
{
    public function __construct(
        private readonly CaseService $caseService,
        private readonly TrackingService $trackingService,
        private readonly ReferenceDataService $referenceData,
        private readonly DataExportQueries $exportQueries,
        private readonly DataExportService $exportService,
        private readonly OnboardingService $onboardingService,
    ) {}

    public function index(Request $request)
    {
        $categoryFilters = CategoryFilter::fromRequest($request)->toArray();

        // Filter options come from the reference tables, so a value the page
        // offers is always a value the database actually holds.
        $caseStatusOptions = array_column($this->referenceData->getCaseStatusOptions(), 'value');
        $clientTypeOptions = array_column($this->referenceData->getClientTypeOptions(), 'value');
        $vulnerabilityOptions = array_column($this->referenceData->getVulnerabilityOptions(), 'value');

        // agcy_id is the agencies FK column name (also on users/referrals),
        // not a typo for agency_id — keep the query param name in sync.
        // The sort rule is generated from CaseService::SORT_KEYS so an accepted
        // sort key can never drift from one getCases() can actually order by.
        //
        // List filters accept both `?status=OPEN` and `?status[]=OPEN&status[]=CLOSED`;
        // MultiSelectFilter::rule validates whichever form arrives while keeping
        // the error key as the plain attribute name.
        $listing = $request->validate([
            'status' => MultiSelectFilter::rule($caseStatusOptions),
            'client_type' => MultiSelectFilter::rule($clientTypeOptions),
            'search' => ['nullable', 'string', 'max:255'],
            'vulnerability_indicator' => MultiSelectFilter::rule($vulnerabilityOptions),
            'user_id' => ['nullable'],
            'agcy_id' => ['nullable'],
            'case_issue_id' => ['nullable'],
            'age_min_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'referral_state' => ['nullable', 'string', 'in:none'],
            'date_from' => ['nullable', 'string', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'string', 'date_format:Y-m-d'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', array_keys(CaseService::SORT_KEYS))],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $filters = array_merge($listing, $categoryFilters);

        // Normalise the list filters to arrays so the query layer and the echoed
        // `filters` prop always agree on one shape.
        foreach (['status', 'client_type', 'vulnerability_indicator', 'user_id', 'agcy_id', 'case_issue_id'] as $key) {
            $values = MultiSelectFilter::ids($filters[$key] ?? null);
            $filters[$key] = $values === [] ? null : $values;
        }

        $cases = $this->caseService->getCases(
            $filters,
            $listing['sort'] ?? 'created_at',
            $listing['direction'] ?? 'desc',
            (int) ($listing['per_page'] ?? 15)
        );

        return Inertia::render('Case/Index', [
            'cases' => $cases,
            'filters' => (object) array_filter($filters, fn ($value) => $value !== null),
            'stats' => $this->caseService->getCaseStats($request->user()),
            'users' => $this->referenceData->getCaseManagerUsers(),
            'agencies' => $this->referenceData->getAgenciesDropdown(),
            'categories' => $this->referenceData->getActiveCategories(),
            'caseIssues' => $this->referenceData->getActiveIssues(),
            'statuses' => $this->referenceData->getCaseStatusOptions(),
            'clientTypes' => $this->referenceData->getClientTypeOptions(),
            'vulnerabilities' => $this->referenceData->getVulnerabilityOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $client = null;
        if ($request->has('client_id')) {
            $client = Client::with(['addresses', 'employments', 'nextOfKin', 'caseFiles'])->find($request->client_id);
            // OFW self-file: only their own linked client may prefill.
            if ($request->user()->isOfw() && (! $client || $client->id !== $request->user()->client_id)) {
                $client = $request->user()->client
                    ? $request->user()->client->load(['addresses', 'employments', 'nextOfKin', 'caseFiles'])
                    : null;
            }
        } elseif ($request->user()->isOfw() && $request->user()->client) {
            $client = $request->user()->client->load(['addresses', 'employments', 'nextOfKin', 'caseFiles']);
        }

        return Inertia::render('Case/Create', [
            'client' => $client,
            'categories' => $this->referenceData->getActiveCategories(),
            'caseIssues' => $this->referenceData->getActiveIssues(),
            'occupationOptions' => $this->referenceData->getOccupationOptions(),
        ]);
    }

    public function store(StoreCaseRequest $request)
    {
        $validated = $request->validated();

        $case = $this->caseService->createCase(
            $validated,
            $request->user()->id,
        );

        $this->onboardingService
            ->markChecklistItemQuietly($request->user(), 'create-first-case');

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Case created successfully.');
    }

    public function intake()
    {
        $user = auth()->user();

        // Resolve linked client same way storeIntake does: OFW always files for
        // their own linked client. If no client yet, profile is empty so the
        // intake form can prompt the user to complete their profile first.
        $client = $user && $user->client
            ? $user->client->load(['addresses', 'employments', 'nextOfKin'])
            : null;

        // Mirror OfwProfileController's singular address/employment payload so the
        // Intake "Review your details" panel reads profile.address.* / profile.employment.*
        // (the raw loaded addresses[]/employments[] collections are retained too).
        $profile = null;
        if ($client) {
            $profile = $client->toArray();

            $address = $client->addresses()->orderBy('created_at')->first();
            $employment = $client->employments()->orderBy('created_at')->first();

            $profile['address'] = $address ? [
                'region' => $address->region,
                'province' => $address->province,
                'city_municipality' => $address->city_municipality,
                'barangay' => $address->barangay,
                'street' => $address->street,
            ] : null;

            $profile['employment'] = $employment ? [
                'employer_name' => $employment->employer_name,
                'position' => $employment->position,
                'country' => $employment->country,
                'start_date' => $employment->start_date?->toDateString(),
                'end_date' => $employment->end_date?->toDateString(),
                'last_country' => $employment->last_country,
                'last_position' => $employment->last_position,
                'date_of_arrival' => $employment->date_of_arrival?->toDateString(),
            ] : null;
        }

        return Inertia::render('OFW/Intake', [
            'profile' => $profile,
            'vulnerabilityOptions' => VulnerabilityRule::VALUES,
        ]);
    }

    public function storeIntake(StoreIntakeRequest $request)
    {
        $validated = $request->validated();

        // Resolve client_id from the authenticated OFW user
        $user = $request->user();
        $client = $user->client;

        if (! $client) {
            return back()
                ->withInput()
                ->withErrors(['client' => 'No linked client found. Please complete your profile first.']);
        }

        // Build case data from the explicit allow-list only. StoreIntakeRequest
        // already drops category_id / category_ids / case_issue_id, but repeat
        // the allow-list here so a future rule change cannot silently widen the
        // intake boundary (defence in depth).
        $caseData = array_merge(Arr::only($validated, ['summary', 'vulnerability_indicator', 'consent']), [
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        $case = $this->caseService->createCase(
            $caseData,
            $user->id,
        );

        Mail::to($user->email)->queue((new IntakeReviewMail($case))->afterCommit());

        $caseManagers = User::where('role', UserRole::CASE_MANAGER->value)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->get(['id', 'email']);

        foreach ($caseManagers as $caseManager) {
            Mail::to($caseManager->email)->queue((new IntakeReviewMail($case))->afterCommit());
        }

        if ($user->role === UserRole::OFW->value) {
            AuditLogger::fileIntake()
                ->by($user)
                ->forEntity($case, $case->case_number ?? $case->tracker_number)
                ->module(AuditModule::CASE)
                ->write();
        }

        return redirect()
            ->route('ofw.dashboard')
            ->with('success', 'Case submitted successfully. It is now pending review.');
    }

    public function reviewPending(Request $request)
    {
        $listing = $request->validate([
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ]);
        $direction = $listing['direction'] ?? 'asc';

        $cases = CaseFile::with(['client'])
            ->where('status', CaseFile::STATUS_PENDING_REVIEW)
            ->where('is_deleted', false)
            ->when($request->user()->isAgency() && $request->user()->agcy_id, function ($query) use ($request) {
                $query->whereHas('referrals', fn ($q) => $q->where('agcy_id', $request->user()->agcy_id));
            })
            ->orderBy('created_at', $direction)
            ->paginate((int) ($listing['per_page'] ?? 15));

        return Inertia::render('Case/ReviewPending', [
            'cases' => $cases,
            'filters' => (object) $request->only(['search']),
            'sort' => 'created_at',
            'direction' => $direction,
            'categories' => $this->referenceData->getActiveCategories(),
            'caseIssues' => $this->referenceData->getActiveIssues(),
        ]);
    }

    public function reviewApprove(Request $request, CaseFile $case)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [UserRole::CASE_MANAGER->value, UserRole::ADMIN->value], true), 403);

        abort_unless($case->status === CaseFile::STATUS_PENDING_REVIEW, 404);

        // Require category_ids and case_issue_id at approve time for cases
        // that don't already have categories (backward compat: staff-created
        // cases already have categories so approve still works without re-entry).
        $hasExistingCategories = $case->categories()->exists();

        $categoryIds = [];
        $caseIssueId = null;

        if (! $hasExistingCategories) {
            // Validate category_ids and case_issue_id for intake-created cases
            $validated = $request->validate([
                'category_ids' => ['required', 'array', 'min:1'],
                'category_ids.*' => ['bail', 'uuid', 'distinct', Rule::exists('case_categories', 'id')->where('is_active', true)],
                'case_issue_id' => ['required', 'string', 'exists:case_issues,id'],
            ]);

            $categoryIds = $validated['category_ids'];
            $caseIssueId = $validated['case_issue_id'];
        }
        // Backward compat: staff-created cases already have categories;
        // no need to re-validate or re-sync

        // Flip to OPEN via approveCase (atomic transaction, sets reviewed_by/Reviewed_at).
        // Category IDs and case issue ID are synced within the transaction if provided.
        $case = $this->caseService->approveCase(
            $case->id,
            $user->id,
            $categoryIds,
            $caseIssueId,
        );

        return redirect()
            ->route('cases.review-pending')
            ->with('success', 'Case approved successfully.');
    }

    public function reviewReject(Request $request, CaseFile $case)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [UserRole::CASE_MANAGER->value, UserRole::ADMIN->value], true), 403);

        abort_unless($case->status === CaseFile::STATUS_PENDING_REVIEW, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->caseService->rejectCase($case->id, $validated['reason'], $user->id);

        return redirect()
            ->route('cases.review-pending')
            ->with('success', 'Case rejected.');
    }

    public function show(string $id, Request $request)
    {
        $case = $this->caseService->getCase($id);
        $this->authorizeCaseAccess($case, $request->user());

        // Agency users only receive general case files plus their own
        // referral's files; other agencies' referral documents stay
        // server-side (download routes re-check regardless).
        if ($request->user()->isAgency()) {
            $case->load(['documents' => fn ($q) => $q
                ->where('is_deleted', false)
                ->visibleToAgency($request->user()->agcy_id)]);
            $userAgencyId = $request->user()->agcy_id;
            foreach ($case->referrals as $referral) {
                if (! $userAgencyId || $referral->agcy_id !== $userAgencyId) {
                    $referral->unsetRelation('attachments');
                }
            }
        }
        $overdueDays = (int) SystemSetting::getValue('referral_overdue_days', 7);

        $trackingData = $this->trackingService->buildTrackingData($case);

        $categories = $this->referenceData->getActiveCategories();
        $caseIssues = $this->referenceData->getActiveIssues();

        return Inertia::render('Case/Show', [
            'case' => $case,
            'overdueDays' => $overdueDays,
            'milestoneTimeline' => $trackingData['milestoneTimeline'],
            'categories' => $categories,
            'caseIssues' => $caseIssues,
        ]);
    }

    public function update(UpdateCaseRequest $request, string $id)
    {
        $case = $this->caseService->getCase($id);
        $this->authorizeCaseAccess($case, $request->user());
        $case = $this->caseService->updateCase(
            $id,
            $request->validated(),
            $request->user()->id,
        );

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Case details updated successfully.');
    }

    public function toggleStatus(Request $request, string $id)
    {
        $case = $this->caseService->getCase($id);
        $this->authorizeCaseAccess($case, $request->user());
        $case = $this->caseService->toggleCaseStatus($id, $request->user()->id);

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Case status updated successfully.');
    }

    public function archive(Request $request, string $id)
    {
        $case = $this->caseService->getCase($id);
        $this->authorizeCaseAccess($case, $request->user());
        $case = $this->caseService->archiveCase(
            $id,
            $request->user()->id,
        );

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Case archived successfully.');
    }

    public function unarchive(Request $request, string $id)
    {
        $case = $this->caseService->getCase($id);
        $this->authorizeCaseAccess($case, $request->user());
        $case = $this->caseService->unarchiveCase(
            $id,
            $request->user()->id,
        );

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Case restored from archive successfully.');
    }

    /**
     * Filter keys the case export honours. Shared by the download and the live
     * count so the two can never drift apart.
     */
    private const CASE_EXPORT_FILTER_KEYS = [
        'status', 'search', 'client_type', 'vulnerability_indicator',
        'user_id', 'agcy_id', 'case_issue_id',
        'age_min_days', 'referral_state', 'date_from', 'date_to',
    ];

    /**
     * Build the export filter array from the request.
     *
     * List filters arrive as arrays from the filter panel (or as the legacy
     * scalar form), so they are normalised here before reaching the query
     * layer — which reads them through MultiSelectFilter.
     */
    private function caseExportFilters(Request $request): array
    {
        $filters = array_merge(
            $request->only(self::CASE_EXPORT_FILTER_KEYS),
            CategoryFilter::fromRequest($request)->toArray()
        );

        return array_filter($filters, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();

        $filters = $this->caseExportFilters($request);

        $queries = $this->exportQueries;
        $exportService = $this->exportService;

        $data = $queries->getCasesExport($user, $filters);

        $now = now()->format('Y-m-d H:i:s');
        $data = $data->map(function ($row) use ($now) {
            $row->exported_at = $now;

            return $row;
        });

        $filename = 'cases-export-'.now()->format('Ymd-His').'.xlsx';

        AuditLogger::export()
            ->by($request->user())
            ->module(AuditModule::DATA_EXPORT)
            ->context(null, ['format' => 'excel', 'scope' => 'cases'])
            ->write();

        return $exportService->generateSingleSheet(
            'Cases',
            self::casesExportColumnMap(),
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

        $filters = $this->caseExportFilters($request);

        $count = $this->exportQueries->countCasesExport($user, $filters);

        return response()->json(['count' => $count]);
    }

    public function exportPdf(string $id, Request $request)
    {
        $case = $this->caseService->getCase($id);
        $this->authorizeCaseAccess($case, $request->user());

        $client = $case->client;
        $primaryEmployment = $client->employments->first();
        $primaryAddress = $client->addresses->first();
        $primaryNok = $client->nextOfKin->first();

        // dompdf builds the whole DOM before paging, so cap the timeline: a
        // case with thousands of events would otherwise exhaust the 256M limit.
        $eventsQuery = $case->caseEvents()->latest('occurred_at');
        $eventsTotal = $eventsQuery->count();
        $eventsLimit = 500;

        $data = [
            'case' => $case,
            'client' => $client,
            'employment' => $primaryEmployment,
            'address' => $primaryAddress,
            'nok' => $primaryNok,
            'referrals' => $case->referrals,
            'milestones' => $eventsQuery->limit($eventsLimit)->get(),
            'eventsTruncated' => $eventsTotal > $eventsLimit,
            'eventsTotal' => $eventsTotal,
            'exportedAt' => now(config('app.operating_timezone', 'Asia/Manila'))->format('M d, Y h:i A'),
        ];

        $pdf = Pdf::loadView('pdf.case-report', $data);
        $filename = 'case-report-'.$case->case_number.'-'.now(config('app.operating_timezone', 'Asia/Manila'))->format('Ymd-His').'.pdf';

        AuditLogger::export()
            ->by($request->user())
            ->module(AuditModule::DATA_EXPORT)
            ->context(null, ['format' => 'pdf', 'scope' => 'case'])
            ->write();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function trashIndex(Request $request)
    {
        $filters = $request->only(['search', 'per_page']);
        $trashedCases = $this->caseService->getTrashedCases($filters, $request->user());

        return Inertia::render('Case/Trash', [
            'cases' => $trashedCases,
            'filters' => (object) $filters,
        ]);
    }

    public function deleteArchived(Request $request, string $id)
    {
        $request->validate([
            'deletion_reason' => ['required', 'string', 'min:10'],
        ]);

        $case = CaseFile::findOrFail($id);
        $this->authorizeCaseAccess($case, $request->user());

        $this->caseService->deleteArchivedCase(
            $case,
            $request->input('deletion_reason'),
            $request->user()->id,
        );

        return redirect()
            ->route('cases.index')
            ->with('success', 'Case moved to trash successfully.');
    }

    public function restore(Request $request, string $id)
    {
        // Ownership scoping (CASE_MANAGER → own cases only) and the row lock live
        // in the service, so the controller never resolves a trashed case itself.
        $this->caseService->restoreTrashedCase($id, $request->user());

        return redirect()
            ->route('cases.trash')
            ->with('success', 'Case restored successfully.');
    }

    /**
     * Business-export column map — no IDs or system fields.
     */
    public static function casesExportColumnMap(): array
    {
        return [
            ['key' => 'case_number', 'label' => 'Case Number', 'type' => 'string'],
            ['key' => 'status', 'label' => 'Case Status', 'type' => 'status'],
            ['key' => 'tracker_number', 'label' => 'Case Tracking ID', 'type' => 'string'],
            ['key' => 'ofw_full_name', 'label' => 'OFW Full Name', 'type' => 'string'],
            ['key' => 'ofw_sex', 'label' => 'OFW Sex/Gender', 'type' => 'string'],
            ['key' => 'ofw_date_of_birth', 'label' => 'OFW Date of Birth', 'type' => 'date'],
            ['key' => 'ofw_contact_number', 'label' => 'OFW Contact No.', 'type' => 'string'],
            ['key' => 'ofw_email', 'label' => 'OFW Email Address', 'type' => 'string'],
            ['key' => 'ofw_age', 'label' => 'OFW Age', 'type' => 'string'],
            ['key' => 'barangay', 'label' => 'Barangay', 'type' => 'string'],
            ['key' => 'municipality', 'label' => 'Municipality', 'type' => 'string'],
            ['key' => 'province', 'label' => 'Province', 'type' => 'string'],
            ['key' => 'region', 'label' => 'Region', 'type' => 'string'],
            ['key' => 'vulnerability', 'label' => 'Vulnerability', 'type' => 'string'],
            ['key' => 'date_of_arrival', 'label' => 'Date of Arrival in PH', 'type' => 'date'],
            ['key' => 'previous_country', 'label' => 'Previous Country', 'type' => 'string'],
            ['key' => 'work_position', 'label' => 'Work Occupation', 'type' => 'string'],
            ['key' => 'issue_concern', 'label' => 'Issues/Concern', 'type' => 'string'],
            ['key' => 'categories', 'label' => 'Categories', 'type' => 'string'],
            ['key' => 'case_summary', 'label' => 'Case Summary', 'type' => 'string'],
            ['key' => 'receiving_parties', 'label' => 'Receiving Party/s', 'type' => 'string'],
            ['key' => 'nok_full_name', 'label' => 'NOK Full Name', 'type' => 'string'],
            ['key' => 'nok_contact_number', 'label' => 'NOK Contact No.', 'type' => 'string'],
            ['key' => 'nok_email', 'label' => 'NOK Email', 'type' => 'string'],
            ['key' => 'exported_at', 'label' => 'Exported At', 'type' => 'string'],
        ];
    }

    private function authorizeCaseAccess($case, $user)
    {
        if ($user->isAdmin()) {
            return;
        }
        if ($user->isCaseManager()) {
            return;
        }

        $hasActiveReferral = $case->referrals()
            ->where('agcy_id', $user->agcy_id)
            ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
            ->exists();

        if (! $hasActiveReferral) {
            abort(403, 'You do not have access to this case.');
        }
    }
}
