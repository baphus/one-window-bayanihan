<?php

namespace Tests\Feature\TrackController;

use App\Mail\OtpMail;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendOtpTest extends TestCase
{
    use RefreshDatabase;

    private string $email;

    private string $trackerNumber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email = 'test@example.com';
        $client = Client::factory()->create(['email' => $this->email]);
        $user = User::factory()->create();
        $case = CaseFile::factory()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);
        $this->trackerNumber = $case->tracker_number;
    }

    public function test_valid_request_returns_verify_page(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $response = $this->post(route('track.send-otp'), [
            'tracker_number' => $this->trackerNumber,
            'email' => $this->email,
        ]);

        $hint = 'te**@example.com';

        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/Verify')
            ->where('tracker_number', $this->trackerNumber)
            ->where('email', $this->email)
            ->where('hint', $hint)
            ->where('debug_otp', null)
        );
    }

    public function test_invalid_tracker_number_returns_error(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $response = $this->post(route('track.send-otp'), [
            'tracker_number' => 'NONEXISTENT-123',
            'email' => $this->email,
        ]);

        $response->assertSessionHasErrors('tracker_number');
    }

    public function test_invalid_email_format_returns_error(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $response = $this->post(route('track.send-otp'), [
            'tracker_number' => $this->trackerNumber,
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_missing_fields_returns_errors(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $response = $this->post(route('track.send-otp'), []);

        $response->assertSessionHasErrors(['tracker_number', 'email']);
    }

    public function test_otp_sent_via_email(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $this->post(route('track.send-otp'), [
            'tracker_number' => $this->trackerNumber,
            'email' => $this->email,
        ]);

        Mail::assertQueued(OtpMail::class, fn ($mail) => $mail->hasTo($this->email));
    }

    public function test_otp_stored_in_cache(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $this->post(route('track.send-otp'), [
            'tracker_number' => $this->trackerNumber,
            'email' => $this->email,
        ]);

        $cachedOtp = Cache::get("otp:track:{$this->email}");
        $this->assertNotNull($cachedOtp);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $cachedOtp);
    }

    public function test_hint_masks_email_correctly(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $response = $this->post(route('track.send-otp'), [
            'tracker_number' => $this->trackerNumber,
            'email' => $this->email,
        ]);

        $hint = 'te**@example.com';

        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/Verify')
            ->where('hint', $hint)
        );
    }

    public function test_otp_has_five_minute_ttl(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $this->post(route('track.send-otp'), [
            'tracker_number' => $this->trackerNumber,
            'email' => $this->email,
        ]);

        // FR-PORT-003: Verify OTP has 5-min TTL (OtpService default)
        $cachedOtp = Cache::get("otp:track:{$this->email}");
        $this->assertNotNull($cachedOtp);

        // Inspect the ArrayStore's internal expiration timestamp
        $store = Cache::getStore();
        $all = $store->all();
        $key = "otp:track:{$this->email}";
        $this->assertArrayHasKey($key, $all);

        $expiresAt = $all[$key]['expiresAt'];
        $this->assertNotNull($expiresAt);

        // expiresAt is a Unix timestamp: verify it's ~5 minutes from now
        $this->assertGreaterThan(now()->getTimestamp(), $expiresAt);
        $this->assertLessThanOrEqual(now()->addMinutes(6)->getTimestamp(), $expiresAt);
    }
}
