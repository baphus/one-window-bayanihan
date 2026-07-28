<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Services\CaseNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaseNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function generator(): CaseNumberGenerator
    {
        return app(CaseNumberGenerator::class);
    }

    public function test_case_numbers_increment_without_gaps(): void
    {
        $period = now()->timezone(config('app.operating_timezone'))->format('Ym');

        $this->assertSame("OWB-{$period}-00001", $this->generator()->nextCaseNumber());
        $this->assertSame("OWB-{$period}-00002", $this->generator()->nextCaseNumber());
        $this->assertSame("OWB-{$period}-00003", $this->generator()->nextCaseNumber());
    }

    public function test_allocation_does_not_read_the_cases_table(): void
    {
        // The counter is authoritative, so an unrelated case already holding a
        // high number must not influence the next allocation. Under the old
        // MAX(case_number) approach this returned 00043.
        $period = now()->timezone(config('app.operating_timezone'))->format('Ym');

        CaseFile::factory()->create(['case_number' => "OWB-{$period}-00042"]);

        $this->assertSame("OWB-{$period}-00001", $this->generator()->nextCaseNumber());
    }

    public function test_hard_deleting_a_case_does_not_recycle_its_number(): void
    {
        // The defect this replaces: numbers came from MAX() of surviving rows, so
        // force-deleting the row holding the year's maximum handed that number to
        // the next case, while audit_logs still referenced the old case by the
        // same string. Two cases, one identifier, in the audit trail.
        $first = $this->generator()->nextCaseNumber();
        $case = CaseFile::factory()->create(['case_number' => $first]);

        $case->forceDelete();

        $second = $this->generator()->nextCaseNumber();

        $this->assertNotSame($first, $second, 'A hard-deleted case number was reissued.');
        $this->assertStringEndsWith('00002', $second);
    }

    public function test_counter_is_per_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));
        $this->assertSame('OWB-202606-00001', $this->generator()->nextCaseNumber());
        $this->assertSame('OWB-202606-00002', $this->generator()->nextCaseNumber());

        // A new month starts its own counter at 1 rather than continuing, which
        // is why a single non-resetting PostgreSQL sequence was not used.
        $this->travelTo(CarbonImmutable::parse('2026-07-15 04:00:00', 'UTC'));
        $this->assertSame('OWB-202607-00001', $this->generator()->nextCaseNumber());

        // ...and the following year's same month is its own series again.
        $this->travelTo(CarbonImmutable::parse('2027-07-15 04:00:00', 'UTC'));
        $this->assertSame('OWB-202707-00001', $this->generator()->nextCaseNumber());

        $this->travelBack();
    }

    public function test_legacy_yearly_numbers_cannot_collide_with_monthly_ones(): void
    {
        // Cases issued before the switch keep OWB-{YEAR}-{NNNNN}. The period
        // segment is four digits there and six here, so no monthly number can
        // ever equal a yearly one no matter how the counters line up.
        $this->travelTo(CarbonImmutable::parse('2026-07-15 04:00:00', 'UTC'));

        CaseFile::factory()->create(['case_number' => 'OWB-2026-00001']);

        $this->assertSame('OWB-202607-00001', $this->generator()->nextCaseNumber());

        $this->travelBack();
    }

    public function test_allocation_is_a_single_statement_with_no_advisory_lock(): void
    {
        // The previous implementation took pg_advisory_xact_lock and then read
        // MAX(case_number). Allocation should now be one INSERT ... ON CONFLICT
        // ... RETURNING against the counter, with no lock and no SELECT of cases.
        DB::enableQueryLog();
        $this->generator()->nextCaseNumber();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $sql = strtolower(implode(' | ', array_column($queries, 'query')));

        $this->assertStringNotContainsString('pg_advisory', $sql, 'Allocation still takes an advisory lock.');
        $this->assertStringContainsString('on conflict', $sql, 'Allocation is not the atomic upsert.');
        $this->assertStringNotContainsString('from "cases"', $sql, 'Allocation still reads the cases table.');
        $this->assertCount(1, $queries, 'Allocation should be a single round trip.');
    }

    public function test_tracker_uses_an_unambiguous_alphabet(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $tracker = $this->generator()->nextTrackerNumber();

            $this->assertMatchesRegularExpression('/^OWBAP-[0-9A-HJKMNP-TV-Z]{10}$/', $tracker);

            // I, L, O and U must never appear: they are the glyphs a client
            // confuses with 1, 1, 0 when reading a tracker aloud.
            $this->assertDoesNotMatchRegularExpression('/[ILOU]/', substr($tracker, 6));
        }
    }

    public function test_trackers_are_unique_across_many_allocations(): void
    {
        $seen = [];

        for ($i = 0; $i < 50; $i++) {
            $seen[] = $this->generator()->nextTrackerNumber();
        }

        $this->assertCount(50, array_unique($seen));
    }
}
