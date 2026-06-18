<?php

namespace Database\Seeders;

use App\Helpers\DefaultServqualQuestions;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class DefaultServqualQuestionsSeeder extends Seeder
{
    /**
     * Seed the default SERVQUAL questions into SystemSetting.
     */
    public function run(): void
    {
        SystemSetting::firstOrCreate(
            ['key' => 'default_servqual_questions'],
            ['value' => json_encode(DefaultServqualQuestions::get())],
        );
    }
}
