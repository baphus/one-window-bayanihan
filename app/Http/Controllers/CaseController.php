<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\Client;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CaseService;
use App\Services\Export\ColumnMaps;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\PhilippineAddressService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CaseController extends Controller
{
    public function __construct(
        private readonly CaseService $caseService,
        private readonly PhilippineAddressService $addressService,
    ) {}

    public function index(Request $request)
    {
        $filterKeys = ['status', 'search', 'client_type', 'vulnerability_indicator', 'user_id', 'agcy_id', 'category_id'];

        $cases = $this->caseService->getCases(
            $request->only($filterKeys)
        );

        $categories = CaseCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
        $caseIssues = CaseIssue::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Case/Index', [
            'cases' => $cases,
            'filters' => $request->only($filterKeys),
            'stats' => $this->caseService->getCaseStats(),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
            'agencies' => Agency::select('id', 'name')->orderBy('name')->get(),
            'categories' => $categories,
            'caseIssues' => $caseIssues,
        ]);
    }

    public function create(Request $request)
    {
        $client = null;
        if ($request->has('client_id')) {
            $client = Client::with(['addresses', 'employments', 'nextOfKin', 'caseFiles'])->find($request->client_id);
        }

        $existingClients = $this->mapExistingClients(
            Client::with(['addresses', 'employments', 'nextOfKin'])
                ->withCount('caseFiles')
                ->where('is_deleted', false)
                ->orderBy('last_name')
                ->limit(200)
                ->get()
        );

        $categories = CaseCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']);
        $caseIssues = CaseIssue::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Case/Create', [
            'client' => $client,
            'existingClients' => $existingClients,
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

        $existingClients = $this->mapExistingClients(
            Client::with(['addresses', 'employments', 'nextOfKin'])
                ->withCount('caseFiles')
                ->where('is_deleted', false)
                ->orderBy('last_name')
                ->limit(200)
                ->get()
        );

        $categories = CaseCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']);
        $caseIssues = CaseIssue::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Case/Create', [
            'existingDraft' => $case,
            'existingClients' => $existingClients,
            'categories' => $categories,
            'caseIssues' => $caseIssues,
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
        // Require email when publishing a new client draft
        if (empty($case->client_id) && ! empty($case->draft_client_data)) {
            $draftEmail = $case->draft_client_data['email'] ?? null;
            if (empty($draftEmail)) {
                return redirect()->back()->withErrors([
                    'client.email' => 'The client email is required before publishing. Please update the draft with a valid email address.',
                ]);
            }
        }

        $case = $this->caseService->publishDraft($case->id, $request->user()->id);

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

    private function mapExistingClients($clients): array
    {
        $allCodes = collect();
        foreach ($clients as $c) {
            foreach ($c->addresses as $a) {
                $allCodes->push($a->region);
                $allCodes->push($a->province);
                $allCodes->push($a->city_municipality);
                $allCodes->push($a->barangay);
            }
        }
        $names = $this->addressService->resolveNames(
            $allCodes->filter()->unique()->values()->toArray()
        );

        return $clients->map(fn ($c) => [
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
            'addresses' => $c->addresses->map(fn ($a) => [
                'id' => $a->id,
                'region' => $a->region,
                'province' => $a->province,
                'city_municipality' => $a->city_municipality,
                'barangay' => $a->barangay,
                'street' => $a->street,
                'region_name' => $a->region ? ($names[$a->region] ?? null) : null,
                'province_name' => $a->province ? ($names[$a->province] ?? null) : null,
                'city_municipality_name' => $a->city_municipality ? ($names[$a->city_municipality] ?? null) : null,
                'barangay_name' => $a->barangay ? ($names[$a->barangay] ?? null) : null,
            ]),
            'employments' => $c->employments,
            'nextOfKin' => $c->nextOfKin,
            'has_case' => $c->case_files_count > 0,
            'case_count' => $c->case_files_count,
        ])->toArray();
    }

    public function exportExcel()
    {
        $user = auth()->user();
        $queries = new DataExportQueries;
        $cases = $queries->getCases($user);
        $columnMap = ColumnMaps::getMap('cases');
        $filename = 'cases-export-'.now()->format('Ymd-His').'.xlsx';

        return (new DataExportService)->generateSingleSheet('Cases', $columnMap, $cases, $filename);
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
