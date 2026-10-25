<?php

namespace Tests\Feature\Reports;

use App\Enums\UserRole;
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

    private function createCaseAt(string $utc): CaseFile
    {
        return CaseFile::factory()->create([
            'status' => 'OPEN',
            'created_at' => $utc,
            'updated_at' => $utc,
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
        // 2026-01-01 23:30 Manila == 2026-01-01 15:30 UTC — guards against an
        // off-by-a-day shift in the other direction.
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
}
