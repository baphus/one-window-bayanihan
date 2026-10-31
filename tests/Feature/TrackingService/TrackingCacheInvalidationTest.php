<?php

namespace Tests\Feature\TrackingService;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Services\CaseEventRecorder;
use App\Services\ReferralService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

/**
 * Both client-facing payloads — buildTrackingData() (Tracking/Show and
 * OFW/CaseDetail) and buildAgencyMilestonesData() (the referral page) — are
 * computed fresh on every read, with no server-side cache.
 *
 * They used to sit behind a 120s cache cleared by referral/milestone observers.
 * That could not cover case events, which are written without touching any
 * referral: a client request reply or a case reopening produced no
 * invalidation, so a cached copy served stale activity for up to the TTL.
 * These tests pin the guarantee that replaced it — the next read always
 * reflects an activity write that happened a moment earlier.
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
    public function referral_status_change_is_visible_on_the_next_read(): void
    {
        $setup = $this->createCompleteCase(1, 0);
        $case = $setup['case'];
        $referral = $setup['referrals']->first();
        $user = $setup['user'];

        $service = app(TrackingService::class);
        $referral->agency->update(['logo_url' => 'https://example.com/agency.png']);
        $payload = $service->buildAgencyMilestonesData($case, $referral);
        $this->assertSame('https://example.com/agency.png', $payload['agencyMilestones']['agencyLogoUrl']);
        $this->assertSame('https://example.com/agency.png', $service->buildTrackingData($this->loadRelations($case))['trackingAgencies'][0]['logoUrl']);
        $this->assertSame('PENDING', $service->buildTrackingData($this->loadRelations($case))['trackingAgencies'][0]['status']);

        // The agency accepts the referral (PENDING → PROCESSING).
        app(ReferralService::class)->updateStatus($referral->id, 'PROCESSING', 'ACCEPT', null, $user->id);

        $this->assertSame('PROCESSING', $service->buildTrackingData($this->loadRelations($case))['trackingAgencies'][0]['status']);
    }

    #[Test]
    public function milestone_addition_is_visible_on_the_next_read(): void
    {
        $setup = $this->createCompleteCase(1, 0);
        $case = $setup['case'];
        $referral = $setup['referrals']->first();
        $referral->update(['status' => 'PROCESSING']);
        $user = $setup['user'];

        $service = app(TrackingService::class);
        $service->buildTrackingData($this->loadRelations($case));
        $service->buildAgencyMilestonesData($case, $referral);

        // The agency adds a milestone.
        app(ReferralService::class)->addMilestone($referral->id, 'Initial review done', 'All documents verified', $user->id);

        $this->assertSame(1, $service->buildTrackingData($this->loadRelations($case))['trackingAgencies'][0]['milestoneCount']);
    }

    #[Test]
    public function referral_creation_is_visible_on_the_next_read(): void
    {
        $setup = $this->createCompleteCase(0, 0);
        $case = $setup['case'];
        $user = $setup['user'];

        $service = app(TrackingService::class);
        $this->assertCount(0, $service->buildTrackingData($this->loadRelations($case))['trackingAgencies']);

        // The case manager creates a new referral.
        $agency = Agency::factory()->create();
        app(ReferralService::class)->createReferral([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ], $user->id);

        $this->assertCount(1, $service->buildTrackingData($this->loadRelations($case))['trackingAgencies']);
    }

    /**
     * The regression this cache could not handle: a case event is written
     * without touching the referral, so no referral/milestone observer fired.
     * The referral page must still show it on the very next build — including
     * after a previous build of the same referral already ran.
     */
    #[Test]
    public function case_reopening_appears_on_the_next_referral_read(): void
    {
        $setup = $this->createCompleteCase(1, 0);
        $case = $setup['case'];
        $referral = $setup['referrals']->first();
        $service = app(TrackingService::class);

        // Prime the payload first: an old cached implementation would now serve
        // this snapshot until the TTL expired.
        $before = $service->buildAgencyMilestonesData($case, $referral)['agencyMilestones']['milestones'];
        $this->assertNotContains('case_reopened', array_column($before, 'type'));

        app(CaseEventRecorder::class)->caseReopened($case->fresh());

        $after = $service->buildAgencyMilestonesData($case->fresh(), $referral->fresh())['agencyMilestones']['milestones'];
        $this->assertContains('case_reopened', array_column($after, 'type'));
    }

    /**
     * Same guarantee for client request activity, which is also an event-only
     * write (createRequest touches a milestone, but a reply does not).
     */
    #[Test]
    public function client_request_activity_appears_on_the_next_referral_read(): void
    {
        $setup = $this->createCompleteCase(1, 0);
        $referral = $setup['referrals']->first();
        $request = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);
        $service = app(TrackingService::class);

        $before = $service->buildAgencyMilestonesData($referral->caseFile, $referral)['agencyMilestones']['milestones'];
        $this->assertSame([], $before);

        app(CaseEventRecorder::class)->clientRequestActivity(
            $request->load('referral'),
            'client_responded',
            'You responded to an attachment request',
            'You sent a response.',
            'client',
        );

        $after = $service->buildAgencyMilestonesData($referral->caseFile->fresh(), $referral->fresh())['agencyMilestones']['milestones'];
        $this->assertSame(['client_response'], array_column($after, 'type'));
    }
}
