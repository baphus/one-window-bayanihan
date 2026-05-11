<?php

namespace Database\Seeders;

use App\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        $agencies = [
            [
                'short' => 'OWWA',
                'slug' => 'owwa',
                'name' => 'Overseas Workers Welfare Administration',
                'logo_url' => 'https://upload.wikimedia.org/wikipedia/commons/c/c8/Overseas_Workers_Welfare_Administration_%28OWWA%29_-_Philippines.svg',
                'location_query' => 'OWWA Regional Welfare Office VII, Cebu City, Central Visayas, Philippines',
            ],
            [
                'short' => 'DMW',
                'slug' => 'dmw',
                'name' => 'Department of Migrant Workers',
                'logo_url' => 'https://dmw.gov.ph/images/dmw_logo.png',
                'location_query' => 'Department of Migrant Workers Regional Office VII, Cebu City, Central Visayas, Philippines',
            ],
            [
                'short' => 'DOH',
                'slug' => 'doh',
                'name' => 'Department of Health',
                'logo_url' => 'https://upload.wikimedia.org/wikipedia/commons/2/2a/DOH_PH_new_logo.svg',
                'location_query' => 'DOH Central Visayas CHD, Cebu City, Philippines',
            ],
            [
                'short' => 'DOLE',
                'slug' => 'dole',
                'name' => 'Department of Labor and Employment',
                'logo_url' => 'https://upload.wikimedia.org/wikipedia/commons/3/39/Department_of_Labor_and_Employment_%28DOLE%29.svg',
                'location_query' => 'DOLE Regional Office VII, Cebu City, Central Visayas, Philippines',
            ],
            [
                'short' => 'DSWD',
                'slug' => 'dswd',
                'name' => 'Department of Social Welfare and Development',
                'logo_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/76/Seal_of_the_Department_of_Social_Welfare_and_Development.svg/1280px-Seal_of_the_Department_of_Social_Welfare_and_Development.svg.png',
                'location_query' => 'DSWD Field Office VII, Cebu City, Central Visayas, Philippines',
            ],
            [
                'short' => 'TESDA',
                'slug' => 'tesda',
                'name' => 'Technical Education and Skills Development Authority',
                'logo_url' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQm8B1p66Z7vHm5UGtzegHjnts2VStAt3Rb7A&s',
                'location_query' => 'TESDA Regional Office VII, Cebu City, Central Visayas, Philippines',
            ],
            [
                'short' => 'LCI',
                'slug' => 'law-center-inc',
                'name' => 'Law Center Inc.',
                'logo_url' => 'https://res.cloudinary.com/dzjshue6h/image/upload/v1777940090/413828932_753218590185204_121817884749243877_n_hyzme6.jpg',
                'location_query' => 'Law Center Inc., Cebu City, Central Visayas, Philippines',
            ],
            [
                'short' => 'CEBU',
                'slug' => 'province-cebu',
                'name' => 'Province of Cebu',
                'logo_url' => 'https://cebuprovince.org/wp-content/uploads/2025/08/LOGO-13.png',
                'location_query' => 'Cebu Provincial Capitol, Cebu City, Cebu, Philippines',
            ],
            [
                'short' => 'CCG',
                'slug' => 'city-cebu',
                'name' => 'City of Cebu',
                'logo_url' => 'https://www.cebucity.gov.ph/wp-content/uploads/2019/09/official_seal_of_cebu_city_small.png',
                'location_query' => 'Cebu City Hall, Cebu City, Cebu, Philippines',
            ],
        ];

        foreach ($agencies as $agency) {
            Agency::create($agency);
        }
    }
}
