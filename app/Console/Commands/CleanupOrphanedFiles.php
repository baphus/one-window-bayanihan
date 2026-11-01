<?php

namespace App\Console\Commands;

use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\GeneratedDocument;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientMessageAttachment;
use App\Models\ReferralClientRequest;
use App\Services\StorageService;
use App\Settings\SettingRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CleanupOrphanedFiles extends Command
{
    protected $signature = 'storage:cleanup-orphans {--dry-run : List files that would be deleted without actually deleting}';

    protected $description = 'Delete stored files whose row is soft-deleted and whose case is out of trash retention';

    /**
     * The parent case's deleted_at, memoised for the length of one run.
     *
     * `null` means "no case row left to protect" — either the case is live or
     * cases:purge-trashed has already force-deleted it. Keyed by case id, so a
     * case with fifty orphaned documents costs one lookup, not fifty.
     *
     * @var array<string, Carbon|null>
     */
    private array $caseDeletedAtCache = [];

    /**
     * Referral id to case id, memoised for the length of one run.
     *
     * @var array<string, string|null>
     */
    private array $referralCaseCache = [];

    /**
     * Message id to referral id, memoised for the length of one run.
     *
     * @var array<string, string|null>
     */
    private array $messageReferralCache = [];

    /**
     * Number of rows the retention rule deliberately kept this run.
     */
    private int $retained = 0;

    /**
     * Whether the table currently being swept carries files_deleted_at.
     * Resolved once per run; null means "not looked up yet".
     */
    private ?bool $hasStampColumn = null;

    /**
     * Delete stored files for rows that are soft-deleted, in the trash longer
     * than the restore window, and whose parent case can no longer be restored.
     *
     * Retention is keyed off the parent CASE, not the file's own row: a case
     * stays restorable for the whole cases:purge-trashed window, so a document
     * restored on day two used to come back pointing at a bucket key the sweep
     * had already emptied — the row looked fine and the download 404'd. The
     * cutoff is read from the same config key the purge reads, so there is one
     * policy rather than two that can drift apart. The row's own deleted_at must
     * also be past that window: a document trashed yesterday is still restorable
     * even though its case is live.
     *
     * Every delete is stamped with files_deleted_at so the next nightly run
     * skips it instead of retrying the same key forever.
     */
    public function handle(StorageService $storageService): int
    {
        // Artisan reuses one command instance per console application, so the
        // per-run state starts clean even when a test or a worker calls this
        // twice in the same process.
        $this->caseDeletedAtCache = [];
        $this->referralCaseCache = [];
        $this->messageReferralCache = [];
        $this->retained = 0;
        $this->hasStampColumn = null;

        // Same window the purge uses, resolved from the setting rather than
        // config('app.trash_retention_days'). Leaving this on config meant an
        // admin who lowered trash retention changed when CASES were deleted
        // but not when their attachments' bytes were — two windows that were
        // supposed to be one.
        $retentionDays = SettingRegistry::int('trash_retention_days');
        $retentionCutoff = now()->subDays($retentionDays);
        // A trashed document row stays restorable for the same window a trashed
        // case does, so its object may only go once that window has passed.
        $graceCutoff = $retentionCutoff;

        $count = 0;

        // Query 1: CaseDocument soft-deleted past the grace period, parent case
        // not inside the restorable window.
        CaseDocument::withTrashed()
            ->where('is_deleted', true)
            ->whereNull('files_deleted_at')
            ->whereNotNull('file_path')
            ->where('deleted_at', '<', $graceCutoff)
            ->cursor()
            ->each(function (CaseDocument $doc) use ($storageService, $retentionCutoff, &$count) {
                if (! $this->caseAllowsDeletion($doc->case_id, $retentionCutoff)) {
                    return;
                }

                if ($this->sweep($doc, (string) $doc->file_path, $storageService)) {
                    $count++;
                }
            });

        // Query 2: ReferralAttachment soft-deleted, not archived, past the grace
        // period. Chain: attachment -> referral -> case.
        ReferralAttachment::withTrashed()
            ->where('is_deleted', true)
            ->where('is_archived', false)
            ->whereNull('files_deleted_at')
            ->whereNotNull('file_path')
            ->where('deleted_at', '<', $graceCutoff)
            ->cursor()
            ->each(function (ReferralAttachment $att) use ($storageService, $retentionCutoff, &$count) {
                if (! $this->caseAllowsDeletion($this->referralCaseId($att->referral_id), $retentionCutoff)) {
                    return;
                }

                if ($this->sweep($att, (string) $att->file_path, $storageService)) {
                    $count++;
                }
            });

        // Query 3: client-request message attachments — the uploads a citizen
        // sends through their public tracking link, stored under
        // client-request-attachments/. Chain: attachment -> message -> request ->
        // referral -> case. Nothing else in the app ever marks these rows
        // trashed or empties their bucket objects, so without this sweep a
        // passport scan uploaded to an agency chat lives forever.
        ReferralClientMessageAttachment::withTrashed()
            ->where('is_deleted', true)
            ->whereNull('files_deleted_at')
            ->whereNotNull('file_path')
            ->where('deleted_at', '<', $graceCutoff)
            ->cursor()
            ->each(function (ReferralClientMessageAttachment $att) use ($storageService, $retentionCutoff, &$count) {
                if (! $this->caseAllowsDeletion($this->caseIdForMessage($att->message_id), $retentionCutoff)) {
                    return;
                }

                if ($this->sweep($att, (string) $att->file_path, $storageService)) {
                    $count++;
                }
            });

        // Query 4: generated exports and reports under generated-documents/.
        // Different medium, different lifecycle: these rows have no soft
        // delete and no case holding them in a restore window, so the policy is
        // age alone. They live in this command rather than a separate one
        // because storage:cleanup-orphans is already the scheduled job for
        // "objects this application owns but no longer serves", and a second
        // command would be a second schedule entry to remember.
        // Previously config('reports.retention_days'), a key that did not exist
        // in config/reports.php — the call silently fell back to the case
        // trash-retention window, so generated reports were deleted on the
        // wrong schedule. Resolved per run from the setting.
        $documentCutoff = now()->subDays(SettingRegistry::int('report_retention_days'));

        GeneratedDocument::query()
            ->where('created_at', '<', $documentCutoff)
            ->where(function ($query) {
                $query->whereNotNull('path')->orWhere('status', 'failed');
            })
            ->cursor()
            ->each(function (GeneratedDocument $doc) use ($storageService, &$count) {
                if (! $this->sweep($doc, $doc->storagePath(), $storageService)) {
                    return;
                }

                // No files_deleted_at column on this table, and none needed:
                // clearing the path is what makes the row un-sweepable, and
                // `expired` is what tells a reader the bytes are gone rather
                // than that the export never ran.
                $doc->updateQuietly([
                    'status' => GeneratedDocument::STATUS_EXPIRED,
                    'path' => null,
                ]);

                $count++;
            });

        if (! $this->option('dry-run')) {
            $message = "Cleaned up {$count} orphaned files.";
            if ($this->retained > 0) {
                $message .= " Kept {$this->retained} file(s) inside the {$retentionDays}-day restore window.";
            }

            $this->info($message);
        }

        return Command::SUCCESS;
    }

    /**
     * Delete one stored file and stamp the row so later sweeps skip it.
     *
     * The path is passed in rather than read from `file_path` because the fourth
     * pass covers a different medium (generated exports) whose column is
     * `path`.
     *
     * The stamp is written only after the object is actually gone: a failed
     * delete leaves the row unmarked on purpose, so the next run retries that
     * row instead of writing off a key that is still in the bucket.
     */
    private function sweep(Model $row, string $path, StorageService $storageService): bool
    {
        if ($this->option('dry-run')) {
            $this->warn("Would delete: {$path}");

            return false;
        }

        try {
            $deleted = $storageService->delete($path);
        } catch (Throwable $e) {
            $deleted = false;
            $failure = $e;
        }

        if (! $deleted) {
            $this->error("Failed to delete {$path}");
            logger()->warning('CleanupOrphanedFiles: failed to delete orphaned file', array_filter([
                'path' => $path,
                'exception' => $failure ?? null,
            ]));

            return false;
        }

        $this->stamp($row, $path);
        $this->info("Deleted: {$path}");

        return true;
    }

    /**
     * Record that the object behind this row is gone.
     *
     * A stamp can legitimately fail: the row outlived a parent that is no
     * longer there, and the RESTRICT foreign keys that keep such a row alive
     * (case_documents.case_id) refuse an update on it too. The bytes are
     * already deleted at this point, so a refusal costs the next run one
     * redundant delete attempt and nothing else — not worth failing the sweep.
     *
     * The write is wrapped so a refusal rolls back to its own savepoint when the
     * caller already has a transaction open. Without that, one unstampable row
     * aborts the surrounding transaction and every row after it is skipped.
     */
    private function stamp(Model $row, string $path): void
    {
        // Only three of the four tables carry the column; generated_documents
        // marks itself by clearing `path` instead. Resolved once per run.
        if ($this->hasStampColumn === null) {
            $this->hasStampColumn = Schema::hasColumn($row->getTable(), 'files_deleted_at');
        }

        if (! $this->hasStampColumn) {
            return;
        }

        try {
            DB::transaction(function () use ($row) {
                $row->files_deleted_at = now();
                $row->saveQuietly();
            });
        } catch (Throwable $e) {
            $this->warn('Could not stamp '.class_basename($row)." {$row->getKey()}: {$e->getMessage()}");
            logger()->warning('CleanupOrphanedFiles: could not stamp swept row', [
                'model' => $row::class,
                'id' => $row->getKey(),
                'path' => $path,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Whether a file behind a row on this case may be emptied now.
     *
     * A live case (or a case row the purge already removed) leaves nothing to
     * restore to; a trashed case still inside the retention window is exactly
     * the case restoreTrashedCase() can bring back, so its bytes stay.
     */
    private function caseAllowsDeletion(?string $caseId, Carbon $retentionCutoff): bool
    {
        $caseDeletedAt = $this->caseDeletedAt($caseId);

        if ($caseDeletedAt === null) {
            return true;
        }

        if ($caseDeletedAt->greaterThanOrEqualTo($retentionCutoff)) {
            $this->retained++;

            return false;
        }

        // Trashed and past the retention window: cases:purge-trashed owns these
        // now and takes the row on its next run, so the bytes are on their way
        // out regardless of what this sweep does. Deleting them here closes the
        // window in which a purge blocked by a RESTRICT foreign key leaves
        // client-uploaded documents (passport and residence scans) sitting in
        // the bucket forever.
        return true;
    }

    /**
     * The parent case's deletion stamp, or null when there is no case row left.
     */
    private function caseDeletedAt(?string $caseId): ?Carbon
    {
        if ($caseId === null) {
            return null;
        }

        if (! array_key_exists($caseId, $this->caseDeletedAtCache)) {
            $deletedAt = CaseFile::withTrashed()->whereKey($caseId)->value('deleted_at');

            $this->caseDeletedAtCache[$caseId] = $deletedAt === null ? null : Carbon::parse($deletedAt);
        }

        return $this->caseDeletedAtCache[$caseId];
    }

    /**
     * Case id behind a referral, or null when the referral row is gone.
     */
    private function referralCaseId(?string $referralId): ?string
    {
        if ($referralId === null) {
            return null;
        }

        if (! array_key_exists($referralId, $this->referralCaseCache)) {
            $this->referralCaseCache[$referralId] = Referral::withTrashed()
                ->whereKey($referralId)
                ->value('case_id');
        }

        return $this->referralCaseCache[$referralId];
    }

    /**
     * Case id behind a client-request message, or null when the chain is gone.
     *
     * The attachment -> message foreign key cascades, so a message row that is
     * missing has already taken its attachments with it. Returning null here is
     * the unreachable-in-practice arm, and it correctly reads as "nothing left
     * to protect" rather than as a case still sitting in the trash.
     */
    private function caseIdForMessage(?string $messageId): ?string
    {
        if ($messageId === null) {
            return null;
        }

        if (! array_key_exists($messageId, $this->messageReferralCache)) {
            $requestId = ReferralClientMessage::withTrashed()
                ->whereKey($messageId)
                ->value('request_id');

            $referralId = $requestId === null
                ? null
                : ReferralClientRequest::withTrashed()->whereKey($requestId)->value('referral_id');

            $this->messageReferralCache[$messageId] = $referralId;
        }

        return $this->referralCaseId($this->messageReferralCache[$messageId]);
    }
}
