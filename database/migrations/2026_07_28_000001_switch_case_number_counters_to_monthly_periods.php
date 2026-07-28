<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Case numbers move from a per-year to a per-month series:
 * OWB-{YEAR}-{NNNNN} becomes OWB-{YEAR}{MONTH}-{NNNNN}, e.g. OWB-202607-00001.
 *
 * The counter is keyed by the period it counts, so the key itself has to change.
 * It cannot simply be widened in place: `year` is an unsignedSmallInteger and
 * PostgreSQL smallint tops out at 32767, so the first allocation under the new
 * format (202601) would have failed with a numeric overflow rather than anything
 * that pointed at the cause.
 *
 * The old per-year rows are dropped rather than converted. There is no
 * meaningful conversion — a 2026 counter says nothing about how many cases were
 * filed in July — and they can never be read again, because no allocation will
 * ever ask for a four-digit period after this migration.
 *
 * Existing OWB-{YEAR}-{NNNNN} numbers keep working and are left alone. They
 * cannot collide with the new format: the period segment is four digits in the
 * old series and six in the new, so no two are ever equal, and the unique index
 * on cases.case_number still backs that up.
 *
 * The backfill reads only numbers already in the new six-digit format. On a
 * first run it finds none and every month starts at 1, which is correct. It
 * matters when the migration is re-run against a database that has already
 * issued monthly numbers — a rebuild or a restore — where starting from zero
 * would reissue numbers that are already in use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('case_number_counters');

        Schema::create('case_number_counters', function (Blueprint $table) {
            // YYYYMM in the operating timezone, e.g. 202607. unsignedInteger, not
            // smallint: six digits do not fit in a PostgreSQL smallint.
            $table->unsignedInteger('period')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        $this->backfillFrom("'^OWB-\\d{6}-\\d+$'", "'OWB-(\\d{6})-'", "'OWB-\\d{6}-(\\d+)'", 'period');
    }

    public function down(): void
    {
        Schema::dropIfExists('case_number_counters');

        Schema::create('case_number_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        $this->backfillFrom("'^OWB-\\d{4}-\\d+$'", "'OWB-(\\d{4})-'", "'OWB-\\d{4}-(\\d+)'", 'year');
    }

    /**
     * Seed the counter from the highest number already issued in each period, so
     * allocation continues rather than restarting over live identifiers.
     *
     * MAX() is deliberate and safe only here: this is a one-off backfill, not the
     * allocation path. Allocation must never read the cases table — that is the
     * defect the counter exists to prevent.
     */
    private function backfillFrom(string $matchPattern, string $periodPattern, string $numberPattern, string $keyColumn): void
    {
        $rows = DB::table('cases')
            ->selectRaw("SUBSTRING(case_number FROM {$periodPattern}) AS period_key")
            ->selectRaw("MAX(CAST(SUBSTRING(case_number FROM {$numberPattern}) AS INTEGER)) AS mx")
            ->whereRaw("case_number ~ {$matchPattern}")
            ->groupByRaw("SUBSTRING(case_number FROM {$periodPattern})")
            ->get();

        foreach ($rows as $row) {
            if ($row->period_key === null || $row->mx === null) {
                continue;
            }

            DB::table('case_number_counters')->insert([
                $keyColumn => (int) $row->period_key,
                'last_number' => (int) $row->mx,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
