<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseRequest;
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
            $request->only(['status', 'search'])
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
        $case = $this->caseService->createCase(
            $request->validated(),
            $request->user()->id,
        );

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
}
