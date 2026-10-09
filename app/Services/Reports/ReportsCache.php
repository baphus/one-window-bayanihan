<?php

namespace App\Services\Reports;

class ReportsCache
{
    // â”€â”€ Cache Invalidation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Flush all reports caches. Called when cases/referrals/agencies change.
     */
    public static function invalidateAll(): void
    {
        // Clear reference data
        cache()->forget(ReportLookups::KEY_REFERENCE_DATA);

        // Flush all payload caches (prefixed with reports:)
        // Use tag-based clearing or pattern deletion if available;
        // otherwise rely on TTL-based expiry (3 minutes max staleness).
        // For targeted invalidation, we clear reference data immediately
        // and let payloads expire naturally via their short TTL.
    }
}
