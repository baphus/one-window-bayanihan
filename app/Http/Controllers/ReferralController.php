<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralStatusRequest;
use App\Models\CaseFile;
use App\Models\ReferralAttachment;
use App\Services\ReferralService;
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
            $request->only(['status', 'case_id', 'agcy_id']),
            $user->agcy_id,
            $user->role,
        );

        return Inertia::render('Referral/Index', [
            'referrals' => $referrals,
            'filters' => $request->only(['status', 'case_id', 'agcy_id']),
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
                $path = $file->store('referrals', 'public');

                ReferralAttachment::create([
                    'referral_id' => $referral->id,
                    'file_name' => implode(' - ', [str_replace('::', ' / ', $key), $file->getClientOriginalName()]),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'user_id' => $request->user()->id,
                ]);
            }
        }

        return redirect()
            ->route('referrals.show', $referral)
            ->with('success', 'Referral created successfully.');
    }

    public function show(string $id)
    {
        $referral = $this->referralService->getReferral($id);
        $serviceRequirements = $this->referralService->getServiceRequirements($referral->agcy_id);

        return Inertia::render('Referral/Show', [
            'referral' => $referral,
            'serviceRequirements' => $serviceRequirements,
        ]);
    }

    public function updateStatus(UpdateReferralStatusRequest $request, string $id)
    {
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
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('referrals', 'public');

        $attachment = $this->referralService->addAttachment(
            $id,
            [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
            $request->user()->id,
        );

        return redirect()
            ->back()
            ->with('success', 'Attachment added.');
    }

    public function replaceAttachment(Request $request, string $id, string $attachmentId)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('referrals', 'public');

        $attachment = $this->referralService->replaceAttachment(
            $attachmentId,
            [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
            $request->user()->id,
        );

        return redirect()
            ->back()
            ->with('success', 'Attachment replaced.');
    }

    public function getAttachmentVersions(string $id, string $versionGroupId)
    {
        $versions = $this->referralService->getAttachmentVersions($versionGroupId);

        return response()->json($versions);
    }
}
