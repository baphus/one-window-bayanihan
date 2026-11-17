<?php

namespace Tests\Unit;

use App\Services\AuditLogFormatter;
use PHPUnit\Framework\TestCase;

class AuditLogFormatterDateTest extends TestCase
{
    public function test_date_of_birth_is_formatted_for_audit_display(): void
    {
        $formatter = new AuditLogFormatter;

        $value = $formatter->formatFieldValue('CLIENT', 'date_of_birth', '1990-02-15');

        $this->assertSame('Feb 15, 1990', $value);
    }
}
