<?php

namespace Database\Seeders\Staging;

use Illuminate\Support\Carbon;

/**
 * Asia/Manila business calendar + seasonality + SLA transition engine.
 *
 * Rules (plan §5):
 * - Agency actions: weekdays 08:00–17:00, weighted toward 09:00–11:00 and
 *   14:00–16:00, never on PH holidays.
 * - Client actions: any day 07:00–21:00 with evening weighting; allowed on
 *   weekends and holidays.
 * - Seasonality: Dec/Jan 1.6×, Apr–May 1.2×, Aug 0.8×, else 1.0×.
 * - Every transition timestamp derives from the previous one — no
 *   independent random subDays() (the core fix over TestingSeeder).
 *
 * All helpers return NEW Carbon instances (never mutate the argument) and
 * expect inputs already in the Asia/Manila wall clock; no timezone
 * conversion is performed, so a UTC instant passed in would silently keep
 * the wrong wall time. The orchestrator builds base dates with
 * now('Asia/Manila').
 *
 * Randomness comes from the injected StagingDataFactory, sharing its single
 * deterministic stream — fixed call order means identical output every run.
 */
class TemporalEngine
{
    public const TIMEZONE = 'Asia/Manila';

    public const AGENCY_DAY_START_HOUR = 8;

    public const AGENCY_DAY_END_HOUR = 17;

    public const CLIENT_DAY_START_HOUR = 7;

    public const CLIENT_DAY_END_HOUR = 21;

    /**
     * Recurring PH public holidays matched by month-day (any year).
     *
     * @var list<string>
     */
    private const FIXED_HOLIDAYS = [
        '01-01', // New Year's Day
        '04-09', // Araw ng Kagitingan
        '05-01', // Labor Day
        '06-12', // Independence Day
        '08-21', // Ninoy Aquino Day
        '11-01', // All Saints' Day
        '11-02', // All Souls' Day
        '12-25', // Christmas Day
        '12-30', // Rizal Day
    ];

    /**
     * Movable-feast approximations for the seeded window (plan §5.2 allows a
     * static approximation): Holy Week + Eid al-Fitr / Eid al-Adha 2026.
     * Extend via addHoliday() for other years.
     *
     * @var list<string>
     */
    private const MOVABLE_HOLIDAYS = [
        '2026-03-20', // Eid'l Fitr (approx.)
        '2026-04-02', // Maundy Thursday
        '2026-04-03', // Good Friday
        '2026-05-27', // Eid'l Adha (approx.)
    ];

    /**
     * Agency hour weights: peaks 09:00–11:00 and 14:00–16:00.
     *
     * @var array<int, int>
     */
    private const AGENCY_HOUR_WEIGHTS = [
        8 => 1, 9 => 3, 10 => 3, 11 => 3, 12 => 1,
        13 => 2, 14 => 3, 15 => 3, 16 => 2, 17 => 1,
    ];

    /**
     * Client hour weights: skewed to PH evenings (OFWs act after work).
     *
     * @var array<int, int>
     */
    private const CLIENT_HOUR_WEIGHTS = [
        7 => 1, 8 => 1, 9 => 1, 10 => 1, 11 => 1, 12 => 1,
        13 => 1, 14 => 1, 15 => 1, 16 => 1,
        17 => 3, 18 => 3, 19 => 3, 20 => 3, 21 => 2,
    ];

    /**
     * Extra full-date (Y-m-d) holidays, e.g. year-specific movable feasts.
     *
     * @var array<string, true>
     */
    private array $extraHolidays = [];

    public function __construct(
        private readonly StagingDataFactory $factory,
        array $extraHolidays = []
    ) {
        foreach ($extraHolidays as $date) {
            $this->addHoliday($date);
        }
    }

    // ------------------------------------------------------------------
    // Calendar
    // ------------------------------------------------------------------

    /**
     * Register an additional holiday (Y-m-d). Used for movable feasts
     * outside the built-in approximation window.
     */
    public function addHoliday(string $date): void
    {
        $this->extraHolidays[Carbon::parse($date)->format('Y-m-d')] = true;
    }

    public function isWeekend(Carbon $date): bool
    {
        return $date->isWeekend();
    }

    public function isHoliday(Carbon $date): bool
    {
        if (in_array($date->format('m-d'), self::FIXED_HOLIDAYS, true)) {
            return true;
        }

        $ymd = $date->format('Y-m-d');

        return in_array($ymd, self::MOVABLE_HOLIDAYS, true)
            || isset($this->extraHolidays[$ymd]);
    }

    public function isBusinessDay(Carbon $date): bool
    {
        return ! $this->isWeekend($date) && ! $this->isHoliday($date);
    }

