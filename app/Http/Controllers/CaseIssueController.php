<?php

namespace App\Http\Controllers;

use App\Models\CaseIssue;
use Illuminate\Http\Request;

class CaseIssueController extends Controller
{
    public function quickStore(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['ADMIN', 'CASE_MANAGER']), 403, 'Only administrators and case managers can create case issues.');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:case_issues,name',
        ]);

        $maxSort = CaseIssue::max('sort_order') ?? 0;

        $issue = CaseIssue::create([
            'name' => $validated['name'],
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $issue->id,
            'name' => $issue->name,
        ]);
    }
}
