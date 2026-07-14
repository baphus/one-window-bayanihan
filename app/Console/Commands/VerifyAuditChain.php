<?php

namespace App\Console\Commands;

use App\Models\AuditChainCheckpoint;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class VerifyAuditChain extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Verify the SHA-256 hash chain over hot audit log entries';

    public function handle(): int
    {
        $checkpoint = AuditChainCheckpoint::orderBy('created_at', 'desc')->first();
        $verifiedFrom = config('audit.chain_verified_from')
            ? Carbon::parse(config('audit.chain_verified_from'))
            : null;

        $verified = 0;
        $legacy = 0;
        $accepted = 0;
        $chainStarted = false;
        $previous = null;
        $break = null;

        AuditLog::orderBy('chain_seq')
            ->chunk(1000, function ($logs) use (&$previous, &$verified, &$legacy, &$accepted, &$chainStarted, &$break, $checkpoint, $verifiedFrom) {
                foreach ($logs as $log) {
                    if ($previous === null) {
                        // Oldest surviving row: anchor at the prune checkpoint
                        // when one exists, otherwise it is the chain root (or
                        // the start of the pre-chain legacy era).
                        $expected = $checkpoint?->anchor_hash;
                        if ($expected !== null && $log->prev_hash !== $expected) {
                            $break = [$log, $expected];

                            return false;
                        }
                        $chainStarted = $log->prev_hash !== null || $checkpoint !== null;
                        $legacy += $chainStarted ? 0 : 1;
                    } elseif (! $chainStarted && $log->prev_hash === null) {
                        // Contiguous prefix of rows created before the hash
                        // chain existed (2026-07). These are inherently
                        // unprotected and are counted, not verified.
                        $legacy++;
                    } else {
                        $chainStarted = true;
                        $expected = $previous->chainDigest();
                        if ($log->prev_hash !== $expected) {
                            // Forks from the pre-fix race are accepted before
                            // the configured baseline; the chain resyncs at
                            // this row and stays strict afterwards.
                            if ($verifiedFrom !== null && $log->timestamp !== null && $log->timestamp->lt($verifiedFrom)) {
                                $accepted++;
                            } else {
                                $break = [$log, $expected];

                                return false;
                            }
                        }
                    }

                    $previous = $log;
                    $verified++;
                }
            });

        if ($break !== null) {
            [$log, $expected] = $break;
            $message = sprintf(
                'Audit chain broken at entry %s (timestamp %s, position %d): prev_hash %s, expected %s',
                $log->id,
                $log->timestamp?->toIso8601String() ?? 'unknown',
                $verified + 1,
                $log->prev_hash ?? 'null',
                $expected ?? 'null'
            );

            Log::error($message);
            $this->error($message);

            return Command::FAILURE;
        }

        $suffix = $checkpoint ? ' (anchored at checkpoint)' : '';
        if ($legacy > 0) {
            $suffix .= " — {$legacy} pre-chain legacy entries counted but not verifiable";
        }
        if ($accepted > 0) {
            $suffix .= " — {$accepted} accepted pre-baseline anomalies (see audit.chain_verified_from)";
        }
        $this->info("Audit chain intact: {$verified} entries verified{$suffix}");

        return Command::SUCCESS;
    }
}
