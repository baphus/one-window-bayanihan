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
            // can unserialize into a state that throws "tried to access a
            // property on an incomplete object" the moment the caller reads a
            // property — even though get_class() still reports stdClass. The
            // robust recovery is to rebuild it from its array form, which
            // works for both healthy and incomplete stdClass instances and
            // yields a clean, fully-usable object. Other object types (models,
            // DTOs) are left untouched.
            if ($value instanceof \stdClass) {
                return (object) (array) $value;
            }

            return $value;
        } catch (\Throwable $e) {
            Cache::forget($key);

            return $callback();
        }
    }
}
