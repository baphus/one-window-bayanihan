<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

            // A cached object that failed to unserialize properly (PHP version
            // change, partial write) carries no usable data: drop it and build
            // once more instead of returning it.
            if (static::isIncompleteClass($value)) {
                Cache::forget($key);

                return $callback();
            }

            return $value;
        } catch (\Throwable $e) {
            Log::warning('CacheHelper: cache read failed, recomputing value', ['key' => $key, 'exception' => $e->getMessage()]);
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
