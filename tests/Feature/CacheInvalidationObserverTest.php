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
    public function referral_changes_invalidate_the_tracking_caches(): void
    {
        $referral = Referral::factory()->create();
        $milestonesKey = 'tracking:milestones:'.$referral->case_id.':'.$referral->id;

        Cache::put($milestonesKey, ['milestones' => 'cached']);

        $referral->update(['status' => 'PROCESSING']);

        $this->assertFalse(Cache::has($milestonesKey));
    }

    #[Test]
    public function milestone_changes_invalidate_the_tracking_caches(): void
    {
        $referral = Referral::factory()->create();
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);
        $milestonesKey = 'tracking:milestones:'.$referral->case_id.':'.$referral->id;

        Cache::put($milestonesKey, ['milestones' => 'cached']);

        $milestone->update(['title' => 'Follow-up document received']);

        $this->assertFalse(Cache::has($milestonesKey));
    }

    #[Test]
    public function milestone_creation_invalidates_the_tracking_caches(): void
    {
        $referral = Referral::factory()->create();
        $milestonesKey = 'tracking:milestones:'.$referral->case_id.':'.$referral->id;

        Cache::put($milestonesKey, ['milestones' => 'cached']);

        Milestone::factory()->create(['refr_id' => $referral->id]);

        $this->assertFalse(Cache::has($milestonesKey));
    }

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
