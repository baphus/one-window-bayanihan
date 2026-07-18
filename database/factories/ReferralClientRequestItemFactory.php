<?php

namespace Database\Factories;

use App\Models\ReferralClientRequest;
use App\Models\ReferralClientRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralClientRequestItemFactory extends Factory
{
    protected $model = ReferralClientRequestItem::class;

    public function definition(): array
    {
        return [
            'request_id' => ReferralClientRequest::factory(),
            'label' => $this->faker->sentence(5),
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
