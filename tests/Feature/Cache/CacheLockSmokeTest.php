<?php

namespace Tests\Feature\Cache;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lock-driver verification (fix plan Task 0.5 / MCP-B).
 *
 * `withoutOverlapping()` schedules rely on atomic cache locks, so every
 * environment must run a lock-capable cache store. This smoke test pins the
 * acquire / mutual-exclusion / release contract of `Cache::lock()`.
 */
class CacheLockSmokeTest extends TestCase
{
    #[Test]
    public function test_cache_lock_acquire_release(): void
    {
        $lock = Cache::lock('smoke-lock', 10);

        $this->assertTrue($lock->acquire());

        // A contender for the same key must not acquire while the lock is held.
        $this->assertFalse(Cache::lock('smoke-lock', 10)->acquire());

        $lock->release();

        // After release the key is acquirable again.
        $reacquired = Cache::lock('smoke-lock', 10);
        $this->assertTrue($reacquired->acquire());
        $reacquired->release();
    }
}
