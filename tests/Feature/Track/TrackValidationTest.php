<?php

namespace Tests\Feature\Track;

use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseFile;
use App\Services\TrackingService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class TrackValidationTest extends TestCase
{
    use CreatesTrackingCase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([
            VerifyTurnstile::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
        ]);
    }

    public function test_send_otp_rejects_empty_payload(): void
    {
        $response = $this->post(route('track.send-otp'), []);

        $response->assertSessionHasErrors(['tracker_number', 'email']);
    }

    public function test_send_otp_rejects_invalid_email_shape(): void
    {
        $case = CaseFile::factory()->create();

        $response = $this->post(route('track.send-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_send_otp_returns_422_json_with_same_shape(): void
    {
        $response = $this->postJson(route('track.send-otp'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tracker_number', 'email']);
    }

    public function test_send_otp_accepts_valid_payload(): void
    {
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $email = strtolower($result['client']->email);

        $response = $this->post(route('track.send-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $email,
        ]);

        $response->assertOk();
        $this->assertNotNull(Cache::get('otp:track:'.$email));
    }

    public function test_verify_otp_rejects_empty_payload(): void
    {
        $response = $this->post(route('track.verify-otp'), []);

        $response->assertSessionHasErrors(['tracker_number', 'email', 'otp']);
    }

    public function test_verify_otp_rejects_malformed_otp(): void
    {
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $email = strtolower($result['client']->email);

        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $email,
            'otp' => '123',
        ]);

        $response->assertSessionHasErrors(['otp']);
    }

    public function test_verify_otp_returns_422_json_with_same_shape(): void
    {
        $response = $this->postJson(route('track.verify-otp'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tracker_number', 'email', 'otp']);
    }

    public function test_verify_otp_accepts_valid_payload(): void
    {
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $email = strtolower($result['client']->email);
        Cache::put('otp:track:'.$email, '123456', now()->addMinutes(5));

        $response = $this->post(route('track.verify-otp'), [
            'tracker_number' => $case->tracker_number,
            'email' => $email,
            'otp' => '123456',
        ]);

        $response->assertRedirect(route('track.show', ['tracker_number' => $case->tracker_number]));

        $binding = session(TrackingService::SESSION_KEY);
        $this->assertSame($case->tracker_number, $binding['tracker_number']);
        $this->assertSame($email, $binding['email']);
    }

    public function test_show_rejects_missing_tracker_number(): void
    {
        // TrackController::show retains its single-field GET query guard
        // (tracker_number required|string); neither new Form Request matches
        // that query-only shape, so it stays inline by design.
        $response = $this->get(route('track.show'));

        $response->assertSessionHasErrors(['tracker_number']);
    }

    public function test_show_accepts_valid_tracker_with_session(): void
    {
        $result = $this->createCompleteCase();
        $case = $result['case'];
        $email = strtolower($result['client']->email);

        $response = $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => $case->tracker_number,
                'email' => $email,
            ],
        ])->get(route('track.show', ['tracker_number' => $case->tracker_number]));

        $response->assertOk();
    }
}
