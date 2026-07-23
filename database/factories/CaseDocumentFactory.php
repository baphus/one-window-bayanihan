<?php

namespace Database\Factories;

use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaseDocumentFactory extends Factory
{
    protected $model = CaseDocument::class;

    public function definition(): array
    {
        return [
            'file_name' => $this->faker->word().'.pdf',
            'file_path' => 'documents/'.$this->faker->uuid().'.pdf',
            'file_type' => 'application/pdf',
            'category' => 'general',
            'size' => $this->faker->numberBetween(1024, 10240),
            'case_id' => CaseFile::factory(),
            'user_id' => User::factory(),
            'is_deleted' => false,
        ];
    }
}
