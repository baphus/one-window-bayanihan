<?php

namespace Database\Seeders;

/*
 * ┌─────────────────────────────────────────────────────────────────────────────
 * │ TESTING SEEDER FOR ONE WINDOW BAYANIHAN
 * │
 * │ FK-dependent insertion order (parent → children):
 * │   1. cases                      (FK → users.id; case-owned intake facts)
 * │   2. referrals                  (FK → cases.id, agencies.id, case_categories.id)
 * │   3. milestones                 (FK → referrals.id, users.id)
 * │   4. audit_logs                 (FK → users.id)
 * │
 * │ Cases carry their own intake snapshot. No shared clients, addresses,
 * │ employments or next-of-kin rows are created: `cases.user_id` is the staff
 * │ creator and `cases.ofw_user_id` stays null for these synthetic records.
 * │
 * │ Total rows: ~4000
 * │ Works on: PostgreSQL
 * │ Idempotent: guarded by DB::table('cases')->count() > 10
 * │ No Eloquent, no factories
 * └─────────────────────────────────────────────────────────────────────────────
 */

use App\Models\AuditLog;
use App\Services\CaseNumberGenerator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // =====================================================================
        // 0. REFERENCE SEEDERS — always called (they are idempotent)
        // =====================================================================

        $this->call([
            AgencySeeder::class,
            ServiceSeeder::class,
            CaseCategorySeeder::class,
            ProductionSeeder::class,
            SystemSettingSeeder::class,
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
                    'onboarding_completed_at' => null,
                    'onboarding_step' => null,
                    'seen_page_guides' => null,
                    'checklist_progress' => null,
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
                'onboarding_completed_at' => null,
                'onboarding_step' => null,
                'seen_page_guides' => null,
                'checklist_progress' => null,
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
        // Using > 10 threshold because ProductionSeeder creates the baseline
        // administrator; once TestingSeeder has run there are 850+ cases.
        if (DB::table('cases')->count() > 10) {
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
        if ($caseManagerId === null) {
            throw new \LogicException('Seed a case manager before testing cases.');
        }
        $adminId = DB::table('users')->where('email', 'admin@bayanihan.gov.ph')->value('id');
        if ($adminId === null) {
            throw new \LogicException('Seed an administrator before testing cases.');
        }
        $categoryIds = DB::table('case_categories')->pluck('id')->toArray();
        if ($categoryIds === []) {
            throw new \LogicException('Seed managed referral categories before testing referrals.');
        }
        $agencyIds = DB::table('agencies')->pluck('id')->toArray();
        if ($agencyIds === []) {
            throw new \LogicException('Seed agencies before testing referrals.');
        }
        $allServiceIds = DB::table('services')->pluck('id', 'agcy_id')->toArray();

        $firstServicePerAgency = [];
        foreach ($agencies as $agency) {
            $name = DB::table('services')
                ->where('agcy_id', $agency->id)
                ->orderBy('name')
                ->value('name');
            $firstServicePerAgency[$agency->id] = $name ?? 'General Assistance';
        }

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
        // Address data — see resources/js/data/testingAddresses.ts for the
        // canonical TypeScript definition of these regions.
        //
        // Region VII (Central Visayas) per EO 64 (2024):
        //   Cebu, Bohol
        // ---------------------------------------------------------------------

        $regionVIIProvinces = [
            [
                'province' => 'Cebu',
                'cities' => [
                    'Cebu City', 'Lapu-Lapu City', 'Mandaue City', 'Talisay City',
                    'Toledo City', 'Danao City', 'Carcar City', 'Naga City',
                    'Bogo City', 'Minglanilla',
                ],
                'slots' => 8,
            ],
            [
                'province' => 'Bohol',
                'cities' => [
                    'Tagbilaran City', 'Panglao', 'Dauis', 'Tubigon', 'Talibon',
                    'Ubay', 'Carmen', 'Jagna', 'Loon', 'Anda',
                ],
                'slots' => 5,
            ],
        ];

        $locationCycle = [];
        foreach ($regionVIIProvinces as $provinceSpec) {
            for ($slot = 0; $slot < $provinceSpec['slots']; $slot++) {
                $locationCycle[] = $provinceSpec;
            }
        }

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

        $civilStatuses = ['SINGLE', 'MARRIED', 'WIDOWED', 'SEPARATED', 'ANNULLED'];
        $deploymentModes = ['AGENCY_HIRED', 'DIRECT_HIRED', 'TOURIST_VISIT', 'BUSINESS', 'INVITATION'];
        $employmentStatuses = ['ON_SITE', 'FOR_DEPLOYMENT', 'RETURNED'];
        $assessments = ['DISTRESSED_OFW', 'MEDICALLY_REPATRIATED', 'BURIAL_ASSISTANCE_CONCERN', 'POSSIBLE_LOCAL_EMPLOYMENT', 'PSYCHOSOCIAL_MEDICAL_INTERVENTION', 'SKILLS_ENHANCEMENT_TRAINING', 'LEGAL_ASSISTANCE'];
        $returnReasons = ['DISTRESSED_WORKER', 'TERMINATION', 'OTHERS'];

        // Shuffle name pools for better distribution
        shuffle($femaleNames);
        shuffle($maleNames);
        shuffle($surnames);

        // =====================================================================
        // 1. CASES  (850 rows, case-owned intake facts)
        // =====================================================================

        // Every synthetic case owns its intake snapshot: no shared clients,
        // addresses, employments or next-of-kin rows are created. `user_id` is
        // the staff creator; `ofw_user_id` stays null because these cases were
        // recorded on behalf of a walk-in applicant.
        $casesToCreate = 850;
        $caseNumbers = app(CaseNumberGenerator::class);
        $usedTrackers = [];

        // Status distribution: 40% OPEN, 30% CLOSED, 15% PENDING_REVIEW, 15% ARCHIVED
        $caseStatusDistribution = [];
        for ($i = 0; $i < $casesToCreate; $i++) {
            if ($i < 340) {
                $caseStatusDistribution[] = 'OPEN';
            } elseif ($i < 595) {
                $caseStatusDistribution[] = 'CLOSED';
            } elseif ($i < 723) {
                $caseStatusDistribution[] = 'PENDING_REVIEW';
            } else {
                $caseStatusDistribution[] = 'ARCHIVED';
            }
        }
        shuffle($caseStatusDistribution);

        $cases = [];
        $caseIds = [];
        $caseCreatedDates = []; // case_id => Carbon

        DB::beginTransaction();

        for ($i = 0; $i < $casesToCreate; $i++) {
            $caseId = (string) Str::uuid();
            $caseIds[] = $caseId;
            $status = $caseStatusDistribution[$i];

            $caseNumber = $caseNumbers->nextCaseNumber();

            // Unique tracker_number
            do {
                // Matches CaseNumberGenerator: Crockford base32, 10 characters,
                // excluding the ambiguous glyphs I, L, O and U.
                $tracker = 'OWBAP-'.collect(range(1, 10))
                    ->map(fn () => '0123456789ABCDEFGHJKMNPQRSTVWXYZ'[random_int(0, 31)])
                    ->implode('');
            } while (isset($usedTrackers[$tracker]));
            $usedTrackers[$tracker] = true;

            // Chronological: cases created between 180 and 5 days ago, distributed evenly
            $caseCreatedAt = now()->subDays(180 - intval($i * 175 / $casesToCreate))->addHours(rand(0, 12));
            $caseCreatedDates[$caseId] = $caseCreatedAt;

            $sex = $i < 500 ? 'MALE' : 'FEMALE';
            $namePool = $sex === 'MALE' ? $maleNames : $femaleNames;
            $firstName = $namePool[$i % count($namePool)];
            $lastName = $surnames[$i % count($surnames)];
            $location = $locationCycle[$i % count($locationCycle)];
            $city = $location['cities'][$i % count($location['cities'])];
            $country = $countries[array_rand($countries)];
            $returnReason = $returnReasons[array_rand($returnReasons)];
            $closedAt = null;

            if ($status === 'CLOSED') {
                $closedAt = $caseCreatedAt->copy()->addDays(rand(7, 60));
            }

            $isPending = $status === 'PENDING_REVIEW';

            $cases[] = [
                'id' => $caseId,
                'case_number' => $caseNumber,
                'vulnerability_indicator' => $vulnerabilities[array_rand($vulnerabilities)],
                'nok_vulnerability_indicator' => null,
                'tracker_number' => $tracker,
                'summary' => 'Synthetic intake case — '.$status,
                'status' => $status,
                'closed_at' => $closedAt,
                'consent_given_at' => $caseCreatedAt,
                'user_id' => $caseManagerId,
                'ofw_user_id' => null,
                'client_type' => 'OFW',
                'intake_date' => $caseCreatedAt->toDateString(),
                'first_name' => $firstName,
                'middle_name' => rand(0, 100) < 60 ? $middleInitials[array_rand($middleInitials)] : null,
                'last_name' => $lastName,
                'suffix' => rand(0, 100) < 3 ? $suffixes[array_rand($suffixes)] : null,
                'date_of_birth' => Crypt::encryptString($caseCreatedAt->copy()->subYears(rand(22, 65))->toDateString()),
                'sex' => $sex,
                'email' => strtolower($firstName).'.'.strtolower(str_replace(' ', '', $lastName))
                    .rand(1, 9999).'@email.com',
                'contact_number' => '09'.str_pad((string) rand(0, 999999999), 9, '0', STR_PAD_LEFT),
                'civil_status' => $civilStatuses[array_rand($civilStatuses)],
                'civil_status_other' => null,
                'deployment_mode' => $deploymentModes[array_rand($deploymentModes)],
                'employment_status' => $employmentStatuses[array_rand($employmentStatuses)],
                'address_region' => 'Central Visayas',
                'address_province' => $location['province'],
                'address_city_municipality' => $city,
                'address_barangay' => $barangays[array_rand($barangays)],
                'address_street' => Crypt::encryptString(rand(1, 999).' '.$barangays[array_rand($barangays)].' St'),
                'employment_employer_name' => Crypt::encryptString($employers[array_rand($employers)]),
                'employment_employer_address' => Crypt::encryptString($city.', '.$location['province']),
                'employment_position' => Crypt::encryptString($positions[array_rand($positions)]),
                'employment_country' => Crypt::encryptString($country),
                'employment_last_country' => Crypt::encryptString($country),
                'employment_last_position' => Crypt::encryptString($positions[array_rand($positions)]),
                'employment_start_date' => $caseCreatedAt->copy()->subYears(rand(1, 5))->toDateString(),
                'employment_end_date' => $caseCreatedAt->copy()->subDays(rand(10, 300))->toDateString(),
                'employment_date_of_arrival' => $caseCreatedAt->copy()->subDays(rand(1, 30))->toDateString(),
                'nok_name' => null,
                'nok_relationship' => null,
                'nok_contact_number' => null,
                'nok_email' => null,
                'staff_summary' => $isPending ? Crypt::encryptString('Synthetic pending intake for review.') : null,
                'proposed_assessments' => json_encode([$proposedAssessment = $assessments[array_rand($assessments)]]),
                'proposed_assessment_other' => null,
                'proposed_assessment_by' => $caseManagerId,
                'proposed_assessment_at' => $caseCreatedAt,
                'confirmed_assessments' => $isPending ? null : json_encode([$proposedAssessment]),
                'confirmed_assessment_other' => null,
                'confirmed_assessment_by' => $isPending ? null : $caseManagerId,
                'confirmed_assessment_at' => $isPending ? null : $caseCreatedAt,
                'consent_text_version' => 'case-intake-v1',
                'consent_method' => 'WRITTEN',
                'consent_evidence' => Crypt::encryptString('Synthetic signed consent retained.'),
                'consent_accepted_by' => $caseManagerId,
                'return_reason' => $returnReason,
                'missing_document_reasons' => null,
                'requested_document_slots' => null,
                'document_request_reason' => null,
                'document_requested_by' => null,
                'document_requested_at' => null,
                'reviewed_document_ids' => null,
                'assigned_case_manager_id' => $caseManagerId,
                'last_correction_reason' => null,
                'reviewed_by' => $isPending ? null : $caseManagerId,
                'reviewed_at' => $isPending ? null : $caseCreatedAt,
                'created_at' => $caseCreatedAt,
                'updated_at' => $closedAt ?? $caseCreatedAt->copy()->addDays(rand(0, 14)),
            ];
        }

        foreach (array_chunk($cases, 100) as $chunk) {
            DB::table('cases')->insert($chunk);
        }

        DB::commit();

        unset($cases);

        // =====================================================================
        // 6. REFERRALS  (variable — target 896)
        //    Distribution: OPEN×2, CLOSED×2, PENDING_REVIEW×0, ARCHIVED×2
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

        DB::beginTransaction();

        $caseCategories = [];
        foreach ($caseIds as $caseId) {
            $caseCategories[$caseId] = $categoryIds[array_rand($categoryIds)];
        }
        $caseDocumentAuditRows = [];
        foreach ($caseIds as $caseId) {
            $caseInsert = DB::table('cases')->where('id', $caseId)->first();
            if (! $caseInsert) {
                continue;
            }

            $caseStatus = $caseInsert->status;

            // PENDING_REVIEW cases get 0 referrals; all others get 2
            if ($caseStatus === 'PENDING_REVIEW') {
                continue;
            }

            $caseCreated = $caseCreatedDates[$caseId] ?? Carbon::parse($caseInsert->created_at);

            for ($r = 0; $r < 2; $r++) {
                $refId = (string) Str::uuid();
                $referralIds[] = $refId;

                // Pick a random agency
                $agencyId = $agencyIds[array_rand($agencyIds)];
                $serviceName = $firstServicePerAgency[$agencyId] ?? 'General Assistance';
                $refStatus = $referralStatusPool[array_rand($referralStatusPool)];

                // Referral created 1–3 days after the case
                $refCreatedAt = $caseCreated->copy()->addDays(rand(1, 3))->addHours(rand(0, 8));

                // Determine conditional fields
                $decision = null;
                $decisionComment = null;
                $firstActionAt = null;
                $referralAssignedAt = null;
                $refUpdatedAt = $refCreatedAt->copy();

                if ($refStatus === 'COMPLETED') {
                    $decision = 'ACCEPT';
                    $decisionComment = null;
                    $firstActionAt = $refCreatedAt->copy()->addDays(rand(1, 5));
                    $referralAssignedAt = $refCreatedAt->copy()->addDays(rand(1, 3));
                    $refUpdatedAt = $firstActionAt->copy()->addDays(rand(7, 30));
                } elseif ($refStatus === 'REJECTED') {
                    $decision = 'REJECT';
                    $decisionComment = 'Requirements not met';
                    $refUpdatedAt = $refCreatedAt->copy()->addDays(rand(1, 7));
                } elseif ($refStatus === 'PROCESSING') {
                    $firstActionAt = $refCreatedAt->copy()->addDays(rand(1, 5));
                    $refUpdatedAt = $firstActionAt->copy()->addDays(rand(1, 10));
                } elseif ($refStatus === 'FOR_COMPLIANCE') {
                    $firstActionAt = $refCreatedAt->copy()->addDays(rand(1, 5));
                    $refUpdatedAt = $firstActionAt->copy()->addDays(rand(3, 14));
                } else {
                    // PENDING — no status change yet
                    $refUpdatedAt = $refCreatedAt->copy();
                }

                $referrals[] = [
                    'id' => $refId,
                    'required_services' => $serviceName,
                    'category_id' => $caseCategories[$caseId],
                    'concern' => Crypt::encryptString('Synthetic referral concern for '.$serviceName),
                    'notes' => rand(0, 100) < 60 ? 'Referral notes for '.$caseStatus.' case' : null,
                    'status' => $refStatus,
                    'decision' => $decision,
                    'decision_comment' => $decisionComment,
                    'case_id' => $caseId,
                    'agcy_id' => $agencyId,
                    'first_action_at' => $firstActionAt,
                    'referral_assigned_at' => $referralAssignedAt,
                    'created_at' => $refCreatedAt,
                    'updated_at' => $refUpdatedAt,
                ];
            }
        }

        foreach (array_chunk($referrals, 100) as $chunk) {
            DB::table('referrals')->insert($chunk);
        }

        DB::commit();

        unset($referrals);

        $slotsByReason = [
            'DISTRESSED_WORKER' => ['passport_front_page', 'arrival_stamp', 'employment_contract', 'distress_certificate'],
            'TERMINATION' => ['passport_front_page', 'arrival_stamp', 'oec', 'termination_letter'],
            'OTHERS' => ['passport_front_page', 'arrival_stamp', 'work_visa', 'sworn_affidavit'],
        ];
        foreach ($caseIds as $caseId) {
            $case = DB::table('cases')->where('id', $caseId)->first();
            $slots = $slotsByReason[$case->return_reason];
            $documents = [];
            $reviewedDocumentIds = [];
            foreach ($slots as $slot) {
                if ($case->status === 'PENDING_REVIEW' && $slot === 'passport_front_page') {
                    continue;
                }
                $documentId = (string) Str::uuid();
                if ($case->status !== 'PENDING_REVIEW') {
                    $reviewedDocumentIds[] = $documentId;
                }
                $caseDocumentAuditRows[] = [
                    'id' => (string) Str::uuid(),
                    'action' => 'CREATE',
                    'module' => 'case_document',
                    'entity_id' => $documentId,
                    'description' => null,
                    'old_value' => null,
                    'new_value' => null,
                    'user_id' => $caseManagerId,
                    'timestamp' => Carbon::parse($case->created_at),
                ];
                $documents[] = [
                    'id' => $documentId,
                    'file_name' => str_replace('_', '-', $slot).'.pdf',
                    'file_path' => 'testing/case-documents/'.$documentId.'.pdf',
                    'file_type' => 'application/pdf',
                    'size' => rand(100_000, 2_000_000),
                    'case_id' => $caseId,
                    'user_id' => $caseManagerId,
                    'category' => $slot,
                    'referral_id' => null,
                    'created_at' => $case->created_at,
                    'updated_at' => $case->created_at,
                ];
            }
            foreach (array_chunk($documents, 100) as $chunk) {
                DB::table('case_documents')->insert($chunk);
            }
            DB::table('cases')->where('id', $caseId)->update([
                'reviewed_document_ids' => $case->status === 'PENDING_REVIEW' ? null : json_encode($reviewedDocumentIds),
                'missing_document_reasons' => $case->status === 'PENDING_REVIEW'
                    ? Crypt::encrypt(json_encode(['passport_front_page' => 'Pending synthetic document']), false)
                    : null,
            ]);
        }

        // =====================================================================
        // 7. MILESTONES  (based on referral status)
        //    PENDING: 0, PROCESSING: 2, FOR_COMPLIANCE: 2,
        //    COMPLETED: 4, REJECTED: 1
        // =====================================================================

        $allReferrals = DB::table('referrals')->select('id', 'status', 'created_at')->get();
        $milestones = [];

        DB::beginTransaction();

        foreach ($allReferrals as $ref) {
            $refCreated = Carbon::parse($ref->created_at);

            switch ($ref->status) {
                case 'PROCESSING':
                    $ms1Date = $refCreated->copy()->addDays(rand(1, 3))->addHours(rand(1, 12));
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received by the agency.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => $ms1Date,
                        'updated_at' => $ms1Date,
                    ];
                    $ms2Date = $ms1Date->copy()->addDays(rand(2, 7))->addHours(rand(1, 8));
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Documents Submitted',
                        'description' => 'Required documents have been submitted.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => $ms2Date,
                        'updated_at' => $ms2Date,
                    ];
                    break;

                case 'FOR_COMPLIANCE':
                    $ms1Date = $refCreated->copy()->addDays(rand(1, 3))->addHours(rand(1, 12));
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received by the agency.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => $ms1Date,
                        'updated_at' => $ms1Date,
                    ];
                    $ms2Date = $ms1Date->copy()->addDays(rand(3, 10))->addHours(rand(1, 8));
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Compliance Check',
                        'description' => 'Compliance requirements have been checked.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => $ms2Date,
                        'updated_at' => $ms2Date,
                    ];
                    break;

                case 'COMPLETED':
                    $ms1Date = $refCreated->copy()->addDays(rand(1, 2))->addHours(rand(1, 8));
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received by the agency.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => $ms1Date,
                        'updated_at' => $ms1Date,
                    ];
                    $ms2Date = $ms1Date->copy()->addDays(rand(2, 5))->addHours(rand(1, 8));
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Documents Submitted',
                        'description' => 'Required documents have been submitted.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => $ms2Date,
                        'updated_at' => $ms2Date,
                    ];
                    $ms3Date = $ms2Date->copy()->addDays(rand(3, 10))->addHours(rand(1, 8));
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Services Rendered',
                        'description' => 'All required services have been provided.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => $ms3Date,
                        'updated_at' => $ms3Date,
                    ];
                    $ms4Date = $ms3Date->copy()->addDays(rand(1, 5))->addHours(rand(1, 8));
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Case Closed',
                        'description' => 'Case successfully closed.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => $ms4Date,
                        'updated_at' => $ms4Date,
                    ];
                    break;

                case 'REJECTED':
                    $ms1Date = $refCreated->copy()->addDays(rand(1, 3))->addHours(rand(1, 8));
                    $milestones[] = [
                        'id' => (string) Str::uuid(),
                        'title' => 'Referral Received',
                        'description' => 'Referral received and reviewed by the agency.',
                        'refr_id' => $ref->id,
                        'user_id' => $userIds[array_rand($userIds)],
                        'created_at' => $ms1Date,
                        'updated_at' => $ms1Date,
                    ];
                    break;

                    // PENDING: 0 milestones
            }
        }

        foreach (array_chunk($milestones, 100) as $chunk) {
            DB::table('milestones')->insert($chunk);
        }

        DB::commit();

        unset($milestones);

        // =====================================================================
        // 8. AUDIT LOGS  (event-driven timeline, sequenced per case)
        //     Order per case: Case CREATED → Referral CREATED → Milestones →
        //     Referral COMPLETED/REJECTED → Case CLOSED/ARCHIVED
        // =====================================================================

        $allCases = DB::table('cases')->get();
        $allReferrals = DB::table('referrals')->get();
        $allMilestones = DB::table('milestones')->get();

        $referralsByCase = [];
        foreach ($allReferrals as $ref) {
            $referralsByCase[$ref->case_id][] = $ref;
        }

        $milestonesByRef = [];
        foreach ($allMilestones as $ms) {
            $milestonesByRef[$ms->refr_id][] = $ms;
        }

        $auditLogs = [];

        DB::beginTransaction();

        foreach ($allCases as $case) {
            $baseTs = Carbon::parse($case->created_at);

            // 1. Case CREATED (ALWAYS first — the opening event)
            $auditLogs[] = [
                'id' => (string) Str::uuid(),
                'action' => 'CREATE',
                'module' => 'case',
                'entity_id' => $case->id,
                'description' => null,
                'old_value' => null,
                'new_value' => json_encode([
                    'case_number' => $case->case_number,
                    'status' => $case->status,
                ]),
                'user_id' => $case->user_id,
                'timestamp' => $baseTs,
            ];

            $ts = $baseTs->copy();

            // Referrals for this case
            $caseRefs = $referralsByCase[$case->id] ?? [];
            foreach ($caseRefs as $ref) {
                // 2. Referral CREATED (comes after case creation)
                $ts = $ts->addMinutes(rand(30, 1440));
                $auditLogs[] = [
                    'id' => (string) Str::uuid(),
                    'action' => 'CREATE',
                    'module' => 'referral',
                    'entity_id' => $ref->id,
                    'description' => null,
                    'old_value' => null,
                    'new_value' => json_encode([
                        'required_services' => $ref->required_services,
                        'status' => $ref->status,
                    ]),
                    'user_id' => $case->user_id,
                    'timestamp' => $ts,
                ];

                // 3. Milestones (progress events after referral)
                $refMss = $milestonesByRef[$ref->id] ?? [];
                foreach ($refMss as $ms) {
                    $ts = $ts->addMinutes(rand(60, 2880));
                    $auditLogs[] = [
                        'id' => (string) Str::uuid(),
                        'action' => 'CREATE',
                        'module' => 'milestone',
                        'entity_id' => $ms->id,
                        'description' => null,
                        'old_value' => null,
                        'new_value' => json_encode([
                            'title' => $ms->title,
                            'description' => $ms->description,
                        ]),
                        'user_id' => $ms->user_id,
                        'timestamp' => $ts,
                    ];
                }

                // 4. Referral status change (COMPLETED or REJECTED — terminal for that referral)
                if ($ref->status === 'COMPLETED' || $ref->status === 'REJECTED') {
                    $ts = $ts->addMinutes(rand(30, 1440));
                    $auditLogs[] = [
                        'id' => (string) Str::uuid(),
                        'action' => 'UPDATE',
                        'module' => 'referral',
                        'entity_id' => $ref->id,
                        'description' => null,
                        'old_value' => json_encode(['status' => 'PROCESSING']),
                        'new_value' => json_encode([
                            'status' => $ref->status,
                            'decision' => $ref->decision,
                        ]),
                        'user_id' => $case->user_id,
                        'timestamp' => $ts,
                    ];
                }
            }

            // 5. Case status change (CLOSED or ARCHIVED — terminal event for the case)
            if ($case->status === 'CLOSED' || $case->status === 'ARCHIVED') {
                $ts = $ts->addMinutes(rand(30, 1440));
                $auditLogs[] = [
                    'id' => (string) Str::uuid(),
                    'action' => 'UPDATE',
                    'module' => 'case',
                    'entity_id' => $case->id,
                    'description' => null,
                    'old_value' => json_encode(['status' => 'OPEN']),
                    'new_value' => json_encode([
                        'status' => $case->status,
                        'closed_at' => $case->closed_at,
                    ]),
                    'user_id' => $case->user_id,
                    'timestamp' => $ts,
                ];
            }
        }

        // Hash-chain the audit rows in chain_seq (insertion) order. Bulk
        // inserts bypass the AuditLog::creating hook, so link prev_hash here
        // using the exact chainDigest() serialisation. Digests are computed
        // after the timeline above is fully built because the shared Carbon
        // instances keep being mutated as later rows are appended. Works on
        // an empty table (first row is the chain root, prev_hash null) and
        // chains off the existing last row otherwise.
        $previousDigest = AuditLog::orderBy('chain_seq', 'desc')->first()?->chainDigest();

        // old_value/new_value are jsonb columns: PostgreSQL normalises the
        // stored document (newer versions also reorder object keys), so the
        // digest must be computed from the normalised form the verifier will
        // read back — not from the raw pre-insert strings. Normalise through
        // the database itself so this stays correct on any server version.
        $rawPayloads = [];
        foreach ($auditLogs as $auditRow) {
            foreach (['old_value', 'new_value'] as $column) {
                if ($auditRow[$column] !== null) {
                    $rawPayloads[] = $auditRow[$column];
                }
            }
        }

        $normalisedByRaw = [];
        foreach (array_chunk(array_values(array_unique($rawPayloads)), 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?)'));
            $normalisedRows = DB::select(
                "SELECT (col::jsonb)::text AS normalised FROM (VALUES {$placeholders}) AS v(col)",
                $chunk
            );
            foreach ($chunk as $index => $raw) {
                $normalisedByRaw[$raw] = $normalisedRows[$index]->normalised;
            }
        }
        unset($rawPayloads);

        $chainDigestOf = function (array $row, ?string $prevHash) use ($normalisedByRaw): string {
            $encodeJson = static function (?string $json) use ($normalisedByRaw): string {
                if ($json === null) {
                    return json_encode(null, JSON_UNESCAPED_SLASHES);
                }

                return json_encode(json_decode($normalisedByRaw[$json], true), JSON_UNESCAPED_SLASHES);
            };

            $content = implode('|', [
                $row['id'],
                $row['action'],
                $row['module'],
                $row['entity_id'] ?? '',
                $row['user_id'] ?? '',
                $row['timestamp'] instanceof \DateTimeInterface
                    ? Carbon::parse($row['timestamp'])->toIso8601String()
                    : '',
                $encodeJson($row['old_value'] ?? null),
                $encodeJson($row['new_value'] ?? null),
                $row['ip_address'] ?? '',
                $prevHash ?? '',
            ]);

            return hash('sha256', $content);
        };

        foreach ($caseDocumentAuditRows as $documentAudit) {
            $auditLogs[] = $documentAudit;
        }

        foreach ($auditLogs as &$auditRow) {
            $auditRow['prev_hash'] = $previousDigest;
            $previousDigest = $chainDigestOf($auditRow, $previousDigest);
        }
        unset($auditRow);

        foreach (array_chunk($auditLogs, 200) as $chunk) {
            DB::table('audit_logs')->insert($chunk);
        }

        DB::commit();

        unset($auditLogs);
    }
}
