<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The tracking payloads are no longer cached (see TrackingCacheInvalidationTest
 * for the read-freshness guarantees), so the referral and milestone observers
 * no longer clear a milestones key. What remains here is the case-level audit
 * cache, which is still written and still must be cleared on an update.
 */
class CacheInvalidationObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function case_changes_invalidate_the_cached_audit_case_number(): void
    {
        $case = CaseFile::factory()->create();
        $key = "audit_case_number:{$case->id}";

        Cache::put($key, 'Case OWB-1');

        $case->update(['summary' => 'Updated summary']);

        $this->assertFalse(Cache::has($key));
    }
}
