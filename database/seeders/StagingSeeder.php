<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * StagingSeeder — ~6 months of realistic staging data using only faker.
 *
 * Volumes mirror the old VolumeModel targets (~1,500 cases, ~2,700
 * referrals, ~100k rows): clients 1800, cases 1500 (2 referrals each,
 * except drafts), milestones per status template, surveys per agency,
 * and a hash-chained audit trail gated by audit:verify.
 *
 * Timestamps use fake()->dateTimeBetween() with each stamp derived from a
 * previous one (case → referral → milestones → closure), capped at now so
 * nothing leaks into the future. No business calendar: staging data does
 * not need agency-hours realism.
 */
class StagingSeeder extends Seeder
{
    private const CLIENTS = 1800;

    private const CASES = 1500;

    private const ARCHIVED = 240;

    private const CLOSED = 480;

    private const DRAFT_SHARE = 0.10;

    private const MILESTONE_TEMPLATES = [
        'PROCESSING' => [
            ['Referral Received', 'Referral received by the agency.'],
            ['Documents Submitted', 'Required documents have been submitted.'],
        ],
        'FOR_COMPLIANCE' => [
            ['Referral Received', 'Referral received by the agency.'],
            ['Compliance Check', 'Compliance requirements have been checked.'],
        ],
        'COMPLETED' => [
            ['Referral Received', 'Referral received by the agency.'],
            ['Documents Submitted', 'Required documents have been submitted.'],
            ['Services Rendered', 'All required services have been provided.'],
            ['Case Closed', 'Case successfully closed.'],
        ],
        'REJECTED' => [
            ['Referral Received', 'Referral received and reviewed by the agency.'],
        ],
    ];

    private const PROVINCES = [
        ['province' => 'Cebu', 'region' => 'Central Visayas', 'cities' => ['Cebu City', 'Lapu-Lapu City', 'Mandaue City', 'Talisay City', 'Toledo City', 'Danao City']],
        ['province' => 'Bohol', 'region' => 'Central Visayas', 'cities' => ['Tagbilaran City', 'Panglao', 'Dauis', 'Tubigon', 'Talibon', 'Ubay']],
        ['province' => 'Negros Oriental', 'region' => 'Negros Island Region', 'cities' => ['Dumaguete City', 'Bais City', 'Bayawan City', 'Sibulan', 'Valencia']],
        ['province' => 'Siquijor', 'region' => 'Negros Island Region', 'cities' => ['Siquijor', 'Larena', 'Maria', 'Lazi', 'San Juan']],
    ];

    private const OWNED_TABLES = [
        'audit_logs',
        'audit_archives',
        'audit_chain_checkpoints',
        'clients',
        'client_addresses',
        'client_employments',
        'next_of_kin',
        'cases',
        'case_category',
        'case_documents',
        'case_notifications',
        'referrals',
        'milestones',
        'referral_comments',
        'referral_attachments',
        'referral_service_requirements',
        'referral_services',
        'referral_client_requests',
        'referral_client_request_items',
        'referral_client_messages',
        'referral_client_access_links',
        'survey_forms',
        'survey_questions',
        'survey_invitations',
        'survey_responses',
        'email_logs',
        'email_events',
    ];

    private Carbon $now;

    private Carbon $windowStart;

    private string $caseManagerId;

    private string $adminId;

    /** @var array<string, string> agency_id => agency user_id */
    private array $agencyUserByAgency = [];

    /** @var list<array{id: string, slug: string}> */
    private array $agencies = [];

    /** @var array<string, list<array{id: string, name: string, processing_days: int}>> */
    private array $servicesByAgency = [];

    /** @var array<string, array{client_id: ?string, email: ?string, name: string, created_at: Carbon, updated_at: Carbon, status: string, user_id: string, tracker: string, is_deleted: bool}> */
    private array $cases = [];

    /** @var array<string, array{case_id: string, agency_id: string, service: string, status: string, created_at: Carbon, updated_at: Carbon, completed_at: ?Carbon, is_deleted: bool}> */
    private array $referrals = [];

    /** @var list<array<string, mixed>> */
    private array $emailRows = [];

    /** @var list<array<string, mixed>> audit rows in chain order */
    private array $auditRows = [];

    /** @var array<string, int> */
    private array $stats = [];

    private float $startedAt = 0.0;

    public function run(): void
    {
        $this->guardEnvironment();
        $this->startedAt = microtime(true);

        $this->now = Carbon::now()->subMinutes(5);
        $this->windowStart = $this->now->copy()->subMonths(6)->startOfDay();

        DB::transaction(function () {
            $this->truncateOwnedTables();

            $this->call([
                AgencySeeder::class,
                ServiceSeeder::class,
                CaseCategorySeeder::class,
                CaseIssueSeeder::class,
                ProductionSeeder::class,
                SystemSettingSeeder::class,
            ]);

            $this->seedUsers();
            $this->seedClients();
            $this->seedCases();
            $this->seedReferrals();
            $this->seedCollaboration();
            $this->seedClientRequests();
            $this->seedSurveys();
            $this->seedEmailLogs();
            $this->seedAuditTrail();
        });

        $this->verifyAuditChain();
        $this->report();
    }

