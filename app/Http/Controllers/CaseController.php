<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Models\Agency;
use App\Models\CaseCategory;
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
        $filterKeys = ['status', 'search', 'client_type', 'vulnerability_indicator', 'user_id', 'agcy_id', 'category_id'];

        $cases = $this->caseService->getCases(
            $request->only($filterKeys)
        );

        $categories = CaseCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Case/Index', [
            'cases' => $cases,
            'filters' => $request->only($filterKeys),
            'stats' => $this->caseService->getCaseStats(),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
            'agencies' => Agency::select('id', 'name')->orderBy('name')->get(),
            'categories' => $categories,
        ]);
    }

    public function create(Request $request)
    {
        $client = null;
        if ($request->has('client_id')) {
            $client = Client::with(['addresses', 'employments', 'nextOfKin', 'caseFiles'])->find($request->client_id);
        }

        $existingClients = Client::with(['addresses', 'employments', 'nextOfKin'])
            ->withCount('caseFiles')
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
                'has_case' => $c->case_files_count > 0,
                'case_count' => $c->case_files_count,
            ]);

        $categories = CaseCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']);

        return Inertia::render('Case/Create', [
            'client' => $client,
            'existingClients' => $existingClients,
            'categories' => $categories,
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
                ->route('cases.drafts')
                ->with('success', 'Draft saved successfully.');
        }

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Case created successfully.');
    }

    public function editDraft(Request $request, string $id)
    {
        $case = $this->caseService->getCase($id);
        abort_unless($case->status === 'DRAFT', 404);
        abort_unless($case->user_id === $request->user()->id, 403);

        $existingClients = Client::with(['addresses', 'employments', 'nextOfKin'])
            ->withCount('caseFiles')
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
                'has_case' => $c->case_files_count > 0,
                'case_count' => $c->case_files_count,
            ]);

        $categories = CaseCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']);

        return Inertia::render('Case/Create', [
            'existingDraft' => $case,
            'existingClients' => $existingClients,
            'categories' => $categories,
        ]);
    }

    public function updateDraft(UpdateDraftRequest $request, string $id)
    {
        $this->caseService->updateDraft($id, $request->validated(), $request->user()->id);

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
