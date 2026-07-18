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

            // A stdClass cached by a serialized driver (redis/file/database)
            // can unserialize into an incomplete object that throws "tried to
            // access a property on an incomplete object" the moment the caller
            // reads a property. This shows up either as a real stdClass or as a
            // __PHP_Incomplete_Class whose original class was stdClass. The
            // robust recovery is to rebuild it from its array form, which works
            // for both healthy and incomplete instances and yields a clean,
            // fully-usable stdClass. Other object types (models, DTOs) are left
            // untouched.
            if ($value instanceof \stdClass || static::isIncompleteStdClass($value)) {
                return (object) (array) $value;
            }

            return $value;
        } catch (\Throwable $e) {
            Cache::forget($key);

            return $callback();
        }
    }

    /**
     * Detect a __PHP_Incomplete_Class whose original (serialized) type was
     * stdClass — the exact shape produced when a cached DB::selectOne() result
     * round-trips through a serialized cache driver and loses its definition.
     */
    private static function isIncompleteStdClass(mixed $value): bool
    {
        if (! is_object($value) || get_class($value) !== '__PHP_Incomplete_Class') {
            return false;
        }

        $vars = (array) $value;

        // The original class name is stored under a key that may carry null
        // bytes depending on PHP version; check both representations.
        $name = $vars['__PHP_Incomplete_Class_Name']
            ?? $vars["\0__PHP_Incomplete_Class_Name\0"]
            ?? null;

        return $name === \stdClass::class;
    }
}
