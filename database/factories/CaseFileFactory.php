<?php

namespace Database\Factories;

use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CaseFileFactory extends Factory
{
    protected $model = CaseFile::class;

    public function definition(): array
    {
        return [
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.$this->faker->unique()->randomNumber(4),
            'tracker_number' => 'OWBAP-'.strtoupper(Str::random(7)),
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

    public function archived(): static
    {
        return $this->state(['status' => 'ARCHIVED']);
    }
}
