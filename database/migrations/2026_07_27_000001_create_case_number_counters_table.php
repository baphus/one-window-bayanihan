<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative per-year counter for case numbers.
 *
 * Case numbers were derived from MAX(case_number) of surviving rows, guarded by
 * a transaction-scoped advisory lock. That worked, but had two defects:
 *
 *   1. Hard deletes recycled numbers. deleteDraft() and the trash auto-purge
 *      call forceDelete(). If the purged row held the year's maximum, the next
 *      case reused that number while audit_logs still referenced the old case by
 *      the same string — two distinct cases sharing one identifier in the audit
 *      trail (ISO 27001 A.8.15, integrity of logging).
 *
 *   2. It was a read-modify-write. Correctness depended entirely on remembering
 *      to take the advisory lock, in two separate generators, forever.
 *
 * This table is the source of truth instead. Allocation is a single atomic
 * statement (INSERT ... ON CONFLICT DO UPDATE ... RETURNING), so no advisory
 * lock is needed and no read-modify-write exists. Because the counter is
 * independent of the cases table, deleting a case — softly or hard — can never
 * hand its number to another case.
 *
 * A single PostgreSQL sequence was considered and rejected: sequences never
 * reset, so 2027 would continue from 2026's count and the per-year semantics of
 * OWB-{YEAR}-{NNNNN} would be lost. Per-year sequences would require DDL at
 * runtime, which is fragile under concurrency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_number_counters', function (Blueprint $table) {
            // The calendar year in the operating timezone, e.g. 2026.
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        // Seed from existing data so numbering continues rather than restarting
        // and colliding with cases already issued. Uses MAX() deliberately: this
        // is a one-off backfill, not the allocation path.
        $rows = DB::table('cases')
            ->selectRaw("SUBSTRING(case_number FROM 'OWB-(\\d{4})-') AS yr")
            ->selectRaw("MAX(CAST(SUBSTRING(case_number FROM 'OWB-\\d{4}-(\\d+)') AS INTEGER)) AS mx")
            ->whereRaw("case_number ~ '^OWB-\\d{4}-\\d+$'")
            ->groupByRaw("SUBSTRING(case_number FROM 'OWB-(\\d{4})-')")
            ->get();

        foreach ($rows as $row) {
            if ($row->yr === null || $row->mx === null) {
                continue;
            }

            DB::table('case_number_counters')->insert([
                'year' => (int) $row->yr,
                'last_number' => (int) $row->mx,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('case_number_counters');
    }
};
