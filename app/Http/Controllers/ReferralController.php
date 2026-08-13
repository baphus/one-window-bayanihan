<?php

namespace App\Http\Controllers;

use App\Exceptions\ReferralDocumentUploadException;
use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralStatusRequest;
use App\Models\Agency;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralComment;
use App\Models\SystemSetting;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\OnboardingService;
use App\Services\ReferenceDataService;
use App\Services\ReferralService;
use App\Services\StorageService;
use App\Support\CategoryFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $queryOptions = $request->validate([
            'sort' => ['sometimes', 'string', 'in:case_number,client,case_issue,agency,status'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ]);
        $categoryFilters = CategoryFilter::fromRequest($request)->toArray();

        $referrals = $this->referralService->getReferrals(
            array_merge($request->only($filterKeys), $categoryFilters, $queryOptions),
            $user->agcy_id,
            $user->role,
            $user->id,
        );

        return Inertia::render('Referral/Index', [
            'referrals' => $referrals,
            'filters' => (object) array_merge($request->only($filterKeys), $categoryFilters, $queryOptions),
            'stats' => $this->referralService->getReferralStats($user->agcy_id, $user->role, $user->id),
            'agencies' => $this->referenceData->getAgenciesDropdown(),
            'categories' => $this->referenceData->getActiveCategories(),
            'caseIssues' => $this->referenceData->getActiveIssues(),
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
        // when a case is already referred to a given agency. Matches the
        // StoreReferralRequest duplicate rule — terminal (REJECTED/COMPLETED)
        // referrals don't block a new one.
        $caseIds = $cases->pluck('id');
        $existingReferrals = Referral::whereIn('case_id', $caseIds)
            ->where('is_deleted', false)
            ->whereNotIn('status', ['REJECTED', 'COMPLETED'])
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
        $storage = app(StorageService::class);
        $storedPaths = [];

        try {
            $referral = DB::transaction(function () use ($request, $storage, &$storedPaths) {
                $referral = $this->referralService->createReferral(
                    $request->validated(),
                    $request->user()->id,
                );

                if ($request->hasFile('documents')) {
                    foreach ($request->file('documents') as $file) {
                        $result = $storage->store($file, 'case-documents/'.$referral->case_id);

                        if (! $result->success) {
                            throw new ReferralDocumentUploadException(
                                $result->error ?? 'Failed to store file.',
                            );
                        }

                        $storedPaths[] = $result->path;

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

                return $referral;
            });
        } catch (ReferralDocumentUploadException $e) {
            // Roll back the object-storage side so a failed upload leaves no
            // orphaned files. The DB transaction is rolled back automatically.
            foreach ($storedPaths as $path) {
                $storage->delete($path);
            }

            return back()->withErrors(['documents' => $e->userMessage]);
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

        // Only the owning agency may move a PENDING referral forward — accept
        // (PROCESSING), gate it (FOR_COMPLIANCE) — or reject it. Case managers
        // and admins can change status only after the agency has engaged the
        // referral, and only in ways that do not dispose of it (e.g. COMPLETED).
        $isAccept = $request->input('status') === 'PROCESSING'
            || $request->input('decision') === 'ACCEPT';

        $isGateOnPending = $referral->status === 'PENDING'
            && $request->input('status') === 'FOR_COMPLIANCE';

        $isReject = $request->input('status') === 'REJECTED'
            || $request->input('decision') === 'REJECT';

        if (! $request->user()->isAgency() && ($isAccept || $isGateOnPending || $isReject)) {
            $message = match (true) {
                $isAccept => 'Only the receiving agency can accept this referral.',
                $isReject => 'Only the receiving agency can reject this referral.',
                default => 'Only the receiving agency can set a pending referral to For Compliance.',
            };

            return redirect()
                ->back()
                ->with('error', $message);
        }

        try {
            $referral = $this->referralService->updateStatus(
                $id,
                $request->input('status'),
                $request->input('decision'),
                $request->input('decision_comment'),
                $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

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

        // Do not let an accessible referral be used as a pivot to another
        // referral's comment tree.
        ReferralComment::where('refr_id', $id)->where('id', $commentId)->firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'visibility' => 'sometimes|in:INTERNAL,AGY_ONLY',
        ]);

        $reply = $this->referralService->replyToComment(
            $id,
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

        // The attachment must belong to the referral whose access was checked.
        ReferralAttachment::where('referral_id', $id)
            ->where('id', $attachmentId)
            ->where('is_deleted', false)
            ->firstOrFail();

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
            $id,
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
        $versions = $this->referralService->getAttachmentVersions($id, $versionGroupId);

        return response()->json($versions);
    }

    public function addService(Request $request, string $id)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        if ($request->user()->role !== 'AGENCY') {
            return redirect()->back()->with('error', 'Only the receiving agency can manage services.');
        }

        $validated = $request->validate([
            'service_id' => 'required|string|exists:services,id',
        ]);

        try {
            $this->referralService->addService($referral, $validated['service_id']);

            return redirect()->back()->with('success', 'Service added to referral.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function removeService(Request $request, string $id, string $serviceId)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        if ($request->user()->role !== 'AGENCY') {
            return redirect()->back()->with('error', 'Only the receiving agency can manage services.');
        }

        try {
            $this->referralService->removeService($referral, $serviceId);

            return redirect()->back()->with('success', 'Service removed from referral.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function addRequirement(Request $request, string $id, string $serviceId)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        if ($request->user()->role !== 'AGENCY') {
            return redirect()->back()->with('error', 'Only the receiving agency can manage service requirements.');
        }

        if ($referral->status === 'COMPLETED') {
            return redirect()->back()->with('error', 'Cannot modify requirements on a completed referral.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'is_required' => 'sometimes|boolean',
        ]);

        try {
            $this->referralService->addRequirement($referral, $serviceId, $validated);

            return redirect()->back()->with('success', 'Requirement added.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function updateRequirement(Request $request, string $id, string $serviceId, string $requirementId)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        if ($request->user()->role !== 'AGENCY') {
            return redirect()->back()->with('error', 'Only the receiving agency can manage service requirements.');
        }

        if ($referral->status === 'COMPLETED') {
            return redirect()->back()->with('error', 'Cannot modify requirements on a completed referral.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:5000',
            'is_required' => 'sometimes|boolean',
        ]);

        try {
            $this->referralService->updateRequirement($referral, $serviceId, $requirementId, $validated);

            return redirect()->back()->with('success', 'Requirement updated.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function deleteRequirement(Request $request, string $id, string $serviceId, string $requirementId)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        if ($request->user()->role !== 'AGENCY') {
            return redirect()->back()->with('error', 'Only the receiving agency can manage service requirements.');
        }

        if ($referral->status === 'COMPLETED') {
            return redirect()->back()->with('error', 'Cannot modify requirements on a completed referral.');
        }

        try {
            $this->referralService->deleteRequirement($referral, $serviceId, $requirementId);

            return redirect()->back()->with('success', 'Requirement deleted.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();

        $filters = array_filter(array_merge($request->only([
            'status', 'search', 'age_min_days', 'age_max_days',
            'date_from', 'date_to', 'agcy_id', 'category_id', 'category_ids', 'case_issue_id',
        ]), CategoryFilter::fromRequest($request)->toArray()));

        $queries = new DataExportQueries;
        $exportService = new DataExportService;

        $data = $queries->getReferralsExport($user, $filters);

        $now = now()->format('Y-m-d H:i:s');
        $data = $data->map(function ($row) use ($now) {
            $row->exported_at = $now;

            return $row;
        });

        $filename = 'referrals-export-'.now()->format('Ymd-His').'.xlsx';

        return $exportService->generateSingleSheet(
            'Referrals',
            self::referralsExportColumnMap(),
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
            'status', 'search', 'age_min_days', 'age_max_days',
            'date_from', 'date_to', 'agcy_id', 'category_id', 'category_ids', 'case_issue_id',
        ]), CategoryFilter::fromRequest($request)->toArray()));

        $count = (new DataExportQueries)->countReferralsExport($user, $filters);

        return response()->json(['count' => $count]);
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
            ['key' => 'work_position',          'label' => 'Work Occupation',         'type' => 'string'],
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
