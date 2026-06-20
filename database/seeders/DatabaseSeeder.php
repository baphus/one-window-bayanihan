<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ServiceSeeder::class,
            AgencySeeder::class,
            HelpdeskSeeder::class,
            HelpdeskSeederV2::class,
            CaseCategorySeeder::class,
            CaseIssueSeeder::class,
            DemoDataSeeder::class,
            SystemSettingSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(DefaultServqualQuestionsSeeder::class);
        }
    }
}
