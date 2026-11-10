<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfwRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_registration_page(): void
    {
        $response = $this->get(route('register'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_guest_can_start_two_step_registration(): void
    {
        $response = $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
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
        ]);

        $response->assertRedirect(route('register.review'));
        $response->assertSessionHas('pending_registration');
        $response->assertSessionHas('pending_registration.profile');
        $this->assertGuest();
        $this->assertNull(User::where('email', 'juan@example.com')->first());
    }

    public function test_registration_defaults_type_to_ofw_and_rejects_nok_or_invalid_type(): void
    {
        $payload = [
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'juan@example.com',
            'password' => 'Password123', 'password_confirmation' => 'Password123',
            'date_of_birth' => '1995-03-15',
            'address' => ['region' => '0700000000', 'city_municipality' => 'Cebu City', 'barangay' => 'Lahug'],
            'employment' => ['employer_name' => 'Employer', 'start_date' => '2010-04-01', 'is_present' => true, 'last_country' => 'Philippines', 'last_position' => 'Worker', 'date_of_arrival' => '1995-05-01'],
        ];

        $this->post(route('register.store'), $payload)->assertRedirect(route('register.review'));
        $this->assertSame('OFW', session('pending_registration.profile.type'));

        foreach (['NEXT_OF_KIN', 'INVALID'] as $type) {
            $this->post(route('register.store'), [...$payload, 'type' => $type])->assertSessionHasErrors('type');
        }
    }

    public function test_stale_nok_registration_session_is_cleared_before_review_persistence(): void
    {
        $pending = [
            'credentials' => ['first_name' => 'Nok', 'last_name' => 'Account', 'email' => 'nok@example.test', 'password' => bcrypt('Password123')],
            'profile' => ['type' => 'NEXT_OF_KIN'],
        ];

        $response = $this->withSession(['pending_registration' => $pending])
            ->post(route('register.review.store'), ['otp' => '123456']);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('registration');
        $response->assertSessionMissing('pending_registration');
        $this->assertDatabaseMissing('users', ['email' => 'nok@example.test']);
    }

    public function test_registration_accepts_a_duplicate_email_at_step_one(): void
    {
        // Duplicate emails are rejected at the review step, not step 1, so a
        // user is not bounced back to a long form at the last second.
        User::factory()->create(['email' => 'juan@example.com', 'role' => UserRole::OFW->value]);

        $response = $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
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
        ]);

        $response->assertRedirect(route('register.review'));
        $response->assertSessionHasNoErrors();
    }

    public function test_registration_requires_valid_password(): void
    {
        $response = $this->post(route('register.store'), [
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'email' => 'juan@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_authenticated_user_cannot_access_registration(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAsWithMfa($user);

        $response = $this->get(route('register'));
        $response->assertRedirect();
    }

    public function test_ofw_cannot_access_registration_review_step(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAsWithMfa($user);

        $response = $this->get(route('register.review'));
        $response->assertRedirect();
    }
}
