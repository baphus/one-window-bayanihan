<?php

namespace App\Helpers;

class SecurityHelper
{
    /**
     * Decode a serialized payload without instantiating any serialized class.
     *
     * This is intended for inspection of queue metadata only. Callers must
     * treat the returned object graph as inert and never invoke its methods.
     */
    public static function unserializeWithoutClasses(string $payload): ?object
    {
        $result = @unserialize($payload, ['allowed_classes' => false]);

        return is_object($result) ? $result : null;
    }

    /**
     * Return properties from an inert serialized object.
     */
    public static function serializedObjectProperties(mixed $value): ?array
    {
        return is_object($value) ? get_object_vars($value) : null;
    }
}
