<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseDocumentRequest;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Services\StorageService;
use Illuminate\Http\Request;

class CaseDocumentController extends Controller
{
    public function index(Request $request, string $caseId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeAccess($case, $request->user());

        $query = $case->documents()->where('is_deleted', false);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $documents = $query->get();

        return response()->json($documents);
    }

    public function store(StoreCaseDocumentRequest $request, string $caseId)
    {
        $file = $request->file('file');

        $errors = app(StorageService::class)->validate($file, 'case_document');
        if (! empty($errors)) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['file' => [$errors[0]]]], 422);
            }

            return back()->withErrors(['file' => $errors[0]]);
        }

        $result = app(StorageService::class)->store($file, 'case-documents/'.$caseId);

        if (! $result->success) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['file' => [$result->error ?? 'Failed to store file.']]], 422);
            }

            return back()->withErrors(['file' => $result->error ?? 'Failed to store file.']);
        }

        $document = CaseDocument::create([
            'file_name' => $result->originalName,
            'file_path' => $result->path,
            'file_type' => $result->type,
            'category' => $request->input('category'),
            'size' => $result->size,
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

        $url = app(StorageService::class)->temporaryUrl($document->file_path, 24);

        if (! $url) {
            abort(404, 'File not found or unavailable.');
        }

        return redirect()->away($url);
    }

    public function destroy(Request $request, string $caseId, string $documentId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeWriteAccess($request->user());

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

    private function authorizeWriteAccess($user)
    {
        if (! $user->isCaseManager()) {
            abort(403, 'Only case managers can manage case documents.');
        }
    }
}
