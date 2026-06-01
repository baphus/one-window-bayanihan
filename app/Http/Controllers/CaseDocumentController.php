<?php

namespace App\Http\Controllers;

use App\Models\CaseDocument;
use App\Models\CaseFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CaseDocumentController extends Controller
{
    public function index(Request $request, string $caseId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeAccess($case, $request->user());

        $documents = $case->documents()->where('is_deleted', false)->get();

        return response()->json($documents);
    }

    public function store(Request $request, string $caseId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeAccess($case, $request->user());

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,gif', 'max:10240'],
        ]);

        $uploaded = $request->file('file')->storeOnCloudinaryAs(
            'case-documents/'.$caseId,
            Str::random(40)
        );

        $document = CaseDocument::create([
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $uploaded->getSecurePath(),
            'file_type' => $request->file('file')->getMimeType(),
            'case_id' => $caseId,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($document, 201);
    }

    public function show(Request $request, string $caseId, string $documentId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeAccess($case, $request->user());

        $document = CaseDocument::where('case_id', $caseId)
            ->where('id', $documentId)
            ->where('is_deleted', false)
            ->firstOrFail();

        return response()->json($document);
    }

    public function download(Request $request, string $caseId, string $documentId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeAccess($case, $request->user());

        $document = CaseDocument::where('case_id', $caseId)
            ->where('id', $documentId)
            ->where('is_deleted', false)
            ->firstOrFail();

        return redirect($document->file_path);
    }

    public function destroy(Request $request, string $caseId, string $documentId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeAccess($case, $request->user());

        $document = CaseDocument::where('case_id', $caseId)
            ->where('id', $documentId)
            ->where('is_deleted', false)
            ->firstOrFail();

        $document->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'deleted_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Document deleted successfully.']);
    }

    private function authorizeAccess(CaseFile $case, $user)
    {
        if ($case->user_id === $user->id) {
            return;
        }
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
            abort(403, 'You do not have access to documents for this case.');
        }
    }
}
