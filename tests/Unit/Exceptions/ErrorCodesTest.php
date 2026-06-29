<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ErrorCodes;
use PHPUnit\Framework\TestCase;

class ErrorCodesTest extends TestCase
{
    private const EXPECTED_CODES = [
        'VALIDATION_ERROR',
        'AUTHENTICATION_FAILED',
        'FORBIDDEN',
        'NOT_FOUND',
        'RATE_LIMITED',
        'CONFLICT',
        'EXTERNAL_SERVICE_ERROR',
        'DATABASE_ERROR',
        'INTERNAL_ERROR',
    ];

    public function test_all_constants_are_defined(): void
    {
        foreach (self::EXPECTED_CODES as $code) {
            $this->assertNotNull(
                constant(ErrorCodes::class.'::'.$code),
                "Constant ErrorCodes::{$code} should be defined.",
            );
        }
    }

    public function test_constants_equal_their_own_name(): void
    {
        foreach (self::EXPECTED_CODES as $code) {
            $this->assertSame(
                $code,
                constant(ErrorCodes::class.'::'.$code),
                "Constant ErrorCodes::{$code} should equal '{$code}'.",
            );
        }
    }

    public function test_message_for_validation_error(): void
    {
        $this->assertSame(
            'The provided data is invalid.',
            ErrorCodes::messageFor(ErrorCodes::VALIDATION_ERROR),
        );
    }

    public function test_message_for_authentication_failed(): void
    {
        $this->assertSame(
            'You are not authenticated.',
            ErrorCodes::messageFor(ErrorCodes::AUTHENTICATION_FAILED),
        );
    }

    public function test_message_for_forbidden(): void
    {
        $this->assertSame(
            'You do not have permission to perform this action.',
            ErrorCodes::messageFor(ErrorCodes::FORBIDDEN),
        );
    }

    public function test_message_for_not_found(): void
    {
        $this->assertSame(
            'The requested resource was not found.',
            ErrorCodes::messageFor(ErrorCodes::NOT_FOUND),
        );
    }

    public function test_message_for_rate_limited(): void
    {
        $this->assertSame(
            'Too many requests. Please slow down.',
            ErrorCodes::messageFor(ErrorCodes::RATE_LIMITED),
        );
    }

    public function test_message_for_conflict(): void
    {
        $this->assertSame(
            'The request conflicts with the current state.',
            ErrorCodes::messageFor(ErrorCodes::CONFLICT),
        );
    }

    public function test_message_for_external_service_error(): void
    {
        $this->assertSame(
            'An external service is unavailable.',
            ErrorCodes::messageFor(ErrorCodes::EXTERNAL_SERVICE_ERROR),
        );
    }

    public function test_message_for_database_error(): void
    {
        $this->assertSame(
            'A database error occurred.',
            ErrorCodes::messageFor(ErrorCodes::DATABASE_ERROR),
        );
    }

    public function test_message_for_internal_error(): void
    {
        $this->assertSame(
            'Something went wrong. Please try again.',
            ErrorCodes::messageFor(ErrorCodes::INTERNAL_ERROR),
        );
    }

    public function test_message_for_unknown_code_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown error code: UNKNOWN_CODE');

        ErrorCodes::messageFor('UNKNOWN_CODE');
    }
}
