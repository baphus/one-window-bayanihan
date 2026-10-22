<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeQueueAndStakeholderCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_case_approval_invalidates_the_cached_intake_queue_count(): void
    {
        $case = CaseFile::factory()->create(['status' => CaseFile::STATUS_PENDING_REVIEW]);

        Cache::put('intake_queue:count', 3);

        $case->update(['status' => 'OPEN']);

        $this->assertFalse(Cache::has('intake_queue:count'));
    }

    #[Test]
    public function a_case_rejection_invalidates_the_cached_intake_queue_count(): void
    {
        $case = CaseFile::factory()->create(['status' => CaseFile::STATUS_PENDING_REVIEW]);

        Cache::put('intake_queue:count', 3);

        $case->delete();

        $this->assertFalse(Cache::has('intake_queue:count'));
    }

    #[Test]
    public function an_agency_update_invalidates_its_cached_stakeholder_show_payload(): void
    {
        $agency = Agency::factory()->create();
        $key = 'stakeholder:agency:'.$agency->id;

        Cache::put($key, ['stakeholder' => 'cached']);

        $agency->update(['name' => 'Updated Agency Name']);

        $this->assertFalse(Cache::has($key));
    }

    #[Test]
    public function an_agency_deletion_invalidates_its_cached_stakeholder_show_payload(): void
    {
        $agency = Agency::factory()->create();
        $key = 'stakeholder:agency:'.$agency->id;

        Cache::put($key, ['stakeholder' => 'cached']);

        $agency->delete();

        $this->assertFalse(Cache::has($key));
    }
}
