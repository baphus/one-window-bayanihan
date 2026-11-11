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

    public const REQUIREMENT_RECEIVED = 'received';

    public const REQUIREMENT_COMPLIED_ONSITE = 'complied_onsite';

    public const REQUIREMENT_FOR_COMPLIANCE = 'for_compliance';

    /** Employment evidence is a group: any one of these slots satisfies it. */
    public const EMPLOYMENT_EVIDENCE_SLOTS = ['employment_contract', 'oec', 'work_visa'];

    /** Keys a case may hold a requirement for: every upload slot plus the employment group. */
    public const REQUIREMENT_KEYS = [...self::SLOTS, 'employment_evidence'];

    public const REQUIREMENT_LABELS = [
        'passport_front_page' => 'Passport front page',
        'arrival_stamp' => 'BI arrival stamp',
        'employment_contract' => 'Employment contract abroad',
        'oec' => 'Overseas Employment Certificate (OEC)',
        'work_visa' => 'Work visa',
        'distress_certificate' => 'Distress certificate',
        'termination_letter' => 'Termination letter',
        'sworn_affidavit' => 'Notarized sworn affidavit stating the reason for return',
        'employment_evidence' => 'Employment evidence (contract, OEC, or work visa)',
    ];

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
        if (! array_intersect(self::EMPLOYMENT_EVIDENCE_SLOTS, $slots)) {
            $missing[] = 'employment_evidence';
        }

        return $missing;
    }

    /**
     * Requirement map recorded at filing: uploaded -> received,
     * ticked onsite -> complied_onsite, everything else owable by the OFW.
     *
     * @param  array<int, string>  $uploadedSlots
     * @param  array<int, string>  $compliedOnsiteSlots
     */
    public static function buildRequirements(array $uploadedSlots, array $compliedOnsiteSlots, string $reason): array
    {
        $received = in_array('employment_evidence', $uploadedSlots, true)
            || array_intersect(self::EMPLOYMENT_EVIDENCE_SLOTS, $uploadedSlots);
        $requirements = [];
        foreach ([...self::requiredSlots($reason), 'employment_evidence'] as $key) {
            $isReceived = $key === 'employment_evidence' ? $received : in_array($key, $uploadedSlots, true);
            $requirements[$key] = match (true) {
                $isReceived => self::REQUIREMENT_RECEIVED,
                in_array($key, $compliedOnsiteSlots, true) => self::REQUIREMENT_COMPLIED_ONSITE,
                default => self::REQUIREMENT_FOR_COMPLIANCE,
            };
        }

        return $requirements;
    }

    /**
     * Re-derive received state from the files actually attached. A slot that
     * was received but lost its file goes back to for compliance; onsite
     * compliance has no file and is left alone.
     */
    public function syncRequirements(CaseFile $case): void
    {
        $requirements = $case->document_requirements ?? [];
        if ($requirements === []) {
            return;
        }

        $slots = $case->documents()->whereNull('referral_id')->where('is_deleted', false)
            ->pluck('category')->all();

        foreach ($requirements as $key => $state) {
            $received = $key === 'employment_evidence'
                ? in_array('employment_evidence', $slots, true) || array_intersect(self::EMPLOYMENT_EVIDENCE_SLOTS, $slots)
                : in_array($key, $slots, true);
            if ($received) {
                $requirements[$key] = self::REQUIREMENT_RECEIVED;
            } elseif ($state === self::REQUIREMENT_RECEIVED) {
                $requirements[$key] = self::REQUIREMENT_FOR_COMPLIANCE;
            }
        }

        $case->update(['document_requirements' => $requirements]);
    }

    /** Keys the case still expects from the OFW. */
    public static function outstandingSlots(?array $requirements): array
    {
        return array_keys(array_filter($requirements ?? [], fn ($state) => $state === self::REQUIREMENT_FOR_COMPLIANCE));
    }

    /** Requirement list for the case workspace and the OFW portal. */
    public static function requirementList(CaseFile $case): array
    {
        return collect($case->document_requirements ?? [])
            ->map(fn ($state, $slot) => [
                'slot' => $slot,
                'state' => $state,
                'label' => self::REQUIREMENT_LABELS[$slot] ?? $slot,
                'note' => $case->missing_document_reasons[$slot] ?? null,
            ])
            ->values()
            ->all();
    }

    public function store(CaseFile $case, array $files, string $actorId, array &$storedPaths): void
    {
        $storage = app(StorageService::class);
        foreach ($files as $slot => $file) {
            if (! in_array($slot, self::REQUIREMENT_KEYS, true)) {
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
