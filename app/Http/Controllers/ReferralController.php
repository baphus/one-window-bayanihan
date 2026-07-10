<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralStatusRequest;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\SystemSetting;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\ReferralService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $referrals = $this->referralService->getReferrals(
            $request->only(['status', 'search', 'case_id', 'agcy_id']),
            $user->agcy_id,
            $user->role,
        );

        return Inertia::render('Referral/Index', [
            'referrals' => $referrals,
            'filters' => (object) $request->only(['status', 'search', 'case_id', 'agcy_id']),
        ]);
    }

    public function create(Request $request)
    {
        $agencies = $this->referralService->getAgenciesWithServices();

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
            foreach ($request->file('documents') as $key => $file) {
                $errors = app(StorageService::class)->validate($file, 'referral_attachment');
                if (! empty($errors)) {
                    return back()->withErrors(['documents.'.$key => $errors[0]]);
                }

                $result = app(StorageService::class)->store($file, 'referrals/'.$referral->id);

                if (! $result->success) {
                    return back()->withErrors(['documents.'.$key => $result->error ?? 'Failed to store file.']);
                }

                ReferralAttachment::create([
                    'referral_id' => $referral->id,
                    'file_name' => implode(' - ', [str_replace('::', ' / ', $key), $result->originalName]),
                    'file_path' => $result->path,
                    'file_type' => $result->type,
                    'size' => $result->size,
                    'user_id' => $request->user()->id,
                ]);
            }
        }

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

        return Inertia::render('Referral/Show', [
            'referral' => $referral,
            'serviceRequirements' => $serviceRequirements,
            'overdueDays' => $overdueDays,
            'timeline' => $this->referralService->getReferralTimeline($referral),
        ]);
    }

    public function updateStatus(UpdateReferralStatusRequest $request, string $id)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        $referral = $this->referralService->updateStatus(
            $id,
            $request->input('status'),
            $request->input('decision'),
            $request->input('decision_comment'),
            $request->user()->id,
        );

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

        $milestone = $this->referralService->addMilestone(
            $id,
            $request->input('title'),
            $request->input('description'),
            $request->user()->id,
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
            'visibility' => 'sometimes|in:INTERNAL,AGY_ONLY,CLIENT_VISIBLE',
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
            'visibility' => 'sometimes|in:INTERNAL,AGY_ONLY,CLIENT_VISIBLE',
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
                'name' => $result->originalName,
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

    public function fulfillCompliance(Request $request, string $id, string $complianceId)
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

        $this->referralService->fulfillCompliance(
            $complianceId,
            [
                'name' => $result->originalName,
                'path' => $result->path,
                'type' => $result->type,
                'size' => $result->size,
            ],
            $request->user()->id,
        );

        return redirect()->back()->with('success', 'Compliance requirement fulfilled.');
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
                'name' => $result->originalName,
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

        $filters = $request->only([
            'status', 'search',
        ]);

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
            // Case info
            ['key' => 'case_number',           'label' => 'Case No',               'type' => 'string'],
            ['key' => 'case_status',            'label' => 'Case Status',           'type' => 'status'],
            ['key' => 'tracker_number',         'label' => 'Case Tracking ID',      'type' => 'string'],
            ['key' => 'client_type',            'label' => 'Client Type',           'type' => 'string'],
            // Client demographics
            ['key' => 'client_full_name',       'label' => 'Client Full Name',      'type' => 'string'],
            ['key' => 'sex',                    'label' => 'Gender',                'type' => 'string'],
            ['key' => 'client_date_of_birth',   'label' => 'Client Date of Birth',  'type' => 'date'],
            ['key' => 'client_age',             'label' => 'Client Age',            'type' => 'string'],
            ['key' => 'client_contact_number',  'label' => 'Client Contact No.',    'type' => 'string'],
            ['key' => 'client_email',           'label' => 'Client Email Address',  'type' => 'string'],
            // Address
            ['key' => 'barangay',               'label' => 'Barangay',              'type' => 'string'],
            ['key' => 'municipality',           'label' => 'Municipality',          'type' => 'string'],
            ['key' => 'province',               'label' => 'Province',              'type' => 'string'],
            ['key' => 'region',                 'label' => 'Region',                'type' => 'string'],
            ['key' => 'client_full_address',    'label' => 'Client Full Address',   'type' => 'string'],
            // Case details
            ['key' => 'vulnerability',          'label' => 'Vulnerability',         'type' => 'string'],
            ['key' => 'date_of_arrival',        'label' => 'Date of Arrival in PH', 'type' => 'date'],
            ['key' => 'previous_country',       'label' => 'Previous Country',      'type' => 'string'],
            ['key' => 'work_position',          'label' => 'Work Position',         'type' => 'string'],
            ['key' => 'issue_concern',          'label' => 'Issues/Concern',        'type' => 'string'],
            ['key' => 'case_summary',           'label' => 'Case Summary',          'type' => 'string'],
            // NOK info
            ['key' => 'nok_full_name',          'label' => 'NOK Full Name',         'type' => 'string'],
            ['key' => 'nok_relationship',       'label' => 'NOK Relationship',      'type' => 'string'],
            ['key' => 'nok_contact_number',     'label' => 'NOK Contact No.',       'type' => 'string'],
            ['key' => 'nok_email',              'label' => 'NOK Email',             'type' => 'string'],
            // Referral info
            ['key' => 'referred_agency',        'label' => 'Referred Agency',       'type' => 'string'],
            ['key' => 'referral_status',        'label' => 'Referral Status',       'type' => 'status'],
            ['key' => 'date_referred',          'label' => 'Date Referred',         'type' => 'date'],
            // Footer
            ['key' => 'exported_at',            'label' => 'Exported At',           'type' => 'string'],
        ];
    }

    private function authorizeReferralAccess($referral, $user)
    {
        if ($user->isAdmin()) {
            return;
        }
        if ($user->isCaseManager()) {
            if ($referral->caseFile && $referral->caseFile->user_id === $user->id) {
                return;
            }
            abort(403, 'You do not have access to this referral.');
        }
        if ($referral->agcy_id === $user->agcy_id) {
            return;
        }

        abort(403, 'You do not have access to this referral.');
    }
}
