<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditLogFormatter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillAuditDescriptions extends Command
{
    protected $signature = 'audit:backfill-descriptions {--dry-run : Preview without updating} {--chunk-size=100 : Records per chunk} {--since= : Only backfill logs since this date (Y-m-d)}';

    protected $description = 'Backfill human-readable descriptions for existing audit logs';

    public function handle(): int
    {
        $query = AuditLog::whereNull('description');

        $since = $this->option('since');

        if ($since) {
            $query->where('timestamp', '>=', Carbon::parse($since));
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('All audit logs already have descriptions.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('Would backfill %d descriptions. Use without --dry-run to execute.', $total));

            return self::SUCCESS;
        }

        $chunkSize = max(1, (int) $this->option('chunk-size'));
        $formatter = new AuditLogFormatter;
        $processed = 0;
        $successful = 0;

        $this->output->progressStart($total);

        // Allow AUDIT_LOG UPDATEs (append-only trigger checks this session variable)
        DB::statement("SET app.allow_audit_mutations = 'true'");

        try {
            $query->orderBy('id')->chunkById($chunkSize, function ($logs) use ($formatter, &$processed, &$successful, $total) {
                foreach ($logs as $log) {
                    $processed++;

                    try {
                        $log->description = $formatter->format($log);
                        $log->save();
                        $successful++;
                    } catch (Throwable $e) {
                        $this->error(sprintf('Failed to backfill audit log %s: %s', $log->id, $e->getMessage()));
                        logger()->error('Failed to backfill audit log description', [
                            'audit_log_id' => $log->id,
                            'exception' => $e,
                        ]);
                    }

                    $this->output->progressAdvance();
                }

                $this->line(sprintf('Backfilled %d of %d descriptions...', $successful, $total));
            });
        } finally {
            DB::statement("SET app.allow_audit_mutations = ''");
        }

        $this->output->progressFinish();

        $this->info(sprintf('Backfilled %d audit log descriptions successfully', $successful));

        return self::SUCCESS;
    }
}
