<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Cache;

/**
 * Invalidation for the reports payload cache.
 *
 * ReportsService::getAll() caches its full aggregate payload (~30 queries) under
 * `reports:payload:<hash of criteria>` for 3 minutes. That payload must go stale
 * the moment a case, referral, agency or reference row changes, so every write
 * path calls ReportsService::invalidateAll().
 *
 * The keys are unbounded in number (one per user/role/date-range/geo
 * combination) and the cache store may be any driver, so the payload keys are
 * not enumerated or tagged. Instead each payload key is prefixed with a version
 * counter; bumping the counter orphans every existing payload at once, and they
 * fall out of the cache on their own TTL. That is one INCR per write and one GET
 * per read, and it works on every Laravel cache store.
 *
 * This class exists so the version prefix and the bump live in exactly one
 * place: previously invalidateAll() was a documented no-op that looked like it
 * flushed reports while every payload survived its full TTL.
 */
class ReportsCache
{
    /** Reference/lookup data (provinces, cities, agencies) shared by all reports. */
    public const KEY_REFERENCE_DATA = ReportLookups::KEY_REFERENCE_DATA;

    private const VERSION_KEY = 'reports:payload:version';

    /**
     * Current cache generation. Reads never bump it, so an empty cache does not
     * cost a write on every page load.
     */
    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Invalidate every cached reports payload and the shared reference data.
     *
     * Called from CacheInvalidationObserver (case/referral/agency/category issue
     * and status writes) and CaseService::invalidateCategoryCaches().
     */
    public static function invalidateAll(): void
    {
        Cache::forget(self::KEY_REFERENCE_DATA);

        // Bump if present, else start a generation above the default so the
        // first write after a flush still moves the prefix forward.
        if (! Cache::add(self::VERSION_KEY, 2, now()->addYears(10))) {
            Cache::increment(self::VERSION_KEY);
        }
    }
}
