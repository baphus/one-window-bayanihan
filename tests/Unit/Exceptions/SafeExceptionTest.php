<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\SafeException;
use PHPUnit\Framework\TestCase;

class SafeExceptionTest extends TestCase
{
    public function test_constructor_stores_error_code_correctly(): void
    {
        $e = new SafeException('NOT_FOUND', 'User not found.');

        $this->assertSame('NOT_FOUND', $e->errorCode);
    }

    public function test_constructor_stores_user_message_correctly(): void
    {
        $e = new SafeException('FORBIDDEN', 'Access denied.');

        $this->assertSame('Access denied.', $e->userMessage);
    }

    public function test_get_message_returns_log_message_when_provided(): void
    {
        $e = new SafeException(
            'DATABASE_ERROR',
            'Something went wrong.',
            'DB connection failed on host 10.0.0.1',
        );

        $this->assertSame('DB connection failed on host 10.0.0.1', $e->getMessage());
    }

    public function test_get_message_returns_user_message_when_log_message_is_null(): void
    {
        $e = new SafeException('VALIDATION_ERROR', 'Invalid input.');

        $this->assertSame('Invalid input.', $e->getMessage());
    }

    public function test_set_incident_id_and_getter(): void
    {
        $e = new SafeException('INTERNAL_ERROR', 'Server error.');

        $this->assertNull($e->incidentId);

        $e->setIncidentId('inc_abc123');

        $this->assertSame('inc_abc123', $e->incidentId);
    }

    public function test_previous_exception_chaining_with_get_previous(): void
    {
        $previous = new \RuntimeException('Underlying cause');
        $e = new SafeException(
            'EXTERNAL_SERVICE_ERROR',
            'External service unavailable.',
            'HTTP 502 from payment gateway',
            $previous,
        );

        $this->assertSame($previous, $e->getPrevious());
        $this->assertSame('Underlying cause', $e->getPrevious()->getMessage());
    }

    public function test_code_is_zero_in_parent(): void
    {
        $e = new SafeException('CONFLICT', 'Resource conflict.');

        $this->assertSame(0, $e->getCode());
    }
}
