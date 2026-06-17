<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MilestoneFactory extends Factory
{
    protected $model = Milestone::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'refr_id' => Referral::factory(),
            'user_id' => User::factory(),
            'is_deleted' => false,
        ];
    }
}
