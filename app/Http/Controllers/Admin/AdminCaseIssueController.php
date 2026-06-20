<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseIssue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminCaseIssueController extends Controller
{
    public function index()
    {
        $issues = CaseIssue::withCount('caseFiles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/CaseIssue/Index', [
            'issues' => $issues,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:case_issues,name',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        CaseIssue::create($validated);

        return redirect()->route('admin.case-issues.index')
            ->with('success', 'Issue created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $issue = CaseIssue::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:case_issues,name,'.$id,
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $issue->update($validated);

        return redirect()->route('admin.case-issues.index')
            ->with('success', 'Issue updated successfully.');
    }

    public function destroy(string $id)
    {
        $issue = CaseIssue::withCount('caseFiles')->findOrFail($id);

        if ($issue->case_files_count > 0) {
            return redirect()->route('admin.case-issues.index')
                ->with('error', 'Cannot delete issue: '.$issue->case_files_count.' case(s) are using it.');
        }

        $issue->update(['is_deleted' => true, 'is_active' => false]);

        return redirect()->route('admin.case-issues.index')
            ->with('success', 'Issue deleted successfully.');
    }
}
