<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\CloudinaryAvatarService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_screen_is_available(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
    }

    public function test_public_registration_submits_credentials_and_profile_then_redirects_to_review(): void
    {
        $response = $this->post('/register', $this->payload());

        $response->assertRedirect('/register/review');
        $response->assertSessionHas('pending_registration');
        $response->assertSessionHas('pending_registration.profile');

        // Step 1 does not create a user row yet — the review step does.
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_review_step_creates_the_account_with_an_optional_profile_picture(): void
    {
        $this->post('/register', $this->payload());

        $code = app(OtpService::class)->generate('test@example.com', 'register');

        $response = $this->post('/register/review', [
            'otp' => $code,
            'profile_picture' => UploadedFile::fake()->image('me.jpg', 160, 160),
        ]);

        $response->assertRedirect(route('ofw.intake'));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertNotNull($user->getRawOriginal('avatar_url'), 'The optional profile picture must be stored.');
        $this->assertStringContainsString('res.cloudinary.com', (string) $user->getRawOriginal('avatar_url'));
    }

    public function test_review_step_creates_the_account_without_a_profile_picture(): void
    {
        $this->post('/register', $this->payload());

        $code = app(OtpService::class)->generate('test@example.com', 'register');

        $response = $this->post('/register/review', ['otp' => $code]);

        $response->assertRedirect(route('ofw.intake'));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertNull($user->getRawOriginal('avatar_url'), 'The photo is optional; the account is still created.');
    }

    public function test_review_step_rejects_a_non_image_profile_picture(): void
    {
        $this->post('/register', $this->payload());

        $code = app(OtpService::class)->generate('test@example.com', 'register');

        $response = $this->post('/register/review', [
            'otp' => $code,
            'profile_picture' => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image'),
        ]);

        $response->assertSessionHasErrors('profile_picture');
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_review_step_registers_even_when_cloudinary_is_unavailable(): void
    {
        $this->app->bind(CloudinaryAvatarService::class, fn () => new class extends CloudinaryAvatarService
        {
            public function __construct() {}

            public function uploadImage(UploadedFile $file, string $folder, string $publicId): string
            {
                throw new \RuntimeException('The image could not be uploaded. Please try again later.');
            }

            public function deleteByUrl(?string $url): void {}
        });

        $this->post('/register', $this->payload());

        $code = app(OtpService::class)->generate('test@example.com', 'register');

        $response = $this->post('/register/review', [
            'otp' => $code,
            'profile_picture' => UploadedFile::fake()->image('me.jpg', 160, 160),
        ]);

        // A Cloudinary outage must not abort registration.
        $response->assertRedirect(route('ofw.intake'));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertNull($user->getRawOriginal('avatar_url'), 'The account is created without a photo.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'first_name' => 'Test',
            'middle_name' => 'A',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'P@ssw0rd!',
            'password_confirmation' => 'P@ssw0rd!',
            'type' => 'OFW',
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
                'start_date' => '2010-04-01',
                'is_present' => true,
                'last_country' => 'Philippines',
                'last_position' => 'Test Last Position',
                'date_of_arrival' => '1995-05-01',
            ],
        ];
    }
}
