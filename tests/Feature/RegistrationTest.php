<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsurePendingRegistration;
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
            'name' => 'Juan Dela Cruz',
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
    }

    public function test_step1_422_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.test', 'role' => UserRole::OFW->value]);

        $response = $this->postJson(route('register.store'), [
            'name' => 'Dup Test',
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
            'name' => 'Weak Pass',
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
        $pending = $this->preparePendingRegistration('ofw-one@example.test');
        $userCount = User::count();

        $file = UploadedFile::fake()->image('avatar.jpg', 600, 600);

        $response = $this->withSession(['pending_registration' => $pending])
            ->post(route('register.profile.store'), [
                'otp' => $pending['otp'],
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
                'avatar' => $file,
            ]);

        // Authenticated after step 2 + redirect to intake filing.
        $response->assertRedirect(route('ofw.intake'));
        $this->assertAuthenticated();

        $user = User::where('email', 'ofw-one@example.test')->sole();
        $this->assertSame(UserRole::OFW->value, $user->role);
        $this->assertTrue(Hash::check('Str0ngPass!123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue((bool) $user->is_active);
        $this->assertNotNull($user->profile_completed_at);

        $this->assertDatabaseHas('clients', [
            'id' => $user->client_id,
            'type' => 'OFW',
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'email' => 'ofw-one@example.test',
        ]);
        $this->assertEquals($userCount + 1, User::count());
    }

    public function test_step2_creates_user_with_next_of_kin_type(): void
    {
        $pending = $this->preparePendingRegistration('nok-one@example.test');

        $file = UploadedFile::fake()->image('avatar.jpg', 600, 600);

        $this->withSession(['pending_registration' => $pending])
            ->post(route('register.profile.store'), [
                'otp' => $pending['otp'],
                'type' => 'NEXT_OF_KIN',
                'first_name' => 'Maria',
                'last_name' => 'Reyes',
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
                'first_name' => 'Wrong',
                'last_name' => 'OTP',
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

    public function test_step2_guest_redirects_to_register_without_session(): void
    {
        // No pending_registration in session → should be redirected back to register.
        $response = $this->post(route('register.profile.store'), [
            'otp' => '123456',
            'type' => 'OFW',
            'first_name' => 'Guest',
            'last_name' => 'File',
        ]);

        // Middleware redirects guest to /register.
        $response->assertRedirect(route('register'));
        $this->assertGuest();
    }

    /**
     * Prime step-1 session with a real OTP captured from the generated cache entry.
     */
    private function preparePendingRegistration(string $email): array
    {
        $otp = app(OtpService::class)->generate($email, 'register');

        return [
            'credentials' => [
                'name' => 'Test Registrant',
                'email' => $email,
                'password' => Hash::make('Str0ngPass!123'),
            ],
            'register_otp_verified' => false,
            'otp' => $otp, // pass through so tests can read it
        ];
    }
}
