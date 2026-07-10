<?php

namespace Database\Factories;

use App\Helpers\DefaultServqualQuestions;
use App\Models\Agency;
use App\Models\ServqualConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServqualConfigFactory extends Factory
{
    protected $model = ServqualConfig::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => $this->faker->words(3, true).' Form',
            'service_id' => null,
            'service_name' => $this->faker->company.' Service',
            'questions' => DefaultServqualQuestions::get(),
            'is_active' => false,
            'activated_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }
}
