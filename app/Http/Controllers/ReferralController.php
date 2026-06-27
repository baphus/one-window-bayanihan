<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralStatusRequest;
use App\Models\CaseFile;
use App\Models\ReferralAttachment;
use App\Models\SystemSetting;
use App\Services\Export\ColumnMaps;
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
            'filters' => $request->only(['status', 'search', 'case_id', 'agcy_id']),
        ]);
    }

    public function create(Request $request)
    {
        $agencies = $this->referralService->getAgenciesWithServices();
        $cases = CaseFile::with('client')
            ->where('status', 'OPEN')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Referral/Create', [
            'case_id' => $request->query('case_id'),
            'agencies' => $agencies,
            'cases' => $cases,
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
        ]);
    }

    public function updateStatus(UpdateReferralStatusRequest $request, string $id)
    {
        $referral = $this->referralService->getReferral($id);
        $this->authorizeReferralAccess($referral, $request->user());

        if (auth()->user()->role === 'CASE_MANAGER' && ! $referral->isIntervention()) {
            abort(403, 'Case managers can only update status on intervention referrals.');
        }

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

    public function getAttachmentVersions(string $id, string $versionGroupId)
    {
        $versions = $this->referralService->getAttachmentVersions($versionGroupId);

        return response()->json($versions);
    }

    public function exportExcel()
    {
        $user = auth()->user();
        $queries = new DataExportQueries;
        $referrals = $queries->getReferrals($user);
        $columnMap = ColumnMaps::getMap('referrals');
        $filename = 'referrals-export-'.now()->format('Ymd-His').'.xlsx';

        return (new DataExportService)->generateSingleSheet('Referrals', $columnMap, $referrals, $filename);
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
