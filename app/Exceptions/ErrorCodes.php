<?php

namespace App\Exceptions;

final class ErrorCodes
{
    const VALIDATION_ERROR = 'VALIDATION_ERROR';

    const AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';

    const FORBIDDEN = 'FORBIDDEN';

    const NOT_FOUND = 'NOT_FOUND';

    const RATE_LIMITED = 'RATE_LIMITED';

    const CONFLICT = 'CONFLICT';

    const EXTERNAL_SERVICE_ERROR = 'EXTERNAL_SERVICE_ERROR';

    const DATABASE_ERROR = 'DATABASE_ERROR';

    const INTERNAL_ERROR = 'INTERNAL_ERROR';

    public static function messageFor(string $code): string
    {
        return match ($code) {
            self::VALIDATION_ERROR => 'The provided data is invalid.',
            self::AUTHENTICATION_FAILED => 'You are not authenticated.',
            self::FORBIDDEN => 'You do not have permission to perform this action.',
            self::NOT_FOUND => 'The requested resource was not found.',
            self::RATE_LIMITED => 'Too many requests. Please slow down.',
            self::CONFLICT => 'The request conflicts with the current state.',
            self::EXTERNAL_SERVICE_ERROR => 'An external service is unavailable.',
            self::DATABASE_ERROR => 'A database error occurred.',
            self::INTERNAL_ERROR => 'Something went wrong. Please try again.',
            default => throw new \InvalidArgumentException("Unknown error code: {$code}"),
        };
    }
}
