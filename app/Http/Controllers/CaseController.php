<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Models\CaseFile;
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
            $request->only(['status', 'search', 'client_type'])
        );

        return Inertia::render('Case/Index', [
            'cases' => $cases,
            'filters' => $request->only(['status', 'search', 'client_type']),
            'stats' => $this->caseService->getCaseStats(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Case/Create');
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

    public function show(string $id)
    {
        $case = $this->caseService->getCase($id);

        return Inertia::render('Case/Show', [
            'case' => $case,
        ]);
    }

    public function update(UpdateCaseRequest $request, string $id)
    {
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
        $case = $this->caseService->toggleCaseStatus($id, $request->user()->id);

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Case status updated successfully.');
    }

    public function publish(CaseFile $case)
    {
        $case = $this->caseService->publishDraft($case->id);

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Draft published successfully.');
    }

    public function archive(Request $request, string $id)
    {
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
        $case = $this->caseService->unarchiveCase(
            $id,
            $request->user()->id,
        );

        return redirect()
            ->route('cases.show', $case)
            ->with('success', 'Case restored from archive successfully.');
    }
}
