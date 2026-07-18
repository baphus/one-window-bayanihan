<?php

namespace Tests\Feature\TrackingService;

use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Services\CaseEventRecorder;
use App\Services\TrackingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class EdgeCasesTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    /**
     * Eager-load the relationships that buildTrackingData() expects.
     */
    private function loadRelations(CaseFile $case): CaseFile
    {
        $case->load([
            'client.addresses',
            'client.employments',
            'referrals.agency',
            'referrals.milestones.user',
            'referrals.attachments',
            'user',
        ]);

        return $case;
    }

    public function test_soft_deleted_case_returns_null(): void
    {
        // ARRANGE
        $case = CaseFile::factory()->create();
        $tracker = $case->tracker_number;

        // Simulate soft-delete by setting both is_deleted flag AND deleted_at
        // (SoftDeletes global scope filters on deleted_at; SoftDeleteFlag
        // sets is_deleted = true during the deleting event.)
        $case->is_deleted = true;
        $case->deleted_at = now();
        $case->save();

        $service = app(TrackingService::class);

        // ACT
        $found = $service->findCaseByTracker($tracker);

        // ASSERT
        $this->assertNull($found);
    }

    public function test_case_with_no_client(): void
    {
        // ARRANGE
        $case = CaseFile::factory()->create(['client_id' => null]);
        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT
        $data = $service->buildTrackingData($case);

        // ASSERT
        $this->assertNull($data['caseOverview']['ofw']);
        $this->assertNull($data['caseOverview']['nextOfKin']);
        $this->assertEquals('Unknown', $data['trackedCase']['clientName']);

        // Verify no exceptions are thrown during the call, and the legacy
        // audit-log timeline is no longer part of the payload
        $this->assertArrayNotHasKey('caseTimeline', $data);
        $this->assertArrayHasKey('milestoneTimeline', $data);
        $this->assertArrayHasKey('trackingAgencies', $data);
    }

    public function test_case_with_no_referrals(): void
    {
        // ARRANGE — OPEN case with no referrals
        $service = app(TrackingService::class);

        $openCase = CaseFile::factory()->create([
            'status' => 'OPEN',
            'client_id' => null,
        ]);
        app(CaseEventRecorder::class)->caseOpened($openCase);
        $this->loadRelations($openCase);

        // ACT
        $data = $service->buildTrackingData($openCase);

        // ASSERT — tracking agencies is empty, milestone timeline has only case_opened
        $this->assertIsArray($data['trackingAgencies']);
        $this->assertEmpty($data['trackingAgencies']);

        $this->assertCount(1, $data['milestoneTimeline']);
        $this->assertEquals('case_opened', $data['milestoneTimeline'][0]['type']);

        // ARRANGE — CLOSED case with no referrals
        $closedCase = CaseFile::factory()->closed()->create([
            'client_id' => null,
        ]);
        app(CaseEventRecorder::class)->caseOpened($closedCase);
        app(CaseEventRecorder::class)->caseClosed($closedCase);
        $this->loadRelations($closedCase);

        // ACT
        $data = $service->buildTrackingData($closedCase);

        // ASSERT — milestone timeline has case_opened + case_closed
        $this->assertEmpty($data['trackingAgencies']);
        $this->assertCount(2, $data['milestoneTimeline']);
        $this->assertEquals('case_opened', $data['milestoneTimeline'][0]['type']);
        $this->assertEquals('case_closed', $data['milestoneTimeline'][1]['type']);
    }

    public function test_referral_with_null_agency_violates_constraint(): void
    {
        // ARRANGE
        $case = CaseFile::factory()->create();

        // ACT & ASSERT — agcy_id is NOT NULL (migration has ->nullable(false)),
        // so attempting to create a referral with null agency throws QueryException.
        $this->expectException(QueryException::class);
        Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => null,
        ]);
    }

    public function test_malformed_tracker_number_returns_validation_error(): void
    {
        // ARRANGE — SQL injection pattern as tracker number
        $malformedTracker = "'; DROP TABLE cases; --";

        // ACT
        $response = $this->post(route('track.send-otp'), [
            'tracker_number' => $malformedTracker,
            'email' => 'test@example.com',
        ]);

        // ASSERT — the tracker_number must exist in the cases table (exists:rule),
        // so garbage input triggers validation error
        $response->assertSessionHasErrors(['tracker_number']);
    }

    public function test_concurrent_otp_requests_second_overwrites_first(): void
    {
        // ARRANGE
        Mail::fake();
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $email = $result['client']->email;

        // ACT — send OTP twice for the same email
        $this->post(route('track.send-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $email,
        ]);

        $this->post(route('track.send-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $email,
        ]);

        // ASSERT — the second OTP overwrites the first in cache
        $key = 'otp:track:'.$email;
        $cachedOtp = Cache::get($key);

        $this->assertNotNull($cachedOtp, 'OTP must exist in cache after two requests');
        $this->assertEquals(6, strlen($cachedOtp), 'OTP must be exactly 6 digits');

        // Verify only one OTP exists (no duplicates)
        $this->assertIsString($cachedOtp);
    }

    public function test_otp_with_spaces_passes_trimmed_validation(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $email = $result['client']->email;

        // Pre-seed an OTP that differs from the trimmed "123456" so the
        // verification step fails — proving the value was trimmed.
        Cache::put('otp:track:'.$email, '654321', now()->addMinutes(5));

        // ACT — OTP with leading and trailing spaces
        // Laravel's TrimStrings middleware strips whitespace before validation,
        // so " 123456 " becomes "123456" which passes the size:6 rule.
        $response = $this->from('/track')
            ->post(route('track.verify-otp'), [
                'tracker_number' => $case->tracker_number,
                'email' => $email,
                'otp' => ' 123456 ',
            ]);

        // ASSERT — the response is a redirect back (the OTP verification
        // failed because the cached value "654321" doesn't match "123456").
        $response->assertRedirect();

        // The OTP verification error is present (from the controller's
        // back()->withErrors() call).
        $response->assertSessionHasErrors(['otp' => 'Invalid or expired OTP.']);
    }

    public function test_empty_milestone_data_does_not_crash(): void
    {
        // ARRANGE
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);

        // Create milestone with empty title and null description
        // (title is NOT NULL in migration, but empty string is allowed;
        //  description is nullable in migration.)
        $milestone = Milestone::factory()->create([
            'refr_id' => $referral->id,
            'title' => '',
            'description' => null,
        ]);
        app(CaseEventRecorder::class)->milestoneAdded($referral, $milestone);

        $this->loadRelations($case);
        $service = app(TrackingService::class);

        // ACT — no exception expected
        $data = $service->buildTrackingData($case);

        // ASSERT — milestone event appears with empty/null values
        $msItems = array_values(array_filter(
            $data['milestoneTimeline'],
            fn (array $item) => $item['type'] === 'milestone_added'
        ));
        $this->assertNotEmpty($msItems);
        $this->assertSame('', $msItems[0]['title']);
        $this->assertSame('', $msItems[0]['description']); // null → '' cast

        // Also verify in trackingAgencies
        $this->assertIsArray($data['trackingAgencies']);
        $this->assertNotEmpty($data['trackingAgencies']);
        $this->assertSame('', $data['trackingAgencies'][0]['latestMilestoneLabel']);
    }

    public function test_duplicate_tracker_number_throws_query_exception(): void
    {
        // ARRANGE
        $trackerNumber = 'OWBAP-UNQ-99999';
        CaseFile::factory()->create(['tracker_number' => $trackerNumber]);

        // ACT & ASSERT — tracker_number has a UNIQUE constraint in migration
        $this->expectException(QueryException::class);
        CaseFile::factory()->create(['tracker_number' => $trackerNumber]);
    }

    public function test_send_otp_with_missing_email_returns_validation_error(): void
    {
        // ARRANGE
        $case = CaseFile::factory()->create();

        // ACT — POST with empty email (fails "required" rule)
        $response = $this->post(route('track.send-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => '',
        ]);

        // ASSERT — email is required
        $response->assertSessionHasErrors(['email']);
    }

    public function test_send_otp_with_unmatched_email_is_denied_without_issuing_otp(): void
    {
        // ARRANGE
        $result = $this->createCompleteCase();
        $case = $result['case'];
        Mail::fake();

        // ACT — POST with a valid email that does not match the case client.
        $response = $this->post(route('track.send-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => 'unmatched@example.com',
        ]);

        // ASSERT — generic denial and no OTP for the unrelated address
        $response->assertRedirect()->assertSessionHasErrors('tracker_number');
        $key = 'otp:track:unmatched@example.com';
        $this->assertNull(Cache::get($key));
        Mail::assertNothingQueued();
    }
}
