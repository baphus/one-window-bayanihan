<?php

namespace Database\Factories;

use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaseFileFactory extends Factory
{
    protected $model = CaseFile::class;

    public function definition(): array
    {
        return [
            // Matches CaseNumberGenerator: OWB-{YEAR}{MONTH}-{NNNNN}.
            'case_number' => 'OWB-'.now()->format('Ym').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            // Matches CaseNumberGenerator: Crockford base32, 10 characters.
            'tracker_number' => 'OWBAP-'.collect(range(1, 10))
                ->map(fn () => '0123456789ABCDEFGHJKMNPQRSTVWXYZ'[random_int(0, 31)])
                ->implode(''),
            'vulnerability_indicator' => $this->faker->randomElement(['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None', null]),
            'summary' => $this->faker->sentence(),
            'status' => 'OPEN',
            'user_id' => User::factory(),
            'is_deleted' => false,
        ];
    }

    public function withIntake(): static
    {
        return $this->state(fn () => [
            'client_type' => 'OFW',
            'intake_date' => '2026-11-10',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1990-06-15',
            'sex' => 'MALE',
            'email' => 'juan@example.test',
            'contact_number' => '09171234567',
            'civil_status' => 'SINGLE',
            'deployment_mode' => 'AGENCY_HIRED',
            'employment_status' => 'RETURNED',
            'address_region' => 'Central Visayas',
            'address_city_municipality' => 'Cebu City',
            'address_barangay' => 'Lahug',
            'address_street' => '123 Example Street',
            'employment_employer_name' => 'Example Employer',
            'employment_employer_address' => 'Example Employer Address',
            'return_reason' => 'OTHERS',
            'employment_position' => 'Engineer',
            'employment_country' => 'Japan',
            'employment_last_country' => 'Japan',
            'employment_last_position' => 'Engineer',
            'employment_start_date' => '2020-01-01',
            'employment_end_date' => '2026-10-01',
            'employment_date_of_arrival' => '2026-10-02',
            'proposed_assessments' => ['LEGAL_ASSISTANCE'],
        ]);
    }

    public function open(): static
    {
        return $this->state(['status' => 'OPEN']);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'CLOSED', 'closed_at' => now()]);
    }

    public function draft(): static
    {
        return $this->state(['status' => 'DRAFT']);
    }

    public function pendingReview(): static
    {
        return $this->state(['status' => CaseFile::STATUS_PENDING_REVIEW]);
    }

    public function archived(): static
    {
        return $this->state(['status' => 'ARCHIVED']);
    }
}
