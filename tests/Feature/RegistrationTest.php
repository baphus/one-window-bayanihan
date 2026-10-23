<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsurePendingRegistration;
use App\Mail\OtpMail;
use App\Models\Client;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([ThrottleRequests::class]);
        // Workaround for missing import in bootstrap/app.php:75 (reported as
        // blocker): re-register the alias with its FQCN so the middleware
        // under test actually resolves. No source files touched.
        Route::aliasMiddleware('pending.registration', EnsurePendingRegistration::class);
    }

    // ── Step 1: POST /register ──────────────────────────────────────────────

    public function test_step1_stores_credentials_in_session_without_creating_user(): void
    {
        $response = $this->postJson(route('register.store'), [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'juan-test@example.test',
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'Str0ngPass!123',
        ]);

        // Step 1 redirects to the profile page.
        $response->assertRedirect(route('register.profile'));

        // No User row yet; OTP was generated and session holds pending data.
        $this->assertGuest();
        $this->assertNull(User::where('email', 'juan-test@example.test')->first());
        $this->assertTrue(Cache::has('otp:register:juan-test@example.test'));

        $credentials = session('pending_registration.credentials');
        $this->assertSame('Juan', $credentials['first_name']);
        $this->assertSame('Santos', $credentials['middle_name']);
        $this->assertSame('Dela Cruz', $credentials['last_name']);
    }

    public function test_pending_registration_can_resend_email_verification_code(): void
    {
        $email = 'juan-test@example.test';

        $this->withSession(['pending_registration' => [
            'credentials' => ['email' => $email],
            'register_otp_verified' => false,
        ]])->postJson(route('register.profile.resend-otp'))
            ->assertRedirect();

        $this->assertTrue(Cache::has('otp:register:'.$email));
        Mail::assertQueued(OtpMail::class);
    }

    public function test_resend_code_requires_pending_registration(): void
    {
        $this->postJson(route('register.profile.resend-otp'))
            ->assertRedirect(route('register'));
    }

    public function test_step1_422_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.test', 'role' => UserRole::OFW->value]);

        $response = $this->postJson(route('register.store'), [
            'first_name' => 'Dup',
            'last_name' => 'Test',
            'email' => 'dup@example.test',
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'Str0ngPass!123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_step1_422_on_weak_password(): void
    {
        $response = $this->postJson(route('register.store'), [
            'first_name' => 'Weak',
            'last_name' => 'Pass',
            'email' => 'weak@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    // ── Step 2: POST /register/profile ──────────────────────────────────────

    public function test_step2_creates_user_ofw_with_linked_ofw_client(): void
    {
        $pending = $this->preparePendingRegistration('ofw-one@example.test', 'Juan', 'Santos', 'Cruz');
        $userCount = User::count();

        $file = UploadedFile::fake()->image('avatar.jpg', 600, 600);

        $response = $this->withSession(['pending_registration' => $pending])
            ->post(route('register.profile.store'), [
                'otp' => $pending['otp'],
                'type' => 'OFW',
                'date_of_birth' => '1995-03-15',
                'sex' => 'Male',
                'contact_number' => '+639171234567',
                'address' => [
                    'region' => '0700000000',
                    'city_municipality' => 'Cebu City',
                    'barangay' => 'Lahug',
                    'province' => 'Cebu',
                    'street' => 'Test Street',
                ],
                'employment' => [
                    'employer_name' => 'Test Employer',
                    'position' => 'Test Position',
                    'country' => 'Philippines',
                    'start_date' => '2010-04-01',
                    'is_present' => true,
                    'end_date' => null,
                    'last_country' => 'Philippines',
                    'last_position' => 'Test Last Position',
                    'date_of_arrival' => '1995-05-01',
                ],
                'avatar' => $file,
            ]);

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

    public function test_step2_creates_user_with_next_of_kin_type(): void
    {
        $pending = $this->preparePendingRegistration('nok-one@example.test', 'Maria', null, 'Reyes');

        $file = UploadedFile::fake()->image('avatar.jpg', 600, 600);

        $this->withSession(['pending_registration' => $pending])
            ->post(route('register.profile.store'), [
                'otp' => $pending['otp'],
                'type' => 'NEXT_OF_KIN',
                'date_of_birth' => '1995-03-15',
                'sex' => 'Female',
                'contact_number' => '+639171234567',
                'address' => [
                    'region' => '0700000000',
                    'city_municipality' => 'Cebu City',
                    'barangay' => 'Lahug',
                    'province' => 'Cebu',
                    'street' => 'Test Street',
                ],
                'employment' => [
                    'employer_name' => 'Test Employer',
                    'position' => 'Test Position',
                    'country' => 'Philippines',
                    'start_date' => '2010-04-01',
                    'is_present' => true,
                    'end_date' => null,
                    'last_country' => 'Philippines',
                    'last_position' => 'Test Last Position',
                    'date_of_arrival' => '1995-05-01',
                ],
                'avatar' => $file,
            ]);

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

    public function test_step2_rejects_wrong_otp_with_422(): void
    {
        $pending = $this->preparePendingRegistration('otp-wrong@example.test');
        $wrongOtp = $pending['otp'] === '000000' ? '111111' : '000000';

        $file = UploadedFile::fake()->image('avatar.jpg', 600, 600);

        $response = $this->withSession(['pending_registration' => $pending])
            ->post(route('register.profile.store'), [
                'otp' => $wrongOtp,
                'type' => 'OFW',
                'date_of_birth' => '1995-03-15',
                'sex' => 'Male',
                'contact_number' => '+639171234567',
                'address' => [
                    'region' => '0700000000',
                    'city_municipality' => 'Cebu City',
                    'barangay' => 'Lahug',
                    'province' => 'Cebu',
                    'street' => 'Test Street',
                ],
                'employment' => [
                    'employer_name' => 'Test Employer',
                    'position' => 'Test Position',
                    'country' => 'Philippines',
                    'start_date' => '2010-04-01',
                    'is_present' => true,
                    'end_date' => null,
                    'last_country' => 'Philippines',
                    'last_position' => 'Test Last Position',
                    'date_of_arrival' => '1995-05-01',
                ],
                'avatar' => $file,
            ]);

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
     * must still be accepted on the next attempt.
     */
    public function test_step2_failed_transaction_keeps_the_otp_usable_for_the_retry(): void
    {
        $pending = $this->preparePendingRegistration('race@example.test');

        // Another registration lands between step 1 and step 2, so the unique
        // constraint trips inside the transaction.
        User::factory()->create(['email' => 'race@example.test', 'role' => UserRole::OFW->value]);

        $response = $this->withSession(['pending_registration' => $pending])
            ->from(route('register.profile'))
            ->post(route('register.profile.store'), $this->profilePayload($pending['otp']));

        $response->assertRedirect(route('register.profile'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'race@example.test')->count(), 'The failed transaction must not leave a partial user behind.');

        // The code verify() consumed is back in the cache, unchanged.
        $this->assertTrue(Cache::has('otp:register:race@example.test'));
        $this->assertSame($pending['otp'], Cache::get('otp:register:race@example.test'));

        // Clear the blocker and resubmit the very same code.
        User::withTrashed()->where('email', 'race@example.test')->forceDelete();

        $retry = $this->withSession(['pending_registration' => $pending])
            ->post(route('register.profile.store'), $this->profilePayload($pending['otp']));

        $retry->assertRedirect(route('ofw.intake'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'race@example.test']);
        $this->assertFalse(Cache::has('otp:register:race@example.test'), 'A committed registration still consumes the code.');
    }

    public function test_step2_guest_redirects_to_register_without_session(): void
    {
        // No pending_registration in session → should be redirected back to register.
        $response = $this->post(route('register.profile.store'), [
            'otp' => '123456',
            'type' => 'OFW',
        ]);

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
            'register_otp_verified' => false,
            'otp' => $otp, // pass through so tests can read it
        ];
    }

    /**
     * Step-2 payload that clears validation, so any failure downstream comes
     * from the registration transaction itself.
     */
    private function profilePayload(string $otp): array
    {
        return [
            'otp' => $otp,
            'type' => 'OFW',
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'middle_name' => 'Santos',
            'date_of_birth' => '1995-03-15',
            'sex' => 'Male',
            'contact_number' => '+639171234567',
            'address' => [
                'region' => '0700000000',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
                'province' => 'Cebu',
                'street' => 'Test Street',
            ],
            'employment' => [
                'employer_name' => 'Test Employer',
                'position' => 'Test Position',
                'country' => 'Philippines',
                'start_date' => '2010-04-01',
                'is_present' => true,
                'end_date' => null,
                'last_country' => 'Philippines',
                'last_position' => 'Test Last Position',
                'date_of_arrival' => '1995-05-01',
            ],
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 600, 600),
        ];
    }
}