    /**
     * Copy of $date advanced by $days business days (the start date itself
     * is never counted; $days must be >= 0).
     */
    public function addBusinessDays(Carbon $date, int $days): Carbon
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Business-day offsets must be >= 0.');
        }

        $result = $date->copy();
        $added = 0;

        while ($added < $days) {
            $result->addDay();

            if ($this->isBusinessDay($result)) {
                $added++;
            }
        }

        return $result;
    }

    /**
     * Copy of $date moved forward to the next business day.
     */
    public function nextBusinessDay(Carbon $date): Carbon
    {
        return $this->addBusinessDays($date, 1);
    }

    /**
     * Copy of $date moved forward until it lands on a business day
     * (no-op when already on one).
     */
    public function snapToBusinessDay(Carbon $date): Carbon
    {
        $result = $date->copy();

        while (! $this->isBusinessDay($result)) {
            $result->addDay();
        }

        return $result;
    }

    /**
     * Random calendar day within the given month, in the Asia/Manila wall
     * clock (midnight). Pass $weekdaysOnly to restrict intake-style draws
     * to Mon–Fri (intake still skews Mon–Wed at the distribution layer in
     * T5; this helper only bounds the day).
     */
    public function randomDayInMonth(int $year, int $month, bool $weekdaysOnly = false): Carbon
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1, self::TIMEZONE)->daysInMonth;

        do {
            $day = Carbon::createFromDate($year, $month, $this->factory->int(1, $daysInMonth), self::TIMEZONE);
        } while ($weekdaysOnly && $day->isWeekend());

        return $day;
    }

    // ------------------------------------------------------------------
    // Actor timestamps
    // ------------------------------------------------------------------

    /**
     * Agency action timestamp: the given day snapped forward to a business
     * day, at a weighted hour 08:00–17:00.
     */
    public function agencyDateTime(Carbon $day): Carbon
    {
        return $this->snapToBusinessDay($day)->setTime(
            $this->factory->weightedPick(self::AGENCY_HOUR_WEIGHTS),
            $this->factory->int(0, 59),
            $this->factory->int(0, 59)
        );
    }

    /**
     * Client action timestamp: any day (weekends/holidays allowed) at a
     * weighted hour 07:00–21:00 with evening skew.
     */
    public function clientDateTime(Carbon $day): Carbon
    {
        return $day->copy()->setTime(
            $this->factory->weightedPick(self::CLIENT_HOUR_WEIGHTS),
            $this->factory->int(0, 59),
            $this->factory->int(0, 59)
        );
    }

    // ------------------------------------------------------------------
    // Seasonality (plan §5.2)
    // ------------------------------------------------------------------

    /**
     * Monthly intake multiplier for a 1–12 month number: Dec/Jan 1.6×
     * (holiday repatriation), Apr–May 1.2× (school-year end), Aug 0.8×
     * (rainy season), 1.0× otherwise.
     */
    public function seasonalityMultiplier(int $month): float
    {
        return match ($month) {
            12, 1 => 1.6,
            4, 5 => 1.2,
            8 => 0.8,
            default => 1.0,
        };
    }

    /**
     * Split $total items across the given 'Y-m' month keys proportionally
     * to their seasonality multipliers. The returned counts sum to exactly
     * $total; largest remainders win ties in key order (deterministic).
     *
     * @param  list<string>  $monthKeys
     * @return array<string, int>
     */
    public function monthlyDistribution(int $total, array $monthKeys): array
    {
        if ($monthKeys === []) {
            throw new \InvalidArgumentException('Monthly distribution requires at least one month.');
        }

        $weights = [];

        foreach ($monthKeys as $key) {
            $weights[$key] = $this->seasonalityMultiplier((int) Carbon::createFromFormat('Y-m', $key)->format('n'));
        }

        $weightSum = array_sum($weights);
        $counts = [];
        $remainders = [];
        $assigned = 0;

        foreach ($weights as $key => $weight) {
            $exact = $total * $weight / $weightSum;
            $counts[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $counts[$key];
            $assigned += $counts[$key];
        }

        arsort($remainders);

        foreach (array_keys($remainders) as $key) {
            if ($assigned >= $total) {
                break;
            }
            $counts[$key]++;
            $assigned++;
        }

        return $counts;
    }

    // ------------------------------------------------------------------
    // SLA transitions (plan §5.3) — each derives from the previous stamp.
    // ------------------------------------------------------------------

    /**
     * PENDING → PROCESSING: +1–3 business days, agency hours.
     */
    public function pendingToProcessing(Carbon $created): Carbon
    {
        return $this->agencyDateTime(
            $this->addBusinessDays($created, $this->factory->int(1, 3))
        );
    }

    /**
     * PROCESSING → FOR_COMPLIANCE: +2–5 business days, agency hours
     * (first_action_at is set at this stamp).
     */
    public function processingToCompliance(Carbon $from): Carbon
    {
        return $this->agencyDateTime(
            $this->addBusinessDays($from, $this->factory->int(2, 5))
        );
    }

    /**
     * FOR_COMPLIANCE → COMPLETED: +processing_days business days
     * (from the services table), agency hours.
     */
    public function complianceToCompleted(Carbon $from, int $processingDays): Carbon
    {
        return $this->agencyDateTime(
            $this->addBusinessDays($from, max(1, $processingDays))
        );
    }

    /**
     * REJECTED with decision REJECT + 'Requirements not met': +3–7
     * business days, agency hours.
     */
    public function rejectionAt(Carbon $from): Carbon
    {
        return $this->agencyDateTime(
            $this->addBusinessDays($from, $this->factory->int(3, 7))
        );
    }

    /**
     * Case closure follows referral completion by +7–30 calendar days,
     * resolved to an agency-hours stamp.
     */
    public function caseClosedAt(Carbon $completedAt): Carbon
    {
        return $this->agencyDateTime(
            $completedAt->copy()->addDays($this->factory->int(7, 30))
        );
    }
}
