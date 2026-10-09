<?php

namespace App\Console\Commands;

use App\Models\AuditChainCheckpoint;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off guarded repair for the audit hash chain.
 *
 * Background: TestingSeeder bulk-inserted audit rows via DB::table()->insert(),
 * bypassing AuditLog's `creating` hook, so every seeded row has prev_hash = NULL
 * in the middle of an otherwise healthy chain. Because chainDigest() takes
 * prev_hash as an input, relinking a row changes its digest and cascades forward,
 * so the repair must run from the first broken row to the END of the table.
 *
 * Safety design:
 *  1. PRE-FLIGHT (read-only): walks the chain in chain_seq order and aborts if
 *     ANY row carries a NON-NULL prev_hash that does not match its predecessor's
 *     digest. That pattern means tampering/corruption, not the seeder bypass —
 *     repairing over it would mask the evidence. Only NULL-prev_hash mismatches
 *     are repairable.
 *  2. BACKUP: copies the full audit_logs table to audit_logs_backup_<timestamp>
 *     inside the database before mutating anything. An in-DB copy is chosen over
 *     pg_dump because it needs no shell credentials, works on every platform,
 *     and restores with a single INSERT..SELECT. (For production-like data, take
 *     an external pg_dump as well — the command prints a reminder.)
 *  3. The append-only trigger is bypassed with the same pattern as
 *     PruneAuditLogs (SET LOCAL app.allow_audit_mutations = 'true' inside the
 *     repair transaction); the setting evaporates automatically at commit.
 *  4. REPAIR: raw DB updates (no model hooks) set each row's prev_hash to its
 *     predecessor's digest, cascading to the end of the table. A post-repair
 *     verification pass confirms the chain before reporting success.
 */
class RepairAuditChain extends Command
{
    protected $signature = 'audit:chain:repair
                            {--dry-run : Run the pre-flight check only, without backing up or modifying data}
                            {--force : Skip the confirmation prompt}
                            {--skip-backup : Skip creating the in-database backup table (NOT recommended)}';

    protected $description = 'One-off guarded repair: relink NULL-prev_hash audit rows (seeder bypass) into the hash chain';

    public function handle(): int
    {
        // ------------------------------------------------------------------
        // Phase 1 — PRE-FLIGHT (strictly read-only, no mutations yet).
        // ------------------------------------------------------------------
        $logs = AuditLog::orderBy('chain_seq')->get();

        if ($logs->isEmpty()) {
            $this->info('No audit log entries found — nothing to repair.');

            return Command::SUCCESS;
        }

        $checkpoint = AuditChainCheckpoint::orderBy('created_at', 'desc')->first();

        $previous = null;
        $chainStarted = false;
        $repairCount = 0;
        $firstRepairSeq = null;

        foreach ($logs as $log) {
            if ($previous === null) {
                // Oldest surviving row: must anchor at the prune checkpoint
                // when one exists, otherwise it is the chain root (or the
                // start of the pre-chain legacy era).
                $expected = $checkpoint?->anchor_hash;
                if ($expected !== null && $log->prev_hash !== $expected) {
                    $this->error(sprintf(
                        'ABORT: oldest surviving entry %s (chain_seq %d) has prev_hash %s, expected prune-checkpoint anchor %s. '
                        .'This is NOT the seeder NULL-prev_hash pattern — investigate before repairing. No data was modified.',
                        $log->id, $log->chain_seq, $log->prev_hash ?? 'NULL', $expected
                    ));

                    return Command::FAILURE;
                }
                $chainStarted = $log->prev_hash !== null || $checkpoint !== null;
            } elseif (! $chainStarted && $log->prev_hash === null) {
                // Contiguous prefix of rows created before the hash chain
                // existed — inherently unprotected, counted but untouched,
                // exactly as audit:verify treats them.
            } else {
                $chainStarted = true;
                $expected = $previous->chainDigest();
                if ($log->prev_hash !== $expected) {
                    if ($log->prev_hash !== null) {
                        $this->error(sprintf(
                            'ABORT: entry %s (chain_seq %d) has a NON-NULL prev_hash %s but the previous row\'s digest is %s. '
                            .'This looks like tampering or corruption, NOT the seeder NULL-prev_hash pattern. '
                            .'Repairing over it would mask the evidence. No data was modified.',
                            $log->id, $log->chain_seq, $log->prev_hash, $expected
                        ));

                        return Command::FAILURE;
                    }
                    $repairCount++;
                    $firstRepairSeq ??= $log->chain_seq;
                }
            }

            $previous = $log;
        }

        if ($repairCount === 0) {
            $this->info("Pre-flight passed: {$logs->count()} entries checked, chain is already consistent — nothing to repair.");

            return Command::SUCCESS;
        }

        $this->info(sprintf(
            'Pre-flight passed: %d of %d entries have NULL prev_hash (first at chain_seq %d) and all other links are intact. '
            .'The ONLY mismatches are the repairable seeder-bypass pattern.',
            $repairCount, $logs->count(), $firstRepairSeq
        ));

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] No data modified. Re-run without --dry-run to repair.');

