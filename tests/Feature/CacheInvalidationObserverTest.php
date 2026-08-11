<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
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

    #[Test]
    public function referral_changes_invalidate_the_tracking_caches(): void
    {
        $referral = Referral::factory()->create();
        $dataKey = 'tracking:data:'.$referral->case_id;
        $milestonesKey = 'tracking:milestones:'.$referral->case_id.':'.$referral->id;

        Cache::put($dataKey, ['status' => 'cached']);
        Cache::put($milestonesKey, ['milestones' => 'cached']);

        $referral->update(['status' => 'PROCESSING']);

        $this->assertFalse(Cache::has($dataKey));
        $this->assertFalse(Cache::has($milestonesKey));
    }

    #[Test]
    public function milestone_changes_invalidate_the_tracking_caches(): void
    {
        $referral = Referral::factory()->create();
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);
        $dataKey = 'tracking:data:'.$referral->case_id;
        $milestonesKey = 'tracking:milestones:'.$referral->case_id.':'.$referral->id;

        Cache::put($dataKey, ['status' => 'cached']);
        Cache::put($milestonesKey, ['milestones' => 'cached']);

        $milestone->update(['title' => 'Follow-up document received']);

        $this->assertFalse(Cache::has($dataKey));
        $this->assertFalse(Cache::has($milestonesKey));
    }

    #[Test]
    public function milestone_creation_invalidates_the_tracking_caches(): void
    {
        $referral = Referral::factory()->create();
        $dataKey = 'tracking:data:'.$referral->case_id;
        $milestonesKey = 'tracking:milestones:'.$referral->case_id.':'.$referral->id;

        Cache::put($dataKey, ['status' => 'cached']);
        Cache::put($milestonesKey, ['milestones' => 'cached']);

        Milestone::factory()->create(['refr_id' => $referral->id]);

        $this->assertFalse(Cache::has($dataKey));
        $this->assertFalse(Cache::has($milestonesKey));
    }
}
