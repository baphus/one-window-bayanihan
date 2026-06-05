<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actions = ['CREATE', 'UPDATE', 'DELETE', 'VIEW', 'CLOSE', 'REOPEN', 'ASSIGN', 'REFER', 'COMMENT'];
        $modules = ['cases', 'referrals', 'clients', 'agencies', 'users', 'settings'];

        return [
            'action' => fake()->randomElement($actions),
            'module' => fake()->randomElement($modules),
            'entity_id' => fake()->uuid(),
            'description' => fake()->sentence(),
            'old_value' => fake()->randomElement([
                ['status' => 'OPEN'],
                ['status' => 'IN_PROGRESS'],
                null,
            ]),
            'new_value' => [
                'status' => fake()->randomElement(['OPEN', 'IN_PROGRESS', 'CLOSED', 'REFERRED']),
            ],
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'timestamp' => fake()->dateTimeBetween('-6 months'),
            'is_deleted' => false,
        ];
    }
}
