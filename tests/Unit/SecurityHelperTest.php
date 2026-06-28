<?php

namespace Tests\Unit;

use App\Helpers\SecurityHelper;
use stdClass;
use Tests\TestCase;

class SecurityHelperTest extends TestCase
{
    public function test_returns_object_for_allowed_class(): void
    {
        $payload = serialize(new stdClass);

        $result = SecurityHelper::safeUnserialize($payload, [stdClass::class]);

        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function test_returns_null_for_disallowed_class(): void
    {
        $payload = serialize(new stdClass);

        $result = SecurityHelper::safeUnserialize($payload, [self::class]);

        $this->assertNull($result);
    }

    public function test_returns_null_for_empty_payload(): void
    {
        $result = SecurityHelper::safeUnserialize('', [stdClass::class]);

        $this->assertNull($result);
    }

    public function test_returns_null_for_invalid_payload(): void
    {
        $result = SecurityHelper::safeUnserialize('not a serialized value', [stdClass::class]);

        $this->assertNull($result);
    }

    public function test_throws_exception_for_empty_allowed_classes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('safeUnserialize: at least one allowed class must be specified');

        SecurityHelper::safeUnserialize(serialize(new stdClass), []);
    }

    public function test_matches_any_class_in_allowed_list(): void
    {
        $payload = serialize(new stdClass);

        $result = SecurityHelper::safeUnserialize($payload, [self::class, stdClass::class]);

        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function test_rejected_payload_triggers_log_and_returns_null(): void
    {
        $payload = serialize(new stdClass);

        $result = SecurityHelper::safeUnserialize($payload, [self::class]);

        $this->assertNull($result);
    }
}
