<?php

namespace App\Services;

use App\Models\CaseDocument;
use App\Models\CaseFile;
use Illuminate\Validation\ValidationException;

class IntakeDocumentService
{
    public const CONSENT_VERSION = 'case-intake-v1';

    public const SLOTS = ['passport_front_page', 'arrival_stamp', 'employment_contract', 'oec', 'work_visa', 'distress_certificate', 'termination_letter', 'sworn_affidavit'];

    public const RETURN_REASONS = ['DISTRESSED_WORKER', 'TERMINATION', 'OTHERS'];

    public static function requiredSlots(string $reason): array
    {
        return ['passport_front_page', 'arrival_stamp', match ($reason) {
            'DISTRESSED_WORKER' => 'distress_certificate',
            'TERMINATION' => 'termination_letter',
            default => 'sworn_affidavit',
        }];
    }

    public static function missingSlots(array $slots, string $reason): array
    {
        $missing = array_values(array_diff(self::requiredSlots($reason), $slots));
        if (! array_intersect(['employment_contract', 'oec', 'work_visa'], $slots)) {
            $missing[] = 'employment_evidence';
        }

        return $missing;
    }

    public function store(CaseFile $case, array $files, string $actorId, array &$storedPaths): void
    {
        $storage = app(StorageService::class);
        foreach ($files as $slot => $file) {
            if (! in_array($slot, self::SLOTS, true)) {
                throw ValidationException::withMessages(['documents.'.$slot => 'Invalid document slot.']);
            }
            $errors = $storage->validate($file, 'case_document');
            if ($errors) {
                throw ValidationException::withMessages(['documents.'.$slot => $errors[0]]);
            }
            $result = $storage->store($file, 'case-documents/'.$case->id);
            if (! $result->success) {
                throw ValidationException::withMessages(['documents.'.$slot => $result->error ?? 'Failed to store document.']);
            }
            $storedPaths[] = $result->path;
            CaseDocument::create([
                'case_id' => $case->id, 'user_id' => $actorId, 'category' => $slot,
                'file_name' => $result->originalName, 'file_path' => $result->path,
                'file_type' => $result->type, 'size' => $result->size,
            ]);
        }
    }

    public function assertCompliant(CaseFile $case, array $reviewedIds): void
    {
        $documents = $case->documents()->whereNull('referral_id')->where('is_deleted', false)
            ->whereIn('id', $reviewedIds)->get();
        if ($documents->count() !== count(array_unique($reviewedIds))) {
            throw ValidationException::withMessages(['reviewed_document_ids' => 'Only current documents belonging to this case may be reviewed.']);
        }
        $slots = $documents->pluck('category')->all();
        if (self::missingSlots($slots, $case->return_reason) || $case->requested_document_slots) {
            throw ValidationException::withMessages(['reviewed_document_ids' => 'Review all required documents and resolve replacement requests before approval.']);
        }
    }

    public function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                app(StorageService::class)->delete($path);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
