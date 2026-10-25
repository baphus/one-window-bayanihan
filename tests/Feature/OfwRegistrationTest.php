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
        $this->actingAs($user);

        $response = $this->get(route('register'));
        $response->assertRedirect();
    }

    public function test_ofw_cannot_access_registration_review_step(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAs($user);

        $response = $this->get(route('register.review'));
        $response->assertRedirect();
    }
}
