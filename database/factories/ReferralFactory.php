<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        return [
            'required_services' => $this->faker->sentence(),
            'status' => 'PENDING',
            'case_id' => CaseFile::factory(),
            'agcy_id' => Agency::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'PENDING']);
    }

    public function processing(): static
    {
        return $this->state(['status' => 'PROCESSING']);
    }

    public function forCompliance(): static
    {
        return $this->state(['status' => 'FOR_COMPLIANCE']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'COMPLETED']);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'REJECTED']);
    }
}
