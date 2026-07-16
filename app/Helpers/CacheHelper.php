<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Cache::remember wrapper that evicts corrupted entries instead of crashing.
     *
     * If unserialize fails on a stale/corrupt cache entry (e.g. due to a server
     * restart mid-write or class definition mismatch), the bad key is forgotten
     * and the closure is re-executed to produce a fresh value.
     */
    public static function safeRemember(string $key, mixed $ttl, \Closure $callback): mixed
    {
        try {
            $value = Cache::remember($key, $ttl, $callback);

            // PHP's unserialize() may return __PHP_Incomplete_Class silently
            // (no exception) when the class isn't autoloaded yet — commonly
            // seen with the database cache driver after bootstrap changes.
            // Treat the stale entry as a miss so the callback re-runs fresh.
            if (is_object($value) && get_class($value) === '__PHP_Incomplete_Class') {
                Cache::forget($key);

                return $callback();
            }

            return $value;
        } catch (\Throwable $e) {
            Cache::forget($key);

            return $callback();
        }
    }
}
