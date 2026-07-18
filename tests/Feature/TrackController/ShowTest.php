<?php

namespace Tests\Feature\TrackController;

use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    // ---------------------------------------------------------------
    //  1. Access with a valid tracker/email session binding
    // ---------------------------------------------------------------

    public function test_bound_session_with_valid_tracker_renders_tracking_page(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        // ACT
        $response = $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => $case->tracker_number,
                'email' => $result['client']->email,
            ],
        ])->get(route('track.show', [
            'tracker_number' => $case->tracker_number,
        ]));

        // ASSERT
        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/Show')
            ->has('trackingId')
            ->has('trackedCase')
            ->has('caseOverview')
            ->missing('caseTimeline')
            ->has('milestoneTimeline')
            ->has('trackingAgencies')
            ->has('caseNotifications')
        );
    }

    // ---------------------------------------------------------------
    //  2. Direct access without the OTP-bound session is denied
    // ---------------------------------------------------------------

    public function test_direct_access_without_otp_binding_is_denied(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        // ACT — no OTP-bound session, no email, no authentication
        $response = $this->get(route('track.show', [
            'tracker_number' => $case->tracker_number,
        ]));

        // ASSERT — safe denial instead of case data
        $response->assertRedirect()->assertSessionHasErrors('tracker_number');
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
