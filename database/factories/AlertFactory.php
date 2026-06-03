<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            'case_stalled',
            'referral_stalled',
            'sla_breach_warning',
            'sla_breached',
            'agency_overloaded',
            'referral_rejected',
            'new_referral',
            'feedback_submitted',
            'capacity_warning',
            'system_health',
        ];

        $severities = ['info', 'medium', 'high', 'critical'];

        return [
            'type' => fake()->randomElement($types),
            'severity' => fake()->randomElement($severities),
            'title' => fake()->sentence(4),
            'message' => fake()->optional(0.8)->paragraph(),
            'entity_type' => fake()->optional(0.6)->randomElement(['case', 'referral', 'agency', 'user']),
            'entity_id' => fake()->optional(0.6)->uuid(),
            'assigned_to_id' => User::factory(),
            'dismissed_at' => fake()->optional(0.3)->dateTimeThisMonth(),
            'read_at' => fake()->optional(0.4)->dateTimeThisMonth(),
            'created_at' => fake()->dateTimeThisMonth(),
        ];
    }
}
