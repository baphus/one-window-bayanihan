<?php

namespace Database\Factories;

use App\Models\CaseFile;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->email(),
            'contact_number' => $this->faker->phoneNumber(),
            'case_id' => CaseFile::factory(),
            'date_of_birth' => $this->faker->date(),
        ];
    }
}
