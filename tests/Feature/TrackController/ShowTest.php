<?php

namespace Tests\Feature\TrackController;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    // ---------------------------------------------------------------
    //  1. Direct access with a valid tracker_number
    // ---------------------------------------------------------------

    public function test_direct_access_with_valid_tracker(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        // ACT
        $response = $this->get(route('track.show', [
            'tracker_number' => $case->tracker_number,
        ]));

        // ASSERT
        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/Show')
            ->has('trackingId')
            ->has('trackedCase')
            ->has('caseOverview')
            ->has('caseTimeline')
            ->has('milestoneTimeline')
            ->has('trackingAgencies')
            ->has('caseNotifications')
        );
    }

    // ---------------------------------------------------------------
    //  2. Direct access — no OTP enforced (documented security gap)
    // ---------------------------------------------------------------

    /**
     * Design gap: show() does NOT require OTP verification.
     *
     * Unlike verifyOtp(), the show() endpoint returns full case tracking
     * data with only a tracker_number query parameter — no OTP check is
     * performed. This means anyone with a valid tracker_number can access
     * case tracking details without proving identity via email OTP.
     *
     * Security should be added separately per SECURITY_REQUIREMENTS.md.
     */
    public function test_direct_access_no_otp_enforced(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        // ACT — no OTP required, no email, no authentication
        $response = $this->get(route('track.show', [
            'tracker_number' => $case->tracker_number,
        ]));

        // ASSERT — tracking data returned WITHOUT OTP flow
        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/Show')
            ->has('trackingId')
            ->has('trackedCase')
            ->has('caseOverview')
            ->has('caseTimeline')
            ->has('milestoneTimeline')
            ->has('trackingAgencies')
            ->has('caseNotifications')
        );
    }

    // ---------------------------------------------------------------
    //  3. Non-existent tracker_number
    // ---------------------------------------------------------------

    public function test_invalid_tracker_returns_error(): void
    {
        // ACT
        $response = $this->get(route('track.show', [
            'tracker_number' => 'NONEXISTENT-123',
        ]));

        // ASSERT
        $response->assertSessionHasErrors('tracker_number');
    }

    // ---------------------------------------------------------------
    //  4. Missing tracker_number parameter
    // ---------------------------------------------------------------

    public function test_missing_tracker_number_returns_error(): void
    {
        // ACT — no query parameters at all
        $response = $this->get(route('track.show'));

        // ASSERT
        $response->assertSessionHasErrors('tracker_number');
    }
}
