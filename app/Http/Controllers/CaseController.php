<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CaseController extends Controller
{
    public function __construct(
        private readonly CaseService $caseService,
    ) {}

    public function index(Request $request)
    {
        $cases = $this->caseService->getCases(
            $request->only(['status', 'search', 'client_type', 'vulnerability_indicator', 'user_id', 'agcy_id'])
        );

        return Inertia::render('Case/Index', [
            'cases' => $cases,
            'filters' => $request->only(['status', 'search', 'client_type', 'vulnerability_indicator', 'user_id', 'agcy_id']),
            'stats' => $this->caseService->getCaseStats(),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
            'agencies' => Agency::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $client = null;
        if ($request->has('client_id')) {
            $client = Client::with(['addresses', 'employments', 'nextOfKin', 'caseFile'])->find($request->client_id);
        }

        $existingClients = Client::with(['addresses', 'employments', 'nextOfKin'])
            ->where('is_deleted', false)
            ->orderBy('last_name')
            ->limit(200)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'full_name' => trim("{$c->first_name} {$c->middle_name} {$c->last_name} {$c->suffix}"),
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'middle_name' => $c->middle_name,
                'suffix' => $c->suffix,
                'sex' => $c->sex,
                'date_of_birth' => $c->date_of_birth?->format('Y-m-d'),
                'email' => $c->email,
                'contact_number' => $c->contact_number,
                'addresses' => $c->addresses,
                'employments' => $c->employments,
                'nextOfKin' => $c->nextOfKin,
                'has_case' => $c->case_id !== null,
            ]);

        return Inertia::render('Case/Create', [
            'client' => $client,
            'existingClients' => $existingClients,
        ]);
    }

    public function store(StoreCaseRequest $request)
    {
        $isDraft = $request->boolean('is_draft');

        $case = $this->caseService->createCase(
            $request->validated(),
            $request->user()->id,
            $isDraft,
        );

        if ($isDraft) {
            return redirect()
                ->route('cases.index')
                ->with('success', 'Draft saved successfully.');
        }

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Case created successfully.');
    }

    public function show(string $id, Request $request)
    {
        $case = $this->caseService->getCase($id);
        $this->authorizeCaseAccess($case, $request->user());
        if ($case->status === 'DRAFT' && $case->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this draft.');
        }
        $overdueDays = (int) SystemSetting::getValue('referral_overdue_days', 7);

        return Inertia::render('Case/Show', [
            'case' => $case,
            'overdueDays' => $overdueDays,
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
        $case = $this->caseService->publishDraft($case->id, $request->user()->id);

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Draft published successfully.');
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
        $drafts = $this->caseService->getUserDrafts($request->user()->id);

        return Inertia::render('Draft/Index', [
            'drafts' => $drafts,
        ]);
    }

    public function destroyDraft(string $id, Request $request)
    {
        $this->caseService->deleteDraft($id, $request->user()->id);

        return redirect()
            ->route('cases.drafts')
            ->with('success', 'Draft deleted successfully.');
    }

    private function authorizeCaseAccess($case, $user)
    {
        if ($user->hasRole('ADMIN') || $user->role === 'ADMIN') {
            return;
        }
        if ($user->hasRole('CASE_MANAGER') || $user->role === 'CASE_MANAGER') {
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
