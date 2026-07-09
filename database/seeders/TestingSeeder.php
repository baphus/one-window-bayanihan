<?php

namespace Database\Seeders;

/*
 * ┌─────────────────────────────────────────────────────────────────────────────
 * │ TESTING SEEDER FOR ONE WINDOW BAYANIHAN
 * │
 * │ FK-dependent insertion order (parent → children):
 * │   1. clients                    (no FK dependencies)
 * │   2. client_addresses           (FK → clients.id)
 * │   3. client_employments         (FK → clients.id)
 * │   4. next_of_kin                (FK → clients.id)
 * │   5. cases                      (FK → clients.id, case_categories.id, case_issues.id, users.id)
 * │   6. referrals                  (FK → cases.id, agencies.id)
 * │   7. milestones                 (FK → referrals.id, users.id)
 * │   8. feedback                   (FK → cases.id, agencies.id, referrals.id; UNIQUE case+agency+referral)
 * │   9. feedback_servqual_responses (FK → feedback.id)
 * │  10. alerts                     (FK → users.id)
 * │  11. audit_logs                 (FK → users.id)
 * │
 * │ Total rows: ~4000
 * │ Works on: PostgreSQL
 * │ Idempotent: guarded by DB::table('clients')->count() > 0
 * │ No Eloquent, no factories
 * └─────────────────────────────────────────────────────────────────────────────
 */

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestingSeeder extends Seeder
{
    public function run(): void
    {
        // =====================================================================
        // 0. REFERENCE SEEDERS — always called (they are idempotent)
        // =====================================================================

        $this->call([
            AgencySeeder::class,
            ServiceSeeder::class,
            CaseCategorySeeder::class,
            CaseIssueSeeder::class,
            ProductionSeeder::class,
            SystemSettingSeeder::class,
            DefaultServqualQuestionsSeeder::class,
        ]);

        // =====================================================================
        // CREATE TEST USERS — always runs, idempotent via updateOrInsert
        // =====================================================================

        $now = now();

        $agenciesList = DB::table('agencies')->select('id', 'slug')->get()->keyBy('slug');
        $dmwId = $agenciesList['dmw']->id ?? null;

        // ── Agency users (AGENCY role, linked to their agency) ────────────

        $agencyUsers = [
            'owwa' => ['name' => 'OWWA',           'email' => 'owwa@bayanihan.gov.ph'],
            'dswd' => ['name' => 'DSWD',           'email' => 'dswd@bayanihan.gov.ph'],
            'doh' => ['name' => 'DOH',            'email' => 'doh@bayanihan.gov.ph'],
            'law-center-inc' => ['name' => 'Law Center Inc.', 'email' => 'law-center-inc@bayanihan.gov.ph'],
            'province-cebu' => ['name' => 'Province of Cebu', 'email' => 'province-cebu@bayanihan.gov.ph'],
            'tesda' => ['name' => 'TESDA',          'email' => 'tesda@bayanihan.gov.ph'],
            'city-cebu' => ['name' => 'Cebu City',      'email' => 'city-cebu@bayanihan.gov.ph'],
            'dole' => ['name' => 'DOLE',           'email' => 'dole@bayanihan.gov.ph'],
            'dmw' => ['name' => 'DMW',            'email' => 'dmw@bayanihan.gov.ph'],
        ];

        foreach ($agencyUsers as $slug => $spec) {
            $agency = $agenciesList[$slug] ?? null;
            if (! $agency) {
                continue;
            }
            DB::table('users')->updateOrInsert(
                ['email' => $spec['email']],
                [
                    'id' => DB::table('users')->where('email', $spec['email'])->value('id') ?? (string) Str::uuid(),
                    'name' => $spec['name'],
                    'password' => Hash::make('P@ssw0rd!'),
                    'role' => 'AGENCY',
                    'agcy_id' => $agency->id,
                    'is_active' => true,
                    'email_verified_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        // ── Case Manager (CASE_MANAGER role, under DMW) ───────────────────

        DB::table('users')->updateOrInsert(
            ['email' => 'case@bayanihan.gov.ph'],
            [
                'id' => DB::table('users')->where('email', 'case@bayanihan.gov.ph')->value('id') ?? (string) Str::uuid(),
                'name' => 'Case Manager',
                'password' => Hash::make('P@ssw0rd!'),
                'role' => 'CASE_MANAGER',
                'agcy_id' => $dmwId,
                'is_active' => true,
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        // ── Administrator (ADMIN role, under DMW) ─────────────────────────

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@bayanihan.gov.ph'],
            [
                'id' => DB::table('users')->where('email', 'admin@bayanihan.gov.ph')->value('id') ?? (string) Str::uuid(),
                'name' => 'System Administrator',
                'password' => Hash::make('P@ssw0rd!'),
                'role' => 'ADMIN',
                'agcy_id' => $dmwId,
                'is_active' => true,
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        // =====================================================================
        // IDEMPOTENCY GUARD — skip all bulk inserts if data already exists
        // =====================================================================

        // Guard: skip if testing data was already seeded.
        // Using > 10 threshold because ProductionSeeder creates 3 baseline clients;
        // once TestingSeeder has run there will be 1000+ clients.
        if (DB::table('clients')->count() > 10) {
            return;
        }

        // =====================================================================
        // GLOBALS
        // =====================================================================

        // ---------------------------------------------------------------------
        // Reference lookups
        // ---------------------------------------------------------------------

        /** @var Collection $agencies slug-keyed */
        $agencies = DB::table('agencies')->select('id', 'slug')->get()->keyBy('slug');
        $userIds = DB::table('users')->pluck('id')->toArray();
        $caseManagerId = DB::table('users')->where('email', 'case@bayanihan.gov.ph')->value('id')
            ?? DB::table('users')->where('email', 'admin@bayanihan.gov.ph')->value('id');
        $categoryIds = DB::table('case_categories')->pluck('id')->toArray();
        $issueIds = DB::table('case_issues')->pluck('id')->toArray();
        $agencyIds = DB::table('agencies')->pluck('id')->toArray();
        $allServiceIds = DB::table('services')->pluck('id', 'agcy_id')->toArray();

        $firstServicePerAgency = [];
        foreach ($agencies as $agency) {
            $name = DB::table('services')
                ->where('agcy_id', $agency->id)
                ->orderBy('name')
                ->value('name');
            $firstServicePerAgency[$agency->id] = $name ?? 'General Assistance';
        }

        $servqualSettings = DB::table('system_settings')
            ->where('key', 'default_servqual_questions')
            ->value('value');
        $servqualQuestions = json_decode($servqualSettings ?? '[]', true);

        // ---------------------------------------------------------------------
        // Filipino name pools
        // ---------------------------------------------------------------------

        $surnames = [
            'Dela Cruz', 'Santos', 'Reyes', 'Ramos', 'Mendoza', 'Garcia',
            'Martinez', 'Flores', 'Cruz', 'Aquino', 'Castro', 'Bautista',
            'Villanueva', 'Fernandez', 'Lopez', 'Gonzales', 'Rodriguez',
            'Rivera', 'Jimenez', 'Tan', 'Mercado', 'Gonzaga', 'Salvador',
            'Pascual', 'De Leon', 'Soriano', 'Navarro', 'Acosta',
            'Delos Santos', 'Manalo', 'Santiago', 'Ferrer', 'Salazar',
            'Gutierrez', 'Buenaventura', 'Tolentino', 'Panganiban',
            'Sarmiento', 'Gatdula', 'Dimaculangan', 'Kalaw', 'Sangalang',
            'Lacsamana',
        ];

        $femaleNames = [
            'Maria', 'Ana', 'Lucia', 'Cristina', 'Rosario', 'Teresa',
            'Josefina', 'Dolores', 'Guadalupe', 'Concepcion', 'Caridad',
            'Lourdes', 'Milagros', 'Erlinda', 'Corazon', 'Luzviminda',
            'Perla', 'Bella', 'Fe', 'Grace', 'Hope', 'Joy', 'Rose',
            'Pearl', 'Gemma', 'Donna', 'Regine',
        ];

        $maleNames = [
            'Juan', 'Jose', 'Carlos', 'Antonio', 'Manuel', 'Francisco',
            'Pedro', 'Miguel', 'Ramon', 'Benito', 'Eduardo', 'Ernesto',
            'Rodrigo', 'Gregorio', 'Vicente', 'Fernando', 'Roberto',
            'Mario', 'Danilo', 'Ricardo', 'Alberto', 'Jaime', 'Andres',
            'Felipe', 'Gerardo', 'Arturo', 'Efren', 'Nestor', 'Rolando',
        ];

        $middleInitials = [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'L',
            'M', 'N', 'P', 'R', 'S', 'T', 'V',
        ];

        $suffixes = ['Jr.', 'Sr.', 'III'];

        // ---------------------------------------------------------------------
        // Address data (Central Visayas)
        // ---------------------------------------------------------------------

        $cities = [
            'Cebu City', 'Lapu-Lapu City', 'Mandaue City', 'Talisay City',
            'Toledo City', 'Danao City', 'Carcar City', 'Naga City',
            'Bogo City', 'Minglanilla',
        ];

        $barangays = [
            'Poblacion', 'Mabini', 'Rizal', 'Lahug', 'Banilad', 'Guadalupe',
            'Basak', 'Tisa', 'Punta Princesa', 'Mambaling', 'Bulacao',
            'Inayawan', 'Labangon', 'Sambag 1', 'Sambag 2', 'Kamputhaw',
            'Kasambagan', 'Ermita', 'Capitol Site', 'Suba', 'San Nicolas',
            'Sawang Calero',
        ];

        // ---------------------------------------------------------------------
        // Employment data (OFW)
        // ---------------------------------------------------------------------

        $countries = [
            'Saudi Arabia', 'UAE', 'Hong Kong', 'Taiwan', 'Singapore',
            'Qatar', 'Kuwait', 'Japan', 'Malaysia', 'South Korea',
        ];

        $employers = [
            'Saudi Construction Co.', 'Dubai Hospitality Group',
            'HK Domestic Agency', 'Taiwan Electronics Inc.',
            'Singapore Marine Services', 'Qatar Petroleum Services',
            'Kuwait Medical Center', 'Tokyo Manufacturing Co.',
            'Kuala Lumpur Services', 'Seoul Tech Corp',
        ];

        $positions = [
            'Domestic Worker', 'Heavy Equipment Operator',
            'Production Supervisor', 'Nurse', 'Engineer', 'Driver',
            'Clerk', 'Sales Assistant', 'Technician', 'Laborer',
        ];

        // ---------------------------------------------------------------------
        // Other data pools
        // ---------------------------------------------------------------------

        $vulnerabilities = [
            'PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person',
            'None', null,
        ];

        $relationships = ['Spouse', 'Parent', 'Sibling', 'Child'];

        $feedbackComments = [
            'Maayos ang serbisyo ng ahensya. Salamat po!',
            'Mabilis ang processing ng mga documents.',
            'Magalang at matulungin ang mga staff.',
            'Sana po mapabilis pa ang proseso.',
            'Satisfied naman ako sa assistance na natanggap.',
            'Mabagal minsan ang response pero okay naman ang resulta.',
            'Malaking tulong ito sa mga OFW na katulad ko.',
            'Maayos ang pag-handle ng aking kaso.',
            'Salamat sa mabilis na action sa aking reklamo.',
            'Maganda ang serbisyo, maraming salamat!',
            'Excellent service from the agency staff.',
            'Very helpful and responsive to my concerns.',
        ];

        // Shuffle name pools for better distribution
        shuffle($femaleNames);
        shuffle($maleNames);
        shuffle($surnames);

        // =====================================================================
        // 1. CLIENTS  (1000 rows)
        // =====================================================================

        $clients = [];
        $clientIds = [];

        DB::beginTransaction();

        for ($i = 0; $i < 1000; $i++) {
            $id = (string) Str::uuid();
            $clientIds[] = $id;

            $sex = $i < 500 ? 'MALE' : 'FEMALE';
            $namePool = $sex === 'MALE' ? $maleNames : $femaleNames;
            $firstName = $namePool[$i % count($namePool)];
            $lastName = $surnames[$i % count($surnames)];

            $hasMiddle = rand(0, 100) < 60;
            $hasSuffix = rand(0, 100) < 3;

            $dobYear = rand(1940, 2005);
            $dobMonth = rand(1, 12);
            $dobDay = rand(1, 28);

            $clients[] = [
                'id' => $id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_initial' => $hasMiddle ? $middleInitials[array_rand($middleInitials)] : null,
                'suffix' => $hasSuffix ? $suffixes[array_rand($suffixes)] : null,
                'date_of_birth' => sprintf('%d-%02d-%02d', $dobYear, $dobMonth, $dobDay),
                'sex' => $sex,
                'email' => strtolower($firstName).'.'.strtolower(str_replace(' ', '', $lastName))
                    .rand(1, 9999).'@email.com',
                'contact_number' => '09'.str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT),
                'created_at' => now()->subDays(rand(0, 180)),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($clients, 100) as $chunk) {
            DB::table('clients')->insert($chunk);
        }

        DB::commit();

        // Free memory
        unset($clients);

        // =====================================================================
        // 2. CLIENT ADDRESSES  (1000 rows — one per client)
        // =====================================================================

        $addresses = [];

        DB::beginTransaction();

        for ($i = 0; $i < 1000; $i++) {
            $addresses[] = [
                'id' => (string) Str::uuid(),
                'client_id' => $clientIds[$i],
                'region' => 'Central Visayas',
                'province' => 'Cebu',
                'city_municipality' => $cities[array_rand($cities)],
                'barangay' => $barangays[array_rand($barangays)],
                'street' => rand(1, 999).' '.$barangays[array_rand($barangays)].' St',
                'created_at' => now()->subDays(rand(0, 180)),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($addresses, 100) as $chunk) {
            DB::table('client_addresses')->insert($chunk);
        }

        DB::commit();

        unset($addresses);

        // =====================================================================
        // 3. CLIENT EMPLOYMENTS  (1000 rows — one per client)
        // =====================================================================

        $employments = [];

        DB::beginTransaction();

        for ($i = 0; $i < 1000; $i++) {
            $cntIdx = array_rand($countries);
            $startYear = rand(2018, 2024);
            $endYear = $startYear + rand(1, 3);

            $employments[] = [
                'id' => (string) Str::uuid(),
                'client_id' => $clientIds[$i],
                'employer_name' => $employers[array_rand($employers)],
                'position' => $positions[array_rand($positions)],
                'last_position' => $positions[array_rand($positions)],
                'country' => $countries[$cntIdx],
                'last_country' => $countries[array_rand($countries)],
                'start_date' => sprintf('%d-%02d-01', $startYear, rand(1, 12)),
                'end_date' => sprintf('%d-%02d-01', $endYear, rand(1, 12)),
                'date_of_arrival' => now()->subDays(rand(0, 180))->format('Y-m-d'),
                'created_at' => now()->subDays(rand(0, 180)),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($employments, 100) as $chunk) {
            DB::table('client_employments')->insert($chunk);
        }

        DB::commit();

        unset($employments);

        // =====================================================================
        // 4. NEXT OF KIN  (500 rows — first 500 clients, one each)
        // =====================================================================

        $kinRecords = [];

        DB::beginTransaction();

        for ($i = 0; $i < 500; $i++) {
            $sex = $i < 250 ? 'MALE' : 'FEMALE';
            $namePool = $sex === 'MALE' ? $maleNames : $femaleNames;
            $kinFirstName = $namePool[array_rand($namePool)];

            $kinRecords[] = [
                'id' => (string) Str::uuid(),
                'client_id' => $clientIds[$i],
                'first_name' => $kinFirstName,
                'last_name' => $surnames[array_rand($surnames)],
                'middle_initial' => rand(0, 1) ? $middleInitials[array_rand($middleInitials)] : null,
                'relationship' => $relationships[array_rand($relationships)],
                'is_primary' => rand(0, 100) < 70,
                'phone_number' => '09'.str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT),
                'email' => strtolower($kinFirstName).'.'.strtolower(str_replace(' ', '', $surnames[array_rand($surnames)])).rand(1, 999).'@email.com',
                'full_address' => rand(1, 999).' '.$barangays[array_rand($barangays)].' St, '
                    .$cities[array_rand($cities)].', Cebu',
                'region' => 'Central Visayas',
                'province' => 'Cebu',
                'city_municipality' => $cities[array_rand($cities)],
                'barangay' => $barangays[array_rand($barangays)],
                'street' => rand(1, 999).' '.$barangays[array_rand($barangays)].' St',
                'sort_order' => 0,
                'created_at' => now()->subDays(rand(0, 180)),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($kinRecords, 100) as $chunk) {
            DB::table('next_of_kin')->insert($chunk);
        }

        DB::commit();

        unset($kinRecords);

        // =====================================================================
        // 5. CASES  (560 rows)
        // =====================================================================

        // We assign the first 560 clients to cases. The remaining 440 clients
        // exist in the database but have no case — that's realistic.
        $casesToCreate = 560;
        $caseCounter = 1;
        $usedTrackers = [];
        $datePrefix = now()->format('Ymd');

        // Status distribution: 40% OPEN, 30% CLOSED, 20% DRAFT, 10% ARCHIVED
        $caseStatusDistribution = [];
        for ($i = 0; $i < $casesToCreate; $i++) {
            if ($i < 224) {
                $caseStatusDistribution[] = 'OPEN';
            } elseif ($i < 392) {
                $caseStatusDistribution[] = 'CLOSED';
            } elseif ($i < 504) {
                $caseStatusDistribution[] = 'DRAFT';
            } else {
                $caseStatusDistribution[] = 'ARCHIVED';
            }
        }
        shuffle($caseStatusDistribution);

        $cases = [];
        $caseIds = [];
        $caseClientMap = []; // case_id => client_id

        DB::beginTransaction();

        for ($i = 0; $i < $casesToCreate; $i++) {
            $caseId = (string) Str::uuid();
            $caseIds[] = $caseId;
            $status = $caseStatusDistribution[$i];

            // Unique case_number
            $caseNumber = 'CASE-'.$datePrefix.'-'.str_pad($caseCounter++, 4, '0', STR_PAD_LEFT);

            // Unique tracker_number
            do {
                $tracker = 'OWBAP-'.strtoupper(Str::random(8));
            } while (isset($usedTrackers[$tracker]));
            $usedTrackers[$tracker] = true;

            // Client assignment — DRAFT cases may or may not have a client
            $assignClient = $status !== 'DRAFT' || rand(0, 100) < 50;
            $clientId = $assignClient ? $clientIds[$i % 1000] : null;
            if ($clientId) {
                $caseClientMap[$caseId] = $clientId;
            }

            $hasCategory = rand(0, 100) < 80;
            $hasIssue = rand(0, 100) < 70;

            // Always include ALL columns so every row in a batch has identical keys (required by PostgreSQL)
            $closedAt = null;

            if ($status === 'CLOSED') {
                $closedAt = now()->subDays(rand(1, 90));
            } elseif ($status === 'ARCHIVED') {
                // no special handling
            }

            $cases[] = [
                'id' => $caseId,
                'case_number' => $caseNumber,
                'client_type' => rand(0, 100) < 80 ? 'OFW' : 'NON-OFW',
                'vulnerability_indicator' => $vulnerabilities[array_rand($vulnerabilities)],
                'nok_vulnerability_indicator' => null,
                'tracker_number' => $tracker,
                'summary' => 'Test case for '.($clientId ? 'client' : 'walk-in').' — '.$status,
                'status' => $status,
                'closed_at' => $closedAt,
                'consent_given_at' => null,
                'user_id' => $caseManagerId,
                'client_id' => $clientId,
                'category_id' => $hasCategory ? $categoryIds[array_rand($categoryIds)] : null,
                'case_issue_id' => $hasIssue ? $issueIds[array_rand($issueIds)] : null,
                'created_at' => now()->subDays(rand(0, 180)),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($cases, 100) as $chunk) {
            DB::table('cases')->insert($chunk);
        }

        DB::commit();

        unset($cases);

        // =====================================================================
        // 6. REFERRALS  (variable — target 896)
        //    Distribution: OPEN×2, CLOSED×2, DRAFT×0, ARCHIVED×2
        //    Referral status: 30% PENDING, 25% PROCESSING, 15% FOR_COMPLIANCE,
        //                     20% COMPLETED, 10% REJECTED
        // =====================================================================

        $referralStatusPool = [];
        for ($i = 0; $i < 100; $i++) {
            if ($i < 30) {
                $referralStatusPool[] = 'PENDING';
            } elseif ($i < 55) {
                $referralStatusPool[] = 'PROCESSING';
            } elseif ($i < 70) {
                $referralStatusPool[] = 'FOR_COMPLIANCE';
            } elseif ($i < 90) {
                $referralStatusPool[] = 'COMPLETED';
            } else {
                $referralStatusPool[] = 'REJECTED';
            }
        }

        $referrals = [];
        $referralIds = [];
        $completedReferralIds = []; // for feedback later
        $completedReferralInfo = []; // [refId => ['case_id' => ..., 'agcy_id' => ...]]

        DB::beginTransaction();

        foreach ($caseIds as $caseId) {
            $caseInsert = DB::table('cases')->where('id', $caseId)->first();
            if (! $caseInsert) {
                continue;
            }

            $caseStatus = $caseInsert->status;

            // DRAFT cases get 0 referrals; all others get 2
            if ($caseStatus === 'DRAFT') {
                continue;
            }

            for ($r = 0; $r < 2; $r++) {
                $refId = (string) Str::uuid();
                $referralIds[] = $refId;

                // Pick a random agency
                $agencyId = $agencyIds[array_rand($agencyIds)];
                $serviceName = $firstServicePerAgency[$agencyId] ?? 'General Assistance';
                $refStatus = $referralStatusPool[array_rand($referralStatusPool)];

                // Determine conditional fields (set all to avoid PostgreSQL column count mismatch in batches)
                $decision = null;
                $decisionComment = null;
                $firstActionAt = null;
                $referralAssignedAt = null;

                if ($refStatus === 'COMPLETED') {
                    $decision = 'ACCEPT';
                    $decisionComment = null;
                    $firstActionAt = now()->subDays(rand(1, 90));
                    $referralAssignedAt = now()->subDays(rand(1, 90));

                    $completedReferralIds[] = $refId;
                    $completedReferralInfo[$refId] = [
                        'case_id' => $caseId,
                        'agcy_id' => $agencyId,
                    ];
                } elseif ($refStatus === 'REJECTED') {
                    $decision = 'REJECT';
                    $decisionComment = 'Requirements not met';
                } elseif ($refStatus === 'PROCESSING') {
                    $firstActionAt = now()->subDays(rand(1, 30));
                } elseif ($refStatus === 'FOR_COMPLIANCE') {
                    $firstActionAt = now()->subDays(rand(1, 30));
                }

                $referrals[] = [
                    'id' => $refId,
                    'required_services' => $serviceName,
                    'notes' => rand(0, 100) < 60 ? 'Referral notes for '.$caseStatus.' case' : null,
                    'status' => $refStatus,
                    'decision' => $decision,
                    'decision_comment' => $decisionComment,
                    'case_id' => $caseId,
                    'agcy_id' => $agencyId,
                    'first_action_at' => $firstActionAt,
                    'referral_assigned_at' => $referralAssignedAt,
                    'created_at' => now()->subDays(rand(0, 180)),
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($referrals, 100) as $chunk) {
            DB::table('referrals')->insert($chunk);
        }

        DB::commit();

        unset($referrals);

        // =====================================================================
        // 7. MILESTONES  (based on referral status)
        //    PENDING: 0, PROCESSING: 2, FOR_COMPLIANCE: 2,
        //    COMPLETED: 4, REJECTED: 1
        // =====================================================================

        $allReferrals = DB::table('referrals')->select('id', 'status')->get();
        $milestones = [];

        DB::beginTransaction();

        foreach ($allReferrals as $ref) {
            switch ($ref->status) {
                case 'PROCESSING':
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received by the agency.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => now()->subDays(rand(0, 180)),
                        'updated_at' => $now,
                    ];
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Documents Submitted',
                        'description' => 'Required documents have been submitted.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => now()->subDays(rand(0, 180)),
                        'updated_at' => $now,
                    ];
                    break;

                case 'FOR_COMPLIANCE':
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received by the agency.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => now()->subDays(rand(0, 180)),
                        'updated_at' => $now,
                    ];
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Compliance Check',
                        'description' => 'Compliance requirements have been checked.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => now()->subDays(rand(0, 180)),
                        'updated_at' => $now,
                    ];
                    break;

                case 'COMPLETED':
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received by the agency.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => now()->subDays(rand(0, 180)),
                        'updated_at' => $now,
                    ];
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Documents Submitted',
                        'description' => 'Required documents have been submitted.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => now()->subDays(rand(0, 180)),
                        'updated_at' => $now,
                    ];
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Services Rendered',
                        'description' => 'All required services have been provided.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => now()->subDays(rand(0, 180)),
                        'updated_at' => $now,
                    ];
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Case Closed',
                        'description' => 'Case successfully closed.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => now()->subDays(rand(0, 180)),
                        'updated_at' => $now,
                    ];
                    break;

                case 'REJECTED':
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received by the agency.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => now()->subDays(rand(0, 180)),
                        'updated_at' => $now,
                    ];
                    break;

                    // PENDING: 0 milestones — no break needed
            }
        }

        foreach (array_chunk($milestones, 100) as $chunk) {
            DB::table('milestones')->insert($chunk);
        }

        DB::commit();

        unset($milestones);

        // =====================================================================
        // 8. FEEDBACK  (50 rows — random subset of COMPLETED referrals)
        //    Uses $usedFeedbackKeys set to avoid UNIQUE(case_id, agency_id, referral_id)
        // =====================================================================

        // Pick 50 completed referrals (shuffle the list first)
        shuffle($completedReferralIds);
        $feedbackTargets = array_slice($completedReferralIds, 0, 50);

        $feedbackRecords = [];
        $feedbackIds = [];
        $usedFeedbackKeys = [];

        DB::beginTransaction();

        foreach ($feedbackTargets as $refId) {
            $info = $completedReferralInfo[$refId] ?? null;
            if (! $info) {
                continue;
            }

            $key = $info['case_id'].'|'.$info['agcy_id'].'|'.$refId;
            if (isset($usedFeedbackKeys[$key])) {
                continue;
            }
            $usedFeedbackKeys[$key] = true;

            $fbId = (string) Str::uuid();
            $feedbackIds[] = $fbId;
            $feedbackRecords[] = [
                'id' => $fbId,
                'case_id' => $info['case_id'],
                'agency_id' => $info['agcy_id'],
                'referral_id' => $refId,
                'service_name' => DB::table('referrals')->where('id', $refId)->value('required_services'),
                'overall_rating' => rand(0, 100) < 20 ? 3 : (rand(0, 1) ? 4 : 5),
                'comments' => $feedbackComments[array_rand($feedbackComments)],
                'created_at' => now()->subDays(rand(0, 180)),
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($feedbackRecords, 50) as $chunk) {
            DB::table('feedback')->insert($chunk);
        }

        DB::commit();

        unset($feedbackRecords);

        // =====================================================================
        // 9. FEEDBACK SERVQUAL RESPONSES  (22 per feedback = 1100+ rows)
        // =====================================================================

        $servqualRows = [];

        DB::beginTransaction();

        foreach ($feedbackIds as $fbId) {
            foreach ($servqualQuestions as $q) {
                $servqualRows[] = [
                    'id' => (string) Str::uuid(),
                    'feedback_id' => $fbId,
                    'question_id' => (string) Str::uuid(),
                    'question_text' => $q['question'] ?? 'Service quality question',
                    'dimension' => $q['dimension'] ?? 'General',
                    'expectation' => rand(3, 5),
                    'perception' => rand(1, 5),
                    'created_at' => now()->subDays(rand(0, 180)),
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($servqualRows, 100) as $chunk) {
            DB::table('feedback_servqual_responses')->insert($chunk);
        }

        DB::commit();

        unset($servqualRows);

        // =====================================================================
        // 10. AUDIT LOGS  (50 rows)
        //     Only CREATE actions for cases and referrals
        //     Table uses `module`, `action`, `entity_id`, `timestamp` columns
        // =====================================================================

        // Collect some case and referral IDs for entity_id references
        $allCaseIds = DB::table('cases')->pluck('id')->toArray();
        $allReferralIds = DB::table('referrals')->pluck('id')->toArray();

        $auditLogs = [];

        DB::beginTransaction();

        for ($i = 0; $i < 50; $i++) {
            $isCase = rand(0, 1) === 0;
            $entityId = $isCase
                ? $allCaseIds[array_rand($allCaseIds)]
                : $allReferralIds[array_rand($allReferralIds)];

            $auditLogs[] = [
                'id' => (string) Str::uuid(),
                'action' => 'CREATE',
                'module' => $isCase ? 'case' : 'referral',
                'entity_id' => $entityId,
                'description' => $isCase ? 'Case created' : 'Referral created',
                'old_value' => null,
                'new_value' => json_encode(['status' => $isCase ? 'OPEN' : 'PENDING']),
                'user_id' => $userIds[array_rand($userIds)],
                'timestamp' => now()->subDays(rand(0, 180)),
            ];
        }

        foreach (array_chunk($auditLogs, 50) as $chunk) {
            DB::table('audit_logs')->insert($chunk);
        }

        DB::commit();

        unset($auditLogs);
    }
}
