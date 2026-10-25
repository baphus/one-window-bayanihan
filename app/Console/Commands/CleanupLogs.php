<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupLogs extends Command
{
    protected $signature = 'logs:cleanup';

    protected $description = 'Clean up old log files';

    /**
     * Delete wedged daily logs older than 7 days. Each file is guarded
     * individually: a file that vanished between glob() and stat, or one a
     * process keeps locked (Windows holds log handles open), is logged at
     * notice and left for the next scheduled run instead of aborting the
     * whole pass or raising a PHP warning. Skipped files are summarised in
     * the command's own output (respecting --quiet) so one operator-visible
     * line reports every problem file per run.
     *
     * The glob covers every laravel*.log name because the framework writes
     * more than the daily shape: LOG_STACK=single and the test channel write
     * a bare laravel.log, which the older laravel-*.log pattern never
     * matched — so the single-file deployments this cleanup exists for kept
     * growing forever.
     */
    public function handle(): int
    {
        $logFiles = glob(storage_path('logs').DIRECTORY_SEPARATOR.'laravel*.log') ?: [];
        $cutoff = now()->subDays(7)->timestamp;
        $logDeleted = 0;
        $skipped = [];

        foreach ($logFiles as $file) {
            try {
                if (! is_file($file)) {
                    $skipped[] = basename($file);

                    continue;
                }

                $modifiedAt = filemtime($file);

                if ($modifiedAt === false) {
                    $skipped[] = basename($file);
                    $this->logSkip(basename($file), 'filemtime returned false');

                    continue;
                }

                if ($modifiedAt >= $cutoff) {
                    continue;
                }

                if (unlink($file)) {
                    $logDeleted++;
                } else {
                    $skipped[] = basename($file);
                    $this->logSkip(basename($file), 'unlink returned false');
                }
            } catch (\ErrorException $e) {
                $skipped[] = basename($file);
                $this->logSkip(basename($file), $e->getMessage());
            }
        }

        $this->info("Cleanup: deleted {$logDeleted} log files");

        if ($skipped !== []) {
            $this->warn(sprintf(
                'Cleanup: skipped %d file(s) that could not be removed: %s',
                count($skipped),
                implode(', ', $skipped)
            ));
        }

        return 0;
    }

    /**
     * Best-effort audit of a skipped file. The default logging channel writes
     * to storage/logs/laravel.log through a lazily-opened StreamHandler — when
     * another local process holds that file open without sharing (common on
     * Windows), the write throws Monolog's UnexpectedValueException. A
     * visibility notice must never abort the cleanup pass, so a failed write
     * is swallowed and the operator-visible skip summary below stands alone.
     */
    private function logSkip(string $basename, string $reason): void
    {
        try {
            Log::notice('Could not delete expired log file; skipping.', [
                'file' => $basename,
                'reason' => $reason,
            ]);
        } catch (\Throwable) {
            // Log destination unavailable — the command output reports the skip.
        }
    }
}