            return Command::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Repair {$repairCount} audit chain link(s), cascading to the end of the table ({$logs->count()} entries total)?"
        )) {
            $this->info('Repair cancelled — no data was modified.');

            return Command::SUCCESS;
        }

        // ------------------------------------------------------------------
        // Phase 2 — BACKUP (full in-database copy before any mutation).
        // ------------------------------------------------------------------
        $backupTable = 'audit_logs_backup_'.now()->format('Ymd_His');

        if ($this->option('skip-backup')) {
            $this->warn('WARNING: --skip-backup given — proceeding WITHOUT a backup. Make sure you have a pg_dump of audit_logs.');
        } else {
            DB::statement("CREATE TABLE \"{$backupTable}\" AS SELECT * FROM audit_logs");
            $backedUp = DB::table($backupTable)->count();
            $this->info("Backup created: {$backupTable} ({$backedUp} rows). Keep it until audit:verify passes; restore with INSERT INTO audit_logs SELECT * FROM {$backupTable}.");
            $this->warn('For production-like data, also take an external pg_dump of audit_logs before proceeding.');
        }

        // ------------------------------------------------------------------
        // Phase 3 — REPAIR. Raw updates (no model hooks) under the same
        // trigger bypass PruneAuditLogs uses; the chain advisory lock keeps
        // concurrent writers from interleaving mid-repair. SET LOCAL expires
        // automatically when the transaction commits.
        // ------------------------------------------------------------------
        $updated = 0;

        DB::transaction(function () use ($logs, &$updated) {
            DB::statement("SET LOCAL app.allow_audit_mutations = 'true'");
            DB::statement("SELECT pg_advisory_xact_lock(hashtext('audit_log_chain'))");

            $previous = null;

            foreach ($logs as $log) {
                if ($previous === null) {
                    $previous = $log;

                    continue;
                }

                $expected = $previous->chainDigest();

                if ($log->prev_hash !== $expected) {
                    DB::table('audit_logs')->where('id', $log->id)->update(['prev_hash' => $expected]);
                    $log->prev_hash = $expected;
                    $updated++;
                }

                $previous = $log;
            }
        });

        // ------------------------------------------------------------------
        // Phase 4 — POST-REPAIR VERIFICATION (fresh read, strict walk).
        // ------------------------------------------------------------------
        $fresh = AuditLog::orderBy('chain_seq')->get();
        $previous = null;
        $chainStarted = false;
        $break = null;

        foreach ($fresh as $log) {
            if ($previous === null) {
                $expected = $checkpoint?->anchor_hash;
                if ($expected !== null && $log->prev_hash !== $expected) {
                    $break = [$log, $expected];

                    break;
                }
                $chainStarted = $log->prev_hash !== null || $checkpoint !== null;
            } elseif (! $chainStarted && $log->prev_hash === null) {
                // Untouched pre-chain legacy prefix.
            } else {
                $chainStarted = true;
                $expected = $previous->chainDigest();
                if ($log->prev_hash !== $expected) {
                    $break = [$log, $expected];

                    break;
                }
            }

            $previous = $log;
        }

        if ($break !== null) {
            [$badLog, $expected] = $break;
            $this->error(sprintf(
                'Repair FAILED verification at entry %s (chain_seq %d): prev_hash %s, expected %s. '
                .'Investigate immediately; the pre-repair data is preserved in %s.',
                $badLog->id, $badLog->chain_seq, $badLog->prev_hash ?? 'NULL', $expected ?? 'NULL', $backupTable
            ));

            return Command::FAILURE;
        }

        $this->info(sprintf(
            'Repair complete: %d prev_hash value(s) relinked across %d entries; post-repair verification passed.',
            $updated, $fresh->count()
        ));
        $this->info('Confirm with: php artisan audit:verify');

        return Command::SUCCESS;
    }
}
