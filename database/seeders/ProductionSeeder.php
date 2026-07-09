<?php

namespace Database\Seeders;

use App\Helpers\DefaultServqualQuestions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ────────────────────────────────────────────
        // 1. Look up reference data
        // ────────────────────────────────────────────

        $agencies = DB::table('agencies')->select('id', 'slug', 'short')->get()->keyBy('slug');
        $categoriesByName = DB::table('case_categories')->pluck('id', 'name')->toArray();
        $issuesByName = DB::table('case_issues')->pluck('id', 'name')->toArray();

        // ────────────────────────────────────────────
        // 2. Create admin user (idempotent via updateOrInsert)
        // ────────────────────────────────────────────

        $adminEmail = 'admin@bayanihan.gov.ph';
        DB::table('users')->updateOrInsert(
            ['email' => $adminEmail],
            [
                'id' => DB::table('users')->where('email', $adminEmail)->value('id') ?? (string) Str::uuid(),
                'name' => 'System Administrator',
                'password' => Hash::make('P@ssw0rd!'),
                'role' => 'ADMIN',
                'is_active' => true,
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        $adminId = DB::table('users')->where('email', $adminEmail)->value('id');

        // ────────────────────────────────────────────
        // 3. Create one active SERVQUAL config per agency
        // ────────────────────────────────────────────

        $defaultQuestions = DefaultServqualQuestions::get();

        $inserted = 0;
        foreach ($agencies as $slug => $agency) {
            $serviceName = DB::table('services')
                ->where('agcy_id', $agency->id)
                ->orderBy('name')
                ->value('name') ?? 'General Assistance';

            // Skip if this agency already has a servqual config
            $existingConfig = DB::table('servqual_configs')
                ->where('agency_id', $agency->id)
                ->exists();

            if (! $existingConfig) {
                try {
                    DB::table('servqual_configs')->insert([
                        'id' => (string) Str::uuid(),
                        'agency_id' => $agency->id,
                        'service_name' => $serviceName,
                        'questions' => json_encode($defaultQuestions),
                        'is_active' => true,
                        'activated_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inserted++;
                } catch (\Throwable $e) {
                    logger()->warning('Failed to create servqual config for agency '.$slug.' ('.$agency->id.'): '.$e->getMessage());
                }
            } else {
                // Ensure at least one config is active for this agency
                $activeExists = DB::table('servqual_configs')
                    ->where('agency_id', $agency->id)
                    ->where('is_active', true)
                    ->exists();

                if (! $activeExists) {
                    DB::table('servqual_configs')
                        ->where('agency_id', $agency->id)
                        ->limit(1)
                        ->update(['is_active' => true, 'activated_at' => $now]);
                }
            }
        }

        // Safety: ensure no agency has more than one active config
        $activeCounts = DB::table('servqual_configs')
            ->select('agency_id', DB::raw('count(*) as cnt'))
            ->where('is_active', true)
            ->groupBy('agency_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($activeCounts as $dup) {
            $dups = DB::table('servqual_configs')
                ->where('agency_id', $dup->agency_id)
                ->where('is_active', true)
                ->orderBy('activated_at', 'desc')
                ->skip(1)
                ->take(100)
                ->get();

            foreach ($dups as $d) {
                DB::table('servqual_configs')
                    ->where('id', $d->id)
                    ->update(['is_active' => false, 'activated_at' => null]);
            }
        }

        // ────────────────────────────────────────────
        // 4. Case definitions with all supporting data
        // ────────────────────────────────────────────

        $casesData = [
            // Case 1: Ricardo Mariano — Legal / Contract Violation — OWWA referral (PROCESSING)
            [
                'case_number' => 'PRD-20260627-001',
                'tracker_number' => 'OWBAP-PRD-A7K2M9Q3',
                'client_type' => 'OFW',
                'summary' => 'Mariano, a returning OFW from Saudi Arabia, needs assistance with repatriation benefits and livelihood support after his contract was abruptly terminated.',
                'status' => 'OPEN',
                'category_name' => 'Legal',
                'issue_name' => 'Contract Violation',
                'referral_status' => 'PROCESSING',
                'agency_slug' => 'owwa',
                'client' => [
                    'first_name' => 'Ricardo',
                    'last_name' => 'Mariano',
                    'middle_initial' => 'J',
                    'date_of_birth' => '1985-03-15',
                    'sex' => 'MALE',
                ],
                'address' => [
                    'region' => 'Central Visayas',
                    'province' => 'Cebu',
                    'city_municipality' => 'Cebu City',
                    'barangay' => 'Poblacion',
                    'street' => '123 Poblacion St',
                ],
                'employment' => [
                    'employer_name' => 'Saudi Construction Co.',
                    'position' => 'Heavy Equipment Operator',
                    'country' => 'Saudi Arabia',
                    'start_date' => '2022-01-15',
                    'end_date' => '2026-04-30',
                    'last_country' => 'Saudi Arabia',
                    'last_position' => 'Heavy Equipment Operator',
                    'date_of_arrival' => '2026-05-01',
                ],
            ],
            // Case 2: Elena Dela Cruz — Medical / Medical Repatriation — DOH referral (COMPLETED / CLOSED)
            [
                'case_number' => 'PRD-20260627-002',
                'tracker_number' => 'OWBAP-PRD-P4T8X1L2',
                'client_type' => 'OFW',
                'summary' => 'Dela Cruz family seeking assistance for Elena who suffered a work-related injury in Hong Kong.',
                'status' => 'CLOSED',
                'category_name' => 'Medical',
                'issue_name' => 'Medical Repatriation',
                'referral_status' => 'COMPLETED',
                'agency_slug' => 'doh',
                'client' => [
                    'first_name' => 'Elena',
                    'last_name' => 'Dela Cruz',
                    'middle_initial' => 'S',
                    'date_of_birth' => '1990-07-22',
                    'sex' => 'FEMALE',
                ],
                'address' => [
                    'region' => 'Central Visayas',
                    'province' => 'Cebu',
                    'city_municipality' => 'Lapu-Lapu City',
                    'barangay' => 'Mabini',
                    'street' => '456 Mabini St',
                ],
                'employment' => [
                    'employer_name' => 'HK Domestic Agency',
                    'position' => 'Domestic Worker',
                    'country' => 'Hong Kong',
                    'start_date' => '2023-03-01',
                    'end_date' => '2026-05-01',
                    'last_country' => 'Hong Kong',
                    'last_position' => 'Domestic Worker',
                    'date_of_arrival' => '2026-05-02',
                ],
            ],
            // Case 3: Arturo Panganiban — Financial / Salary/Wage Dispute — DOLE referral (PENDING)
            [
                'case_number' => 'PRD-20260627-003',
                'tracker_number' => 'OWBAP-PRD-Z9D3R6N1',
                'client_type' => 'OFW',
                'summary' => 'Panganiban completed his contract in Taiwan and is seeking livelihood assistance and reintegration support.',
                'status' => 'OPEN',
                'category_name' => 'Financial',
                'issue_name' => 'Salary/Wage Dispute',
                'referral_status' => 'PENDING',
                'agency_slug' => 'dole',
                'client' => [
                    'first_name' => 'Arturo',
                    'last_name' => 'Panganiban',
                    'middle_initial' => 'G',
                    'date_of_birth' => '1978-11-08',
                    'sex' => 'MALE',
                ],
                'address' => [
                    'region' => 'Central Visayas',
                    'province' => 'Cebu',
                    'city_municipality' => 'Mandaue City',
                    'barangay' => 'Rizal',
                    'street' => '789 Rizal Ave',
                ],
                'employment' => [
                    'employer_name' => 'Taiwan Electronics Inc.',
                    'position' => 'Production Supervisor',
                    'country' => 'Taiwan',
                    'start_date' => '2020-06-01',
                    'end_date' => '2026-02-28',
                    'last_country' => 'Taiwan',
                    'last_position' => 'Production Supervisor',
                    'date_of_arrival' => '2026-03-01',
                ],
            ],
        ];

        // ────────────────────────────────────────────
        // 5. Insert cases (skip existing case_numbers for idempotency)
        // ────────────────────────────────────────────

        foreach ($casesData as $caseData) {
            if (DB::table('cases')->where('case_number', $caseData['case_number'])->exists()) {
                continue;
            }

            $caseId = (string) Str::uuid();
            $clientId = (string) Str::uuid();

            // Client
            DB::table('clients')->insert(array_merge($caseData['client'], [
                'id' => $clientId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            // Client address
            DB::table('client_addresses')->insert(array_merge($caseData['address'], [
                'id' => (string) Str::uuid(),
                'client_id' => $clientId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            // Client employment
            DB::table('client_employments')->insert(array_merge($caseData['employment'], [
                'id' => (string) Str::uuid(),
                'client_id' => $clientId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            // Case
            $caseInsert = [
                'id' => $caseId,
                'case_number' => $caseData['case_number'],
                'client_type' => $caseData['client_type'],
                'tracker_number' => $caseData['tracker_number'],
                'summary' => $caseData['summary'],
                'status' => $caseData['status'],
                'client_id' => $clientId,
                'user_id' => $adminId,
                'category_id' => $categoriesByName[$caseData['category_name']] ?? null,
                'case_issue_id' => $issuesByName[$caseData['issue_name']] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($caseData['status'] === 'CLOSED') {
                $caseInsert['closed_at'] = $now;
            }

            DB::table('cases')->insert($caseInsert);

            // ────────────────────────────────────────────
            // 6. Referral
            // ────────────────────────────────────────────

            $agency = $agencies[$caseData['agency_slug']] ?? null;

            if ($agency) {
                $refId = (string) Str::uuid();
                $serviceName = DB::table('services')->where('agcy_id', $agency->id)->value('name') ?? 'General Assistance';

                $refInsert = [
                    'id' => $refId,
                    'required_services' => $serviceName,
                    'status' => $caseData['referral_status'],
                    'case_id' => $caseId,
                    'agcy_id' => $agency->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($caseData['referral_status'] === 'COMPLETED') {
                    $refInsert['decision'] = 'ACCEPT';
                    $refInsert['first_action_at'] = $now;
                    $refInsert['referral_assigned_at'] = $now;
                }

                DB::table('referrals')->insert($refInsert);

                // ────────────────────────────────────────────
                // 7. Milestones
                // ────────────────────────────────────────────

                // Referral received milestone (for non-PENDING referrals)
                if ($caseData['referral_status'] !== 'PENDING') {
                    DB::table('milestones')->insert([
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received by the agency.',
                        'refr_id' => $refId,
                        'user_id' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Services Rendered + Case Closed milestones (for COMPLETED referrals)
                if ($caseData['referral_status'] === 'COMPLETED') {
                    DB::table('milestones')->insert([
                        'id' => (string) Str::uuid(),
                        'title' => 'Services Rendered',
                        'description' => 'All required services have been provided.',
                        'refr_id' => $refId,
                        'user_id' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('milestones')->insert([
                        'id' => (string) Str::uuid(),
                        'title' => 'Case Closed',
                        'description' => 'Case successfully closed.',
                        'refr_id' => $refId,
                        'user_id' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
