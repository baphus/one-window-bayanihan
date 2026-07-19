<?php

namespace Database\Factories;

use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReferralClientAccessLinkFactory extends Factory
{
    protected $model = ReferralClientAccessLink::class;

    public function definition(): array
    {
        return [
            'request_id' => ReferralClientRequest::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
            'issued_by' => User::factory(),
            'recipient_snapshot' => ['name' => $this->faker->name(), 'email' => $this->faker->safeEmail()],
        ];
    }
}
