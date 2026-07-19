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

            // If a cached object corrupted into a __PHP_Incomplete_Class
            // (from serialization glitches, PHP version changes, or
            // partial writes), evict the stale entry and recompute.
            if ($value instanceof \stdClass || static::isIncompleteClass($value)) {
                if (static::isIncompleteClass($value)) {
                    Cache::forget($key);

                    return $callback();
                }

                return (object) (array) $value;
            }

            return $value;
        } catch (\Throwable $e) {
            Cache::forget($key);

            return $callback();
        }
    }

    /**
     * Detect a __PHP_Incomplete_Class — a cached object that failed to
     * unserialize properly (e.g. due to class definition mismatch, PHP
     * version change, or partial cache write).
     */
    private static function isIncompleteClass(mixed $value): bool
    {
        if (! is_object($value) || get_class($value) !== '__PHP_Incomplete_Class') {
            return false;
        }

        return true;
    }
}
