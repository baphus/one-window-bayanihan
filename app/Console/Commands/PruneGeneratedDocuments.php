<?php

namespace App\Console\Commands;

use App\Models\GeneratedDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneGeneratedDocuments extends Command
{
    protected $signature = 'documents:prune
                            {--completed-days=30 : Delete completed documents older than this many days}
                            {--failed-days=7 : Delete failed documents older than this many days}';

    protected $description = 'Prune old generated documents from S3 and database.';

    public function handle(): int
    {
        $completedDays = (int) $this->option('completed-days');
        $failedDays = (int) $this->option('failed-days');

        $completedCount = $this->pruneCompleted($completedDays);
        $failedCount = $this->pruneFailed($failedDays);

        $total = $completedCount + $failedCount;

        if ($total === 0) {
            $this->info('No documents to prune.');

            return Command::SUCCESS;
        }

        $message = "Pruned {$completedCount} completed (>{$completedDays}d) and {$failedCount} failed (>{$failedDays}d) documents.";
        $this->info($message);
        Log::info("documents:prune — {$message}");

        return Command::SUCCESS;
    }

    private function pruneCompleted(int $days): int
    {
        $cutoff = now()->subDays($days);
        $documents = GeneratedDocument::where('status', 'completed')
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($documents as $document) {
            // Delete S3 file if it exists
            if ($document->path) {
                try {
                    Storage::disk('supabase')->delete($document->path);
                } catch (\Throwable $e) {
                    Log::warning("documents:prune — Failed to delete S3 file: {$document->path}", [
                        'error' => $e->getMessage(),
                    ]);
                    // Continue — don't let S3 failures block DB cleanup
                }
            }

            $document->delete();
            $count++;
        }

        return $count;
    }

    private function pruneFailed(int $days): int
    {
        $cutoff = now()->subDays($days);

        return GeneratedDocument::where('status', 'failed')
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
