<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralStatusRequest;
use App\Http\Requests\StoreMilestoneRequest;
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
                    'file_url' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $request->user()->id,
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

        return Inertia::render('Referral/Show', [
            'referral' => $referral,
        ]);
    }

    public function updateStatus(UpdateReferralStatusRequest $request, string $id)
    {
        $referral = $this->referralService->updateStatus(
            $id,
            $request->input('status'),
            $request->input('decision'),
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
}
