<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\OtpMail;
use App\Models\Client;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([ThrottleRequests::class]);
    }

    // ── Step 1: POST /register ──────────────────────────────────────────────

    public function test_step1_stores_credentials_and_profile_in_session_without_creating_user(): void
    {
        $response = $this->postJson(route('register.store'), [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'juan-test@example.test',
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'Str0ngPass!123',
        ] + $this->stepOneProfile());

        $response->assertRedirect(route('register.review'));

        $this->assertGuest();
        $this->assertNull(User::where('email', 'juan-test@example.test')->first());

        // No code is issued at step 1 any more — it is sent from the review step.
        $this->assertFalse(Cache::has('otp:register:juan-test@example.test'));
        Mail::assertNothingQueued();

        $credentials = session('pending_registration.credentials');
        $this->assertSame('Juan', $credentials['first_name']);
        $this->assertSame('Santos', $credentials['middle_name']);
        $this->assertSame('Dela Cruz', $credentials['last_name']);

        $profile = session('pending_registration.profile');
        $this->assertSame('OFW', $profile['type']);
        $this->assertSame('1995-03-15', $profile['date_of_birth']);
        $this->assertSame('Cebu City', $profile['address']['city_municipality']);
        $this->assertSame('Test Employer', $profile['employment']['employer_name']);
    }

    public function test_step1_requires_profile_fields(): void
    {
        $response = $this->postJson(route('register.store'), [
            'first_name' => 'No',
            'last_name' => 'Profile',
            'email' => 'noprofile@example.test',
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'Str0ngPass!123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'type',
            'date_of_birth',
            'address.region',
            'address.city_municipality',
            'address.barangay',
            'employment.employer_name',
            'employment.start_date',
            'employment.last_country',
            'employment.last_position',
            'employment.date_of_arrival',
        ]);
        $this->assertNull(session('pending_registration'));
    }

    public function test_step1_does_not_reject_a_duplicate_email(): void
    {
        // Uniqueness is re-checked at review time so a user is not bounced back
        // to a long form after spending 5 minutes on it.
        User::factory()->create(['email' => 'dup-late@example.test', 'role' => UserRole::OFW->value]);

        $this->postJson(route('register.store'), [
            'first_name' => 'Dup',
            'last_name' => 'Late',
            'email' => 'dup-late@example.test',
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'Str0ngPass!123',
        ] + $this->stepOneProfile())->assertRedirect(route('register.review'));
    }

    public function test_step1_422_on_weak_password(): void
    {
        $response = $this->postJson(route('register.store'), [
            'first_name' => 'Weak',
            'last_name' => 'Pass',
            'email' => 'weak@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ] + $this->stepOneProfile());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    // ── Step 2: review step ─────────────────────────────────────────────────

    public function test_review_step_renders_saved_profile(): void
    {
        $pending = $this->preparePendingRegistration('review-view@example.test', 'Juan', 'Santos', 'Cruz');

        $this->withSession(['pending_registration' => $pending])
            ->get(route('register.review'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/RegisterReview')
                ->where('pendingName.first_name', 'Juan')
                ->where('pending.employment.employer_name', 'Test Employer')
                ->where('pending.address.city_municipality', 'Cebu City'));
    }

    public function test_review_step_sends_the_verification_code(): void
    {
        $email = 'review-send@example.test';
        $pending = $this->preparePendingRegistration($email);
        // preparePendingRegistration() already issued one code; start clean.
        Cache::forget('otp:register:'.$email);
        Mail::fake();

        $this->withSession(['pending_registration' => $pending])
            ->from(route('register.review'))
            ->postJson(route('register.review.send-otp'))
            ->assertRedirect(route('register.review'));

        $this->assertTrue(Cache::has('otp:register:'.$email));
        Mail::assertQueued(OtpMail::class);
    }

    public function test_send_code_requires_pending_registration(): void
    {
        $this->postJson(route('register.review.send-otp'))
            ->assertRedirect(route('register'));
    }

    public function test_send_code_issues_a_register_subject_email(): void
    {
        $email = 'review-subject@example.test';
        $pending = $this->preparePendingRegistration($email);
        Cache::forget('otp:register:'.$email);

        $this->withSession(['pending_registration' => $pending])
            ->from(route('register.review'))
            ->post(route('register.review.send-otp'));

        Mail::assertQueued(OtpMail::class, fn (OtpMail $mail) => $mail->purpose === 'register'
            && $mail->envelope()->subject === 'Verify your One Window Bayanihan email');
    }

    public function test_review_step_ignores_smuggled_profile_fields(): void
    {
        $pending = $this->preparePendingRegistration('smuggle@example.test', 'Juan', 'Santos', 'Cruz');

        $this->withSession(['pending_registration' => $pending])
            ->post(route('register.review.store'), [
                'otp' => $pending['otp'],
                // Attacker-supplied identity + profile data — must be ignored.
                'first_name' => 'Injected',
                'type' => 'NEXT_OF_KIN',
                'employment' => ['employer_name' => 'Injected Employer'],
            ])
            ->assertRedirect(route('ofw.intake'));

        $user = User::where('email', 'smuggle@example.test')->sole();
        $this->assertSame('Juan Santos Cruz', $user->name);

        $client = Client::find($user->client_id);
        $this->assertSame('Juan', $client->first_name);
        $this->assertSame('OFW', $client->type);
        // employer_name is encrypted at rest, so assert through the model cast.
        $this->assertSame('Test Employer', $client->employments()->orderBy('created_at')->first()->employer_name);
    }

    public function test_review_step_creates_user_ofw_with_linked_ofw_client(): void
    {
        $pending = $this->preparePendingRegistration('ofw-one@example.test', 'Juan', 'Santos', 'Cruz');
        $userCount = User::count();

        $response = $this->withSession(['pending_registration' => $pending])
            ->post(route('register.review.store'), ['otp' => $pending['otp']]);

        // Authenticated after step 2 + redirect to intake filing.
        $response->assertRedirect(route('ofw.intake'));
        $this->assertAuthenticated();

        $user = User::where('email', 'ofw-one@example.test')->sole();
        $this->assertSame(UserRole::OFW->value, $user->role);
        $this->assertSame('Juan Santos Cruz', $user->name);
        $this->assertTrue(Hash::check('Str0ngPass!123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue((bool) $user->is_active);
        $this->assertNotNull($user->profile_completed_at);

        $this->assertDatabaseHas('clients', [
            'id' => $user->client_id,
            'type' => 'OFW',
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'middle_name' => 'Santos',
            'email' => 'ofw-one@example.test',
        ]);
        $this->assertEquals($userCount + 1, User::count());
    }

    public function test_review_step_creates_user_with_next_of_kin_type(): void
    {
        $pending = $this->preparePendingRegistration('nok-one@example.test', 'Maria', null, 'Reyes');
        $pending['profile']['type'] = 'NEXT_OF_KIN';
        $pending['profile']['sex'] = 'Female';

        $this->withSession(['pending_registration' => $pending])
            ->post(route('register.review.store'), ['otp' => $pending['otp']]);

        $user = User::where('email', 'nok-one@example.test')->sole();
        $this->assertSame('Maria Reyes', $user->name);
        $this->assertSame(UserRole::OFW->value, $user->role); // role stays OFW; type is on client.
        $this->assertDatabaseHas('clients', [
            'id' => $user->client_id,
            'type' => 'NEXT_OF_KIN',
            'first_name' => 'Maria',
            'last_name' => 'Reyes',
        ]);
    }

    public function test_review_step_rejects_wrong_otp(): void
    {
        $pending = $this->preparePendingRegistration('otp-wrong@example.test');
        $wrongOtp = $pending['otp'] === '000000' ? '111111' : '000000';

        $response = $this->withSession(['pending_registration' => $pending])
            ->post(route('register.review.store'), ['otp' => $wrongOtp]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['otp']);
        $this->assertGuest();
        $this->assertNull(User::where('email', 'otp-wrong@example.test')->first());
    }

    /**
     * Regression for the lost-race dead end: verify() consumes the code before
     * the registration transaction runs, so a rolled-back transaction (here the
     * `users_email_unique` violation the step-1 check could not yet see) used to
     * leave pending_registration pointing at a dead code — every retry answered
     * "Invalid or expired code" until the whole wizard restarted. The same code
     * must still be accepted on the next attempt — unless the profile re-check
     * itself rejects the duplicate first, which is why this test also asserts
     * the code survives that path.
     */
    public function test_review_step_failed_transaction_keeps_the_otp_usable_for_the_retry(): void
    {
        $pending = $this->preparePendingRegistration('race@example.test');

        // Another registration lands between step 1 and step 2, so the unique
        // constraint trips inside the transaction.
        User::factory()->create(['email' => 'race@example.test', 'role' => UserRole::OFW->value]);

        $response = $this->withSession(['pending_registration' => $pending])
            ->from(route('register.review'))
            ->post(route('register.review.store'), ['otp' => $pending['otp']]);

        $response->assertRedirect(route('register.review'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'race@example.test')->count(), 'The failed transaction must not leave a partial user behind.');

        // The code verify() consumed is back in the cache, unchanged.
        $this->assertTrue(Cache::has('otp:register:race@example.test'));
        $this->assertSame($pending['otp'], Cache::get('otp:register:race@example.test'));

        // Clear the blocker and resubmit the very same code.
        User::withTrashed()->where('email', 'race@example.test')->forceDelete();

        $retry = $this->withSession(['pending_registration' => $pending])
            ->post(route('register.review.store'), ['otp' => $pending['otp']]);

        $retry->assertRedirect(route('ofw.intake'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'race@example.test']);
        $this->assertFalse(Cache::has('otp:register:race@example.test'), 'A committed registration still consumes the code.');
    }

    public function test_review_step_rejects_a_duplicate_email(): void
    {
        $pending = $this->preparePendingRegistration('dup-review@example.test');
        User::factory()->create(['email' => 'dup-review@example.test', 'role' => UserRole::OFW->value]);

        $this->withSession(['pending_registration' => $pending])
            ->from(route('register.review'))
            ->post(route('register.review.store'), ['otp' => $pending['otp']])
            ->assertRedirect(route('register.review'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_review_step_guest_redirects_to_register_without_session(): void
    {
        // No pending_registration in session → should be redirected back to register.
        $response = $this->post(route('register.review.store'), ['otp' => '123456']);

        // Middleware redirects guest to /register.
        $response->assertRedirect(route('register'));
        $this->assertGuest();
    }

    /**
     * Prime step-1 session with a real OTP captured from the generated cache entry.
     */
    private function preparePendingRegistration(
        string $email,
        string $firstName = 'Test',
        ?string $middleName = null,
        string $lastName = 'Registrant',
    ): array {
        $otp = app(OtpService::class)->generate($email, 'register');

        return [
            'credentials' => [
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make('Str0ngPass!123'),
            ],
            'profile' => $this->stepOneProfile(),
            'otp' => $otp, // pass through so tests can read it
        ];
    }

    /**
     * The profile half of the step-1 payload.
     */
    private function stepOneProfile(): array
    {
        return [
            'type' => 'OFW',
            'suffix' => '',
            'date_of_birth' => '1995-03-15',
            'sex' => 'Male',
            'contact_number' => '+639171234567',
            'address' => [
                'region' => '0700000000',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
                'street' => 'Test Street',
            ],
            'employment' => [
                'employer_name' => 'Test Employer',
                'position' => 'Test Position',
                'country' => 'Philippines',
                'start_date' => '2010-04-01',
                'end_date' => null,
                'is_present' => true,
                'last_country' => 'Philippines',
                'last_position' => 'Test Last Position',
                'date_of_arrival' => '1995-05-01',
            ],
        ];
    }
}
