<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralStatusRequest;
use App\Models\Agency;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\SystemSetting;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\OnboardingService;
use App\Services\ReferenceDataService;
use App\Services\ReferralService;
use App\Services\StorageService;
use App\Support\CategoryFilter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
        private readonly ReferenceDataService $referenceData,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filterKeys = ['status', 'search', 'case_id', 'agcy_id', 'category_id', 'category_ids', 'case_issue_id', 'age_min_days', 'age_max_days', 'date_from', 'date_to'];
        $categoryFilters = CategoryFilter::fromRequest($request)->toArray();

        $referrals = $this->referralService->getReferrals(
            array_merge($request->only($filterKeys), $categoryFilters),
            $user->agcy_id,
            $user->role,
            $user->id,
        );

        return Inertia::render('Referral/Index', [
            'referrals' => $referrals,
            'filters' => (object) array_merge($request->only($filterKeys), $categoryFilters),
            'stats' => $this->referralService->getReferralStats($user->agcy_id, $user->role, $user->id),
            'agencies' => $this->referenceData->getAgenciesDropdown(),
            'categories' => $this->referenceData->getActiveCategories(),
            'caseIssues' => $this->referenceData->getActiveIssues(),
            'exportRowCount' => (new DataExportQueries)->countReferralsExport($user, array_filter(array_merge($request->only(['status', 'search', 'case_id', 'agcy_id', 'category_id', 'category_ids', 'case_issue_id', 'age_min_days', 'age_max_days', 'date_from', 'date_to']), $categoryFilters))),
        ]);
    }

    public function create(Request $request)
    {
        $agencies = $this->referenceData->getAgenciesWithServices();

        $casesQuery = CaseFile::with('client')
            ->where('status', 'OPEN')
            ->orderBy('created_at', 'desc');

        if ($search = $request->query('search')) {
            $searchTerm = "%{$search}%";
            $casesQuery->where(function ($q) use ($searchTerm) {
                $q->where('tracker_number', 'ilike', $searchTerm)
                    ->orWhere('case_number', 'ilike', $searchTerm)
                    ->orWhereHas('client', function ($q) use ($searchTerm) {
                        $q->where('first_name', 'ilike', $searchTerm)
                            ->orWhere('last_name', 'ilike', $searchTerm);
                    });
            });
        }

        $cases = $casesQuery->paginate(12)->withQueryString();

        // Build a lookup: case_id → [agcy_id, ...] so the frontend can warn
        // when a case is already referred to a given agency.
        $caseIds = $cases->pluck('id');
        $existingReferrals = Referral::whereIn('case_id', $caseIds)
            ->where('is_deleted', false)
            ->select('case_id', 'agcy_id')
            ->get();
        $caseReferrals = [];
        foreach ($existingReferrals as $ref) {
            $caseReferrals[$ref->case_id][] = $ref->agcy_id;
        }

        return Inertia::render('Referral/Create', [
            'case_id' => $request->query('case_id'),
            'agencies' => $agencies,
            'cases' => $cases,
            'caseReferrals' => $caseReferrals,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreReferralRequest $request)
    {
        $referral = $this->referralService->createReferral(
            $request->validated(),
            $request->user()->id,
        );

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $errors = app(StorageService::class)->validate($file, 'case_document');
                if (! empty($errors)) {
                    return back()->withErrors(['documents' => $errors[0]]);
                }

                $result = app(StorageService::class)->store($file, 'case-documents/'.$referral->case_id);

                if (! $result->success) {
                    return back()->withErrors(['documents' => $result->error ?? 'Failed to store file.']);
                }

                CaseDocument::create([
                    'file_name' => $result->originalName,
                    'file_path' => $result->path,
                    'file_type' => $result->type,
                    'size' => $result->size,
                    'case_id' => $referral->case_id,
                    'referral_id' => $referral->id,
                    'user_id' => $request->user()->id,
                    'category' => 'referral',
                ]);
            }
        }

        app(OnboardingService::class)
            ->markChecklistItemQuietly($request->user(), 'send-first-referral');

        return redirect()
            ->route('referrals.show', $referral)
            ->with('success', 'Referral created successfully.');
    }

    public function show(Request $request, string $id)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());
        $serviceRequirements = $this->referralService->getServiceRequirements($referral->agcy_id);
        $overdueDays = (int) SystemSetting::getValue('referral_overdue_days', 7);
        $clientRequestHistory = $this->referralService->getClientRequestHistory($referral);
        // Keep the eager-loaded models out of the general referral payload; the
        // service's allow-listed history is the only client-request projection.
        $referral->unsetRelation('clientRequests');

        return Inertia::render('Referral/Show', [
            'referral' => $referral,
            'serviceRequirements' => $serviceRequirements,
            'overdueDays' => $overdueDays,
            'timeline' => $this->referralService->getReferralTimeline($referral),
            'clientRequestHistory' => $clientRequestHistory,
            'clientRequestPermissions' => $this->referralService->getClientRequestPermissions($referral, $request->user()),
        ]);
    }

    public function updateStatus(UpdateReferralStatusRequest $request, string $id)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        // Only the owning agency may accept (move to PROCESSING) a referral.
        $isAccept = $request->input('status') === 'PROCESSING'
            || $request->input('decision') === 'ACCEPT';

        if ($isAccept && ! $request->user()->isAgency()) {
            return redirect()
                ->back()
                ->with('error', 'Only the receiving agency can accept this referral.');
        }

        $referral = $this->referralService->updateStatus(
            $id,
            $request->input('status'),
            $request->input('decision'),
            $request->input('decision_comment'),
            $request->user()->id,
        );

        if ($request->user()->role === 'AGENCY') {
            app(OnboardingService::class)
                ->markChecklistItemQuietly($request->user(), 'act-on-referral');
        }

        return redirect()
            ->back()
            ->with('success', 'Referral status updated.');
    }

    public function addMilestone(StoreMilestoneRequest $request, string $id)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        if ($referral->status === 'COMPLETED') {
            return redirect()
                ->back()
                ->with('error', 'Cannot add milestones to a completed referral.');
        }

        // Agencies may only add milestones after accepting the referral.
        if ($request->user()->role === 'AGENCY' && ! in_array($referral->status, ['PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED'], true)) {
            return redirect()
                ->back()
                ->with('error', 'You can only add milestones after accepting the referral.');
        }

        $milestone = $this->referralService->addMilestone(
            $id,
            $request->input('title'),
            $request->input('description'),
            $request->user()->id,
            $request->input('requirements'),
        );

        return redirect()
            ->back()
            ->with('success', 'Milestone added.');
    }

    public function addComment(Request $request, string $id)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'visibility' => 'sometimes|in:INTERNAL,AGY_ONLY',
        ]);

        $comment = $this->referralService->addComment(
            $id,
            $validated['content'],
            $request->user()->id,
            $validated['visibility'] ?? 'INTERNAL',
        );

        return redirect()
            ->back()
            ->with('success', 'Comment added.');
    }

    public function replyToComment(Request $request, string $id, string $commentId)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'visibility' => 'sometimes|in:INTERNAL,AGY_ONLY',
        ]);

        $reply = $this->referralService->replyToComment(
            $commentId,
            $validated['content'],
            $request->user()->id,
            $validated['visibility'] ?? 'INTERNAL',
        );

        return redirect()
            ->back()
            ->with('success', 'Reply added.');
    }

    public function addAttachment(Request $request, string $id)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        $file = $request->file('file');

        $errors = app(StorageService::class)->validate($file, 'referral_attachment');
        if (! empty($errors)) {
            return back()->withErrors(['file' => $errors[0]]);
        }

        $result = app(StorageService::class)->store($file, 'referrals/'.$referral->id);

        if (! $result->success) {
            return back()->withErrors(['file' => $result->error ?? 'Failed to store file.']);
        }

        $attachment = $this->referralService->addAttachment(
            $id,
            [
                'name' => $request->input('document_label')
                    ? str_replace('::', ' / ', $request->input('document_label')).' - '.$result->originalName
                    : $result->originalName,
                'path' => $result->path,
                'type' => $result->type,
                'size' => $result->size,
            ],
            $request->user()->id,
        );

        return redirect()
            ->back()
            ->with('success', 'Attachment added.');
    }

    public function replaceAttachment(Request $request, string $id, string $attachmentId)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        $file = $request->file('file');

        $errors = app(StorageService::class)->validate($file, 'referral_attachment');
        if (! empty($errors)) {
            return back()->withErrors(['file' => $errors[0]]);
        }

        $result = app(StorageService::class)->store($file, 'referrals/'.$referral->id);

        if (! $result->success) {
            return back()->withErrors(['file' => $result->error ?? 'Failed to store file.']);
        }

        $attachment = $this->referralService->replaceAttachment(
            $attachmentId,
            [
                'name' => $request->input('document_label')
                    ? str_replace('::', ' / ', $request->input('document_label')).' - '.$result->originalName
                    : $result->originalName,
                'path' => $result->path,
                'type' => $result->type,
                'size' => $result->size,
            ],
            $request->user()->id,
        );

        return redirect()
            ->back()
            ->with('success', 'Attachment replaced.');
    }

    public function deleteAttachment(Request $request, string $id, string $attachmentId)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        $attachment = ReferralAttachment::where('referral_id', $id)
            ->where('id', $attachmentId)
            ->where('is_deleted', false)
            ->firstOrFail();

        // Only the uploader can remove their own attachment
        if ($attachment->user_id !== $request->user()->id) {
            abort(403, 'Only the uploader can remove this attachment.');
        }

        $this->referralService->deleteAttachment($attachmentId, $request->user()->id);

        return redirect()
            ->route('referrals.show', $id)
            ->with('success', 'Attachment removed.');
    }

    public function downloadAttachment(Request $request, string $id, string $attachmentId)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        $attachment = ReferralAttachment::where('referral_id', $id)
            ->where('id', $attachmentId)
            ->where('is_deleted', false)
            ->firstOrFail();

        $url = app(StorageService::class)->temporaryUrl($attachment->file_path, 24);

        if (! $url) {
            abort(404, 'File not found or unavailable.');
        }

        return redirect()->away($url);
    }

    public function getAttachmentVersions(Request $request, string $id, string $versionGroupId)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());
        $versions = $this->referralService->getAttachmentVersions($versionGroupId);

        return response()->json($versions);
    }

    public function exportExcel(Request $request)
    {
        $user = auth()->user();
        $queries = new DataExportQueries;

        $filters = array_merge($request->only([
            'status', 'search', 'age_min_days', 'age_max_days',
            'date_from', 'date_to', 'agcy_id', 'category_id', 'category_ids', 'case_issue_id',
        ]), CategoryFilter::fromRequest($request)->toArray());

        $referrals = $queries->getReferralsExport($user, array_filter($filters));

        $columnMap = self::referralsExportColumnMap();

        // Tag each row with the export timestamp for provenance
        $now = now()->format('Y-m-d H:i:s');
        $referrals = $referrals->map(function ($row) use ($now) {
            $row->exported_at = $now;

            return $row;
        });

        $filename = 'referrals-export-'.now()->format('Ymd-His').'.xlsx';

        return (new DataExportService)->generateSingleSheet('Referrals', $columnMap, $referrals, $filename);
    }

    /**
     * Merged business-export column map — case + referral data in one row per referral.
     * No IDs or system fields. The referrals export is replaced by this richer view.
     */
    public static function referralsExportColumnMap(): array
    {
        return [
            // Primary columns (aligned with Referrals page table)
            ['key' => 'case_number',           'label' => 'Case No',               'type' => 'string'],
            ['key' => 'client_full_name',       'label' => 'Client',                'type' => 'string'],
            ['key' => 'client_contact_number',  'label' => 'Client Contact No.',    'type' => 'string'],
            ['key' => 'client_email',           'label' => 'Client Email',          'type' => 'string'],
            ['key' => 'case_summary',           'label' => 'Case Summary',          'type' => 'string'],
            ['key' => 'issue_concern',          'label' => 'Issue / Concern',       'type' => 'string'],
            ['key' => 'referred_agency',        'label' => 'Agency',                'type' => 'string'],
            ['key' => 'required_services',      'label' => 'Service',               'type' => 'string'],
            ['key' => 'referral_status',        'label' => 'Status',                'type' => 'status'],
            ['key' => 'latest_update',          'label' => 'Latest Update',         'type' => 'string'],
            ['key' => 'date_referred',          'label' => 'Date Referred',         'type' => 'date'],
            // Case details
            ['key' => 'case_status',            'label' => 'Case Status',           'type' => 'status'],
            ['key' => 'tracker_number',         'label' => 'Case Tracking ID',      'type' => 'string'],
            ['key' => 'client_type',            'label' => 'Client Type',           'type' => 'string'],
            // Client demographics
            ['key' => 'sex',                    'label' => 'Gender',                'type' => 'string'],
            ['key' => 'client_date_of_birth',   'label' => 'Date of Birth',         'type' => 'date'],
            ['key' => 'client_age',             'label' => 'Age',                   'type' => 'string'],
            // Address
            ['key' => 'client_full_address',    'label' => 'Full Address',          'type' => 'string'],
            ['key' => 'barangay',               'label' => 'Barangay',              'type' => 'string'],
            ['key' => 'municipality',           'label' => 'Municipality',          'type' => 'string'],
            ['key' => 'province',               'label' => 'Province',              'type' => 'string'],
            ['key' => 'region',                 'label' => 'Region',                'type' => 'string'],
            // Employment
            ['key' => 'vulnerability',          'label' => 'Vulnerability',         'type' => 'string'],
            ['key' => 'date_of_arrival',        'label' => 'Date of Arrival in PH', 'type' => 'date'],
            ['key' => 'previous_country',       'label' => 'Previous Country',      'type' => 'string'],
            ['key' => 'work_position',          'label' => 'Work Position',         'type' => 'string'],
            // NOK info
            ['key' => 'nok_full_name',          'label' => 'NOK Full Name',         'type' => 'string'],
            ['key' => 'nok_relationship',       'label' => 'NOK Relationship',      'type' => 'string'],
            ['key' => 'nok_contact_number',     'label' => 'NOK Contact No.',       'type' => 'string'],
            ['key' => 'nok_email',              'label' => 'NOK Email',             'type' => 'string'],
            // Footer
            ['key' => 'exported_at',            'label' => 'Exported At',           'type' => 'string'],
        ];
    }

    private function authorizeReferralAccess($referral, $user)
    {
        // ADMIN and CASE_MANAGER: full access to all referrals
        if ($user->isAdmin() || $user->isCaseManager()) {
            return;
        }
        if ($user->isAgency() && ! $user->agcy_id) {
            abort(403, 'You do not have access to this referral.');
        }
        if ($referral->agcy_id === $user->agcy_id) {
            return;
        }

        abort(403, 'You do not have access to this referral.');
    }
}
