<?php

namespace App\Console\Commands;

use App\Models\CaseDocument;
use App\Models\ReferralAttachment;
use App\Services\StorageService;
use Illuminate\Console\Command;

class CleanupOrphanedFiles extends Command
{
    protected $signature = 'storage:cleanup-orphans {--dry-run : List files that would be deleted without actually deleting}';

    protected $description = 'Delete S3 files for records soft-deleted more than 1 day ago';

    public function handle(StorageService $storageService): int
    {
        $count = 0;
        $cutoff = now()->subDay();

        // Query 1: CaseDocument soft-deleted more than 1 day ago
        CaseDocument::withTrashed()
            ->where('is_deleted', true)
            ->whereNotNull('file_path')
            ->where('deleted_at', '<', $cutoff)
            ->cursor()
            ->each(function (CaseDocument $doc) use ($storageService, &$count) {
                if ($this->option('dry-run')) {
                    $this->warn("Would delete: {$doc->file_path}");

                    return;
                }

                try {
                    $storageService->delete($doc->file_path);
                    $this->info("Deleted: {$doc->file_path}");
                    $count++;
                } catch (\Throwable $e) {
                    $this->error("Failed to delete {$doc->file_path}: {$e->getMessage()}");
                }
            });

        // Query 2: ReferralAttachment soft-deleted, not archived, more than 1 day ago
        ReferralAttachment::withTrashed()
            ->where('is_deleted', true)
            ->where('is_archived', false)
            ->whereNotNull('file_path')
            ->where('deleted_at', '<', $cutoff)
            ->cursor()
            ->each(function (ReferralAttachment $att) use ($storageService, &$count) {
                if ($this->option('dry-run')) {
                    $this->warn("Would delete: {$att->file_path}");

                    return;
                }

                try {
                    $storageService->delete($att->file_path);
                    $this->info("Deleted: {$att->file_path}");
                    $count++;
                } catch (\Throwable $e) {
                    $this->error("Failed to delete {$att->file_path}: {$e->getMessage()}");
                }
            });

        if (! $this->option('dry-run')) {
            $this->info("Cleaned up {$count} orphaned files.");
        }

        return Command::SUCCESS;
    }
}
