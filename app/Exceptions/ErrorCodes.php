<?php

namespace App\Exceptions;

enum ErrorCodes: string
{
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';
    case FORBIDDEN = 'FORBIDDEN';
    case NOT_FOUND = 'NOT_FOUND';
    case RATE_LIMITED = 'RATE_LIMITED';
    case CONFLICT = 'CONFLICT';
    case EXTERNAL_SERVICE_ERROR = 'EXTERNAL_SERVICE_ERROR';
    case DATABASE_ERROR = 'DATABASE_ERROR';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';

    public function message(): string
    {
        return match ($this) {
            self::VALIDATION_ERROR => 'The provided data is invalid.',
            self::AUTHENTICATION_FAILED => 'You are not authenticated.',
            self::FORBIDDEN => 'You do not have permission to perform this action.',
            self::NOT_FOUND => 'The requested resource was not found.',
            self::RATE_LIMITED => 'Too many requests. Please slow down.',
            self::CONFLICT => 'The request conflicts with the current state.',
            self::EXTERNAL_SERVICE_ERROR => 'An external service is unavailable.',
            self::DATABASE_ERROR => 'A database error occurred.',
            self::INTERNAL_ERROR => 'Something went wrong. Please try again.',
        };
    }

    public static function messageFor(self|string $code): string
    {
        $enum = $code instanceof self ? $code : self::tryFrom($code);

        if ($enum === null) {
            throw new \InvalidArgumentException("Unknown error code: {$code}");
        }

        return $enum->message();
    }
}
