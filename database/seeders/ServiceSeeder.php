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

        $agenciesBySlug = [];
        $rawAgencies = DB::table('agencies')->select('id', 'slug')->get();
        foreach ($rawAgencies as $a) {
            $agenciesBySlug[$a->slug] = $a->id;
        }

        $servicesData = [
            // OWWA services
            ['name' => 'EDSP (Emergency Shelter Assistance)', 'description' => 'Emergency shelter assistance for displaced OFWs', 'agency_slug' => 'owwa', 'processing_days' => 7],
            ['name' => 'Calamity Assistance', 'description' => 'Financial assistance for OFWs affected by calamities', 'agency_slug' => 'owwa', 'processing_days' => 14],
            ['name' => 'Repatriation Assistance', 'description' => 'Assistance for OFWs needing repatriation', 'agency_slug' => 'owwa', 'processing_days' => 30],
            ['name' => 'Legal Assistance', 'description' => 'Legal counsel and representation for OFWs', 'agency_slug' => 'owwa', 'processing_days' => 14],
            ['name' => 'Medical Assistance', 'description' => 'Medical and health assistance for OFWs', 'agency_slug' => 'owwa', 'processing_days' => 7],
            ['name' => 'Financial Relief', 'description' => 'Financial relief assistance for OFWs in distress', 'agency_slug' => 'owwa', 'processing_days' => 14],
            ['name' => 'Livelihood Assistance', 'description' => 'Livelihood and business assistance for returning OFWs', 'agency_slug' => 'owwa', 'processing_days' => 30],
            ['name' => 'Reintegration Seminar', 'description' => 'Seminars for reintegrating OFWs', 'agency_slug' => 'owwa', 'processing_days' => 3],

            // DMW services
            ['name' => 'OEC (Overseas Employment Certificate)', 'description' => 'Processing of Overseas Employment Certificate', 'agency_slug' => 'dmw', 'processing_days' => 3],
            ['name' => 'Repatriation Assistance (DMW)', 'description' => 'Repatriation assistance by DMW', 'agency_slug' => 'dmw', 'processing_days' => 30],

            // DOH services
            ['name' => 'Medical Assistance (DOH)', 'description' => 'Medical and health assistance', 'agency_slug' => 'doh', 'processing_days' => 14],

            // DOLE services
            ['name' => 'TUPAD Program', 'description' => 'Tulong Panghanapbuhay sa Ating Disadvantaged/Displaced Workers', 'agency_slug' => 'dole', 'processing_days' => 21],

            // DSWD services
            ['name' => 'AICS (Assistance to Individuals in Crisis Situation)', 'description' => 'Social welfare assistance for individuals in crisis', 'agency_slug' => 'dswd', 'processing_days' => 14],

            // TESDA services
            ['name' => 'National Certification', 'description' => 'Skills assessment and national certification', 'agency_slug' => 'tesda', 'processing_days' => 30],
            ['name' => 'Skills Training for Returning OFWs', 'description' => 'Skills training programs for returning OFWs', 'agency_slug' => 'tesda', 'processing_days' => 45],

            // Law Center services
            ['name' => 'Legal Counseling', 'description' => 'Free legal counseling services', 'agency_slug' => 'law-center-inc', 'processing_days' => 3],
            ['name' => 'Affidavit/Document Assistance', 'description' => 'Notarization and document preparation assistance', 'agency_slug' => 'law-center-inc', 'processing_days' => 7],

            // Province of Cebu services
            ['name' => 'Social Assistance (Province)', 'description' => 'Social welfare and assistance programs', 'agency_slug' => 'province-cebu', 'processing_days' => 14],
            ['name' => 'Certification/Endorsement (Province)', 'description' => 'Issuance of certifications and endorsements', 'agency_slug' => 'province-cebu', 'processing_days' => 7],

            // City of Cebu services
            ['name' => 'Social Assistance (City)', 'description' => 'Social welfare and assistance programs', 'agency_slug' => 'city-cebu', 'processing_days' => 14],
            ['name' => 'Certification/Endorsement (City)', 'description' => 'Issuance of certifications and endorsements', 'agency_slug' => 'city-cebu', 'processing_days' => 7],
        ];

        $requirementTemplates = [
            'EDSP (Emergency Shelter Assistance)' => ['Valid ID', 'Proof of displacement'],
            'Calamity Assistance' => ['Barangay certificate', 'Valid ID'],
            'Repatriation Assistance' => ['Passport', 'Employment contract'],
            'Legal Assistance' => ['Case documents', 'Valid ID'],
            'Medical Assistance' => ['Medical certificate', 'Hospital bills'],
            'Financial Relief' => ['Proof of need', 'Valid ID'],
            'Livelihood Assistance' => ['Business plan', 'Valid ID'],
            'Reintegration Seminar' => ['Valid ID'],
            'OEC (Overseas Employment Certificate)' => ['Passport', 'Employment contract', 'Valid ID'],
            'Repatriation Assistance (DMW)' => ['Passport', 'Employment contract'],
            'Medical Assistance (DOH)' => ['Medical certificate', 'Valid ID'],
            'TUPAD Program' => ['Valid ID', 'Barangay certificate'],
            'AICS (Assistance to Individuals in Crisis Situation)' => ['Valid ID', 'Barangay certificate'],
            'National Certification' => ['Valid ID', 'Previous certificates'],
            'Skills Training for Returning OFWs' => ['Valid ID', 'Proof of OFW status'],
            'Legal Counseling' => ['Valid ID'],
            'Affidavit/Document Assistance' => ['Valid ID', 'Supporting documents'],
            'Social Assistance (Province)' => ['Valid ID', 'Barangay certificate'],
            'Certification/Endorsement (Province)' => ['Valid ID'],
            'Social Assistance (City)' => ['Valid ID', 'Barangay certificate'],
            'Certification/Endorsement (City)' => ['Valid ID'],
        ];

        foreach ($servicesData as $svc) {
            $agencyId = $agenciesBySlug[$svc['agency_slug']] ?? null;
            if (! $agencyId) {
                continue;
            }

            // Skip if this service already exists for the agency (idempotent).
            $existing = DB::table('services')
                ->where('name', $svc['name'])
                ->where('agcy_id', $agencyId)
                ->first();

            if ($existing) {
                $serviceId = $existing->id;

                // Seed requirements only if the service has none yet.
                if (DB::table('service_requirements')->where('service_id', $serviceId)->doesntExist()) {
                    $requirements = $requirementTemplates[$svc['name']] ?? ['Valid ID'];
                    foreach ($requirements as $req) {
                        DB::table('service_requirements')->insert([
                            'id' => (string) Str::uuid(),
                            'name' => $req,
                            'description' => $req,
                            'is_required' => true,
                            'service_id' => $serviceId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                continue;
            }

            $serviceId = (string) Str::uuid();
            DB::table('services')->insert([
                'id' => $serviceId,
                'name' => $svc['name'],
                'description' => $svc['description'],
                'agcy_id' => $agencyId,
                'processing_days' => $svc['processing_days'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $requirements = $requirementTemplates[$svc['name']] ?? ['Valid ID'];
            foreach ($requirements as $req) {
                DB::table('service_requirements')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $req,
                    'description' => $req,
                    'is_required' => true,
                    'service_id' => $serviceId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
