<?php

namespace Tests\Feature\TrackingService;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Services\ReferralService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

/**
 * The OFW/public tracking payload (Tracking/Show and OFW/CaseDetail) is
 * served through TrackingService::buildTrackingData() behind a server-side
 * cache (TTL 90s) and TrackingService::buildAgencyMilestonesData() (TTL 120s).
 *
 * These tests pin the regression that the referral write paths must invalidate
 * that cache immediately, so status and milestone updates appear on the
 * tracking page as soon as the agency acts — not after the TTL expires.
 */
class TrackingCacheInvalidationTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    /**
     * Eager-load all relationships that buildTrackingData() requires.
     */
    private function loadRelations(CaseFile $case): CaseFile
    {
        return $case->load([
            'client.addresses',
            'client.employments',
            'client.nextOfKin',
            'referrals.agency',
            'referrals.services',
            'referrals.milestones.user',
            'user',
            'category',
            'categories',
        ]);
    }

    #[Test]
    public function referral_status_change_invalidates_tracking_cache_immediately(): void
    {
        $setup = $this->createCompleteCase(1, 0);
        $case = $setup['case'];
        $referral = $setup['referrals']->first();
        $user = $setup['user'];

        $service = app(TrackingService::class);
        $service->buildTrackingData($this->loadRelations($case));
        $service->buildAgencyMilestonesData($case, $referral);

        $this->assertSame('PENDING', $service->buildTrackingData($this->loadRelations($case))['trackingAgencies'][0]['status']);
        $this->assertTrue(Cache::has(TrackingService::trackingDataCacheKey($case->id)));
        $this->assertTrue(Cache::has(TrackingService::trackingMilestonesCacheKey($case->id, $referral->id)));

        // The agency accepts the referral (PENDING → PROCESSING).
        app(ReferralService::class)->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $user->id);

        // The tracking cache is invalidated immediately — no 90s staleness.
        $this->assertFalse(Cache::has(TrackingService::trackingDataCacheKey($case->id)));
        $this->assertFalse(Cache::has(TrackingService::trackingMilestonesCacheKey($case->id, $referral->id)));

        // The very next read is already fresh.
        $this->assertSame('PROCESSING', $service->buildTrackingData($this->loadRelations($case))['trackingAgencies'][0]['status']);
    }

    #[Test]
    public function milestone_addition_invalidates_tracking_cache_immediately(): void
    {
        $setup = $this->createCompleteCase(1, 0);
        $case = $setup['case'];
        $referral = $setup['referrals']->first();
        $referral->update(['status' => 'PROCESSING']);
        $user = $setup['user'];

        $service = app(TrackingService::class);
        $service->buildTrackingData($this->loadRelations($case));
        $service->buildAgencyMilestonesData($case, $referral);

        $this->assertTrue(Cache::has(TrackingService::trackingDataCacheKey($case->id)));
        $this->assertTrue(Cache::has(TrackingService::trackingMilestonesCacheKey($case->id, $referral->id)));

        // The agency adds a milestone.
        app(ReferralService::class)->addMilestone($referral->id, 'Initial review done', 'All documents verified', $user->id);

        // Both tracking caches are invalidated immediately — no 120s staleness.
        $this->assertFalse(Cache::has(TrackingService::trackingDataCacheKey($case->id)));
        $this->assertFalse(Cache::has(TrackingService::trackingMilestonesCacheKey($case->id, $referral->id)));

        // The very next read already shows the new milestone.
        $this->assertSame(1, $service->buildTrackingData($this->loadRelations($case))['trackingAgencies'][0]['milestoneCount']);
    }

    #[Test]
    public function referral_creation_invalidates_tracking_cache_immediately(): void
    {
        $setup = $this->createCompleteCase(0, 0);
        $case = $setup['case'];
        $user = $setup['user'];

        $service = app(TrackingService::class);
        $this->assertCount(0, $service->buildTrackingData($this->loadRelations($case))['trackingAgencies']);
        $this->assertTrue(Cache::has(TrackingService::trackingDataCacheKey($case->id)));

        // The case manager creates a new referral.
        $agency = Agency::factory()->create();
        app(ReferralService::class)->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'services' => [],
        ], $user->id);

        // The tracking cache is invalidated immediately so the new agency card shows up.
        $this->assertFalse(Cache::has(TrackingService::trackingDataCacheKey($case->id)));
        $this->assertCount(1, $service->buildTrackingData($this->loadRelations($case))['trackingAgencies']);
    }
}
