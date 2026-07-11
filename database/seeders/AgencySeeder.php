<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgencySeeder extends Seeder
{
    /**
     * Official agency logo URLs hosted on Cloudinary.
     * Sourced from Wikimedia Commons (public domain Philippine government works).
     */
    private const LOGO_PATHS = [
        'owwa' => 'https://res.cloudinary.com/fsc2meuy/image/upload/v1783801443/bayanihan/agencies/owwa.png',
        'dmw' => 'https://res.cloudinary.com/fsc2meuy/image/upload/v1783801446/bayanihan/agencies/dmw.png',
        'doh' => 'https://res.cloudinary.com/fsc2meuy/image/upload/v1783801448/bayanihan/agencies/doh.png',
        'dole' => 'https://res.cloudinary.com/fsc2meuy/image/upload/v1783801450/bayanihan/agencies/dole.png',
        'dswd' => 'https://res.cloudinary.com/fsc2meuy/image/upload/v1783801451/bayanihan/agencies/dswd.png',
        'tesda' => 'https://res.cloudinary.com/fsc2meuy/image/upload/v1783801453/bayanihan/agencies/tesda.png',
        'law-center-inc' => null, // Small NGO — no publicly available official logo
        'province-cebu' => 'https://res.cloudinary.com/fsc2meuy/image/upload/v1783801455/bayanihan/agencies/province-cebu.png',
        'city-cebu' => 'https://res.cloudinary.com/fsc2meuy/image/upload/v1783801456/bayanihan/agencies/city-cebu.png',
    ];

    /**
     * Generate a simple SVG text logo as a data URI fallback for agencies without official logos.
     */
    private function svgLogo(string $text, string $bgColor, string $textColor = '#ffffff'): string
    {
        $encoded = rawurlencode(<<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
  <rect width="200" height="200" rx="20" fill="{$bgColor}"/>
  <text x="100" y="120" font-family="Arial,Helvetica,sans-serif" font-size="64" font-weight="bold" fill="{$textColor}" text-anchor="middle">{$text}</text>
</svg>
SVG);

        return 'data:image/svg+xml;charset=utf-8,'.$encoded;
    }

    public function run(): void
    {
        $now = now();

        $agencies = [
            [
                'short' => 'OWWA',
                'slug' => 'owwa',
                'name' => 'Overseas Workers Welfare Administration',
                'description' => 'OWWA is the lead government agency tasked to protect and promote the welfare and well-being of Overseas Filipino Workers (OFWs) and their families. It provides a wide range of services including emergency assistance, legal aid, medical support, repatriation, and reintegration programs for returning OFWs.',
                'contact_info' => "OWWA Regional Welfare Office VII\r\nG/F DMW Building, Cardinal Rosales Avenue\r\nCebu City 6000, Philippines\r\nTel: (032) 232-1234\r\nEmail: owwa_ro7@owwa.gov.ph",
                'logo_url' => self::LOGO_PATHS['owwa'],
                'location_query' => 'OWWA Regional Welfare Office VII, Cardinal Rosales Avenue, Cebu City, Cebu, Philippines',
                'map_link' => 'https://www.google.com/maps/place/OWWA+Regional+Welfare+Office+VII/@10.3157,123.8854,17z',
                'latitude' => 10.3157000,
                'longitude' => 123.8854000,
            ],
            // DMW is the permanent default agency — do not change
            [
                'short' => 'DMW',
                'slug' => 'dmw',
                'name' => 'Department of Migrant Workers',
                'description' => 'DMW is the primary national government agency responsible for the protection of the rights and promotion of the welfare of migrant workers and members of their families. It oversees the entire migration process from pre-employment to reintegration, ensuring ethical recruitment and fair treatment of Filipino workers abroad.',
                'contact_info' => "Department of Migrant Workers Regional Office VII\r\nCardinal Rosales Avenue, Barangay Capitol Site\r\nCebu City 6000, Philippines\r\nTel: (032) 233-5678\r\nEmail: dmw_ro7@dmw.gov.ph",
                'logo_url' => self::LOGO_PATHS['dmw'],
                'location_query' => 'Department of Migrant Workers Regional Office VII, Cardinal Rosales Avenue, Cebu City, Cebu, Philippines',
                'map_link' => 'https://www.google.com/maps/place/Department+of+Migrant+Workers+Regional+Office+VII/@10.3157,123.8854,17z',
                'latitude' => 10.3157000,
                'longitude' => 123.8854000,
            ],
            [
                'short' => 'DOH',
                'slug' => 'doh',
                'name' => 'Department of Health',
                'description' => 'DOH is the principal health agency in the Philippines responsible for ensuring access to basic public health services and promoting the health and well-being of every Filipino. Through its Central Visayas Center for Health Development, it provides medical assistance, health programs, and disease prevention services to OFWs and their families.',
                'contact_info' => "DOH Central Visayas Center for Health Development\r\nOsmeña Boulevard, Sambag I\r\nCebu City 6000, Philippines\r\nTel: (032) 253-6355\r\nEmail: dohro7@doh.gov.ph",
                'logo_url' => self::LOGO_PATHS['doh'],
                'location_query' => 'DOH Central Visayas CHD, Osmeña Boulevard, Cebu City, Philippines',
                'map_link' => 'https://www.google.com/maps/place/DOH+Central+Visayas+CHD/@10.3115,123.8910,17z',
                'latitude' => 10.3115000,
                'longitude' => 123.8910000,
            ],
            [
                'short' => 'DOLE',
                'slug' => 'dole',
                'name' => 'Department of Labor and Employment',
                'description' => 'DOLE is the national government agency tasked to formulate and implement policies and programs on labor and employment. Its regional office in Central Visayas handles employment facilitation, workers\' welfare, and enforcement of labor laws including the TUPAD program and livelihood assistance for displaced workers.',
                'contact_info' => "DOLE Regional Office VII\r\nG/F Doña Luisa Bldg., Fuente Osmeña\r\nCebu City 6000, Philippines\r\nTel: (032) 412-1478\r\nEmail: dole_ro7@dole.gov.ph",
                'logo_url' => self::LOGO_PATHS['dole'],
                'location_query' => 'DOLE Regional Office VII, Fuente Osmeña, Cebu City, Cebu, Philippines',
                'map_link' => 'https://www.google.com/maps/place/DOLE+Regional+Office+VII/@10.3134,123.8940,17z',
                'latitude' => 10.3134000,
                'longitude' => 123.8940000,
            ],
            [
                'short' => 'DSWD',
                'slug' => 'dswd',
                'name' => 'Department of Social Welfare and Development',
                'description' => 'DSWD is the government agency responsible for the protection and promotion of the welfare of the poor, vulnerable, and disadvantaged individuals and families. Its Field Office VII provides social welfare programs including AICS (Assistance to Individuals in Crisis Situation) and other support services for OFWs and their families.',
                'contact_info' => "DSWD Field Office VII\r\nM.J. Cuenco Avenue, Barangay Mabolo\r\nCebu City 6000, Philippines\r\nTel: (032) 233-7920\r\nEmail: fo7@dswd.gov.ph",
                'logo_url' => self::LOGO_PATHS['dswd'],
                'location_query' => 'DSWD Field Office VII, M.J. Cuenco Avenue, Cebu City, Cebu, Philippines',
                'map_link' => 'https://www.google.com/maps/place/DSWD+Field+Office+VII/@10.3205,123.8951,17z',
                'latitude' => 10.3205000,
                'longitude' => 123.8951000,
            ],
            [
                'short' => 'TESDA',
                'slug' => 'tesda',
                'name' => 'Technical Education and Skills Development Authority',
                'description' => 'TESDA is the government agency tasked to manage and supervise technical education and skills development in the Philippines. Its regional office provides skills training, assessment, and national certification programs, including specialized training for returning OFWs to enhance their employability and livelihood opportunities.',
                'contact_info' => "TESDA Regional Office VII\r\nJ. Llorente Street, Barangay Kamputhaw\r\nCebu City 6000, Philippines\r\nTel: (032) 255-9559\r\nEmail: tesdaro7@tesda.gov.ph",
                'logo_url' => self::LOGO_PATHS['tesda'],
                'location_query' => 'TESDA Regional Office VII, J. Llorente Street, Cebu City, Cebu, Philippines',
                'map_link' => 'https://www.google.com/maps/place/TESDA+Regional+Office+VII/@10.3182,123.8887,17z',
                'latitude' => 10.3182000,
                'longitude' => 123.8887000,
            ],
            [
                'short' => 'LCI',
                'slug' => 'law-center-inc',
                'name' => 'Law Center Inc.',
                'description' => 'Law Center Inc. is a non-profit legal aid institution that provides free and low-cost legal services to marginalized communities and OFWs. It offers legal counseling, documentation assistance, notarization, and representation in cases involving illegal recruitment, contract violations, and other legal concerns.',
                'contact_info' => "Law Center Inc.\r\n3/F JEG Building, Osmeña Boulevard\r\nCebu City 6000, Philippines\r\nTel: (032) 255-4321\r\nEmail: info@lawcenterinc.org",
                // No publicly available official logo for this NGO; uses SVG text fallback
                'logo_url' => self::LOGO_PATHS['law-center-inc'] ?? $this->svgLogo('LCI', '#5b2c6f'),
                'location_query' => 'Law Center Inc., Osmeña Boulevard, Cebu City, Cebu, Philippines',
                'map_link' => 'https://www.google.com/maps/place/Law+Center+Inc./@10.3120,123.8925,17z',
                'latitude' => 10.3120000,
                'longitude' => 123.8925000,
            ],
            [
                'short' => 'Province of Cebu',
                'slug' => 'province-cebu',
                'name' => 'Province of Cebu',
                'description' => 'The Province of Cebu, through its Provincial Social Welfare and Development Office (PSWDO), provides social welfare services and assistance to constituents including OFWs and their families. Services include social assistance programs, certifications, endorsements, and livelihood support for returning migrant workers.',
                'contact_info' => "Cebu Provincial Capitol\r\nCapitol Compound, Escario Street\r\nCebu City 6000, Philippines\r\nTel: (032) 253-4642\r\nEmail: pswdo@cebu.gov.ph",
                'logo_url' => self::LOGO_PATHS['province-cebu'],
                'location_query' => 'Cebu Provincial Capitol, Escario Street, Cebu City, Cebu, Philippines',
                'map_link' => 'https://www.google.com/maps/place/Cebu+Provincial+Capitol/@10.3167,123.8888,17z',
                'latitude' => 10.3167000,
                'longitude' => 123.8888000,
            ],
            [
                'short' => 'City of Cebu',
                'slug' => 'city-cebu',
                'name' => 'City of Cebu',
                'description' => 'The City Government of Cebu, through its City Social Welfare and Services Office (CSWSO), extends social welfare and assistance programs to city residents, including OFWs and their families. Services include crisis intervention, social assistance, certifications, and referral services.',
                'contact_info' => "Cebu City Hall\r\nM.C. Briones Street, Barangay Sto. Niño\r\nCebu City 6000, Philippines\r\nTel: (032) 256-7777\r\nEmail: cswso@cebucity.gov.ph",
                'logo_url' => self::LOGO_PATHS['city-cebu'],
                'location_query' => 'Cebu City Hall, M.C. Briones Street, Cebu City, Cebu, Philippines',
                'map_link' => 'https://www.google.com/maps/place/Cebu+City+Hall/@10.2937,123.9020,17z',
                'latitude' => 10.2937000,
                'longitude' => 123.9020000,
            ],
        ];

        foreach ($agencies as $agency) {
            $existing = DB::table('agencies')->where('slug', $agency['slug'])->first();

            $updateData = [
                'short' => $agency['short'],
                'slug' => $agency['slug'],
                'name' => $agency['name'],
                'description' => $agency['description'],
                'contact_info' => $agency['contact_info'],
                'logo_url' => $agency['logo_url'],
                'location_query' => $agency['location_query'],
                'map_link' => $agency['map_link'],
                'latitude' => $agency['latitude'],
                'longitude' => $agency['longitude'],
                'is_active' => true,
                'is_default' => $agency['slug'] === 'dmw',
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('agencies')->where('slug', $agency['slug'])->update($updateData);
            } else {
                DB::table('agencies')->insert(array_merge(
                    ['id' => (string) Str::uuid(), 'created_at' => $now],
                    $updateData
                ));
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
