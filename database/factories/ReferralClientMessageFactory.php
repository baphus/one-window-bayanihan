<?php

namespace Database\Factories;

use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralClientMessageFactory extends Factory
{
    protected $model = ReferralClientMessage::class;

    public function definition(): array
    {
        return [
            'request_id' => ReferralClientRequest::factory(),
            'body' => $this->faker->paragraph(),
            'sender_kind' => ReferralClientMessage::SENDER_AGENCY_USER,
            'user_id' => User::factory(),
            'access_link_id' => null,
            'kind' => ReferralClientMessage::KIND_MESSAGE,
        ];
    }
}
