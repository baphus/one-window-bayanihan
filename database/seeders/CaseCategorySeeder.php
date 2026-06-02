<?php

namespace Database\Seeders;

use App\Models\CaseCategory;
use Illuminate\Database\Seeder;

class CaseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Legal', 'description' => 'Cases requiring legal assistance, counsel, or representation', 'color' => '#2563EB', 'sort_order' => 1],
            ['name' => 'Financial', 'description' => 'Cases involving financial claims, benefits, or monetary concerns', 'color' => '#059669', 'sort_order' => 2],
            ['name' => 'Medical', 'description' => 'Cases requiring medical attention, health services, or hospitalization', 'color' => '#DC2626', 'sort_order' => 3],
            ['name' => 'Repatriation', 'description' => 'Cases involving repatriation of OFWs or their remains', 'color' => '#D97706', 'sort_order' => 4],
            ['name' => 'Employment', 'description' => 'Cases concerning employment contracts, violations, or disputes', 'color' => '#7C3AED', 'sort_order' => 5],
            ['name' => 'Other', 'description' => 'Cases that do not fall under the other categories', 'color' => '#6B7280', 'sort_order' => 6],
        ];

        foreach ($categories as $category) {
            CaseCategory::firstOrCreate(
                ['name' => $category['name']],
                $category,
            );
        }
    }
}
