<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\SystemSetting;
use App\Services\CaseService;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\OnboardingService;
use App\Services\PhilippineAddressService;
use App\Services\ReferenceDataService;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CaseController extends Controller
{
    public function __construct(
        private readonly CaseService $caseService,
        private readonly PhilippineAddressService $addressService,
        private readonly TrackingService $trackingService,
        private readonly ReferenceDataService $referenceData,
    ) {}

    public function index(Request $request)
    {
        $filterKeys = ['status', 'search', 'client_type', 'vulnerability_indicator', 'user_id', 'agcy_id', 'category_id', 'case_issue_id', 'age_min_days', 'referral_state', 'date_from', 'date_to', 'sort', 'direction', 'per_page'];

        $cases = $this->caseService->getCases(
            $request->only($filterKeys),
            $request->input('sort', 'created_at'),
            $request->input('direction', 'desc'),
            (int) $request->input('per_page', 15)
        );

        return Inertia::render('Case/Index', [
            'cases' => $cases,
            'filters' => (object) $request->only($filterKeys),
            'stats' => $this->caseService->getCaseStats(),
            'users' => $this->referenceData->getCaseManagerUsers(),
            'agencies' => $this->referenceData->getAgenciesDropdown(),
            'categories' => $this->referenceData->getActiveCategories(),
            'caseIssues' => $this->referenceData->getActiveIssues(),
            'exportRowCount' => (new DataExportQueries)->countCasesExport($request->user(), array_filter($request->only(['status', 'search', 'client_type', 'vulnerability_indicator', 'user_id', 'agcy_id', 'category_id', 'case_issue_id', 'age_min_days', 'referral_state', 'date_from', 'date_to']))),
        ]);
    }

    public function create(Request $request)
    {
        $client = null;
        if ($request->has('client_id')) {
            $client = Client::with(['addresses', 'employments', 'nextOfKin', 'caseFiles'])->find($request->client_id);
        }

        $categories = $this->referenceData->getActiveCategories();
        $caseIssues = $this->referenceData->getActiveIssues();

        return Inertia::render('Case/Create', [
            'client' => $client,
            'categories' => $categories,
            'caseIssues' => $caseIssues,
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
        abort_unless($case->user_id === $request->user()->id, 403);

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
            'draftResolvedAddress' => $draftResolvedAddress,
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

    public function destroyDraft(string $id, Request $request)
    {
        $this->caseService->deleteDraft($id, $request->user()->id);

        return redirect()
            ->route('cases.drafts')
            ->with('success', 'Draft deleted successfully.');
    }

    public function exportExcel(Request $request)
    {
        $user = auth()->user();
        $queries = new DataExportQueries;

        $filters = $request->only([
            'status', 'search', 'client_type', 'vulnerability_indicator',
            'user_id', 'agcy_id', 'category_id', 'case_issue_id',
            'age_min_days', 'referral_state', 'date_from', 'date_to',
        ]);

        $cases = $queries->getCasesExport($user, array_filter($filters));

        $columnMap = self::casesExportColumnMap();

        // Tag each row with the export timestamp for provenance
        $now = now()->format('Y-m-d H:i:s');
        $cases = $cases->map(function ($row) use ($now) {
            $row->exported_at = $now;

            return $row;
        });

        $filename = 'cases-export-'.now()->format('Ymd-His').'.xlsx';

        return (new DataExportService)->generateSingleSheet('Cases', $columnMap, $cases, $filename);
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
            ['key' => 'work_position',      'label' => 'Work Position',        'type' => 'string'],
            ['key' => 'issue_concern',      'label' => 'Issues/Concern',       'type' => 'string'],
            ['key' => 'case_summary',       'label' => 'Case Summary',         'type' => 'string'],
            ['key' => 'receiving_parties',  'label' => 'Receiving Party/s',    'type' => 'string'],
            ['key' => 'nok_full_name',      'label' => 'NOK Full Name',        'type' => 'string'],
            ['key' => 'nok_contact_number', 'label' => 'NOK Contact No.',      'type' => 'string'],
            ['key' => 'nok_email',          'label' => 'NOK Email',            'type' => 'string'],
            ['key' => 'exported_at',        'label' => 'Exported At',          'type' => 'string'],
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
