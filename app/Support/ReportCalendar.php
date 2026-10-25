<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendar helpers for report queries.
 *
 * Every report timestamp column is stored in UTC, but the business day is the
 * operating timezone's day (Asia/Manila). Comparing the raw column against a
 * `Y-m-d` string — which is what `whereDate()` does — reads the date off the
 * UTC clock, so a case filed at 03:00 Manila (19:00 UTC the previous evening)
 * falls in the previous Manila day, and `to_char()` buckets it into the
 * previous month.
 *
 * Both the window filters and the month buckets must therefore speak the same
 * Manila calendar. `localDate()` / `localMonth()` emit the SQL that shifts a
 * UTC timestamp to the operating zone before truncating; every report query
 * uses them so a number on screen, a bucket in a chart and a row in the export
 * all agree.
 */
class ReportCalendar
{
    /**
     * Operating timezone name, e.g. "Asia/Manila".
     */
    public static function timezone(): string
    {
        return (string) config('app.operating_timezone', 'Asia/Manila');
    }

    /**
     * SQL expression for a column's calendar date in the operating timezone.
     *
     * The first `AT TIME ZONE` names the zone the stored timestamp is written
     * in. That is `app.timezone` (UTC for this app); hardcoding 'UTC' here
     * would silently shift every report window on a deployment that changed it.
     *
     * @param  string  $column  Qualified timestamp column, e.g. "cases.created_at".
     */
    public static function localDate(string $column): string
    {
        return "({$column} AT TIME ZONE '".self::storageTimezone()."' AT TIME ZONE '".self::timezone()."')::date";
    }

    /**
     * SQL expression for a column's "YYYY-MM" bucket in the operating timezone.
     */
    public static function localMonth(string $column): string
    {
        return "to_char({$column} AT TIME ZONE '".self::storageTimezone()."' AT TIME ZONE '".self::timezone()."', 'YYYY-MM')";
    }

    /**
     * Timezone the stored timestamps are written in, i.e. `app.timezone`.
     */
    public static function storageTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * The `Y-m-d` calendar date a Carbon instant falls on, in the operating
     * timezone. Use this when a report default (e.g. now() / subMonths()) must
     * become a window bound.
     */
    public static function toLocalDate(CarbonInterface|string $instant): string
    {
        return CarbonImmutable::parse($instant)->setTimezone(self::timezone())->toDateString();
    }

    /**
     * The UTC instants bounding one operating-timezone calendar day.
     *
     * Returned as `[startInclusive, endExclusive)`. Comparing a bare column
     * against these keeps the predicate a plain range so the index on the
     * column still serves it — wrapping the column in a timezone expression
     * (see localDate()) cannot use that index.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function localDayBounds(string $date): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::timezone())->startOfDay();

        return [$start->utc(), $start->addDay()->utc()];
    }

    /**
     * The UTC instants bounding a whole operating-timezone month.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function localMonthBounds(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, self::timezone())->startOfMonth();

        return [$start->utc(), $start->addMonth()->utc()];
    }
}
