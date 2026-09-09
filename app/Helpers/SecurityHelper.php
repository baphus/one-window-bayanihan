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

        $result = @unserialize($payload, ['allowed_classes' => $allowedClasses]);

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

    /**
     * Decode a serialized payload without instantiating any serialized class.
     *
     * This is intended for inspection of queue metadata only. Callers must
     * treat the returned object graph as inert and never invoke its methods.
     *
     * @return object|null
     */
    public static function unserializeWithoutClasses(string $payload): ?object
    {
        $result = @unserialize($payload, ['allowed_classes' => false]);

        return is_object($result) ? $result : null;
    }

    /**
     * Return properties from an inert serialized object.
     *
     * @return array<string|int, mixed>|null
     */
    public static function serializedObjectProperties(mixed $value): ?array
    {
        return is_object($value) ? get_object_vars($value) : null;
    }
}
