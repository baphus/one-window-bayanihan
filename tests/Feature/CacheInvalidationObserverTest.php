<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CacheInvalidationObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function case_changes_invalidate_the_case_tracking_cache(): void
    {
        $case = CaseFile::factory()->create();
        $key = 'tracking:data:'.$case->id;

        Cache::put($key, ['status' => 'cached']);

        $case->update(['summary' => 'Updated case summary']);

        $this->assertFalse(Cache::has($key));
    }
}
