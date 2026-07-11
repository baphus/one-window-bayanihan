<?php

namespace App\Console\Commands;

use App\Models\AuditArchive;
use App\Models\AuditChainCheckpoint;
use App\Models\AuditLog;
use App\Services\AuditArchiveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune
                            {--days= : Retention period in days (defaults to config audit.retention_days)}
                            {--dry-run : Preview counts without deleting}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Prune audit log entries older than the retention period, only where a finalized archive bundle exists';

    public function handle(AuditArchiveService $archiveService): int
    {
        $days = (int) ($this->option('days') ?: config('audit.retention_days'));
        $cutoff = now()->subDays($days);

        $expiredTotal = AuditLog::where('timestamp', '<', $cutoff)->count();

        if ($expiredTotal === 0) {
            $this->info("No audit log entries older than {$days} days found.");

            return Command::SUCCESS;
        }

        // Only rows covered by a finalized, checksum-valid bundle may be
        // deleted — and only as a contiguous oldest-first prefix. Deleting a
        // newer period while an older one remains would punch a hole in the
        // hash chain that no single checkpoint can anchor, making audit:verify
        // fail forever on untampered data.
        $prunablePeriods = [];
        $prunableCount = 0;

        $expiredPeriods = AuditLog::where('timestamp', '<', $cutoff)
            ->selectRaw("to_char(timestamp, 'YYYY-MM') as period")
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('period');

        $archivesByPeriod = AuditArchive::whereNotNull('finalized_at')->get()->keyBy('period');

        foreach ($expiredPeriods as $period) {
            $archive = $archivesByPeriod->get($period);

            if ($archive === null) {
                $this->warn("Period {$period} has no finalized archive — stopping here; newer periods are kept to preserve chain contiguity.");
                break;
            }

            if (! $archiveService->verifyBundle($archive)) {
                $this->error("Bundle checksum verification FAILED for {$period} ({$archive->path}) — stopping here; newer periods are kept to preserve chain contiguity.");
                logger()->error("Audit prune stopped at period {$period}: bundle missing or checksum mismatch");
                break;
            }

            $prunablePeriods[] = $archive;
            $prunableCount += $this->periodQuery($period, $cutoff)->count();
        }

        $skipped = $expiredTotal - $prunableCount;

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] Would delete {$prunableCount} archived entries older than {$cutoff->format('Y-m-d H:i:s')}; {$skipped} entries skipped pending archive.");

            return Command::SUCCESS;
        }

        if ($prunableCount === 0) {
            $this->info("No prunable entries: {$skipped} expired entries are pending archive (run audit:archive first).");

            return Command::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm("Delete {$prunableCount} archived audit log entries older than {$cutoff->format('Y-m-d H:i:s')}? ({$skipped} unarchived entries will be kept)")) {
                $this->info('Pruning cancelled.');

                return Command::SUCCESS;
            }
        }

        $deleted = 0;

        DB::transaction(function () use ($prunablePeriods, $cutoff, $archiveService, &$deleted) {
            // The chain-newest row being deleted anchors the chain for the
            // oldest surviving row; record it before the rows disappear.
            $anchorRow = null;
            foreach ($prunablePeriods as $archive) {
                $candidate = $this->periodQuery($archive->period, $cutoff)
                    ->orderBy('chain_seq', 'desc')
                    ->first();
                if ($candidate && ($anchorRow === null || $candidate->chain_seq > $anchorRow->chain_seq)) {
                    $anchorRow = $candidate;
                }
            }

            if ($anchorRow === null) {
                return;
            }

            $newestArchive = collect($prunablePeriods)->sortBy('period')->last();

            AuditChainCheckpoint::create([
                'anchor_hash' => $anchorRow->chainDigest(),
                'pruned_through' => $anchorRow->timestamp,
                'bundle_manifest_path' => $archiveService->manifestPathFor($newestArchive),
                'created_at' => now(),
            ]);

            DB::statement("SET LOCAL app.allow_audit_mutations = 'true'");

            foreach ($prunablePeriods as $archive) {
                $deleted += $this->periodQuery($archive->period, $cutoff)->forceDelete();
            }
        });

        $this->info("Pruned {$deleted} archived audit log entries; {$skipped} entries skipped pending archive.");

        return Command::SUCCESS;
    }

    private function periodQuery(string $period, $cutoff)
    {
        return AuditLog::where('timestamp', '<', $cutoff)
            ->whereRaw("to_char(timestamp, 'YYYY-MM') = ?", [$period]);
    }
}
