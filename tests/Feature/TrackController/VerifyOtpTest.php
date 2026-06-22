<?php

namespace Tests\Feature\TrackController;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class VerifyOtpTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    private string $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->email = 'test@example.com';
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
    }

    /**
     * Recursively check that no confidential/internal field names appear
     * anywhere in the response data tree.
     */
    private function assertNoConfidentialData(array $data, string $path = ''): void
    {
        $forbidden = ['internal_notes', 'user_id', 'old_value'];

        foreach ($data as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : (string) $key;

            foreach ($forbidden as $pattern) {
                $this->assertFalse(
                    str_contains($key, $pattern),
                    "Confidential field '{$key}' found at {$currentPath}"
                );
            }

            if (is_array($value)) {
                $this->assertNoConfidentialData($value, $currentPath);
            }
        }
    }

    private function sendOtp(string $trackerNumber): string
    {
        $this->post(route('track.send-otp'), [
            'tracker_number' => $trackerNumber,
            'email' => $this->email,
        ]);

        $otp = Cache::get("otp:track:{$this->email}");
        $this->assertNotNull($otp, 'OTP should be cached after sendOtp');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        return $otp;
    }

    // ---------------------------------------------------------------
    //  1. Valid OTP flow — full end-to-end with data shape assertion
    // ---------------------------------------------------------------

    public function test_valid_otp_flow_returns_tracking_data(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        $otp = $this->sendOtp($case->tracker_number);

        // ACT
        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $this->email,
            'otp' => $otp,
        ]);

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
    //  2. Invalid OTP
    // ---------------------------------------------------------------

    public function test_invalid_otp_returns_error(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        $this->sendOtp($case->tracker_number);

        // ACT — use wrong OTP
        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $this->email,
            'otp' => '000000',
        ]);

        // ASSERT
        $response->assertSessionHasErrors('otp');
        $errors = session('errors');
        $this->assertEquals('Invalid or expired OTP.', $errors->first('otp'));
    }

    // ---------------------------------------------------------------
    //  3. Expired OTP (removed from cache)
    // ---------------------------------------------------------------

    public function test_expired_otp_returns_error(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        $otp = $this->sendOtp($case->tracker_number);

        // Remove OTP from cache to simulate expiry
        Cache::forget("otp:track:{$this->email}");

        // ACT — use the previously-valid OTP (now expired)
        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $this->email,
            'otp' => $otp,
        ]);

        // ASSERT
        $response->assertSessionHasErrors('otp');
    }

    // ---------------------------------------------------------------
    //  4. Case deleted after OTP generated
    // ---------------------------------------------------------------

    public function test_missing_case_after_otp_returns_error(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        $otp = $this->sendOtp($case->tracker_number);

        // Soft-delete the case (is_deleted = true)
        $case->delete();

        // ACT — OTP verifies, but findCaseByTracker finds nothing
        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $this->email,
            'otp' => $otp,
        ]);

        // ASSERT
        $response->assertSessionHasErrors('tracker_number');
    }

    // ---------------------------------------------------------------
    //  5. Rate limiting bypassed via withoutMiddleware
    // ---------------------------------------------------------------

    public function test_rate_limiting_bypassed(): void
    {
        // withoutMiddleware(ThrottleRequests::class) is applied in setUp,
        // so the verify-otp endpoint (normally 5 req/min) is not throttled.

        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        $otp = $this->sendOtp($case->tracker_number);

        // ACT — would be rate-limited without the bypass
        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $this->email,
            'otp' => $otp,
        ]);

        // ASSERT — request succeeds (not throttled)
        $response->assertInertia(fn ($page) => $page->component('Tracking/Show'));
    }

    // ---------------------------------------------------------------
    //  6. Full tracking data shape — all required keys present
    // ---------------------------------------------------------------

    public function test_full_tracking_data_contains_all_keys(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        $otp = $this->sendOtp($case->tracker_number);

        // ACT
        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $this->email,
            'otp' => $otp,
        ]);

        // ASSERT — top-level keys
        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/Show')
            ->has('trackingId')
            ->has('trackedCase')
            ->has('trackedCase.status')
            ->has('trackedCase.clientName')
            ->has('trackedCase.clientType')
            ->where('trackedCase.status', 'IN_PROGRESS')
            ->where('trackedCase.clientType', 'Overseas Filipino Worker')
            ->has('caseOverview')
            ->has('caseTimeline')
            ->has('milestoneTimeline')
            ->has('trackingAgencies')
            ->has('caseNotifications')
        );
    }

    // ---------------------------------------------------------------
    //  7. FR-PORT-007 — no internal/confidential fields leaked
    // ---------------------------------------------------------------

    public function test_fr_port_007_no_confidential_data_in_response(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        $otp = $this->sendOtp($case->tracker_number);

        // ACT
        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $this->email,
            'otp' => $otp,
        ]);

        // ASSERT — component assertion first
        $response->assertInertia(fn ($page) => $page->component('Tracking/Show'));

        // Deep-scan entire props tree for confidential field patterns
        $content = json_decode($response->getContent(), true);
        $props = $content['props'] ?? [];
        $this->assertNoConfidentialData($props);
    }

    // ---------------------------------------------------------------
    //  8. Missing OTP field in request
    // ---------------------------------------------------------------

    public function test_missing_otp_field_returns_error(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];

        // ACT — POST without otp field
        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $this->email,
        ]);

        // ASSERT
        $response->assertSessionHasErrors('otp');
    }
}
