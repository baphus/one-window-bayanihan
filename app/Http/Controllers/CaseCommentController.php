<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CaseComment;
use App\Models\CaseFile;
use Illuminate\Http\Request;

class CaseCommentController extends Controller
{
    public function store(Request $request, string $caseId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeCaseAccess($case, $request->user());

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment = CaseComment::create([
            'case_id' => $caseId,
            'content' => $validated['content'],
            'user_id' => $request->user()->id,
        ]);

        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'CASE_COMMENT',
            'entity_id' => $comment->id,
            'new_value' => $comment->toArray(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Comment added.');
    }

    public function reply(Request $request, string $caseId, string $commentId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeCaseAccess($case, $request->user());

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $parent = CaseComment::findOrFail($commentId);

        $reply = CaseComment::create([
            'case_id' => $caseId,
            'parent_id' => $commentId,
            'content' => $validated['content'],
            'user_id' => $request->user()->id,
        ]);

        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'CASE_REPLY',
            'entity_id' => $reply->id,
            'new_value' => $reply->toArray(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Reply added.');
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
