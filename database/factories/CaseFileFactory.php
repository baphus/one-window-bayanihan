<?php

namespace Database\Factories;

use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CaseFileFactory extends Factory
{
    protected $model = CaseFile::class;

    public function definition(): array
    {
        $statuses = ['OPEN', 'IN_PROGRESS', 'PENDING_REFERRAL', 'REFERRED', 'CLOSED', 'ARCHIVED'];
        $clientTypes = ['INDIVIDUAL', 'FAMILY', 'GROUP', 'OFW'];
        $vulnerabilityLevels = ['Low', 'Medium', 'High', 'Critical', null];

        return [
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.$this->faker->unique()->randomNumber(4),
            'tracker_number' => 'OWBAP-'.strtoupper(Str::random(7)),
            'client_type' => $this->faker->randomElement($clientTypes),
            'vulnerability_indicator' => $this->faker->randomElement($vulnerabilityLevels),
            'summary' => $this->faker->paragraph(2),
            'status' => $this->faker->randomElement($statuses),
            'consent_given_at' => $this->faker->dateTimeBetween('-6 months'),
            'user_id' => User::where('role', 'CASE_MANAGER')->inRandomOrder()->first()?->id ?? User::factory(),
            'client_id' => Client::inRandomOrder()->first()?->id ?? Client::factory(),
            'category_id' => CaseCategory::inRandomOrder()->first()?->id,
            'is_deleted' => false,
        ];
    }

    /**
     * Case with CLOSED status.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'CLOSED',
        ]);
    }

    /**
     * Case with REFERRED status.
     */
    public function referred(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'REFERRED',
        ]);
    }

    /**
     * Case with IN_PROGRESS status.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'IN_PROGRESS',
        ]);
    }

    /**
     * Case with OPEN status.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'OPEN',
        ]);
    }
}
