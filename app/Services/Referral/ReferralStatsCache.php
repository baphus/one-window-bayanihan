<?php

namespace App\Services\Referral;

use Illuminate\Support\Facades\Cache;

class ReferralStatsCache
{
    public const REFERRAL_STATS_CACHE_VERSION_KEY = 'stats:referrals:version';

    public static function referralStatsCacheKey(?string $userAgencyId, ?string $userRole, ?string $userId): string
    {
        $version = (int) Cache::rememberForever(self::REFERRAL_STATS_CACHE_VERSION_KEY, static fn () => 1);

        return 'stats:referrals:v'.$version.':'.($userRole ?? 'all').':'.($userAgencyId ?? 'global').':'.($userId ?? 'global');
    }

    public static function invalidateReferralStats(): void
    {
        Cache::increment(self::REFERRAL_STATS_CACHE_VERSION_KEY);
    }
}
