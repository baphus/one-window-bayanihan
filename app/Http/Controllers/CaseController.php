<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\SystemSetting;
use App\Services\AddressNameResolver;
use App\Services\CaseService;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\OnboardingService;
use App\Services\PhilippineAddressService;
use App\Services\ReferenceDataService;
use App\Services\TrackingService;
use App\Support\CategoryFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CaseController extends Controller
{
    public function __construct(
        private readonly CaseService $caseService,
        private readonly PhilippineAddressService $addressService,
        private readonly TrackingService $trackingService,
        private readonly ReferenceDataService $referenceData,
        private readonly AddressNameResolver $addressNames,
    ) {}

    public function index(Request $request)
    {
        $filterKeys = ['status', 'search', 'client_type', 'vulnerability_indicator', 'user_id', 'agcy_id', 'category_id', 'category_ids', 'case_issue_id', 'age_min_days', 'referral_state', 'date_from', 'date_to', 'sort', 'direction', 'per_page'];
        $categoryFilters = CategoryFilter::fromRequest($request)->toArray();

        $cases = $this->caseService->getCases(
            array_merge($request->only($filterKeys), $categoryFilters),
            $request->input('sort', 'created_at'),
            $request->input('direction', 'desc'),
            (int) $request->input('per_page', 15)
        );

        return Inertia::render('Case/Index', [
            'cases' => $cases,
            'filters' => (object) array_merge($request->only($filterKeys), $categoryFilters),
            'stats' => $this->caseService->getCaseStats($request->user()),
            'users' => $this->referenceData->getCaseManagerUsers(),
            'agencies' => $this->referenceData->getAgenciesDropdown(),
            'categories' => $this->referenceData->getActiveCategories(),
            'caseIssues' => $this->referenceData->getActiveIssues(),
        ]);
    }

    public function create(Request $request)
    {
        $client = null;
        if ($request->has('client_id')) {
            $client = Client::with(['addresses', 'employments', 'nextOfKin', 'caseFiles'])->find($request->client_id);

            // ?client_id= prefills the form with the client's full record, so it
            // is a second way into the same data the picker now hides. An
            // unaccepted self-filed intake is handled from the intake queue, not
            // used as the starting point for a new case.
            if ($client?->hasOnlyUnacceptedIntake()) {
                $client = null;
            }
        }

        $categories = $this->referenceData->getActiveCategories();
        $caseIssues = $this->referenceData->getActiveIssues();

        return Inertia::render('Case/Create', [
            'client' => $client,
            'categories' => $categories,
            'caseIssues' => $caseIssues,
            'occupationOptions' => $this->referenceData->getOccupationOptions(),
        ]);
    }

    public function store(StoreCaseRequest $request)
    {
        $case = $this->caseService->createCase(
            $request->validated(),
            $request->user()->id,
        );

        $isDraft = $request->validated()['is_draft'] ?? true;

        if (! $isDraft) {
            $case = $this->caseService->publishDraft($case->id, $request->user()->id);

            app(OnboardingService::class)
                ->markChecklistItemQuietly($request->user(), 'create-first-case');

            return redirect()
                ->route('cases.show', $case)
                ->with('success', 'Case created successfully.')
                ->with('just_published', true);
        }

        return redirect()
            ->route('cases.drafts')
            ->with('success', 'Draft saved successfully.');
    }

    public function editDraft(Request $request, string $id)
    {
        $case = $this->caseService->getCase($id);
        abort_unless($case->status === 'DRAFT', 404);

        // Allow CMs/Admins to edit self-filed drafts (user_id is null)
        $isSelfFiled = $case->source === CaseFile::SOURCE_SELF_FILED && $case->user_id === null;
        if (! $isSelfFiled) {
            abort_unless($case->user_id === $request->user()->id, 403);
        }

        $categories = $this->referenceData->getActiveCategories();
        $caseIssues = $this->referenceData->getActiveIssues();

        // Resolve draft address names to codes for cascade dropdown pre-population
        $draftResolvedAddress = [];
        $draftData = $case->draft_client_data;
        if (! empty($draftData['address'])) {
            $region = $draftData['address']['region'] ?? '';
            if (! empty($region) && preg_match('/[a-zA-Z]/', $region)) {
                $draftResolvedAddress = $this->addressService->resolveAddressToCodes($draftData['address']);
            } else {
                $draftResolvedAddress = $draftData['address'];
            }
        }

        return Inertia::render('Case/Create', [
            'existingDraft' => $case,
            'categories' => $categories,
            'caseIssues' => $caseIssues,
            'occupationOptions' => $this->referenceData->getOccupationOptions(),
            'draftResolvedAddress' => $draftResolvedAddress,
        ]);
    }

    public function reviewIntake(Request $request, string $id)
    {
        $case = $this->caseService->getCase($id);

        // Only self-filed DRAFT cases can be reviewed via this route
        abort_unless(
            $case->source === CaseFile::SOURCE_SELF_FILED && $case->status === 'DRAFT',
            404,
        );

        $categories = $this->referenceData->getActiveCategories();
        $caseIssues = $this->referenceData->getActiveIssues();

        // Resolve draft address names to codes for cascade dropdown pre-population
        $draftResolvedAddress = [];
        $draftData = $case->draft_client_data;
        if (! empty($draftData['address'])) {
            $region = $draftData['address']['region'] ?? '';
            if (! empty($region) && preg_match('/[a-zA-Z]/', $region)) {
                $draftResolvedAddress = $this->addressService->resolveAddressToCodes($draftData['address']);
            } else {
                $draftResolvedAddress = $draftData['address'];
            }
        }

        // draftResolvedAddress carries PSGC *codes* so the cascade dropdowns can
        // pre-select. The review screen also needs human-readable *names*, and
        // reusing the codes prop there rendered the reviewer four raw numbers
        // (e.g. "0730600041, 0730600000, ...") instead of the OFW's address.
        $draftAddressNames = [];
        if (! empty($draftData['address'])) {
            $a = $draftData['address'];
            $draftAddressNames = [
                'barangay' => $this->addressNames->resolve($a['barangay'] ?? null),
                'city_municipality' => $this->addressNames->resolve($a['city_municipality'] ?? null),
                'province' => $this->addressNames->resolve($a['province'] ?? null),
                'region' => $this->addressNames->resolve($a['region'] ?? null),
                'street' => $a['street'] ?? '',
            ];
        }

        return Inertia::render('Case/ReviewIntake', [
            'case' => $case,
            'categories' => $categories,
            'caseIssues' => $caseIssues,
            'occupationOptions' => $this->referenceData->getOccupationOptions(),
            'draftResolvedAddress' => $draftResolvedAddress,
            'draftAddressNames' => $draftAddressNames,
        ]);
    }

    public function updateDraft(UpdateDraftRequest $request, string $id)
    {
        $case = $this->caseService->updateDraft($id, $request->validated(), $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $case->id,
                'saved_at' => $case->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ]);
        }

        return redirect()
            ->route('cases.drafts')
            ->with('success', 'Draft updated successfully.');
    }

    public function show(string $id, Request $request)
    {
        $case = $this->caseService->getCase($id);
        $this->authorizeCaseAccess($case, $request->user());
        if ($case->status === 'DRAFT' && $case->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this draft.');
        }
        $overdueDays = (int) SystemSetting::getValue('referral_overdue_days', 7);

        $trackingData = $this->trackingService->buildTrackingData($case);

        return Inertia::render('Case/Show', [
            'case' => $case,
            'overdueDays' => $overdueDays,
            'milestoneTimeline' => $trackingData['milestoneTimeline'],
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

    public function publish(Request $request, CaseFile $case)
    {
        $this->authorizeCaseAccess($case, $request->user());

        $case = $this->caseService->publishDraft($case->id, $request->user()->id);

        app(OnboardingService::class)
            ->markChecklistItemQuietly($request->user(), 'create-first-case');

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Draft published successfully.')
            ->with('just_published', true);
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

    public function drafts(Request $request)
    {
        $filters = $request->only(['search', 'date_from', 'date_to']);
        $drafts = $this->caseService->getUserDrafts($request->user()->id, $filters);

        return Inertia::render('Draft/Index', [
            'drafts' => $drafts,
            'filters' => $filters,
        ]);
    }

    public function intakeQueue(Request $request)
    {
        $filters = $request->only(['search']);
        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'asc');

        $cases = $this->caseService->getIntakeQueue(
            $filters,
            (int) $request->input('per_page', 15),
            $sort,
            $direction,
        );

        return Inertia::render('Case/IntakeQueue', [
            'cases' => $cases,
            'filters' => (object) $filters,
            'stats' => $this->caseService->getIntakeQueueStats(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function rejectIntake(Request $request, string $id)
    {
        $request->validate([
            'deletion_reason' => ['required', 'string', 'min:10'],
        ]);

        $this->caseService->rejectIntake(
            $id,
            $request->input('deletion_reason'),
            $request->user()->id,
        );

        return redirect()
            ->route('cases.intake-queue')
            ->with('success', 'Intake submission rejected successfully.');
    }

    public function destroyDraft(string $id, Request $request)
    {
        $this->caseService->deleteDraft($id, $request->user()->id);

        return redirect()
            ->route('cases.drafts')
            ->with('success', 'Draft deleted successfully.');
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();

        $filters = array_filter(array_merge($request->only([
            'status', 'search', 'client_type', 'vulnerability_indicator',
            'user_id', 'agcy_id', 'category_id', 'category_ids', 'case_issue_id',
            'age_min_days', 'referral_state', 'date_from', 'date_to',
        ]), CategoryFilter::fromRequest($request)->toArray()));

        $queries = new DataExportQueries;
        $exportService = new DataExportService;

        $data = $queries->getCasesExport($user, $filters);

        $now = now()->format('Y-m-d H:i:s');
        $data = $data->map(function ($row) use ($now) {
            $row->exported_at = $now;

            return $row;
        });

        $filename = 'cases-export-'.now()->format('Ymd-His').'.xlsx';

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

        $filters = array_filter(array_merge($request->only([
            'status', 'search', 'client_type', 'vulnerability_indicator',
            'user_id', 'agcy_id', 'category_id', 'category_ids', 'case_issue_id',
            'age_min_days', 'referral_state', 'date_from', 'date_to',
        ]), CategoryFilter::fromRequest($request)->toArray()));

        $count = (new DataExportQueries)->countCasesExport($user, $filters);

        return response()->json(['count' => $count]);
    }

    /**
     * Business-export column map — no IDs or system fields.
     */
    public static function casesExportColumnMap(): array
    {
        return [
            ['key' => 'case_number',       'label' => 'Case Number',          'type' => 'string'],
            ['key' => 'status',             'label' => 'Case Status',          'type' => 'status'],
            ['key' => 'tracker_number',     'label' => 'Case Tracking ID',     'type' => 'string'],
            ['key' => 'client_type',        'label' => 'Client Type',          'type' => 'string'],
            ['key' => 'ofw_full_name',      'label' => 'OFW Full Name',        'type' => 'string'],
            ['key' => 'ofw_sex',            'label' => 'OFW Sex/Gender',       'type' => 'string'],
            ['key' => 'ofw_date_of_birth',  'label' => 'OFW Date of Birth',    'type' => 'date'],
            ['key' => 'ofw_contact_number', 'label' => 'OFW Contact No.',      'type' => 'string'],
            ['key' => 'ofw_email',          'label' => 'OFW Email Address',    'type' => 'string'],
            ['key' => 'ofw_age',            'label' => 'OFW Age',              'type' => 'string'],
            ['key' => 'barangay',           'label' => 'Barangay',             'type' => 'string'],
            ['key' => 'municipality',       'label' => 'Municipality',         'type' => 'string'],
            ['key' => 'province',           'label' => 'Province',             'type' => 'string'],
            ['key' => 'region',             'label' => 'Region',               'type' => 'string'],
            ['key' => 'vulnerability',      'label' => 'Vulnerability',        'type' => 'string'],
            ['key' => 'date_of_arrival',    'label' => 'Date of Arrival in PH', 'type' => 'date'],
            ['key' => 'previous_country',   'label' => 'Previous Country',     'type' => 'string'],
            ['key' => 'work_position',      'label' => 'Work Occupation',        'type' => 'string'],
            ['key' => 'issue_concern',      'label' => 'Issues/Concern',       'type' => 'string'],
            ['key' => 'categories',         'label' => 'Categories',           'type' => 'string'],
            ['key' => 'case_summary',       'label' => 'Case Summary',         'type' => 'string'],
            ['key' => 'receiving_parties',  'label' => 'Receiving Party/s',    'type' => 'string'],
            ['key' => 'nok_full_name',      'label' => 'NOK Full Name',        'type' => 'string'],
            ['key' => 'nok_contact_number', 'label' => 'NOK Contact No.',      'type' => 'string'],
            ['key' => 'nok_email',          'label' => 'NOK Email',            'type' => 'string'],
            ['key' => 'exported_at',        'label' => 'Exported At',          'type' => 'string'],
        ];
    }

    public function exportPdf(string $id, Request $request)
    {
        $case = $this->caseService->getCase($id);
        $this->authorizeCaseAccess($case, $request->user());

        $client = $case->client;
        $primaryEmployment = $client->employments->first();
        $primaryAddress = $client->addresses->first();
        $primaryNok = $client->nextOfKin->first();

        $data = [
            'case' => $case,
            'client' => $client,
            'employment' => $primaryEmployment,
            'address' => $primaryAddress,
            'nok' => $primaryNok,
            'referrals' => $case->referrals,
            'milestones' => $case->caseEvents()->latest('occurred_at')->get(),
            'exportedAt' => now()->format('M d, Y h:i A'),
        ];

        $pdf = Pdf::loadView('pdf.case-report', $data);
        $filename = 'case-report-'.$case->case_number.'-'.now()->format('Ymd-His').'.pdf';

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
        $case = CaseFile::onlyTrashed()->findOrFail($id);

        $this->caseService->restoreTrashedCase($case, $request->user()->id);

        return redirect()
            ->route('cases.trash')
            ->with('success', 'Case restored successfully.');
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
