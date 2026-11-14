<?php

namespace App\Http\Controllers;

use App\Enums\AuditModule;
use App\Http\Requests\ReplaceIntakeDocumentsRequest;
use App\Http\Requests\StoreCaseDocumentRequest;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\CaseUpdated;
use App\Services\AuditLogger;
use App\Services\IntakeDocumentService;
use App\Services\NotificationService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CaseDocumentController extends Controller
{
    public function replace(ReplaceIntakeDocumentsRequest $request, CaseFile $case)
    {
        $paths = [];
        try {
            DB::transaction(function () use ($case, $request, &$paths) {
                $locked = CaseFile::lockForUpdate()->findOrFail($case->id);
                abort_unless($locked->ofw_user_id === $request->user()->id, 403);
                $outstanding = IntakeDocumentService::outstandingSlots($locked->document_requirements);
                $files = $request->file('documents');
                foreach (array_keys($files) as $slot) {
                    if (! in_array($slot, $outstanding, true)) {
                        throw ValidationException::withMessages(['documents.'.$slot => 'This document was not requested.']);
                    }
                }
                $old = $locked->documents()->whereNull('referral_id')->whereIn('category', array_keys($files))->get();
                app(IntakeDocumentService::class)->store($locked, $files, $request->user()->id, $paths);
                foreach ($old as $document) {
                    $document->delete();
                }
                app(IntakeDocumentService::class)->syncRequirements($locked);
                $remaining = array_values(array_diff($locked->requested_document_slots ?? [], array_keys($files)));
                $locked->update([
                    'requested_document_slots' => $remaining === [] ? null : $remaining,
                    'document_request_reason' => $remaining === [] ? null : $locked->document_request_reason,
                    'document_requested_by' => $remaining === [] ? null : $locked->document_requested_by,
                    'document_requested_at' => $remaining === [] ? null : $locked->document_requested_at,
                    'reviewed_document_ids' => null,
                ]);
            });
            $this->notifyManagerOfOfwUpload($case, $request->user());
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

        app(IntakeDocumentService::class)->syncRequirements($case);

        if ($isInertia) {
            return back()->with('success', 'Document uploaded successfully.');
        }

        if ($request->wantsJson()) {
            return response()->json($document, 201);
        }

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function downloadIntakeArchive(Request $request, string $caseId)
    {
        abort_unless($request->user()->isCaseManager(), 403);

        $case = CaseFile::query()
            ->whereKey($caseId)
            ->where('is_deleted', false)
            ->firstOrFail();
        $documents = $case->documents()
            ->whereNull('referral_id')
            ->where('is_deleted', false)
            ->whereDoesntHave('referral', fn ($referrals) => $referrals->onlyTrashed())
            ->orderBy('category')
            ->get();
        abort_if($documents->isEmpty(), 404, 'No case documents are available for this case.');

        $temporaryFile = tempnam(sys_get_temp_dir(), 'case-intake-zip-');
        abort_if($temporaryFile === false, 500, 'Unable to prepare document archive.');

        try {
            $zip = new \ZipArchive;
            abort_unless($zip->open($temporaryFile, \ZipArchive::OVERWRITE) === true, 500, 'Unable to create document archive.');
            $disk = Storage::disk(app(StorageService::class)->diskName());
            $names = [];

            foreach ($documents as $document) {
                $storagePath = ltrim($document->file_path, '/');
                abort_unless($disk->exists($storagePath), 404, 'A case document is missing from storage.');
                $contents = $disk->get($storagePath);

                $name = $this->intakeDownloadName($case, $document);
                $base = pathinfo($name, PATHINFO_FILENAME);
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $candidate = $name;
                for ($suffix = 2; isset($names[$candidate]); $suffix++) {
                    $candidate = $base.' ('.$suffix.')'.($extension === '' ? '' : '.'.$extension);
                }
                $names[$candidate] = true;
                abort_unless($zip->addFromString($candidate, $contents), 500, 'Unable to add document to archive.');
            }

            abort_unless($zip->close(), 500, 'Unable to finalize document archive.');
            $caseLabel = $this->caseDownloadLabel($case);
            AuditLogger::download()
                ->by($request->user())
                ->forEntity($case, $case->case_number)
                ->module(AuditModule::CASE_DOCUMENT)
                ->context(null, ['format' => 'zip', 'scope' => 'case_documents', 'count' => $documents->count()])
                ->write();

            return response()->download($temporaryFile, $caseLabel.'_case-documents.zip', [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if (isset($zip) && $zip instanceof \ZipArchive) {
                $zip->close();
            }
            @unlink($temporaryFile);
            throw $exception;
        }
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

        AuditLogger::download()
            ->by($request->user())
            ->forEntity($document, $document->file_name)
            ->module(AuditModule::CASE_DOCUMENT)
            ->write();

        if ($document->referral_id === null && in_array($document->category, IntakeDocumentService::REQUIREMENT_KEYS, true)) {
            $disk = Storage::disk(app(StorageService::class)->diskName());
            $path = ltrim($document->file_path, '/');
            abort_unless($disk->exists($path), 404, 'File not found or unavailable.');

            return $disk->download($path, $this->intakeDownloadName($case, $document));
        }

        $url = app(StorageService::class)->temporaryUrl($document->file_path, 24);
        abort_unless($url, 404, 'File not found or unavailable.');

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

        app(IntakeDocumentService::class)->syncRequirements($case);

        return response()->json(['message' => 'Document deleted successfully.']);
    }

    private function intakeDownloadName(CaseFile $case, CaseDocument $document): string
    {
        $client = trim(implode(' ', array_filter([
            $case->first_name, $case->middle_name, $case->last_name, $case->suffix,
        ])));
        $requirement = IntakeDocumentService::REQUIREMENT_LABELS[$document->category] ?? $document->category;
        $extension = pathinfo($document->file_name, PATHINFO_EXTENSION);

        return implode('_', array_map(fn ($part) => $this->safeFilenamePart($part), [
            $case->case_number ?: $case->tracker_number ?: 'case',
            $client ?: 'client',
            $requirement ?: 'document',
        ])).($extension === '' ? '' : '.'.$extension);
    }

    private function caseDownloadLabel(CaseFile $case): string
    {
        $client = trim(implode(' ', array_filter([
            $case->first_name, $case->middle_name, $case->last_name, $case->suffix,
        ])));

        return implode('_', array_map(fn ($part) => $this->safeFilenamePart($part), [
            $case->case_number ?: $case->tracker_number ?: 'case', $client ?: 'client',
        ]));
    }

    private function safeFilenamePart(string $part): string
    {
        $part = preg_replace('/[\x00-\x1F\x7F]+/u', '-', str_replace(['\\', '/'], '-', $part)) ?? '';
        $part = trim(preg_replace('/\.{2,}/', '.', $part) ?? '', ' .-_');

        return $part === '' ? 'unknown' : $part;
    }

    private function notifyManagerOfOfwUpload(CaseFile $case, User $ofw): void
    {
        $manager = $case->assigned_case_manager_id ? User::find($case->assigned_case_manager_id) : null;
        if (! $manager) {
            return;
        }

        app(NotificationService::class)->notifyUsers([$manager], new CaseUpdated(
            $case,
            $ofw->name,
            ['documents' => 'The OFW uploaded a document for this case.'],
        ));
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

        // Agency users: only files on a referral to their own agency. Never
        // another agency's referral documents, and never case-level files; a
        // null agcy_id must never match, even against a null referral agcy_id.
        if ($user->isAgency() && $document->referral_id !== null
            && $user->agcy_id && $document->referral && $document->referral->agcy_id === $user->agcy_id) {
            return;
        }

        abort(403, 'You do not have access to this document.');
    }
}
