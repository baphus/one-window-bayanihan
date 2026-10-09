<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The audit_logs append-only trigger checks this session variable. Both
 * backfill commands bypass it, and the two used to do so in inconsistent ways:
 * one wrapped the writes in a transaction and used SET LOCAL (correct), the
 * other issued a bare SET outside any transaction and cleared it in a finally.
 *
 * A bare SET is not transaction-scoped, so the flag leaked into the rest of the
 * connection's session. In a long-lived worker or a pooled connection that is a
 * window where the append-only trigger is silently off for unrelated writers.
 *
 * One shared guard, one behaviour: SET LOCAL inside a transaction, so the flag
 * dies with the transaction.
 */
trait BypassesAuditAppendOnly
{
    /**
     * Run $work with the append-only trigger bypassed, then restore it.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    protected function withAuditMutationsAllowed(callable $work): mixed
    {
        return DB::transaction(function () use ($work) {
            DB::statement("SET LOCAL app.allow_audit_mutations = 'true'");

            try {
                return $work();
            } catch (Throwable $e) {
                // Re-throw so the transaction rolls back and the LOCAL flag
                // goes with it. The finally below only guards the happy path.
                report($e);

                throw $e;
            }
        });
    }
}
