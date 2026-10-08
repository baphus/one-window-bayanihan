<?php

namespace Tests\Unit;

use App\Helpers\SecurityHelper;
use Tests\TestCase;

class ExplosiveSerializedPayload
{
    public static bool $woke = false;

    public function __wakeup(): void
    {
        self::$woke = true;
    }
}

class SecurityHelperTest extends TestCase
{
    public function test_inert_deserialization_does_not_invoke_magic_methods(): void
    {
        ExplosiveSerializedPayload::$woke = false;

        $result = SecurityHelper::unserializeWithoutClasses(serialize(new ExplosiveSerializedPayload));

        $this->assertIsObject($result);
        $this->assertFalse(ExplosiveSerializedPayload::$woke);
        $this->assertSame(
            ExplosiveSerializedPayload::class,
            SecurityHelper::serializedObjectProperties($result)['__PHP_Incomplete_Class_Name']
        );
    }
}
