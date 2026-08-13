<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Models\User;
use App\Services\TrackingService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\TrackingService\Traits\CreatesTrackingCase;
use Tests\TestCase;

class TrackRegistrationTest extends TestCase
{
    use CreatesTrackingCase;
    use RefreshDatabase;

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

    #[Test]
    public function test_creates_ofw_account_linked_to_case_client_and_logs_in(): void
    {
        $email = 'ofw-track@example.com';
        $trackerNumber = $this->buildTrackerNumber();

        $case = $this->createCompleteCase();
        $case['case']->update(['tracker_number' => $trackerNumber]);
        $case['client']->update(['email' => $email]);

        $response = $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => $trackerNumber,
                'email' => $email,
            ],
        ])->postJson(route('track.register'), [
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'Str0ngPass!123',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect' => route('ofw.dashboard'),
        ]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('OFW', $user->role);
        $this->assertEquals($case['client']->id, $user->client_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue(Hash::check('Str0ngPass!123', $user->password));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function test_existing_ofw_account_is_logged_in_again_without_duplicate(): void
    {
        $email = 'returning-track@example.com';
        $trackerNumber = $this->buildTrackerNumber();

        $case = $this->createCompleteCase();
        $case['case']->update(['tracker_number' => $trackerNumber]);
        $case['client']->update(['email' => $email]);

        $existing = User::factory()->create([
            'email' => $email,
            'role' => 'OFW',
            'client_id' => $case['client']->id,
        ]);

        $response = $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => $trackerNumber,
                'email' => $email,
            ],
        ])->postJson(route('track.register'), [
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'Str0ngPass!123',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertEquals(1, User::where('email', $email)->count());
        $this->assertAuthenticatedAs($existing);
    }

    #[Test]
    public function test_requires_verified_session_binding(): void
    {
        $response = $this->postJson(route('track.register'), [
            'password' => 'Str0ngPass!123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'Session expired. Please verify your case again.',
        ]);

        $this->assertGuest();
    }

    #[Test]
    public function test_rejects_weak_password(): void
    {
        $trackerNumber = $this->buildTrackerNumber();

        $case = $this->createCompleteCase();
        $case['case']->update(['tracker_number' => $trackerNumber]);

        $response = $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => $trackerNumber,
                'email' => 'weak-pass@example.com',
            ],
        ])->postJson(route('track.register'), [
            'password' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');

        $this->assertGuest();
        $this->assertEquals(0, User::where('email', 'weak-pass@example.com')->count());
    }

    #[Test]
    public function test_rejects_mismatched_password_confirmation(): void
    {
        $email = 'mismatch-track@example.com';
        $trackerNumber = $this->buildTrackerNumber();

        $case = $this->createCompleteCase();
        $case['case']->update(['tracker_number' => $trackerNumber]);
        $case['client']->update(['email' => $email]);

        $response = $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => $trackerNumber,
                'email' => $email,
            ],
        ])->postJson(route('track.register'), [
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'DifferentPass!123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');

        $this->assertGuest();
        $this->assertEquals(0, User::where('email', $email)->count());
    }

    #[Test]
    public function test_rejects_unknown_tracker_number(): void
    {
        $response = $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => 'OWBAP-NOTFOUND',
                'email' => 'ghost@example.com',
            ],
        ])->postJson(route('track.register'), [
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'Str0ngPass!123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'Unable to process request. Please check your details and try again.',
        ]);

        $this->assertGuest();
        $this->assertEquals(0, User::where('email', 'ghost@example.com')->count());
    }
}
