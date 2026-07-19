<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralClientRequestFactory extends Factory
{
    protected $model = ReferralClientRequest::class;

    public function definition(): array
    {
        return [
            'referral_id' => Referral::factory(),
            'creator_user_id' => User::factory(),
            'type' => ReferralClientRequest::TYPE_QUESTION,
            'title' => $this->faker->sentence(4),
            'instructions' => $this->faker->paragraph(),
            'status' => ReferralClientRequest::STATUS_OPEN,
            'due_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }
}
