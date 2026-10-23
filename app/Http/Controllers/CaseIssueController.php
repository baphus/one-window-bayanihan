<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\CaseIssue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaseIssueController extends Controller
{
    public function quickStore(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::ADMIN->value, UserRole::CASE_MANAGER->value]), 403, 'Only administrators and case managers can create case issues.');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:case_issues,name',
        ]);

        try {
            // Two concurrent quick-adds for the same name both pass the unique
            // rule against stale data. The locked existence check serializes
            // the visible check+create pair inside the transaction, and the
            // case_issues unique key is the final tiebreaker for an in-flight
            // duplicate insert. A lost race surfaces as the same
            // validation-error UX the rule would have produced, not a 500.
            return DB::transaction(function () use ($validated) {
                if (CaseIssue::where('name', $validated['name'])->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'name' => ['The name has already been taken.'],
                    ]);
                }

                $issue = CaseIssue::create([
                    'name' => $validated['name'],
                    'sort_order' => (CaseIssue::max('sort_order') ?? 0) + 1,
                    'is_active' => true,
                ]);

                return response()->json([
                    'id' => $issue->id,
                    'name' => $issue->name,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => ['The name has already been taken.'],
            ]);
        }
    }
}
