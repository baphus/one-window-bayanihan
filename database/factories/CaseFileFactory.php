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
            'case_number' => 'OWB-'.now()->format('Y').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            // Matches CaseNumberGenerator: Crockford base32, 10 characters.
            'tracker_number' => 'OWBAP-'.collect(range(1, 10))
                ->map(fn () => '0123456789ABCDEFGHJKMNPQRSTVWXYZ'[random_int(0, 31)])
                ->implode(''),
            'client_type' => CaseFile::CLIENT_TYPE_OFW,
            'vulnerability_indicator' => $this->faker->randomElement(['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None', null]),
            'summary' => $this->faker->sentence(),
            'status' => 'OPEN',
            'user_id' => User::factory(),
            'is_deleted' => false,
        ];
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

    public function archived(): static
    {
        return $this->state(['status' => 'ARCHIVED']);
    }
}
