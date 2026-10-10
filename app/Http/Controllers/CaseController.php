<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectIntakeRequest;
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
        private readonly DataExportQueries $exportQueries,
        private readonly DataExportService $exportService,
        private readonly OnboardingService $onboardingService,
    ) {}

    public function index(Request $request)
    {
        $categoryFilters = CategoryFilter::fromRequest($request)->toArray();

        // agcy_id is the agencies FK column name (also on users/referrals),
        // not a typo for agency_id — keep the query param name in sync.
        $listing = $request->validate([
            'status' => ['nullable', 'string', 'in:OPEN,CLOSED,ARCHIVED'],
            'search' => ['nullable', 'string', 'max:255'],
            'client_type' => ['nullable', 'string', 'in:'.implode(',', CaseFile::CLIENT_TYPES)],
            'vulnerability_indicator' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'string', 'uuid'],
            'agcy_id' => ['nullable', 'string', 'uuid'],
            'case_issue_id' => ['nullable', 'string', 'uuid'],
            'age_min_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'referral_state' => ['nullable', 'string', 'in:none'],
            'date_from' => ['nullable', 'string', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'string', 'date_format:Y-m-d'],
            'sort' => ['nullable', 'string', 'in:case_number,tracker_number,client_type,status,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $filters = array_merge($listing, $categoryFilters);

        $cases = $this->caseService->getCases(
            $filters,
            $listing['sort'] ?? 'created_at',
            $listing['direction'] ?? 'desc',
            (int) ($listing['per_page'] ?? 15)
        );

        return Inertia::render('Case/Index', [
            'cases' => $cases,
            'filters' => (object) $filters,
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
        $validated = $request->validated();

        $case = $this->caseService->createCase(
            $validated,
            $request->user()->id,
        );

        $isDraft = $validated['is_draft'] ?? true;

        if (! $isDraft) {
            $case = $this->caseService->publishDraft(
                $case->id,
                $request->user()->id,
                (bool) ($validated['confirm_duplicate_client'] ?? false),
            );

            $this->onboardingService
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

        $draftResolvedAddress = $this->resolveDraftAddressForCascade($case->draft_client_data);

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

        $draftData = $case->draft_client_data;
        $draftResolvedAddress = $this->resolveDraftAddressForCascade($draftData);

        // draftResolvedAddress carries PSGC *codes* so the cascade dropdowns can
        // pre-select. The review screen also needs human-readable *names*, and
        // reusing the codes prop there rendered the reviewer four raw numbers
        // (e.g. "0730600041, 0730600000, ...") instead of the OFW's address.
        $draftAddressNames = [];
        if (! empty($draftData['address'])) {
            $a = $draftData['address'];
            $draftAddressNames = [
                'barangay' => $this->addressService->resolve($a['barangay'] ?? null),
                'city_municipality' => $this->addressService->resolve($a['city_municipality'] ?? null),
                'province' => $this->addressService->resolve($a['province'] ?? null),
                'region' => $this->addressService->resolve($a['region'] ?? null),
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

    public function publish(Request $request, CaseFile $case)
    {
        $this->authorizeCaseAccess($case, $request->user());

        $case = $this->caseService->publishDraft(
            $case->id,
            $request->user()->id,
            $request->boolean('confirm_duplicate_client'),
        );

        $this->onboardingService
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
        $listing = $request->validate([
            'sort' => ['sometimes', 'string', 'in:created_at,client_name,vulnerability_indicator'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ]);
        $sort = $listing['sort'] ?? 'created_at';
        $direction = $listing['direction'] ?? 'asc';

        $cases = $this->caseService->getIntakeQueue(
            $filters,
            (int) ($listing['per_page'] ?? 15),
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

    public function rejectIntake(RejectIntakeRequest $request, string $id)
    {
        $this->caseService->rejectIntake(
            $id,
            $request->validated()['deletion_reason'],
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

        $queries = $this->exportQueries;
        $exportService = $this->exportService;

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

        $count = $this->exportQueries->countCasesExport($user, $filters);

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

    /**
     * Resolve draft address names to PSGC codes for cascade dropdown
     * pre-population. Returns the address as-is when it already holds codes.
     */
    private function resolveDraftAddressForCascade(?array $draftClientData): array
    {
        $address = $draftClientData['address'] ?? null;

        if (empty($address) || ! is_array($address)) {
            return [];
        }

        $region = $address['region'] ?? '';

        if (! empty($region) && preg_match('/[a-zA-Z]/', $region)) {
            return $this->addressService->resolveAddressToCodes($address);
        }

        return $address;
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
