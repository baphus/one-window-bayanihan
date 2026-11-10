<?php

namespace App\Http\Controllers;

use App\Enums\AuditModule;
use App\Http\Requests\ReplaceIntakeDocumentsRequest;
use App\Http\Requests\StoreCaseDocumentRequest;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Services\AuditLogger;
use App\Services\IntakeDocumentService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CaseDocumentController extends Controller
{
    public function requestReplacement(Request $request, CaseFile $case)
    {
        $data = $request->validate([
            'slots' => ['required', 'array', 'min:1'],
            'slots.*' => ['required', 'distinct', Rule::in(IntakeDocumentService::SLOTS)],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($case, $request, $data) {
            $locked = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)->lockForUpdate()->findOrFail($case->id);
            abort_unless($request->user()->isAdmin() || ($request->user()->isCaseManager() && $locked->assigned_case_manager_id === $request->user()->id), 403);
            $locked->update([
                'requested_document_slots' => array_values(array_unique(array_merge($locked->requested_document_slots ?? [], $data['slots']))),
                'document_request_reason' => $data['reason'], 'document_requested_by' => $request->user()->id,
                'document_requested_at' => now(), 'reviewed_document_ids' => null,
            ]);
        });

        return back()->with('success', 'Replacement documents requested.');
    }

    public function replace(ReplaceIntakeDocumentsRequest $request, CaseFile $case)
    {
        $paths = [];
        try {
            DB::transaction(function () use ($case, $request, &$paths) {
                $locked = CaseFile::where('status', CaseFile::STATUS_PENDING_REVIEW)->lockForUpdate()->findOrFail($case->id);
                abort_unless($locked->ofw_user_id === $request->user()->id, 403);
                $files = $request->file('documents');
                foreach (array_keys($files) as $slot) {
                    if (! in_array($slot, $locked->requested_document_slots ?? [], true)) {
                        throw ValidationException::withMessages(['documents.'.$slot => 'This document was not requested.']);
                    }
                }
                $old = $locked->documents()->whereNull('referral_id')->whereIn('category', array_keys($files))->get();
                app(IntakeDocumentService::class)->store($locked, $files, $request->user()->id, $paths);
                foreach ($old as $document) {
                    $document->delete();
                }
                $remaining = array_values(array_diff($locked->requested_document_slots ?? [], array_keys($files)));
                $locked->update([
                    'requested_document_slots' => $remaining === [] ? null : $remaining,
                    'document_request_reason' => $remaining === [] ? null : $locked->document_request_reason,
                    'document_requested_by' => $remaining === [] ? null : $locked->document_requested_by,
                    'document_requested_at' => $remaining === [] ? null : $locked->document_requested_at,
                    'reviewed_document_ids' => null,
                ]);
            });
        } catch (\Throwable $exception) {
            app(IntakeDocumentService::class)->cleanup($paths);
            throw $exception;
        }

        return back()->with('success', 'Replacement documents submitted for review.');
    }

    public function index(Request $request, string $caseId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeAccess($case, $request->user());

        // Orphaned pointers: hide docs whose referral was soft-deleted so
        // managers never list files agencies can no longer resolve.
        $query = $case->documents()
            ->where('is_deleted', false)
            ->whereDoesntHave('referral', fn ($referrals) => $referrals->onlyTrashed());

        if ($request->user()->isAgency()) {
            $query->visibleToAgency($request->user()->agcy_id);
        } elseif ($request->user()->isOfw()) {
            $query->whereNull('referral_id');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('referral_id')) {
            $query->where('referral_id', $request->input('referral_id'));
        }

        $documents = $query->get();

        return response()->json($documents);
    }

    public function store(StoreCaseDocumentRequest $request, string $caseId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeWriteAccess($request->user());
        $this->authorizeAccess($case, $request->user());

        $file = $request->file('file');
        $isInertia = $request->header('X-Inertia');

        $errors = app(StorageService::class)->validate($file, 'case_document');
        if (! empty($errors)) {
            if ($isInertia) {
                return back()->withErrors(['file' => $errors[0]]);
            }

            if ($request->wantsJson()) {
                return response()->json(['errors' => ['file' => [$errors[0]]]], 422);
            }

            return back()->withErrors(['file' => $errors[0]]);
        }

        // If referral_id is provided, verify it belongs to this case
        if ($request->filled('referral_id')) {
            $referral = Referral::where('id', $request->input('referral_id'))
                ->where('case_id', $caseId)
                ->first();

            if (! $referral) {
                return back()->withErrors(['referral_id' => 'Invalid referral for this case.']);
            }
        }

        $result = app(StorageService::class)->store($file, 'case-documents/'.$caseId);

        if (! $result->success) {
            if ($isInertia) {
                return back()->withErrors(['file' => $result->error ?? 'Failed to store file.']);
            }

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
            'referral_id' => $request->input('referral_id'),
            'user_id' => $request->user()->id,
        ]);

        if ($isInertia) {
            return back()->with('success', 'Document uploaded successfully.');
        }

        if ($request->wantsJson()) {
            return response()->json($document, 201);
        }

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function show(Request $request, string $caseId, string $documentId)
    {
        $case = CaseFile::findOrFail($caseId);
        $this->authorizeAccess($case, $request->user());

        $document = CaseDocument::where('case_id', $caseId)
            ->where('id', $documentId)
            ->where('is_deleted', false)
            ->firstOrFail();

        $this->authorizeDocumentAccess($document, $request->user());

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

        $this->authorizeDocumentAccess($document, $request->user());

        $url = app(StorageService::class)->temporaryUrl($document->file_path, 24);

        if (! $url) {
            abort(404, 'File not found or unavailable.');
        }

        AuditLogger::download()
            ->by($request->user())
            ->forEntity($document, $document->file_name)
            ->module(AuditModule::CASE_DOCUMENT)
            ->write();

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

        $this->authorizeDocumentAccess($document, $request->user());

        $document->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'deleted_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Document deleted successfully.']);
    }

    private function authorizeAccess(CaseFile $case, $user)
    {
        // ADMIN and CASE_MANAGER: full access to all cases
        if ($user->isAdmin() || $user->isCaseManager()) {
            return;
        }

        if ($user->isOfw()) {
            abort_unless($case->ofw_user_id === $user->id, 403);

            return;
        }

        // A live referral, not merely any referral: access ends with it.
        $hasActiveReferral = $case->referrals()
            ->where('agcy_id', $user->agcy_id)
            ->where('is_deleted', false)
            ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
            ->exists();

        if (! $hasActiveReferral) {
            abort(403, 'You do not have access to documents for this case.');
        }
    }

    private function authorizeWriteAccess($user)
    {
        if (! $user->isCaseManager() && ! $user->isAdmin()) {
            abort(403, 'Only case managers and administrators can manage case documents.');
        }
    }

    private function authorizeDocumentAccess(CaseDocument $document, $user)
    {
        // ADMIN and CASE_MANAGER: full access to all documents
        if ($user->isAdmin() || $user->isCaseManager()) {
            return;
        }

        if ($user->isOfw() && $document->referral_id === null && $document->caseFile->ofw_user_id === $user->id) {
            return;
        }

        // Agency users: general case files plus files on a referral to their
        // own agency. Never another agency's referral documents; a null
        // agcy_id must never match, even against a null referral agcy_id.
        if ($user->isAgency() && ($document->referral_id === null
            || ($user->agcy_id && $document->referral && $document->referral->agcy_id === $user->agcy_id))) {
            return;
        }

        abort(403, 'You do not have access to this document.');
    }
}
