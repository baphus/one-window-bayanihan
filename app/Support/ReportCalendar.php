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
     * @param  string  $column  Qualified timestamp column, e.g. "cases.created_at".
     */
    public static function localDate(string $column): string
    {
        return "({$column} AT TIME ZONE 'UTC' AT TIME ZONE '".self::timezone()."')::date";
    }

    /**
     * SQL expression for a column's "YYYY-MM" bucket in the operating timezone.
     */
    public static function localMonth(string $column): string
    {
        return "to_char({$column} AT TIME ZONE 'UTC' AT TIME ZONE '".self::timezone()."', 'YYYY-MM')";
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
}
