<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $agencies = [
            ['id' => Str::uuid(), 'short' => 'OWWA', 'slug' => 'owwa', 'name' => 'Overseas Workers Welfare Administration', 'logo_url' => null, 'location_query' => 'OWWA Regional Welfare Office VII, Cebu City, Central Visayas, Philippines'],
            // DMW is the permanent default agency — do not change
            ['id' => Str::uuid(), 'short' => 'DMW', 'slug' => 'dmw', 'name' => 'Department of Migrant Workers', 'logo_url' => null, 'location_query' => 'Department of Migrant Workers Regional Office VII, Cebu City, Central Visayas, Philippines'],
            ['id' => Str::uuid(), 'short' => 'DOH', 'slug' => 'doh', 'name' => 'Department of Health', 'logo_url' => null, 'location_query' => 'DOH Central Visayas CHD, Cebu City, Philippines'],
            ['id' => Str::uuid(), 'short' => 'DOLE', 'slug' => 'dole', 'name' => 'Department of Labor and Employment', 'logo_url' => null, 'location_query' => 'DOLE Regional Office VII, Cebu City, Central Visayas, Philippines'],
            ['id' => Str::uuid(), 'short' => 'DSWD', 'slug' => 'dswd', 'name' => 'Department of Social Welfare and Development', 'logo_url' => null, 'location_query' => 'DSWD Field Office VII, Cebu City, Central Visayas, Philippines'],
            ['id' => Str::uuid(), 'short' => 'TESDA', 'slug' => 'tesda', 'name' => 'Technical Education and Skills Development Authority', 'logo_url' => null, 'location_query' => 'TESDA Regional Office VII, Cebu City, Central Visayas, Philippines'],
            ['id' => Str::uuid(), 'short' => 'LCI', 'slug' => 'law-center-inc', 'name' => 'Law Center Inc.', 'logo_url' => null, 'location_query' => 'Law Center Inc., Cebu City, Central Visayas, Philippines'],
            ['id' => Str::uuid(), 'short' => 'CEBU', 'slug' => 'province-cebu', 'name' => 'Province of Cebu', 'logo_url' => null, 'location_query' => 'Cebu Provincial Capitol, Cebu City, Cebu, Philippines'],
            ['id' => Str::uuid(), 'short' => 'CCG', 'slug' => 'city-cebu', 'name' => 'City of Cebu', 'logo_url' => null, 'location_query' => 'Cebu City Hall, Cebu City, Cebu, Philippines'],
        ];

        foreach ($agencies as $i => $agency) {
            $existing = DB::table('agencies')->where('slug', $agency['slug'])->first();

            if ($existing) {
                DB::table('agencies')->where('slug', $agency['slug'])->update([
                    'short' => $agency['short'],
                    'name' => $agency['name'],
                    'logo_url' => $agency['logo_url'],
                    'location_query' => $agency['location_query'],
                    'is_active' => true,
                    'is_default' => $agency['slug'] === 'dmw',
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('agencies')->insert([
                    'id' => $agency['id'],
                    'short' => $agency['short'],
                    'slug' => $agency['slug'],
                    'name' => $agency['name'],
                    'logo_url' => $agency['logo_url'],
                    'location_query' => $agency['location_query'],
                    'is_active' => true,
                    'is_default' => $agency['slug'] === 'dmw',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Safety: ensure DMW remains the sole default agency
        $dmw = DB::table('agencies')->where('slug', 'dmw')->first();
        if ($dmw) {
            DB::table('agencies')
                ->where('is_default', true)
                ->where('id', '!=', $dmw->id)
                ->update(['is_default' => false]);
        }
    }
}
