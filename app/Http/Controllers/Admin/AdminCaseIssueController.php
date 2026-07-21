<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseIssue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminCaseIssueController extends Controller
{
    public function index(Request $request)
    {
        $query = CaseIssue::withTrashed()->withCount('caseFiles');

        if (! $request->boolean('show_deleted')) {
            $query->where('is_deleted', false);
        }

        $issues = $query->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/CaseIssue/Index', [
            'issues' => $issues,
            'filters' => $request->only(['show_deleted']),
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

        $issue->is_active = false;
        $issue->delete();

        return redirect()->route('admin.case-issues.index')
            ->with('success', 'Issue deleted successfully.');
    }

    public function reactivate(string $id)
    {
        $issue = CaseIssue::withTrashed()->findOrFail($id);

        if ($issue->is_active && ! $issue->is_deleted) {
            return redirect()->route('admin.case-issues.index')
                ->with('error', 'Issue is already active.');
        }

        $issue->is_active = true;
        $issue->is_deleted = false;
        $issue->deleted_at = null;
        $issue->save();

        AuditLog::create([
            'action' => AuditAction::UPDATE->value,
            'module' => AuditModule::CASE_ISSUE->value,
            'entity_id' => $issue->id,
            'user_id' => auth()->id(),
            'timestamp' => now(),
        ]);

        return redirect()->route('admin.case-issues.index')
            ->with('success', 'Issue reactivated successfully.');
    }
}