    private function guardEnvironment(): void
    {
        $allowed = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('STAGING_SEEDER_ALLOWED_ENVS', 'staging,local'))
        ))) ?: ['staging', 'local'];

        if (! app()->environment($allowed)) {
            throw new \RuntimeException(sprintf(
                'StagingSeeder refuses to run in the [%s] environment (allowed: %s; override via STAGING_SEEDER_ALLOWED_ENVS).',
                app()->environment(),
                implode(',', $allowed)
            ));
        }
    }

    private function truncateOwnedTables(): void
    {
        DB::statement('TRUNCATE '.implode(', ', self::OWNED_TABLES).' RESTART IDENTITY CASCADE');
        $this->command?->info('Truncated '.count(self::OWNED_TABLES).' owned tables (RESTART IDENTITY CASCADE).');
    }

    private function verifyAuditChain(): void
    {
        $exit = Artisan::call('audit:verify');
        $output = trim(Artisan::output());

        if ($output !== '') {
            $this->command?->info($output);
        }

        if ($exit !== 0) {
            throw new \RuntimeException(
                'Post-seed audit:verify FAILED (exit '.$exit.'). The staging dataset was committed but its hash chain is broken — see the verifier output above.'
            );
        }
    }

    // ------------------------------------------------------------------
    // Users
    // ------------------------------------------------------------------

    private function seedUsers(): void
    {
        $agencies = DB::table('agencies')->select('id', 'slug')->get()->keyBy('slug');
        $dmwId = $agencies['dmw']->id ?? null;

        $agencyUsers = [
            'owwa' => 'OWWA',
            'dswd' => 'DSWD',
            'doh' => 'DOH',
            'law-center-inc' => 'Law Center Inc.',
            'province-cebu' => 'Province of Cebu',
            'tesda' => 'TESDA',
            'city-cebu' => 'Cebu City',
            'dole' => 'DOLE',
            'dmw' => 'DMW',
        ];

        foreach ($agencyUsers as $slug => $name) {
            $agency = $agencies[$slug] ?? null;
            if (! $agency) {
                continue;
            }
            $email = $slug.'@bayanihan.gov.ph';
            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'id' => DB::table('users')->where('email', $email)->value('id') ?? (string) Str::uuid(),
                    'name' => $name,
                    'password' => Hash::make('P@ssw0rd!'),
                    'role' => 'AGENCY',
                    'agcy_id' => $agency->id,
                    'is_active' => true,
                    'email_verified_at' => $this->now,
                    'updated_at' => $this->now,
                    'created_at' => $this->now,
                ]
            );
            $this->agencyUserByAgency[$agency->id] = DB::table('users')->where('email', $email)->value('id');
        }

        foreach ([
            'case@bayanihan.gov.ph' => ['Case Manager', 'CASE_MANAGER'],
            'admin@bayanihan.gov.ph' => ['System Administrator', 'ADMIN'],
        ] as $email => [$name, $role]) {
            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'id' => DB::table('users')->where('email', $email)->value('id') ?? (string) Str::uuid(),
                    'name' => $name,
                    'password' => Hash::make('P@ssw0rd!'),
                    'role' => $role,
                    'agcy_id' => $dmwId,
                    'is_active' => true,
                    'email_verified_at' => $this->now,
                    'updated_at' => $this->now,
                    'created_at' => $this->now,
                ]
            );
        }

        $this->caseManagerId = DB::table('users')->where('email', 'case@bayanihan.gov.ph')->value('id');
        $this->adminId = DB::table('users')->where('email', 'admin@bayanihan.gov.ph')->value('id');

        $this->agencies = DB::table('agencies')->select('id', 'slug')->get()
            ->map(fn ($row) => ['id' => $row->id, 'slug' => $row->slug])
            ->all();

        foreach (DB::table('services')->select('id', 'name', 'processing_days', 'agcy_id')->get() as $service) {
            $this->servicesByAgency[$service->agcy_id][] = [
                'id' => $service->id,
                'name' => $service->name,
                'processing_days' => (int) ($service->processing_days ?? 14),
            ];
        }
    }

    // ------------------------------------------------------------------
    // Clients
    // ------------------------------------------------------------------

    private function seedClients(): void
    {
        $clientRows = [];
        $addressRows = [];
        $employmentRows = [];
        $kinRows = [];

        for ($i = 0; $i < self::CLIENTS; $i++) {
            $sex = fake()->randomElement(['MALE', 'FEMALE']);
            $firstName = fake()->firstName($sex === 'MALE' ? 'male' : 'female');
            $lastName = fake()->lastName();
            $location = fake()->randomElement(self::PROVINCES);
            $city = fake()->randomElement($location['cities']);
            $barangay = fake()->randomElement(['Poblacion', 'Mabini', 'Rizal', 'Lahug', 'Banilad', 'Guadalupe', 'Basak', 'Tisa', 'Labangon', 'Ermita']);
            $created = $this->at(fake()->dateTimeBetween($this->windowStart, $this->now));
            $clientId = (string) Str::uuid();

            $clientRows[] = [
                'id' => $clientId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_name' => fake()->boolean(60) ? fake()->randomElement(['A', 'B', 'C', 'D', 'M', 'R', 'S']) : null,
                'suffix' => fake()->boolean(3) ? fake()->randomElement(['Jr.', 'Sr.', 'III']) : null,
                'date_of_birth' => Crypt::encryptString(fake()->dateTimeBetween('-65 years', '-21 years')->format('Y-m-d')),
                'sex' => $sex,
                'email' => strtolower($firstName).'.'.strtolower(str_replace(' ', '', $lastName)).fake()->numberBetween(1, 9999).'@email.com',
                'contact_number' => '09'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT),
                'avatar_url' => null,
                'created_at' => $created,
                'updated_at' => $created,
                'is_deleted' => false,
                'deleted_at' => null,
                'deleted_by' => null,
            ];

            $addressCreated = $this->after($created, 5, 600);
            $addressRows[] = [
                'id' => (string) Str::uuid(),
                'client_id' => $clientId,
                'region' => $location['region'],
                'province' => $location['province'],
                'city_municipality' => $city,
                'barangay' => $barangay,
                'street' => Crypt::encryptString(fake()->buildingNumber().' '.$barangay.' St'),
                'created_at' => $addressCreated,
                'updated_at' => $addressCreated,
                'is_deleted' => false,
                'deleted_at' => null,
                'deleted_by' => null,
            ];

            $employmentRows[] = $this->employmentRow($clientId, $created);
            if (fake()->boolean(10)) {
                $employmentRows[] = $this->employmentRow($clientId, $created);
            }

            $kinRows[] = $this->kinRow($clientId, $firstName, $lastName, $location, $city, $barangay, $created, true, 0);
            if (fake()->boolean(20)) {
                $kinRows[] = $this->kinRow($clientId, $firstName, $lastName, $location, $city, $barangay, $created, false, 1);
            }

            $this->audit(AuditAction::CREATE->value, AuditModule::CLIENT->value, $clientId, $this->caseManagerId, $created);
        }

        $this->chunkInsert('clients', $clientRows);
        $this->chunkInsert('client_addresses', $addressRows);
        $this->chunkInsert('client_employments', $employmentRows);
        $this->chunkInsert('next_of_kin', $kinRows);

        foreach (['client_addresses' => AuditModule::CLIENT_ADDRESS, 'client_employments' => AuditModule::CLIENT_EMPLOYMENT, 'next_of_kin' => AuditModule::NEXT_OF_KIN] as $table => $module) {
            foreach (DB::table($table)->select('id', 'created_at')->orderBy('created_at')->orderBy('id')->lazy(1000) as $row) {
                $this->audit(AuditAction::CREATE->value, $module->value, $row->id, $this->caseManagerId, Carbon::parse($row->created_at));
            }
        }
    }

    private function employmentRow(string $clientId, Carbon $clientCreated): array
    {
        $created = $this->after($clientCreated, 5, 900);
        $country = fake()->randomElement(['Saudi Arabia', 'UAE', 'Hong Kong', 'Taiwan', 'Singapore', 'Qatar', 'Kuwait', 'Japan']);

        return [
            'id' => (string) Str::uuid(),
            'client_id' => $clientId,
            'employer_name' => Crypt::encryptString(fake()->company()),
            'position' => Crypt::encryptString(fake()->randomElement(['Domestic Worker', 'Nurse', 'Engineer', 'Driver', 'Technician', 'Laborer'])),
            'country' => Crypt::encryptString($country),
            'start_date' => fake()->dateTimeBetween('-8 years', '-2 years')->format('Y-m-d'),
            'end_date' => fake()->boolean(70) ? fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d') : null,
            'last_country' => Crypt::encryptString($country),
            'last_position' => Crypt::encryptString(fake()->jobTitle()),
            'date_of_arrival' => $clientCreated->format('Y-m-d'),
            'created_at' => $created,
            'updated_at' => $created,
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null,
        ];
    }

    private function kinRow(string $clientId, string $firstName, string $lastName, array $location, string $city, string $barangay, Carbon $clientCreated, bool $primary, int $sortOrder): array
    {
        $kinFirst = fake()->firstName();
        $kinLast = fake()->lastName();
        $created = $this->after($clientCreated, 5, 700);

        return [
            'id' => (string) Str::uuid(),
            'client_id' => $clientId,
            'first_name' => $kinFirst,
            'middle_name' => fake()->boolean(60) ? fake()->randomElement(['A', 'B', 'C', 'M', 'R']) : null,
            'last_name' => $kinLast,
            'is_primary' => $primary,
            'relationship' => fake()->randomElement(['Spouse', 'Parent', 'Sibling', 'Child']),
            'phone_number' => Crypt::encryptString('09'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT)),
            'email' => Crypt::encryptString(strtolower($kinFirst).'.'.strtolower($kinLast).fake()->numberBetween(1, 9999).'@email.com'),
            'full_address' => Crypt::encryptString(fake()->buildingNumber().' '.$barangay.' St, '.$city.', '.$location['province']),
            'region' => $location['region'],
            'province' => $location['province'],
            'city_municipality' => $city,
            'barangay' => $barangay,
            'street' => fake()->streetAddress(),
            'sort_order' => $sortOrder,
            'created_at' => $created,
            'updated_at' => $created,
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null,
        ];
    }

    // ------------------------------------------------------------------
    // Cases
    // ------------------------------------------------------------------

    private function seedCases(): void
    {
        $clients = DB::table('clients')->select('id', 'email', 'first_name', 'last_name', 'created_at')->orderBy('created_at')->get();
        $categoryIds = DB::table('case_categories')->pluck('id')->all();
        $issueIds = DB::table('case_issues')->pluck('id')->all();

        $caseRows = [];
        $pivotRows = [];
        $usedTrackers = [];
        $seqByPeriod = [];

        for ($i = 0; $i < self::CASES; $i++) {
            $created = $this->at(fake()->dateTimeBetween($this->windowStart, $this->now));
            $period = $created->format('Ym');
            $seqByPeriod[$period] = ($seqByPeriod[$period] ?? 0) + 1;

            do {
                $tracker = 'OWBAP-'.strtoupper(Str::random(10));
            } while (isset($usedTrackers[$tracker]));
            $usedTrackers[$tracker] = true;

            $owner = $clients[$i % count($clients)];
            $ownerCreated = Carbon::parse($owner->created_at);
            if ($created->lt($ownerCreated)) {
                $created = $this->after($ownerCreated, 30, 1440);
            }

            $selfFiled = fake()->boolean(15);
            $categoryId = fake()->boolean(80) && $categoryIds !== [] ? fake()->randomElement($categoryIds) : null;
            $caseId = (string) Str::uuid();

            $caseRows[] = [
                'id' => $caseId,
                'case_number' => sprintf('OWB-%s-%s', $period, str_pad((string) $seqByPeriod[$period], 5, '0', STR_PAD_LEFT)),
                'client_type' => fake()->boolean(60) ? 'OFW' : 'NEXT_OF_KIN',
                'vulnerability_indicator' => fake()->randomElement(['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None', null]),
                'nok_vulnerability_indicator' => null,
                'tracker_number' => $tracker,
                'summary' => 'Assistance request for returning OFW — intake '.$created->format('Y-m'),
                'status' => 'OPEN',
                'closed_at' => null,
                'consent_given_at' => $this->after($created, 10, 300),
                'user_id' => $this->caseManagerId,
                'client_id' => $owner->id,
                'category_id' => $categoryId,
                'case_issue_id' => fake()->boolean(70) && $issueIds !== [] ? fake()->randomElement($issueIds) : null,
                'draft_client_data' => null,
                'source' => $selfFiled ? 'self_filed' : 'internal',
                'intake_reviewed_by' => $selfFiled ? $this->caseManagerId : null,
                'created_at' => $created,
                'updated_at' => $created,
                'is_deleted' => false,
                'deleted_at' => null,
                'deleted_by' => null,
                'deletion_reason' => null,
            ];

            if ($categoryId !== null) {
                $pivotRows[] = [
                    'id' => (string) Str::uuid(),
                    'case_id' => $caseId,
                    'case_category_id' => $categoryId,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
            }
        }

        usort($caseRows, fn ($a, $b) => $a['created_at']->getTimestamp() <=> $b['created_at']->getTimestamp());

        $total = count($caseRows);
        $draftCount = (int) round($total * self::DRAFT_SHARE);

        foreach ($caseRows as $index => &$row) {
            if ($index < self::ARCHIVED) {
                $row['status'] = 'ARCHIVED';
            } elseif ($index < self::ARCHIVED + self::CLOSED) {
                $row['status'] = 'CLOSED';
            } elseif ($index >= $total - $draftCount) {
                $row['status'] = 'DRAFT';
                $row['consent_given_at'] = null;
                if (fake()->boolean(50)) {
                    $row['client_id'] = null;
                }
            }
        }
        unset($row);

        $deleted = 0;
        foreach ($caseRows as &$row) {
            if ($row['status'] === 'OPEN' && $deleted < 15 && fake()->boolean(3)) {
                $row['is_deleted'] = true;
                $row['deleted_at'] = $this->after($row['updated_at'], 1440, 28800);
                $row['deleted_by'] = $this->adminId;
                $row['deletion_reason'] = 'Duplicate intake record';
                $deleted++;
            }
        }
        unset($row);

        $insertRows = array_map(function ($row) use ($clients) {
            $owner = $clients->firstWhere('id', $row['client_id']);
            $row['_email'] = $owner->email ?? null;
            $row['_name'] = $owner ? trim($owner->first_name.' '.$owner->last_name) : 'Walk-in client';

            return $row;
        }, $caseRows);

        $this->chunkInsert('cases', array_map(fn ($row) => array_diff_key($row, ['_email' => 1, '_name' => 1]), $insertRows));
        $this->chunkInsert('case_category', $pivotRows);

        foreach ($insertRows as $row) {
            $this->cases[$row['id']] = [
                'client_id' => $row['client_id'],
                'email' => $row['_email'],
                'name' => $row['_name'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'status' => $row['status'],
                'user_id' => $row['user_id'],
                'tracker' => $row['tracker_number'],
                'is_deleted' => $row['is_deleted'],
            ];
            $this->audit(AuditAction::CREATE->value, AuditModule::CASE->value, $row['id'], $row['user_id'], $row['created_at']);
        }
    }

    // ------------------------------------------------------------------
    // Referrals + milestones + requirements
    // ------------------------------------------------------------------

    private function seedReferrals(): void
    {
        $referralRows = [];
        $milestoneRows = [];
        $serviceReqRows = [];
        $serviceLinkRows = [];

        foreach ($this->cases as $caseId => $case) {
            if ($case['status'] === 'DRAFT') {
                continue;
            }

            $pair = collect($this->agencies)->shuffle()->take(2)->values();

            foreach ([0, 1] as $position) {
                if (! isset($pair[$position])) {
                    continue;
                }
                $referral = $this->buildReferral($caseId, $case, $pair[$position], $position);
                $referralRows[] = array_diff_key($referral, ['_service' => 1, '_service_id' => 1, '_terminal' => 1]);

                foreach (array_slice($this->requirementNames($referral), 0, 2) as $sort => $name) {
                    $created = $this->after($referral['created_at'], 5, 300);
                    $serviceReqRows[] = [
                        'id' => (string) Str::uuid(),
                        'referral_id' => $referral['id'],
                        'service_id' => $referral['_service_id'],
                        'name' => $name,
                        'description' => null,
                        'is_required' => fake()->boolean(80),
                        'sort_order' => $sort,
                        'is_deleted' => false,
                        'deleted_by' => null,
                        'deleted_at' => null,
                        'created_at' => $created,
                        'updated_at' => $created,
                    ];
                }

                $previous = $referral['created_at'];
                foreach (self::MILESTONE_TEMPLATES[$referral['status']] ?? [] as $template) {
                    $at = $this->after($previous, 60, 2880);
                    if ($at->gt($referral['updated_at'])) {
                        $at = $referral['updated_at']->copy();
                    }
                    $milestoneRows[] = [
                        'id' => (string) Str::uuid(),
                        'title' => $template[0],
                        'description' => $template[1],
                        'requirements' => null,
                        'refr_id' => $referral['id'],
                        'client_request_id' => null,
                        'user_id' => $this->actorForAgency($referral['agcy_id']),
                        'created_at' => $at,
                        'updated_at' => $at,
                        'is_deleted' => false,
                        'deleted_at' => null,
                        'deleted_by' => null,
                    ];
                    $previous = $at;
                }

                $serviceLinkRows[] = [
                    'referral_id' => $referral['id'],
                    'service_id' => $referral['_service_id'],
                    'created_at' => $referral['created_at'],
                    'updated_at' => $referral['created_at'],
                ];

                $this->referrals[$referral['id']] = [
                    'case_id' => $caseId,
                    'agency_id' => $referral['agcy_id'],
                    'service' => $referral['_service'],
                    'status' => $referral['status'],
                    'created_at' => $referral['created_at'],
                    'updated_at' => $referral['updated_at'],
                    'completed_at' => $referral['_terminal'],
                    'is_deleted' => false,
                ];

                if ($referral['updated_at']->gt($this->cases[$caseId]['updated_at'])) {
                    $this->cases[$caseId]['updated_at'] = $referral['updated_at'];
                }

                $this->audit(AuditAction::CREATE->value, AuditModule::REFERRAL->value, $referral['id'], $this->actorForAgency($referral['agcy_id']), $referral['created_at']);
            }
        }

        $this->chunkInsert('referrals', $referralRows, 500);
        $this->chunkInsert('milestones', $milestoneRows, 500);
        $this->chunkInsert('referral_service_requirements', $serviceReqRows, 500);
        $this->chunkInsert('referral_services', $serviceLinkRows, 500);

        foreach ($this->cases as $caseId => $case) {
            $update = ['updated_at' => $case['updated_at']];

            if (in_array($case['status'], ['CLOSED', 'ARCHIVED'], true)) {
                $completion = $this->latestCompletion($caseId) ?? $case['created_at']->copy()->addDays(60);
                $update['closed_at'] = $this->cap($completion->copy()->addDays(fake()->numberBetween(7, 30)));
                if ($update['closed_at']->gt($case['updated_at'])) {
                    $update['updated_at'] = $update['closed_at'];
                    $this->cases[$caseId]['updated_at'] = $update['updated_at'];
                }
                $this->audit(AuditAction::UPDATE->value, AuditModule::CASE->value, $caseId, $case['user_id'], $update['updated_at'], ['status' => 'OPEN'], ['status' => $case['status']]);
            }

            DB::table('cases')->where('id', $caseId)->update($update);
        }

        $deleted = 0;
        foreach ($this->referrals as $referralId => $referral) {
            if ($deleted >= 20) {
                break;
            }
            if ($this->cases[$referral['case_id']]['status'] !== 'OPEN' || ! fake()->boolean(2)) {
                continue;
            }
            DB::table('referrals')->where('id', $referralId)->update([
                'is_deleted' => true,
                'deleted_at' => $this->after($referral['updated_at'], 1440, 14400),
                'deleted_by' => $this->adminId,
            ]);
            $this->referrals[$referralId]['is_deleted'] = true;
            $deleted++;
        }

        foreach ($this->referrals as $referralId => $referral) {
            if (! in_array($referral['status'], ['COMPLETED', 'REJECTED'], true)) {
                continue;
            }
            $this->audit(
                AuditAction::UPDATE->value,
                AuditModule::REFERRAL->value,
                $referralId,
                $this->actorForAgency($referral['agency_id']),
                $referral['updated_at'],
                ['status' => 'PROCESSING'],
                ['status' => $referral['status']]
            );
        }
    }

    private function buildReferral(string $caseId, array $case, array $agency, int $position): array
    {
        $pool = $this->servicesByAgency[$agency['id']] ?? [];
        if ($pool === []) {
            foreach ($this->servicesByAgency as $services) {
                $pool = array_merge($pool, $services);
            }
        }
        $service = fake()->randomElement($pool);

        $created = $this->after($case['created_at'], 30, 4320);
        $status = in_array($case['status'], ['CLOSED', 'ARCHIVED'], true)
            ? ($position === 0 || fake()->boolean(90) ? 'COMPLETED' : 'REJECTED')
            : fake()->randomElement(['PENDING', 'PENDING', 'PENDING', 'PENDING', 'PROCESSING', 'PROCESSING', 'PROCESSING', 'FOR_COMPLIANCE', 'FOR_COMPLIANCE', 'FOR_COMPLIANCE']);

        $firstAction = null;
        $decision = null;
        $decisionComment = null;
        $terminal = null;
        $updated = $created;

        switch ($status) {
            case 'PROCESSING':
                $firstAction = $this->after($created, 1440, 4320);
                $updated = $firstAction;
                break;
            case 'FOR_COMPLIANCE':
                $firstAction = $this->after($created, 1440, 4320);
                $updated = $this->after($firstAction, 2880, 7200);
                break;
            case 'COMPLETED':
                $firstAction = $this->after($created, 1440, 4320);
                $terminal = $this->after($firstAction, 2880, max(2880, $service['processing_days'] * 1440));
                $decision = 'ACCEPT';
                $updated = $terminal;
                break;
            case 'REJECTED':
                $updated = $this->after($created, 4320, 10080);
                $decision = 'REJECT';
                $decisionComment = 'Requirements not met';
                break;
            default:
                $updated = $this->after($created, 30, 600);
                break;
        }

        return [
            'id' => (string) Str::uuid(),
            'required_services' => $service['name'],
            'notes' => fake()->boolean(60) ? 'Referral for '.$case['status'].' case — '.$service['name'] : null,
            'status' => $status,
            'decision' => $decision,
            'decision_comment' => $decisionComment,
            'case_id' => $caseId,
            'agcy_id' => $agency['id'],
            'first_action_at' => $firstAction,
            'referral_assigned_at' => $status === 'PENDING' ? null : $this->after($created, 10, 480),
            'created_at' => $created,
            'updated_at' => $updated,
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null,
            '_service' => $service['name'],
            '_service_id' => $service['id'],
            '_terminal' => $terminal,
        ];
    }

    private function requirementNames(array $referral): array
    {
        $names = DB::table('service_requirements')->where('service_id', $referral['_service_id'])->pluck('name')->all();
        $names = array_merge($names, ['Valid ID', 'Proof of employment', 'Medical certificate', 'Passport copy', 'Employment contract copy', 'Barangay clearance']);

        return array_values(array_unique($names));
    }

    private function actorForAgency(string $agencyId): string
    {
        $agencyUser = $this->agencyUserByAgency[$agencyId] ?? null;

        if ($agencyUser !== null && fake()->boolean(70)) {
            return $agencyUser;
        }

        return $this->caseManagerId;
    }

    private function latestCompletion(string $caseId): ?Carbon
    {
        $latest = null;

        foreach ($this->referrals as $referral) {
            if ($referral['case_id'] !== $caseId || $referral['completed_at'] === null) {
                continue;
            }

            if ($latest === null || $referral['completed_at']->gt($latest)) {
                $latest = $referral['completed_at'];
            }
        }

        return $latest;
    }

    // ------------------------------------------------------------------
    // Comments, attachments, documents, notifications
    // ------------------------------------------------------------------

    private function seedCollaboration(): void
    {
        $commentRows = [];
        $attachmentRows = [];
        $documentRows = [];
        $notificationRows = [];

        $referralsByCase = [];
        foreach ($this->referrals as $referralId => $referral) {
            $referralsByCase[$referral['case_id']][] = $referralId;
        }

        foreach ($this->referrals as $referralId => $referral) {
            $count = fake()->boolean(50) ? 2 : 1;
            $parentId = null;

            for ($i = 0; $i < $count; $i++) {
                $at = $this->after($referral['created_at'], 30, 10080);
                $id = (string) Str::uuid();
                $commentRows[] = [
                    'id' => $id,
                    'refr_id' => $referralId,
                    'parent_id' => $i > 0 ? $parentId : null,
                    'content' => fake()->randomElement([
                        'Coordinated with the agency focal for documentary requirements.',
                        'Client confirmed receipt of the referral slip.',
                        'Followed up on the pending medical certificate.',
                        'Verified the submitted identification documents.',
                        'Agency acknowledged receipt; awaiting action.',
                        'Noted, proceeding with the next step.',
                    ]),
                    'visibility' => fake()->boolean(70) ? 'INTERNAL' : 'AGY_ONLY',
                    'is_edited' => fake()->boolean(10),
                    'user_id' => $this->actorForAgency($referral['agency_id']),
                    'created_at' => $at,
                    'updated_at' => $at,
                    'is_deleted' => false,
                    'deleted_at' => null,
                    'deleted_by' => null,
                ];

                if ($i === 0) {
                    $parentId = $id;
                }
            }

            if (fake()->boolean(62)) {
                $firstId = (string) Str::uuid();
                $created = $this->after($referral['created_at'], 60, 8640);
                $attachmentRows[] = [
                    'id' => $firstId,
                    'referral_id' => $referralId,
                    'file_name' => 'Supporting-Document.pdf',
                    'file_path' => 'staging/referral-attachments/'.$firstId.'.pdf',
                    'file_type' => 'application/pdf',
                    'size' => fake()->numberBetween(50000, 4000000),
                    'user_id' => $this->actorForAgency($referral['agency_id']),
                    'replaces_id' => null,
                    'version_group_id' => null,
                    'is_archived' => false,
                    'created_at' => $created,
                    'updated_at' => $created,
                    'is_deleted' => false,
                    'deleted_at' => null,
                    'deleted_by' => null,
                ];
            }
        }

        $docNames = [
            ['Passport.pdf', 'application/pdf', 'identification'],
            ['Employment-Contract.pdf', 'application/pdf', 'contract'],
            ['Medical-Certificate.pdf', 'application/pdf', 'medical'],
            ['OEC.pdf', 'application/pdf', 'travel'],
            ['NBI-Clearance.pdf', 'application/pdf', 'identification'],
            ['Payslip.jpg', 'image/jpeg', 'contract'],
        ];

        foreach ($this->cases as $caseId => $case) {
            $docCount = (fake()->boolean(95) ? 1 : 0) + (fake()->boolean(50) ? 1 : 0);
            $caseReferrals = $referralsByCase[$caseId] ?? [];

            for ($i = 0; $i < $docCount; $i++) {
                $doc = fake()->randomElement($docNames);
                $linkedReferral = $caseReferrals !== [] && fake()->boolean(40) ? fake()->randomElement($caseReferrals) : null;
                $base = $linkedReferral !== null ? $this->referrals[$linkedReferral]['created_at'] : $case['created_at'];
                $created = $this->after($base, 60, 5760);
                $docId = (string) Str::uuid();
                $deleted = fake()->boolean(2);

                $documentRows[] = [
                    'id' => $docId,
                    'file_name' => $doc[0],
                    'file_path' => 'staging/case-documents/'.$docId.'.'.pathinfo($doc[0], PATHINFO_EXTENSION),
                    'file_type' => $doc[1],
                    'category' => $doc[2],
                    'size' => fake()->numberBetween(80000, 6000000),
                    'case_id' => $caseId,
                    'referral_id' => $linkedReferral,
                    'user_id' => $case['user_id'],
                    'created_at' => $created,
                    'updated_at' => $created,
                    'is_deleted' => $deleted,
                    'deleted_at' => $deleted ? $this->after($created, 1440, 43200) : null,
                    'deleted_by' => $deleted ? $this->adminId : null,
                ];
            }

            if ($case['email'] === null) {
                continue;
            }

            foreach ([
                ['case.created', 'Case filed', 'Your assistance request '.$case['tracker'].' has been received and is under review.', $case['created_at']],
                ['case.status_updated', 'Case update', 'There is a new update on your case '.$case['tracker'].'.', $case['updated_at']],
            ] as [$type, $title, $message, $at]) {
                $read = fake()->boolean(70);
                $notificationRows[] = [
                    'id' => (string) Str::uuid(),
                    'case_id' => $caseId,
                    'client_email' => $case['email'],
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => json_encode(['case_id' => $caseId, 'event' => $type]),
                    'related_url' => '/cases/'.$caseId,
                    'read_at' => $read ? $this->after($at, 30, 4320) : null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
            }
        }

        $this->chunkInsert('referral_comments', $commentRows, 500);
        $this->chunkInsert('referral_attachments', $attachmentRows, 500);
        $this->chunkInsert('case_documents', $documentRows, 500);
        $this->chunkInsert('case_notifications', $notificationRows, 500);
    }

    // ------------------------------------------------------------------
    // Client requests
    // ------------------------------------------------------------------

    private function seedClientRequests(): void
    {
        $requestRows = [];
        $itemRows = [];
        $linkRows = [];
        $messageRows = [];

        foreach ($this->referrals as $referralId => $referral) {
            $case = $this->cases[$referral['case_id']];

            if (! in_array($referral['status'], ['PROCESSING', 'FOR_COMPLIANCE'], true)) {
                continue;
            }
            if ($case['status'] !== 'OPEN' || $case['is_deleted'] || $referral['is_deleted']) {
                continue;
            }

            $requestCount = 1 + (fake()->boolean(60) ? 1 : 0) + (fake()->boolean(25) ? 1 : 0);

            for ($r = 0; $r < $requestCount; $r++) {
                $type = fake()->randomElement(['DOCUMENT_REQUEST', 'DOCUMENT_REQUEST', 'QUESTION', 'QUESTION', 'INFORMATION_UPDATE']);
                $status = fake()->randomElement(['OPEN', 'IN_PROGRESS', 'CLIENT_RESPONDED', 'COMPLETED']);
                $created = $this->after($referral['created_at'], 1440, 14400);
                $creator = $this->actorForAgency($referral['agency_id']);
                $requestId = (string) Str::uuid();

                $requestRows[] = [
                    'id' => $requestId,
                    'referral_id' => $referralId,
                    'creator_user_id' => $creator,
                    'type' => $type,
                    'title' => fake()->randomElement(['Submission of lacking requirements', 'Clarification on employment details', 'Update your contact details']),
                    'instructions' => Crypt::encrypt('Please comply with each item below before the due date.', false),
                    'status' => $status,
                    'due_at' => $created->copy()->addDays(fake()->numberBetween(7, 14)),
                    'created_at' => $created,
                    'updated_at' => $created,
                    'is_deleted' => false,
                    'deleted_at' => null,
                    'deleted_by' => null,
                ];

                for ($sort = 0; $sort < 2; $sort++) {
                    $itemRows[] = [
                        'id' => (string) Str::uuid(),
                        'request_id' => $requestId,
                        'label' => fake()->randomElement(['Passport bio page', 'Employment contract', 'Medical certificate', 'Birth certificate', 'NBI clearance']),
                        'sort_order' => $sort,
                        'created_at' => $created,
                        'updated_at' => $created,
                        'is_deleted' => false,
                        'deleted_at' => null,
                        'deleted_by' => null,
                    ];
                }

                $rawToken = str_replace('-', '', (string) Str::uuid());
                $linkId = (string) Str::uuid();
                $firstUsed = fake()->boolean(70) ? $this->after($created, 1440, 7200) : null;
                $revoked = fake()->boolean(3);

                $linkRows[] = [
                    'id' => $linkId,
                    'request_id' => $requestId,
                    'token_hash' => hash('sha256', $rawToken),
                    'expires_at' => $created->copy()->addDays(30),
                    'revoked_at' => $revoked ? $this->after($created, 7200, 28800) : null,
                    'revoked_by' => $revoked ? $creator : null,
                    'issued_by' => $creator,
                    'recipient_snapshot' => Crypt::encrypt(json_encode(['name' => $case['name'], 'email' => $case['email']]), false),
                    'first_used_at' => $firstUsed,
                    'last_used_at' => $firstUsed,
                    'use_count' => $firstUsed !== null ? fake()->numberBetween(1, 5) : 0,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];

                $messageRows[] = $this->requestMessage($requestId, 'AGENCY_USER', $creator, null, 'Good day! Please submit the following documents at your earliest convenience.', $created);
                $messageRows[] = $this->requestMessage($requestId, 'CLIENT_ACCESS', null, $linkId, 'Good day! I have submitted the requested documents. Thank you!', $this->after($created, 1440, 7200));

                $this->pushEmail($case['email'], 'New request for your referral', 'App\\Mail\\ClientRequestMail', $created);
            }
        }

        $this->chunkInsert('referral_client_requests', $requestRows, 500);
        $this->chunkInsert('referral_client_request_items', $itemRows, 500);
        $this->chunkInsert('referral_client_access_links', $linkRows, 500);
        $this->chunkInsert('referral_client_messages', $messageRows, 500);
    }

    private function requestMessage(string $requestId, string $senderKind, ?string $userId, ?string $linkId, string $body, Carbon $at): array
    {
        return [
            'id' => (string) Str::uuid(),
            'request_id' => $requestId,
            'body' => Crypt::encrypt($body, false),
            'sender_kind' => $senderKind,
            'user_id' => $userId,
            'access_link_id' => $linkId,
            'kind' => 'MESSAGE',
            'created_at' => $at,
            'updated_at' => $at,
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null,
        ];
    }

    // ------------------------------------------------------------------
    // Surveys
    // ------------------------------------------------------------------

    private function seedSurveys(): void
    {
        $formRows = [];
        $questionRows = [];
        $invitationRows = [];
        $responseRows = [];

        $activeByAgency = [];

        foreach ($this->agencies as $agency) {
            $formId = (string) Str::uuid();
            $formRows[] = [
                'id' => $formId,
                'agency_id' => $agency['id'],
                'title' => strtoupper($agency['slug']).' Client Satisfaction Survey',
                'description' => 'Help us improve our services by answering this short survey.',
                'is_active' => true,
                'activated_at' => $this->windowStart,
                'created_at' => $this->windowStart,
                'updated_at' => $this->windowStart,
            ];
            $activeByAgency[$agency['id']] = $formId;
        }

        $questionTypes = ['likert', 'likert', 'likert', 'likert', 'rating', 'rating', 'text', 'text', 'radio', 'checkbox'];
        $firstQuestionByForm = [];

        foreach ($formRows as $form) {
            foreach ($questionTypes as $order => $type) {
                $questionId = (string) Str::uuid();
                $questionRows[] = [
                    'id' => $questionId,
                    'survey_form_id' => $form['id'],
                    'type' => $type,
                    'label' => 'Question '.($order + 1).' ('.$type.')',
                    'options' => in_array($type, ['radio', 'checkbox'], true)
                        ? json_encode(['Staff courtesy', 'Timeliness', 'Clear process', 'Follow-up support'])
                        : null,
                    'is_required' => ! ($type === 'text' && $order === 7),
                    'order' => $order,
                    'created_at' => $this->windowStart,
                    'updated_at' => $this->windowStart,
                ];

                if ($order === 0) {
                    $firstQuestionByForm[$form['id']] = ['id' => $questionId, 'type' => $type];
                }
            }
        }

        $this->chunkInsert('survey_forms', $formRows);
        $this->chunkInsert('survey_questions', $questionRows);

        $usedTokens = [];

        foreach ($this->referrals as $referralId => $referral) {
            $case = $this->cases[$referral['case_id']];
            $formId = $activeByAgency[$referral['agency_id']] ?? null;

            if ($formId === null || $case['email'] === null || $case['is_deleted'] || $referral['is_deleted']) {
                continue;
            }

            do {
                $rawToken = str_replace('-', '', (string) Str::uuid());
            } while (isset($usedTokens[$rawToken]));
            $usedTokens[$rawToken] = true;

            $created = $this->after($referral['updated_at'], 0, 4320);
            $invitationId = (string) Str::uuid();
            $submittedAt = fake()->boolean(70) ? $this->after($created, 1440, 20160) : null;

            $invitationRows[] = [
                'id' => $invitationId,
                'survey_form_id' => $formId,
                'case_id' => $referral['case_id'],
                'agency_id' => $referral['agency_id'],
                'referral_id' => $referralId,
                'client_name' => $case['name'],
                'client_email' => $case['email'],
                'service_name' => $referral['service'],
                'token' => $rawToken,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => $created->copy()->addDays(30),
                'submitted_at' => $submittedAt,
                'created_at' => $created,
                'updated_at' => $submittedAt ?? $created,
            ];

            if ($submittedAt !== null) {
                $question = $firstQuestionByForm[$formId];
                $responseRows[] = [
                    'id' => (string) Str::uuid(),
                    'survey_invitation_id' => $invitationId,
                    'survey_question_id' => $question['id'],
                    'answer' => in_array($question['type'], ['likert', 'rating'], true) ? (string) fake()->numberBetween(3, 5) : null,
                    'selected_options' => null,
                    'created_at' => $submittedAt,
                ];
            }

            if (fake()->boolean(50)) {
                $this->pushEmail($case['email'], 'We value your feedback on '.$referral['service'], 'App\\Mail\\SurveyRequestMail', $created);
            }
        }

        $this->chunkInsert('survey_invitations', $invitationRows, 500);
        $this->chunkInsert('survey_responses', $responseRows, 500);
    }

    // ------------------------------------------------------------------
    // Emails
    // ------------------------------------------------------------------

    private function seedEmailLogs(): void
    {
        foreach ($this->cases as $case) {
            if ($case['email'] === null || $case['status'] === 'DRAFT') {
                continue;
            }

            $this->pushEmail($case['email'], 'Your case '.$case['tracker'].' has been filed', 'App\\Mail\\IntakePublishedMail', $case['created_at']);
        }

        foreach ($this->referrals as $referral) {
            if ($referral['status'] !== 'COMPLETED' || $referral['completed_at'] === null) {
                continue;
            }

            $case = $this->cases[$referral['case_id']];

            if ($case['email'] === null) {
                continue;
            }

            $this->pushEmail($case['email'], 'Your referral for '.$referral['service'].' is complete', 'App\\Mail\\ClientUpdateMail', $referral['completed_at']);
        }

        $this->chunkInsert('email_logs', $this->emailRows, 500);
        $this->emailRows = [];
    }

    private function pushEmail(?string $to, string $subject, string $mailable, Carbon $eventAt): void
    {
        if ($to === null) {
            return;
        }

        $sent = fake()->boolean(95);
        $sentAt = $sent ? $this->after($eventAt, 1, 30) : null;

        $this->emailRows[] = [
            'id' => (string) Str::uuid(),
            'to_email' => $to,
            'subject' => $subject,
            'mailable_type' => $mailable,
            'status' => $sent ? 'sent' : 'failed',
            'job_uuid' => null,
            'error_message' => $sent ? null : fake()->randomElement(['Connection timed out', 'Mailbox unavailable', 'Message rejected by provider']),
            'provider_message_id' => $sent && fake()->boolean(60) ? 'resend-'.(string) Str::uuid() : null,
            'sent_at' => $sentAt,
            'delivered_at' => $sent && fake()->boolean(80) && $sentAt !== null ? $this->after($sentAt, 1, 60) : null,
            'created_at' => $eventAt,
            'updated_at' => $sentAt ?? $eventAt,
        ];
    }

    // ------------------------------------------------------------------
    // Audit trail (hash-chained bulk insert)
    // ------------------------------------------------------------------

    private function seedAuditTrail(): void
    {
        $users = DB::table('users')->select('id')->orderBy('email')->get();

        foreach ($users as $user) {
            $days = (int) $this->windowStart->diffInDays($this->now);
            for ($offset = 0; $offset <= $days; $offset++) {
                if (! fake()->boolean(15)) {
                    continue;
                }
                $loginAt = $this->at(fake()->dateTimeBetween($this->windowStart->copy()->addDays($offset)->startOfDay(), $this->windowStart->copy()->addDays($offset)->endOfDay()));
                $this->audit(AuditAction::LOGIN->value, AuditModule::AUTH->value, $user->id, $user->id, $loginAt);
                $this->audit(AuditAction::LOGOUT->value, AuditModule::AUTH->value, $user->id, $user->id, $this->after($loginAt, 60, 540));
            }
        }

        foreach (['milestones' => ['refr_id', AuditModule::MILESTONE], 'referral_comments' => ['refr_id', AuditModule::REFERRAL_COMMENT], 'case_documents' => ['case_id', AuditModule::CASE_DOCUMENT]] as $table => [$fk, $module]) {
            foreach (DB::table($table)->select('id', 'created_at')->orderBy('created_at')->orderBy('id')->lazy(1000) as $row) {
                $this->audit(AuditAction::CREATE->value, $module->value, $row->id, $this->caseManagerId, Carbon::parse($row->created_at));
            }
        }

        foreach (DB::table('survey_invitations')->select('id', 'created_at')->orderBy('created_at')->lazy(1000) as $row) {
            $this->audit(AuditAction::CREATE->value, AuditModule::SURVEY_INVITATION->value, $row->id, null, Carbon::parse($row->created_at), null, null, null);
        }

        foreach (DB::table('survey_responses')->select('id', 'created_at')->orderBy('created_at')->lazy(1000) as $row) {
            $this->audit(AuditAction::CREATE->value, AuditModule::SURVEY_RESPONSE->value, $row->id, null, Carbon::parse($row->created_at), null, null, null);
        }

        $this->flushAudits();
    }

    private function audit(string $action, string $module, ?string $entityId, ?string $userId, Carbon $at, ?array $old = null, ?array $new = null, ?string $ip = '127.0.0.1'): void
    {
        $this->auditRows[] = [
            'id' => (string) Str::uuid(),
            'action' => $action,
            'module' => $module,
            'entity_id' => $entityId,
            'description' => null,
            'old_value' => $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES),
            'new_value' => $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES),
            'user_id' => $userId,
            'timestamp' => $this->cap($at)->timezone('UTC'),
            'ip_address' => $ip,
        ];
    }

    /**
     * Chain the buffered rows (prev_hash + sha256 digest, same field
     * list/format as AuditLog::chainDigest()) and bulk-insert.
     */
    private function flushAudits(): void
    {
        if ($this->auditRows === []) {
            return;
        }

        $normalisedByRaw = [];
        $rawPayloads = array_unique(array_filter(array_merge(
            array_column($this->auditRows, 'old_value'),
            array_column($this->auditRows, 'new_value')
        )));

        foreach (array_chunk(array_values($rawPayloads), 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?)'));
            $normalisedRows = DB::select(
                "SELECT (col::jsonb)::text AS normalised FROM (VALUES {$placeholders}) AS v(col)",
                $chunk
            );

            foreach ($chunk as $index => $raw) {
                $normalisedByRaw[$raw] = $normalisedRows[$index]->normalised;
            }
        }

        $previousDigest = AuditLog::orderBy('chain_seq', 'desc')->first()?->chainDigest();

        foreach ($this->auditRows as &$row) {
            $row['prev_hash'] = $previousDigest;
            $encodeJson = fn ($value) => $value === null
                ? 'null'
                : json_encode(json_decode($normalisedByRaw[$value] ?? $value, true), JSON_UNESCAPED_SLASHES);
            $previousDigest = hash('sha256', implode('|', [
                $row['id'],
                $row['action'],
                $row['module'],
                $row['entity_id'] ?? '',
                $row['user_id'] ?? '',
                $row['timestamp'] instanceof \DateTimeInterface ? Carbon::parse($row['timestamp'])->toIso8601String() : '',
                $encodeJson($row['old_value']),
                $encodeJson($row['new_value']),
                $row['ip_address'] ?? '',
                $previousDigest ?? '',
            ]));
        }
        unset($row);

        $inserted = $this->chunkInsert('audit_logs', $this->auditRows, 200);
        $this->auditRows = [];
        $this->command?->info("Audit trail chained: {$inserted} rows.");
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    private function at(\DateTimeInterface $when): Carbon
    {
        return $this->cap(Carbon::parse($when));
    }

    /** Stamp $minutes after $base (minute ranges), never the future. */
    private function after(Carbon $base, int $minMinutes, int $maxMinutes): Carbon
    {
        return $this->cap($base->copy()->addMinutes(fake()->numberBetween($minMinutes, $maxMinutes)));
    }

    private function cap(Carbon $stamp): Carbon
    {
        return $stamp->gt($this->now) ? $this->now->copy() : $stamp;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function chunkInsert(string $table, array $rows, int $size = 500): int
    {
        $inserted = 0;

        foreach (array_chunk($rows, $size) as $piece) {
            DB::table($table)->insert($piece);
            $inserted += count($piece);
        }

        $this->stats[$table] = ($this->stats[$table] ?? 0) + $inserted;

        return $inserted;
    }

    private function report(): void
    {
        $elapsed = $this->startedAt > 0 ? round(microtime(true) - $this->startedAt) : 0;
        $lines = ['StagingSeeder complete — actual inserted rows (audit:verify passed):'];

        foreach ($this->stats as $table => $count) {
            $lines[] = sprintf('  %-38s %d', $table, $count);
        }

        $lines[] = sprintf('  %-38s %d', 'estimated total', array_sum($this->stats));
        $lines[] = "Elapsed: {$elapsed}s. Post-seed step: php artisan chatbot:index.";

        $this->command?->info(implode(PHP_EOL, $lines));
    }
}
