<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\CaseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseCategorySeeder extends Seeder
{
    public function run(): void
    {
        // LAW Inc. = existing LCI; LGU = both Cebu governments, as approved.
        $categories = [
            ['name' => 'LEGAL & RIGHTS ADVOCACY', 'color' => '#2563EB', 'agencies' => ['law-center-inc']],
            ['name' => 'WELFARE & REPATRIATION', 'color' => '#D97706', 'agencies' => ['province-cebu', 'city-cebu', 'owwa', 'dswd', 'dole', 'tesda']],
            ['name' => 'LIVELIHOOD & EMPLOYMENT', 'color' => '#059669', 'agencies' => ['province-cebu', 'city-cebu', 'dole', 'dswd', 'owwa', 'tesda']],
            ['name' => 'SOCIAL & MEDICAL SERVICES', 'color' => '#DC2626', 'agencies' => ['dswd', 'doh']],
            ['name' => 'SKILLS UPSKILLING', 'color' => '#7C3AED', 'agencies' => ['tesda', 'province-cebu', 'city-cebu', 'dole']],
        ];

        DB::transaction(function () use ($categories) {
            foreach ($categories as $index => $attributes) {
                $agencyIds = Agency::whereIn('slug', $attributes['agencies'])->pluck('id', 'slug');
                if ($agencyIds->count() !== count($attributes['agencies'])) {
                    throw new \LogicException('Seed approved agencies before referral categories: '.$attributes['name']);
                }

                $category = CaseCategory::firstOrCreate(
                    ['name' => $attributes['name']],
                    ['id' => (string) Str::uuid(), 'color' => $attributes['color'], 'sort_order' => $index + 1, 'is_active' => true],
                );
                $category->suggestedAgencies()->sync($agencyIds->values()->all());
            }
        });
    }
}
