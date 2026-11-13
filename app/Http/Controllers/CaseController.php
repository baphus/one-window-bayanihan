<?php

namespace App\Http\Controllers;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Http\Requests\ApproveIntakeRequest;
use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\StoreIntakeRequest;
use App\Http\Requests\UpdateCaseCategoriesRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Mail\IntakeReviewMail;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\SystemSetting;
use App\Models\User;
use App\Rules\VulnerabilityRule;
use App\Services\AuditLogger;
use App\Services\CaseService;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\IntakeDocumentService;
use App\Services\OnboardingService;
use App\Services\ReferenceDataService;
use App\Services\TrackingService;
use App\Support\CategoryFilter;
use App\Support\MultiSelectFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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
        $caseStatusOptions = array_values(array_unique([...array_column($this->referenceData->getCaseStatusOptions(), 'value'), 'REJECTED']));
        $clientTypeOptions = ['OFW', 'NEXT_OF_KIN'];
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
        foreach (['status', 'client_type', 'vulnerability_indicator', 'user_id', 'agcy_id'] as $key) {
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
            'cases' => $cases->through(fn ($case) => $this->caseService->caseListPayload($case)),
            'filters' => (object) array_filter($filters, fn ($value) => $value !== null),
            'stats' => $this->caseService->getCaseStats($request->user()),
            'users' => $this->referenceData->getCaseManagerUsers(),
            'agencies' => $this->referenceData->getAgenciesDropdown(),
            'categories' => $this->referenceData->getActiveCategories(),
            'statuses' => $this->referenceData->getCaseStatusOptions(),
            'clientTypes' => array_map(
                fn (string $type) => ['value' => $type, 'label' => $type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin'],
                ['OFW', 'NEXT_OF_KIN'],
            ),
            'vulnerabilities' => $this->referenceData->getVulnerabilityOptions(),
        ]);
    }

    private function intakeProps(): array
    {
        return [
            'consentTextVersion' => IntakeDocumentService::CONSENT_VERSION,
            'documentSlots' => IntakeDocumentService::SLOTS,
            'returnReasons' => IntakeDocumentService::RETURN_REASONS,
            'civilStatuses' => CaseFile::CIVIL_STATUSES,
            'deploymentModes' => CaseFile::DEPLOYMENT_MODES,
            'employmentStatuses' => CaseFile::EMPLOYMENT_STATUSES,
            'assessmentOptions' => CaseFile::ASSESSMENTS,
            'vulnerabilityOptions' => VulnerabilityRule::VALUES,
            'occupationOptions' => $this->referenceData->getOccupationOptions(),
        ];
    }

    public function create(Request $request)
    {
        return Inertia::render('Case/Create', $this->intakeProps());
    }

    /**
     * Which active OFW account, if any, a typed email belongs to.
     * Name only: the filer needs to confirm who they are filing for.
     */
    public function lookupOfw(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return response()->json(['name' => User::query()
            ->where('email', $data['email'])
            ->where('role', UserRole::OFW->value)
            ->where('is_active', true)
            ->value('name')]);
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
        return Inertia::render('OFW/Intake', $this->intakeProps());
    }

    public function storeIntake(StoreIntakeRequest $request)
    {
        $validated = $request->validated();

        $user = $request->user();
        $case = $this->caseService->createCase($validated, $user->id);

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

        $cases = CaseFile::with(['documents' => fn ($query) => $query
            ->whereNull('referral_id')
            ->where('is_deleted', false)])
            ->where('status', CaseFile::STATUS_PENDING_REVIEW)
            ->where('is_deleted', false)
            ->when($request->user()->isAgency() && $request->user()->agcy_id, function ($query) use ($request) {
                $query->whereHas('referrals', fn ($q) => $q->where('agcy_id', $request->user()->agcy_id));
            })
            ->orderBy('created_at', $direction)
            ->paginate((int) ($listing['per_page'] ?? 15));

        return Inertia::render('Case/ReviewPending', [
            'cases' => $cases->through(fn ($case) => $this->authorizedIntakePayload($case)),
            ...$this->intakeProps(),
            'filters' => (object) $request->only(['search']),
            'sort' => 'created_at',
            'direction' => $direction,
            'categories' => CaseCategory::where('is_active', true)
                ->with(['suggestedAgencies' => fn ($query) => $query
                    ->where('agencies.is_active', true)
                    ->where('agencies.is_deleted', false)
                    ->select('agencies.id', 'agencies.name', 'agencies.short')])
                ->orderBy('sort_order')
                ->get(['id', 'name', 'color'])
                ->map(fn (CaseCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'suggested_agency_ids' => $category->suggestedAgencies->pluck('id')->all(),
                ]),
            'agencies' => Agency::where('is_active', true)
                ->where('is_deleted', false)
                ->orderBy('name')
                ->get(['id', 'name', 'short']),
        ]);
    }

    public function reviewApprove(ApproveIntakeRequest $request, CaseFile $case)
    {
        $this->caseService->approveCase($case->id, $request->user()->id, $request->validated());

        return redirect()->route('cases.index')->with('success', 'Case approved successfully.');
    }

    public function reviewReject(Request $request, CaseFile $case)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [UserRole::CASE_MANAGER->value, UserRole::ADMIN->value], true), 403);

        abort_unless($case->status === CaseFile::STATUS_PENDING_REVIEW, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $slots = $request->validate([
            'slots' => ['sometimes', 'array', 'min:1', 'required_with:slots.*'],
            'slots.*' => ['required', 'distinct', Rule::in(IntakeDocumentService::REQUIREMENT_KEYS)],
        ])['slots'] ?? [];

        $this->caseService->rejectCase($case->id, $validated['reason'], $user->id, $slots);

        return redirect()
            ->route('cases.review-pending')
            ->with('success', 'Case declined.');
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

        // Staff pages get the timeline only, and only for referrals this user
        // may see. buildTrackingData() is the CLIENT payload: it carries the
        // client's address, next of kin, work history and notifications, plus
        // every referral's events. A partner agency opening a shared case must
        // never receive another agency's client record.
        // The referral filter is passed into the query, not applied to its
        // result: the event read is capped at the newest rows, so filtering
        // afterwards would let a busy case evict this agency's older entries.
        $visibleReferralIds = $request->user()->isAgency()
            ? $case->referrals->where('agcy_id', $request->user()->agcy_id)->pluck('id')->all()
            : null;

        $milestonesForViewer = $this->trackingService->buildTrackingData(
            $case,
            withClientDetails: false,
            visibleReferralIds: $visibleReferralIds,
        )['milestoneTimeline'];

        $approver = $case->reviewer ? [
            'name' => $case->reviewer->name,
            'role' => UserRole::tryFrom($case->reviewer->role)?->label(),
            'at' => $case->reviewed_at?->toISOString(),
        ] : null;

        $case->load('categories:id,name');
        $caseCategories = $case->categories->map(fn (CaseCategory $category) => ['id' => $category->id, 'name' => $category->name])->values();
        $managerView = $request->user()->isCaseManager();

        return Inertia::render('Case/Show', [
            'case' => $this->authorizedIntakePayload($case),
            'case_categories' => $caseCategories,
            'recommended_agencies' => $managerView ? $case->categories->load('suggestedAgencies:id,name,short,is_active,is_deleted')->flatMap(fn (CaseCategory $category) => $category->suggestedAgencies)->filter(fn ($agency) => $agency->is_active && ! $agency->is_deleted)->unique('id')->values()->map(fn ($agency) => ['id' => $agency->id, 'name' => $agency->name, 'short' => $agency->short]) : null,
            'documentRequirements' => $request->user()->isAgency()
                ? []
                : IntakeDocumentService::requirementList($case),
            ...$this->intakeProps(),
            'categories' => $managerView ? $this->referenceData->getActiveCategories() : [],
            'overdueDays' => $overdueDays,
            'milestoneTimeline' => $milestonesForViewer,
            'approver' => $approver,
            'lifecycle' => $this->caseService->getCaseLifecycle($case),
        ]);
    }

    public function updateCategories(UpdateCaseCategoriesRequest $request, CaseFile $case)
    {
        $case->categories()->sync($request->validated('category_ids'));

        return back()->with('success', 'Case categories updated.');
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
        'user_id', 'agcy_id',
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
        $case = CaseFile::with(['referrals.category', 'referrals.agency'])->findOrFail($id);
        $this->authorizeCaseAccess($case, $request->user());
        $latestEvent = $case->caseEvents()->latest('occurred_at')->latest('created_at')->first();
        $data = [
            'case' => $case,
            'referrals' => $case->referrals,
            'latestEvent' => $latestEvent,
            'exportedAt' => now(config('app.operating_timezone', 'Asia/Manila'))->format('M d, Y h:i A'),
        ];

        // Shorten narrative, never shrink text or silently discard agency rows.
        foreach ([500, 240, 100] as $textLimit) {
            $pdf = Pdf::loadView('pdf.case-report', $data + ['textLimit' => $textLimit])
                ->setPaper('a4', 'portrait');
            $pdf->render();

            if ($pdf->getDomPDF()->getCanvas()->get_page_count() === 1) {
                break;
            }
        }

        abort_if($pdf->getDomPDF()->getCanvas()->get_page_count() !== 1, 422,
            'This case has too much agency or profile information for a readable one-page summary. No details were silently omitted.');

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
            $request->user(),
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
            ['key' => 'employment_country', 'label' => 'Jobsite Country', 'type' => 'string'],
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

    private function authorizedIntakePayload(CaseFile $case): array
    {
        $payload = $case->toArray();
        unset($payload['documents']);
        if (auth()->user()?->isAgency()) {
            unset($payload['document_requirements']);
            $payload['documents'] = $case->documents
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'file_name' => $document->file_name,
                    'file_type' => $document->file_type,
                    'category' => $document->category,
                    'size' => $document->size,
                ])->values()->all();
        }
        $staffOnly = ['staff_summary', 'consent_evidence', 'consent_accepted_by', 'missing_document_reasons', 'last_correction_reason', 'document_request_reason', 'document_requested_by', 'document_requested_at', 'reviewed_document_ids', 'proposed_assessment_by', 'proposed_assessment_at', 'confirmed_assessment_by', 'confirmed_assessment_at', 'assigned_case_manager_id', 'document_requirements'];
        $fields = auth()->user()?->isAgency()
            ? array_diff(CaseFile::INTAKE_FIELDS, $staffOnly)
            : array_values(array_diff(CaseFile::INTAKE_FIELDS, ['ofw_user_id', 'last_correction_reason']));
        foreach ($fields as $field) {
            $value = $case->getAttribute($field);
            $payload[$field] = $value instanceof \DateTimeInterface ? $value->format(str_ends_with($field, '_at') ? DATE_ATOM : 'Y-m-d') : $value;
        }
        $payload['age_at_intake'] = $case->age_at_intake;
        if (! auth()->user()?->isAgency()) {
            $payload['documents'] = $case->documents()
                ->whereNull('referral_id')
                ->where('is_deleted', false)
                ->get()
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'file_name' => IntakeDocumentService::REQUIREMENT_LABELS[$document->category] ?? $document->file_name,
                    'extension' => pathinfo($document->file_name, PATHINFO_EXTENSION),
                    'file_type' => $document->file_type,
                    'category' => $document->category,
                    'size' => $document->size,
                    'user' => $document->user?->name,
                    'created_at' => $document->created_at?->toISOString(),
                    'download_url' => route('cases.documents.download', ['case' => $case->id, 'document' => $document->id]),
                ])->values()->all();
        }

        return $payload;
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
