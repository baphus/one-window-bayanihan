<?php

namespace Tests\Feature\Reports;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\Reports\CaseMetrics;
use App\Services\Reports\ReferralMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Report day and month boundaries must follow the Asia/Manila calendar, not
 * the UTC clock. A case filed at 03:00 Manila is still 19:00 UTC the previous
 * evening, so a naive UTC comparison files it in the previous day and month.
 */
class ManilaReportWindowsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createCaseAt(string $utc, ?string $province = null): CaseFile
    {
        return CaseFile::factory()->create([
            'status' => 'OPEN',
            'created_at' => $utc,
            'updated_at' => $utc,
            'address_province' => $province,
            'address_city_municipality' => $province ? 'Test City' : null,
        ]);
    }

    public function test_case_filed_just_after_manila_midnight_buckets_into_that_month(): void
    {
        // 2026-01-01 03:00 Manila == 2025-12-31 19:00 UTC.
        $this->createCaseAt('2025-12-31 19:00:00');

        $trend = app(CaseMetrics::class)->getCasesOverTime(
            fromDate: '2025-12-01',
            toDate: '2026-01-31',
        );

        $this->assertSame(['2026-01'], $trend['labels']);
        $this->assertSame([1], $trend['datasets'][0]['data']);
    }

    public function test_manila_midnight_record_is_inside_its_manila_day_window(): void
    {
        // A Jan 1 window must include the 03:00 Manila case; a Dec 31 window
        // must not, even though both are 2025-12-31 in UTC.
        $this->createCaseAt('2025-12-31 19:00:00');
        $metrics = app(CaseMetrics::class);

        $january = $metrics->getCasesOverTime(fromDate: '2026-01-01', toDate: '2026-01-01');
        $this->assertSame([1], $january['datasets'][0]['data'], 'The Manila Jan 1 case belongs to the Jan 1 window.');

        $december = $metrics->getCasesOverTime(fromDate: '2025-12-31', toDate: '2025-12-31');
        $this->assertSame([], $december['datasets'][0]['data'], 'It must not leak into the Dec 31 window.');
    }

    public function test_late_manila_evening_record_stays_in_its_own_day(): void
    {
        // 2026-01-01 23:30 Manila == 2026-01-01 15:30 UTC, which guards against
        // an off-by-a-day shift in the other direction.
        $this->createCaseAt('2026-01-01 15:30:00');

        $trend = app(CaseMetrics::class)->getCasesOverTime(fromDate: '2026-01-01', toDate: '2026-01-01');

        $this->assertSame([1], $trend['datasets'][0]['data']);
    }

    public function test_referral_trends_bucket_by_manila_month(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
        ]);
        $case->forceFill(['created_at' => '2025-12-31 19:00:00'])->save();

        Referral::factory()->pending()->create([
            'case_id' => $case->id,
            'created_at' => '2025-12-31 19:00:00',
            'updated_at' => '2025-12-31 19:00:00',
        ]);

        $trend = app(ReferralMetrics::class)->getReferralTrends(
            userId: $manager->id,
            role: UserRole::CASE_MANAGER->value,
            fromDate: '2025-12-01',
            toDate: '2026-01-31',
        );

        $this->assertSame(['2026-01'], $trend['labels']);
        $this->assertSame([1], $trend['datasets'][0]['data']);
    }

    /**
     * The window must stay a plain range on the timestamp column.
     *
     * Wrapping the column in `AT TIME ZONE` (the previous shape) makes the
     * predicate non-sargable: PostgreSQL cannot use the index and scans the
     * whole table on the hottest report path. Shifting the bounds instead
     * selects the same rows with an indexable range.
     */
    public function test_the_day_window_compares_the_bare_column_so_the_index_is_usable(): void
    {
        $metrics = app(CaseMetrics::class);
        $method = new \ReflectionMethod($metrics, 'applyLocalDateWindow');

        $query = CaseFile::query();
        $method->invoke($metrics, $query, 'cases.created_at', '2026-01-01', '2026-01-31');

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('"cases"."created_at" >=', $sql);
        $this->assertStringContainsString('"cases"."created_at" <', $sql);
        $this->assertStringNotContainsStringIgnoringCase('at time zone', $sql);

        // The bounds are the Manila day edges, so the range still selects
        // exactly the calendar days the user typed.
        $this->assertSame('2025-12-31 16:00:00', $this->asUtcString($bindings[0]));
        $this->assertSame('2026-01-31 16:00:00', $this->asUtcString($bindings[1]));
    }

    public function test_geo_case_filter_matches_case_address_snapshot(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->createCaseAt('2026-01-01 00:00:00', 'Active Province');
        $metrics = app(CaseMetrics::class);

        $active = $metrics->getCaseStatusDistribution(
            userId: $manager->id, role: UserRole::CASE_MANAGER->value, province: 'Active Province',
        );
        $deleted = $metrics->getCaseStatusDistribution(
            userId: $manager->id, role: UserRole::CASE_MANAGER->value, province: 'Deleted Province',
        );

        $this->assertSame(1, array_sum($active['data']));
        $this->assertSame(0, array_sum($deleted['data']));
    }

    public function test_referral_geo_filter_matches_case_address_snapshot(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = $this->createCaseAt('2026-01-01 00:00:00', 'Active Province');
        Referral::factory()->pending()->create(['case_id' => $case->id]);

        $dist = app(ReferralMetrics::class)->getReferralStatusDistribution(
            $manager->id, UserRole::CASE_MANAGER->value, province: 'Active Province',
        );

        $this->assertSame(1, array_sum($dist['data']));
    }

    public function test_cycle_buckets_use_exact_seven_fourteen_and_thirty_day_boundaries(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        foreach ([6, 7, 14, 30] as $days) {
            $case = CaseFile::factory()->create(['user_id' => $manager->id, 'status' => 'OPEN']);
            Referral::factory()->completed()->create([
                'case_id' => $case->id,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => Carbon::parse('2026-01-01 00:00:00')->addDays($days),
            ]);
        }

        $result = app(ReferralMetrics::class)->getReferralCycleTimeDistribution(
            userId: $manager->id, role: UserRole::CASE_MANAGER->value,
            fromDate: '2026-01-01', toDate: '2026-12-31', dateScope: 'referral_created_at',
        );

        $this->assertSame([1, 1, 1, 1], $result['data']);
    }

    public function test_referral_kpi_previous_window_has_same_inclusive_length_for_single_and_seven_day_ranges(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        foreach (['2026-01-01', '2026-01-03', '2026-01-07', '2026-01-08', '2026-01-14'] as $date) {
            $case = CaseFile::factory()->create(['user_id' => $manager->id, 'status' => 'OPEN']);
            Referral::factory()->pending()->create([
                'case_id' => $case->id,
                'agcy_id' => $agency->id,
                'created_at' => $date.' 12:00:00',
                'updated_at' => $date.' 12:00:00',
            ]);
        }

        $metrics = app(ReferralMetrics::class);
        $singleDay = $metrics->getReferralKpis(
            userId: $manager->id, role: UserRole::CASE_MANAGER->value,
            fromDate: '2026-01-08', toDate: '2026-01-08', dateScope: 'referral_created_at',
        );
        $sevenDays = $metrics->getReferralKpis(
            userId: $manager->id, role: UserRole::CASE_MANAGER->value,
            fromDate: '2026-01-08', toDate: '2026-01-14', dateScope: 'referral_created_at',
        );

        $this->assertSame(1, $singleDay['totalReferrals']);
        $this->assertSame(0.0, $singleDay['kpiChanges']['totalReferrals']);
        $this->assertSame(2, $sevenDays['totalReferrals']);
        $this->assertSame(-33.3, $sevenDays['kpiChanges']['totalReferrals']);
    }

    public function test_scorecard_overdue_is_strictly_more_than_fourteen_days(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00');
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        foreach ([14, 15] as $days) {
            $case = CaseFile::factory()->create(['user_id' => $manager->id, 'status' => 'OPEN']);
            Referral::factory()->pending()->create([
                'case_id' => $case->id, 'agcy_id' => $agency->id,
                'created_at' => now()->subDays($days), 'updated_at' => now()->subDays($days),
            ]);
        }

        $rows = app(ReferralMetrics::class)->getAgencyScorecard(
            $manager->id, UserRole::CASE_MANAGER->value,
            fromDate: '2026-01-01', toDate: '2026-12-31', dateScope: 'referral_created_at',
        );

        $this->assertSame(1, $rows[0]['overdue']);
    }

    private function asUtcString(mixed $value): string
    {
        return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }
}
