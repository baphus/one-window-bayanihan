<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Get agency IDs keyed by slug
        $agenciesBySlug = [];
        foreach (DB::table('agencies')->select('id', 'slug')->get() as $a) {
            $agenciesBySlug[$a->slug] = $a->id;
        }

        // Get service IDs keyed by name
        $servicesByName = [];
        foreach (DB::table('services')->select('id', 'name')->get() as $s) {
            $servicesByName[$s->name] = $s->id;
        }

        $cmId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $cmId,
            'name' => 'Maria Santos',
            'email' => 'case@bayanihan.gov.ph',
            'password' => Hash::make('password'),
            'role' => 'CASE_MANAGER',
            'contact_number' => '09171234567',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $testUsers = [];
        foreach ($agenciesBySlug as $slug => $id) {
            $uid = (string) Str::uuid();
            DB::table('users')->insert([
                'id' => $uid,
                'name' => strtoupper($slug) . ' Focal',
                'email' => $slug . '@bayanihan.gov.ph',
                'password' => Hash::make('password'),
                'role' => 'AGENCY',
                'agcy_id' => $id,
                'contact_number' => '09170000000',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $testUsers[$slug] = $uid;
        }

        $adminId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $adminId,
            'name' => 'Admin User',
            'email' => 'admin@bayanihan.gov.ph',
            'password' => Hash::make('password'),
            'role' => 'ADMIN',
            'contact_number' => '09171234568',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sampleCases = [
            [
                'case_number' => 'CASE-20260511-000001',
                'tracker_number' => 'OW-A7K2M9Q',
                'client_type' => 'OFW',
                'summary' => 'Mariano, a returning OFW from Saudi Arabia, needs assistance with repatriation benefits and livelihood support after his contract was abruptly terminated.',
                'client' => [
                    'first_name' => 'Ricardo', 'last_name' => 'Mariano', 'middle_name' => 'Javier',
                    'date_of_birth' => '1985-03-15', 'sex' => 'Male',
                ],
                'address' => ['line1' => '123 Poblacion St', 'city' => 'Cebu City', 'province' => 'Cebu', 'postal_code' => '6000', 'country' => 'Philippines'],
                'employment' => ['employer_name' => 'Saudi Construction Co.', 'position' => 'Heavy Equipment Operator', 'country' => 'Saudi Arabia', 'start_date' => '2022-01-15', 'end_date' => '2026-04-30'],
                'agency_slug' => 'owwa',
            ],
            [
                'case_number' => 'CASE-20260511-000002',
                'tracker_number' => 'OW-P4T8X1L',
                'client_type' => 'OFW',
                'summary' => 'Dela Cruz family seeking assistance for Elena who suffered a work-related injury in Hong Kong.',
                'client' => [
                    'first_name' => 'Elena', 'last_name' => 'Dela Cruz', 'middle_name' => 'Santos',
                    'date_of_birth' => '1990-07-22', 'sex' => 'Female',
                ],
                'address' => ['line1' => '456 Mabini St', 'city' => 'Lapu-Lapu City', 'province' => 'Cebu', 'postal_code' => '6015', 'country' => 'Philippines'],
                'employment' => ['employer_name' => 'HK Domestic Agency', 'position' => 'Domestic Worker', 'country' => 'Hong Kong', 'start_date' => '2023-03-01', 'end_date' => '2026-05-01'],
                'agency_slug' => 'doh',
            ],
            [
                'case_number' => 'CASE-20260511-000003',
                'tracker_number' => 'OW-Z9D3R6N',
                'client_type' => 'OFW',
                'summary' => 'Panganiban completed his contract in Taiwan and is seeking livelihood assistance and reintegration support.',
                'client' => [
                    'first_name' => 'Arturo', 'last_name' => 'Panganiban', 'middle_name' => 'Garcia',
                    'date_of_birth' => '1978-11-08', 'sex' => 'Male',
                ],
                'address' => ['line1' => '789 Rizal Ave', 'city' => 'Mandaue City', 'province' => 'Cebu', 'postal_code' => '6014', 'country' => 'Philippines'],
                'employment' => ['employer_name' => 'Taiwan Electronics Inc.', 'position' => 'Production Supervisor', 'country' => 'Taiwan', 'start_date' => '2020-06-01', 'end_date' => '2026-02-28'],
                'agency_slug' => 'dole',
            ],
        ];

        $statuses = ['PROCESSING', 'PENDING', 'COMPLETED'];

        foreach ($sampleCases as $i => $caseData) {
            $caseId = (string) Str::uuid();
            $status = $statuses[$i % count($statuses)];

            DB::table('cases')->insert([
                'id' => $caseId,
                'case_number' => $caseData['case_number'],
                'client_type' => $caseData['client_type'],
                'tracker_number' => $caseData['tracker_number'],
                'summary' => $caseData['summary'],
                'status' => $status === 'COMPLETED' ? 'CLOSED' : 'OPEN',
                'user_id' => $cmId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $clientId = (string) Str::uuid();
            DB::table('clients')->insert(array_merge($caseData['client'], [
                'id' => $clientId,
                'case_id' => $caseId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            DB::table('client_addresses')->insert(array_merge($caseData['address'], [
                'id' => (string) Str::uuid(),
                'client_id' => $clientId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            DB::table('client_employments')->insert(array_merge($caseData['employment'], [
                'id' => (string) Str::uuid(),
                'client_id' => $clientId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $agencyId = $agenciesBySlug[$caseData['agency_slug']] ?? null;
            if ($agencyId) {
                $refId = (string) Str::uuid();
                $refServiceName = 'General Assistance';
                $pivot = DB::table('agency_service')
                    ->where('agency_id', $agencyId)
                    ->first();
                if ($pivot) {
                    $svc = DB::table('services')->where('id', $pivot->service_id)->first();
                    if ($svc) $refServiceName = $svc->name;
                }

                DB::table('referrals')->insert([
                    'id' => $refId,
                    'required_services' => $refServiceName,
                    'status' => $status,
                    'case_id' => $caseId,
                    'agcy_id' => $agencyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($status !== 'PENDING') {
                    DB::table('milestones')->insert([
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received by the agency.',
                        'refr_id' => $refId,
                        'user_id' => $cmId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($status === 'COMPLETED') {
                    DB::table('milestones')->insert([
                        'id' => (string) Str::uuid(),
                        'title' => 'Services Rendered',
                        'description' => 'All required services have been provided.',
                        'refr_id' => $refId,
                        'user_id' => $cmId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('milestones')->insert([
                        'id' => (string) Str::uuid(),
                        'title' => 'Case Closed',
                        'description' => 'Case successfully closed.',
                        'refr_id' => $refId,
                        'user_id' => $cmId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
