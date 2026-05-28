<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'short' => mb_substr($name, 0, 10),
            'slug' => Str::slug($name).'-'.Str::random(4),
            'description' => $this->faker->sentence(),
            'contact_info' => $this->faker->address(),
            'map_link' => $this->faker->url(),
            'logo_url' => null,
            'location_query' => $this->faker->city(),
            'is_active' => true,
            'is_deleted' => false,
        ];
    }
}
