<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class SecurityHelper
{
    /**
     * Safely unserialize a payload with class whitelist validation.
     *
     * Prevents PHP object injection vulnerabilities by ensuring the
     * unserialized object is an instance of an allowed class.
     *
     * @param  string  $payload  Serialized payload.
     * @param  array<int, class-string>  $allowedClasses  List of fully-qualified class names allowed.
     * @return mixed The unserialized object if valid, or null on failure/mismatch.
     *
     * @throws \InvalidArgumentException When $allowedClasses is empty.
     */
    public static function safeUnserialize(string $payload, array $allowedClasses): mixed
    {
        if (empty($allowedClasses)) {
            throw new \InvalidArgumentException('safeUnserialize: at least one allowed class must be specified');
        }

        $result = @unserialize($payload);

        if ($result === false) {
            return null;
        }

        foreach ($allowedClasses as $class) {
            if ($result instanceof $class) {
                return $result;
            }
        }

        Log::warning('Safe deserialization rejected unexpected class', [
            'actual_class' => get_class($result),
            'allowed' => $allowedClasses,
        ]);

        return null;
    }
}
