<?php

namespace Tests\Feature;

use App\Models\ReferralClientRequest;
use App\Services\ReferralClientAccessService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ReferralClientAccessNullGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invalidating_caches_forgets_the_milestones_key(): void
    {
        $request = ReferralClientRequest::factory()->create();
        $key = TrackingService::trackingMilestonesCacheKey(
            $request->referral->case_id,
            $request->referral->id,
        );
        Cache::put($key, ['milestones' => 'cached']);

        $this->invalidate($request);

        $this->assertFalse(Cache::has($key));
    }

    #[Test]
    public function invalidating_caches_for_a_request_without_referral_is_a_no_op(): void
    {
        $request = ReferralClientRequest::factory()->create();
        // Simulate a dangling request whose referral no longer resolves.
        // The in-memory id is never persisted, so no FK is violated.
        $request->referral_id = (string) Str::uuid();
        $request->unsetRelation('referral');

        $this->invalidate($request);

        $this->addToAssertionCount(1);
    }

    private function invalidate(ReferralClientRequest $request): void
    {
        $service = app(ReferralClientAccessService::class);

        (new ReflectionMethod($service, 'invalidateTrackingCaches'))->invoke($service, $request);
    }
}
