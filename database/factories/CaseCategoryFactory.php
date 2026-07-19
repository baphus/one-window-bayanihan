<?php

namespace Database\Factories;

use App\Models\CaseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaseCategoryFactory extends Factory
{
    protected $model = CaseCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'color' => $this->faker->hexColor(),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
