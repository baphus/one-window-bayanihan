<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
