<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $servicesData = [
            ['id' => Str::uuid(), 'name' => 'EDSP (Emergency Shelter Assistance)', 'description' => 'Emergency shelter assistance for displaced OFWs'],
            ['id' => Str::uuid(), 'name' => 'Calamity Assistance', 'description' => 'Financial assistance for OFWs affected by calamities'],
            ['id' => Str::uuid(), 'name' => 'Repatriation Assistance', 'description' => 'Assistance for OFWs needing repatriation'],
            ['id' => Str::uuid(), 'name' => 'Legal Assistance', 'description' => 'Legal counsel and representation for OFWs'],
            ['id' => Str::uuid(), 'name' => 'Medical Assistance', 'description' => 'Medical and health assistance for OFWs'],
            ['id' => Str::uuid(), 'name' => 'Financial Relief', 'description' => 'Financial relief assistance for OFWs in distress'],
            ['id' => Str::uuid(), 'name' => 'Livelihood Assistance', 'description' => 'Livelihood and business assistance for returning OFWs'],
            ['id' => Str::uuid(), 'name' => 'Reintegration Seminar', 'description' => 'Seminars for reintegrating OFWs'],
            ['id' => Str::uuid(), 'name' => 'OEC (Overseas Employment Certificate)', 'description' => 'Processing of Overseas Employment Certificate'],
            ['id' => Str::uuid(), 'name' => 'TUPAD Program', 'description' => 'Tulong Panghanapbuhay sa Ating Disadvantaged/Displaced Workers'],
            ['id' => Str::uuid(), 'name' => 'AICS (Assistance to Individuals in Crisis Situation)', 'description' => 'Social welfare assistance for individuals in crisis'],
            ['id' => Str::uuid(), 'name' => 'National Certification', 'description' => 'Skills assessment and national certification'],
            ['id' => Str::uuid(), 'name' => 'Skills Training for Returning OFWs', 'description' => 'Skills training programs for returning OFWs'],
            ['id' => Str::uuid(), 'name' => 'Legal Counseling', 'description' => 'Free legal counseling services'],
            ['id' => Str::uuid(), 'name' => 'Affidavit/Document Assistance', 'description' => 'Notarization and document preparation assistance'],
            ['id' => Str::uuid(), 'name' => 'Social Assistance', 'description' => 'Social welfare and assistance programs'],
            ['id' => Str::uuid(), 'name' => 'Certification/Endorsement', 'description' => 'Issuance of certifications and endorsements'],
            ['id' => Str::uuid(), 'name' => 'Residency/Indigency Certification', 'description' => 'Certification of residency or indigency'],
            ['id' => Str::uuid(), 'name' => 'Aid Endorsement/Certification', 'description' => 'Endorsement for aid programs'],
        ];

        foreach ($servicesData as $svc) {
            DB::table('services')->insert(array_merge($svc, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $servicesByName = [];
        foreach ($servicesData as $svc) {
            $servicesByName[$svc['name']] = $svc['id'];
        }
        $agenciesBySlug = [];
        $rawAgencies = DB::table('agencies')->select('id', 'slug')->get();
        foreach ($rawAgencies as $a) {
            $agenciesBySlug[$a->slug] = $a->id;
        }

        $agencyServices = [
            'owwa' => [
                ['service' => 'EDSP (Emergency Shelter Assistance)', 'docs' => 'Valid ID, Proof of displacement', 'days' => 7],
                ['service' => 'Calamity Assistance', 'docs' => 'Barangay certificate, Valid ID', 'days' => 14],
                ['service' => 'Repatriation Assistance', 'docs' => 'Passport, Employment contract', 'days' => 30],
                ['service' => 'Legal Assistance', 'docs' => 'Case documents, Valid ID', 'days' => 14],
                ['service' => 'Medical Assistance', 'docs' => 'Medical certificate, Hospital bills', 'days' => 7],
                ['service' => 'Financial Relief', 'docs' => 'Proof of need, Valid ID', 'days' => 14],
                ['service' => 'Livelihood Assistance', 'docs' => 'Business plan, Valid ID', 'days' => 30],
                ['service' => 'Reintegration Seminar', 'docs' => 'Valid ID', 'days' => 3],
            ],
            'dmw' => [
                ['service' => 'OEC (Overseas Employment Certificate)', 'docs' => 'Passport, Employment contract, Valid ID', 'days' => 3],
                ['service' => 'Repatriation Assistance', 'docs' => 'Passport, Employment contract', 'days' => 30],
            ],
            'doh' => [
                ['service' => 'Medical Assistance', 'docs' => 'Medical certificate, Valid ID', 'days' => 14],
            ],
            'dole' => [
                ['service' => 'TUPAD Program', 'docs' => 'Valid ID, Barangay certificate', 'days' => 21],
            ],
            'dswd' => [
                ['service' => 'AICS (Assistance to Individuals in Crisis Situation)', 'docs' => 'Valid ID, Barangay certificate', 'days' => 14],
            ],
            'tesda' => [
                ['service' => 'National Certification', 'docs' => 'Valid ID, Previous certificates', 'days' => 30],
                ['service' => 'Skills Training for Returning OFWs', 'docs' => 'Valid ID, Proof of OFW status', 'days' => 45],
            ],
            'law-center-inc' => [
                ['service' => 'Legal Counseling', 'docs' => 'Valid ID', 'days' => 3],
                ['service' => 'Affidavit/Document Assistance', 'docs' => 'Valid ID, Supporting documents', 'days' => 7],
            ],
            'province-cebu' => [
                ['service' => 'Social Assistance', 'docs' => 'Valid ID, Barangay certificate', 'days' => 14],
                ['service' => 'Certification/Endorsement', 'docs' => 'Valid ID', 'days' => 7],
            ],
            'city-cebu' => [
                ['service' => 'Social Assistance', 'docs' => 'Valid ID, Barangay certificate', 'days' => 14],
                ['service' => 'Certification/Endorsement', 'docs' => 'Valid ID', 'days' => 7],
            ],
        ];

        foreach ($agencyServices as $slug => $svcs) {
            $agencyId = $agenciesBySlug[$slug] ?? null;
            if (!$agencyId) continue;

            foreach ($svcs as $svc) {
                $serviceId = $servicesByName[$svc['service']] ?? null;
                if (!$serviceId) continue;

                DB::table('agency_service')->insert([
                    'id' => Str::uuid(),
                    'agency_id' => $agencyId,
                    'service_id' => $serviceId,
                    'required_documents' => $svc['docs'],
                    'processing_days' => $svc['days'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
