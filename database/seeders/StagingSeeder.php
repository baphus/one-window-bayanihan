<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use Database\Seeders\Staging\AuditChainWriter;
use Database\Seeders\Staging\StagingDataFactory;
use Database\Seeders\Staging\TemporalEngine;
use Database\Seeders\Staging\VolumeModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * StagingSeeder — 6 months of realistic staging data (T5–T11).
 *
 * Truncate-then-seed inside ONE transaction (T11): every table this seeder
 * owns is truncated (CASCADE, RESTART IDENTITY) and rebuilt from the fixed
 * RNG stream, so re-runs produce a clean, identical dataset. Reference
 * seeders re-run idempotently on top; users are reconciled via
 * updateOrInsert and never truncated. Audit rows are written LAST (T10)
 * through AuditChainWriter, then the run gates on php artisan audit:verify.
 *
 * SAFETY: guardEnvironment() refuses anything outside
 * config('staging.seeder_allowed_envs') — never production.
 *
 * Populates every business table using only the Staging/ foundation classes:
 * StagingDataFactory for every random draw, TemporalEngine for every
 * timestamp, VolumeModel for loop bounds. Bulk query-builder inserts bypass
 * Eloquent observers, so no audit rows or notifications fire as side effects
 * during the run.
 *
 * Conventions honored throughout:
 * - Determinism: one shared mt_rand() stream (seed 20260909); identical call
 *   order on every run. Crypt::encryptString()/Hash::make() ciphertexts differ
 *   per run by nature (random IV/salt) — the LOGICAL data is identical.
 * - Temporal coherence: every stamp derives from a previous one
 *   (case → referral → milestones/compliance → collaboration → notifications
 *   → emails → audit). The run anchors on the most recent business-day
 *   17:00:00 (Asia/Manila) — a day-quantized instant, so re-runs within the
 *   same calendar day are byte-identical (fixed RNG seed) while no stamp can
 *   leak into the future; min() with a constant preserves monotonicity, and
 *   the anchor itself sits inside both agency (08:00–17:00) and client
 *   (07:00–21:00) hours so clamping never breaks the hour invariants.
 * - PII: date_of_birth, address street, employment fields, NOK phone/email/
 *   address, request instructions/bodies/snapshots are encrypted with the
 *   exact set-paths of their model casts (Crypt::encryptString for
 *   EncryptedString/EncryptedDate; Crypt::encrypt($v, false) for
 *   'encrypted'/'encrypted:array', json_encode first for the latter), so
 *   reads through the models decrypt transparently. Client names stay
 *   plaintext, matching the Client model (only date_of_birth is cast).
 * - Attachments/documents seed METADATA rows only (placeholder file_path);
 *   no object-storage writes (plan §12).
 *
 * Expected runtime: 1–3 minutes on staging hardware (~115k rows, ~45k audit
 * digests chained through a single jsonb-normalisation pass).
 */
class StagingSeeder extends Seeder
{
    /**
     * Share of DRAFT cases without a linked client (walk-in drafts).
     */
    private const CLIENTLESS_DRAFT_RATE = 0.50;

    /**
     * Share of cases carrying a category pivot row.
     */
    private const CASE_CATEGORY_RATE = 0.80;

    /**
     * Share of cases carrying a case_issue_id.
     */
    private const CASE_ISSUE_RATE = 0.70;

    /**
     * Share of self-filed (client-hours) vs internal (agency-hours) intake.
     */
    private const SELF_FILED_RATE = 0.15;

    /**
     * Referral status mix for OPEN cases: PENDING / PROCESSING / FOR_COMPLIANCE.
     * Closed/archived cases always carry COMPLETED referrals (90% of the
     * second referral, REJECTED otherwise) so closure is explainable.
     */
    private const OPEN_PENDING_MAX = 450;

    private const OPEN_PROCESSING_MAX = 725;

    /**
     * Milestone titles/descriptions per referral status (plan §5.4).
     *
     * @var array<string, list<array{title: string, description: string}>>
     */
    private const MILESTONE_TEMPLATES = [
        'PROCESSING' => [
            ['title' => 'Referral Received', 'description' => 'Referral received by the agency.'],
            ['title' => 'Documents Submitted', 'description' => 'Required documents have been submitted.'],
        ],
        'FOR_COMPLIANCE' => [
            ['title' => 'Referral Received', 'description' => 'Referral received by the agency.'],
            ['title' => 'Compliance Check', 'description' => 'Compliance requirements have been checked.'],
        ],
        'COMPLETED' => [
            ['title' => 'Referral Received', 'description' => 'Referral received by the agency.'],
            ['title' => 'Documents Submitted', 'description' => 'Required documents have been submitted.'],
            ['title' => 'Services Rendered', 'description' => 'All required services have been provided.'],
            ['title' => 'Case Closed', 'description' => 'Case successfully closed.'],
        ],
        'REJECTED' => [
            ['title' => 'Referral Received', 'description' => 'Referral received and reviewed by the agency.'],
        ],
    ];

    /**
     * Fallback compliance requirement names when a service has no
     * service_requirements rows.
     *
     * @var list<string>
     */
    private const GENERIC_REQUIREMENTS = [
        'Valid ID', 'Proof of employment', 'Medical certificate',
        'Passport copy', 'Employment contract copy', 'Barangay clearance',
    ];

    /**
     * Classic 22-item SERVQUAL instrument (Tangibles 4, Reliability 5,
     * Responsiveness 4, Assurance 4, Empathy 5). Used when the
     * default_servqual_questions system setting is empty (it ships as '[]').
     *
     * @var list<array{question: string, dimension: string}>
     */
    private const SERVQUAL_INSTRUMENT = [
        ['question' => 'Up-to-date equipment and facilities', 'dimension' => 'Tangibles'],
        ['question' => 'Visually appealing physical facilities', 'dimension' => 'Tangibles'],
        ['question' => 'Neat and professional staff appearance', 'dimension' => 'Tangibles'],
        ['question' => 'Physical facilities in keeping with the service', 'dimension' => 'Tangibles'],
        ['question' => 'Promises are fulfilled on time', 'dimension' => 'Reliability'],
        ['question' => 'Sincere interest in solving client problems', 'dimension' => 'Reliability'],
        ['question' => 'Service performed right the first time', 'dimension' => 'Reliability'],
        ['question' => 'Service provided at the promised time', 'dimension' => 'Reliability'],
        ['question' => 'Accurate records and documentation', 'dimension' => 'Reliability'],
        ['question' => 'Staff inform clients when services will be performed', 'dimension' => 'Responsiveness'],
        ['question' => 'Prompt service to clients', 'dimension' => 'Responsiveness'],
        ['question' => 'Willingness to help clients', 'dimension' => 'Responsiveness'],
        ['question' => 'Staff are never too busy to respond', 'dimension' => 'Responsiveness'],
        ['question' => 'Staff behavior instills confidence', 'dimension' => 'Assurance'],
        ['question' => 'Clients feel safe in transactions', 'dimension' => 'Assurance'],
        ['question' => 'Staff are consistently courteous', 'dimension' => 'Assurance'],
        ['question' => 'Staff have knowledge to answer questions', 'dimension' => 'Assurance'],
        ['question' => 'Individual attention to clients', 'dimension' => 'Empathy'],
        ['question' => 'Convenient operating hours', 'dimension' => 'Empathy'],
        ['question' => 'Personal attention from staff', 'dimension' => 'Empathy'],
        ['question' => 'Best interests of clients at heart', 'dimension' => 'Empathy'],
        ['question' => 'Understanding of specific client needs', 'dimension' => 'Empathy'],
    ];

    private StagingDataFactory $factory;

    private TemporalEngine $time;

    /**
     * Frozen reference instant for the whole run: the most recent
     * business-day 17:00:00 (Asia/Manila) — see resolveAnchor().
     */
    private Carbon $now;

    /**
     * Upper bound for generated stamps. Equals the run anchor, so every
     * clamped stamp lands at 17:00 on a weekday (inside agency AND client
     * hours) and re-runs within the same calendar day are byte-identical.
     */
    private Carbon $cap;

    /**
     * First day of the 6-month seeding window.
     */
    private Carbon $windowStart;

    private string $caseManagerId;

    private string $adminId;

    /**
     * @var array<string, string> agency_id => agency user_id
     */
    private array $agencyUserByAgency = [];

    /**
     * @var list<array{id: string, slug: string}>
     */
    private array $agencies = [];

    /**
     * @var array<string, list<array{id: string, name: string, processing_days: int}>>
     */
    private array $servicesByAgency = [];

    /**
     * @var array<string, list<string>> service_id => requirement names
     */
    private array $requirementsByService = [];

    /**
     * @var array<string, array{client_id: ?string, email: ?string, name: string, created_at: Carbon, updated_at: Carbon, status: string, user_id: string, tracker: string, is_deleted: bool}>
     */
    private array $cases = [];

    /**
     * @var array<string, array{case_id: string, agency_id: string, service: string, processing_days: int, status: string, created_at: Carbon, updated_at: Carbon, completed_at: ?Carbon, is_deleted: bool}>
     */
    private array $referrals = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $emailRows = [];

    /**
     * @var array<string, int> actual inserted counts per table
     */
    private array $stats = [];

    /**
     * Every table StagingSeeder owns and rebuilds. Reference tables
     * (agencies, services, categories, issues, settings) and users are
     * deliberately EXCLUDED: reference seeders and updateOrInsert reconcile
     * them idempotently. email_events is included explicitly — it holds
     * provider delivery state for email_logs rows that are being replaced.
     * audit_archives / audit_chain_checkpoints are included so a fresh chain
     * (NULL root) never disagrees with a stale prune anchor at verify time.
     *
     * @var list<string>
     */
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
        'feedback',
        'feedback_servqual_responses',
        'email_logs',
        'email_events',
    ];

    public function run(): void
    {
        $this->guardEnvironment();
        $this->startedAt = microtime(true);

        $this->factory = new StagingDataFactory;
        $this->time = new TemporalEngine($this->factory);
        $this->now = $this->resolveAnchor(Carbon::now(TemporalEngine::TIMEZONE));
        $this->cap = $this->now->copy();
        $this->windowStart = $this->now->copy()->subMonths(5)->startOfMonth();

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
            $this->seedSurveysAndFeedback();
            $this->seedEmailLogs();
            $this->seedAuditTrail();
        });

        // Verify OUTSIDE the transaction, against committed data.
        $this->verifyAuditChain();
        $this->report();
    }

    // ------------------------------------------------------------------
    // T11 — env guard + truncate + verify gate
    // ------------------------------------------------------------------

    private float $startedAt = 0.0;

    /**
     * Refuse every environment outside config('staging.seeder_allowed_envs')
     * (default staging,local; override via STAGING_SEEDER_ALLOWED_ENVS).
     * This seeder truncates business tables — it must never run in prod.
     */
    private function guardEnvironment(): void
    {
        $allowed = config('staging.seeder_allowed_envs', ['staging', 'local']);

        if (! is_array($allowed) || $allowed === []) {
            $allowed = ['staging', 'local'];
        }

        if (! app()->environment($allowed)) {
            throw new \RuntimeException(sprintf(
                'StagingSeeder refuses to run in the [%s] environment (allowed: %s; override via STAGING_SEEDER_ALLOWED_ENVS).',
                app()->environment(),
                implode(',', $allowed)
            ));
        }
    }

    /**
     * Truncate every owned table in one statement. No audit bypass is needed:
     * the append-only rule lives in a row-level BEFORE UPDATE OR DELETE
     * trigger, and PostgreSQL fires row triggers on neither TRUNCATE (only
     * TRUNCATE triggers, of which none exist) nor bulk INSERT — exactly why
     * TestingSeeder's bulk audit insert also runs without the bypass.
     * RESTART IDENTITY resets BIGSERIAL sequences (notably chain_seq) so
     * re-runs reproduce identical sequences. CASCADE additionally clears
     * non-seeded runtime dependents (case_comments, case_events,
     * feedback_invitations, referral_services, referral_service_requirements,
     * referral_client_message_attachments, generated_documents) — all
     * derived or empty state that a staging reseed is meant to wipe.
     */
    private function truncateOwnedTables(): void
    {
        DB::statement('TRUNCATE '.implode(', ', self::OWNED_TABLES).' RESTART IDENTITY CASCADE');
        $this->command?->info('Truncated '.count(self::OWNED_TABLES).' owned tables (RESTART IDENTITY CASCADE).');
    }

    /**
     * Post-seed gate: the committed chain must verify. Throws loudly on
     * failure — the dataset stays in place for forensics.
     */
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
    // T5 — users
    // ------------------------------------------------------------------

    private function seedUsers(): void
    {
        $agencies = DB::table('agencies')->select('id', 'slug')->get()->keyBy('slug');
        $dmwId = $agencies['dmw']->id ?? null;
        $now = $this->now;

        $agencyUsers = [
            'owwa' => ['name' => 'OWWA', 'email' => 'owwa@bayanihan.gov.ph'],
            'dswd' => ['name' => 'DSWD', 'email' => 'dswd@bayanihan.gov.ph'],
            'doh' => ['name' => 'DOH', 'email' => 'doh@bayanihan.gov.ph'],
            'law-center-inc' => ['name' => 'Law Center Inc.', 'email' => 'law-center-inc@bayanihan.gov.ph'],
            'province-cebu' => ['name' => 'Province of Cebu', 'email' => 'province-cebu@bayanihan.gov.ph'],
            'tesda' => ['name' => 'TESDA', 'email' => 'tesda@bayanihan.gov.ph'],
            'city-cebu' => ['name' => 'Cebu City', 'email' => 'city-cebu@bayanihan.gov.ph'],
            'dole' => ['name' => 'DOLE', 'email' => 'dole@bayanihan.gov.ph'],
            'dmw' => ['name' => 'DMW', 'email' => 'dmw@bayanihan.gov.ph'],
        ];

        foreach ($agencyUsers as $slug => $spec) {
            $agency = $agencies[$slug] ?? null;
            if (! $agency) {
                continue;
            }
            DB::table('users')->updateOrInsert(
                ['email' => $spec['email']],
                [
                    'id' => DB::table('users')->where('email', $spec['email'])->value('id') ?? $this->factory->uuid(),
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
            $this->agencyUserByAgency[$agency->id] = DB::table('users')->where('email', $spec['email'])->value('id');
        }

        DB::table('users')->updateOrInsert(
            ['email' => 'case@bayanihan.gov.ph'],
            [
                'id' => DB::table('users')->where('email', 'case@bayanihan.gov.ph')->value('id') ?? $this->factory->uuid(),
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

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@bayanihan.gov.ph'],
            [
                'id' => DB::table('users')->where('email', 'admin@bayanihan.gov.ph')->value('id') ?? $this->factory->uuid(),
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

        foreach (DB::table('service_requirements')->select('service_id', 'name')->get() as $requirement) {
            $this->requirementsByService[$requirement->service_id][] = $requirement->name;
        }
    }

    // ------------------------------------------------------------------
    // T5 — clients + addresses + employments + next_of_kin
    // ------------------------------------------------------------------

    private function seedClients(): void
    {
        $clientRows = [];
        $addressRows = [];
        $employmentRows = [];
        $kinRows = [];

        // Client shells first (created_at is backdated once cases exist);
        // T5 assigns case owners after seeding cases below.
        for ($i = 0; $i < VolumeModel::CLIENTS; $i++) {
            $sex = $this->factory->sex();
            $firstName = $this->factory->firstName($sex);
            $lastName = $this->factory->surname();
            $location = $this->factory->location();
            $barangay = $this->factory->barangay();

            // The first ~300 clients predate the window (people exist before
            // they seek help), so rank-paired case owners almost always
            // predate their cases — see seedCases().
            $monthOffset = $i < 300 ? -1 : $this->factory->int(0, 5);
            [$clientYear, $clientMonth] = $this->windowMonth($monthOffset);
            $created = $this->time->clientDateTime(
                $this->intakeDay($clientYear, $clientMonth)
            );

            $clientRows[] = [
                'id' => $this->factory->uuid(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_name' => $this->factory->middleName(),
                'suffix' => $this->factory->suffix(),
                'date_of_birth' => Crypt::encryptString($this->factory->dateOfBirth($created)),
                'sex' => $sex,
                'email' => $this->factory->emailFor($firstName, $lastName),
                'contact_number' => $this->factory->contactNumber(),
                'avatar_url' => null,
                'created_at' => $created,
                'updated_at' => $created,
                'is_deleted' => false,
                'deleted_at' => null,
                'deleted_by' => null,
                '_location' => $location,
                '_barangay' => $barangay,
            ];
        }

        // Case owners predate their cases: backdate after the fact is complex,
        // so clients keep window-spread stamps and cases are created at/after
        // the client's stamp (see seedCases()).
        $this->chunkInsert('clients', array_map(fn ($row) => $this->stripMeta($row), $clientRows));

        foreach ($clientRows as $client) {
            $location = $client['_location'];
            $addressCreated = $this->noLaterThan(
                $client['created_at']->copy()->addMinutes($this->factory->int(5, 600))
            );
            $addressRows[] = [
                'id' => $this->factory->uuid(),
                'client_id' => $client['id'],
                'region' => $this->factory->regionForProvince($location['province']),
                'province' => $location['province'],
                'city_municipality' => $location['city'],
                'barangay' => $client['_barangay'],
                'street' => Crypt::encryptString($this->factory->streetAddress()),
                'created_at' => $addressCreated,
                'updated_at' => $addressCreated,
                'is_deleted' => false,
                'deleted_at' => null,
                'deleted_by' => null,
            ];

            $employmentRows[] = $this->employmentRow($client['id'], $client['created_at'], false);

            if ($this->factory->boolean(VolumeModel::SECOND_EMPLOYMENT_RATE)) {
                $employmentRows[] = $this->employmentRow($client['id'], $client['created_at'], true);
            }

            $kinRows[] = $this->kinRow($client, true, 0);

            if ($this->factory->boolean(VolumeModel::SECOND_NOK_RATE)) {
                $kinRows[] = $this->kinRow($client, false, 1);
            }
        }

        $this->chunkInsert('client_addresses', $addressRows);
        $this->chunkInsert('client_employments', $employmentRows);
        $this->chunkInsert('next_of_kin', $kinRows);

        $this->clientShells = array_map(fn ($row) => [
            'id' => $row['id'],
            'email' => $row['email'],
            'name' => trim($row['first_name'].' '.$row['last_name']),
            'created_at' => $row['created_at'],
        ], $clientRows);

        unset($clientRows, $addressRows, $employmentRows, $kinRows);
    }

    /**
     * @var list<array{id: string, email: string, name: string, created_at: Carbon}>
     */
    private array $clientShells = [];

    private function employmentRow(string $clientId, Carbon $clientCreated, bool $isSecond): array
    {
        $startYear = (int) $clientCreated->format('Y') - $this->factory->int(1, 6);
        $start = sprintf('%d-%02d-%02d', $startYear, $this->factory->int(1, 12), $this->factory->int(1, 28));
        $created = $this->noLaterThan($clientCreated->copy()->addMinutes($this->factory->int(5, 900)));

        return [
            'id' => $this->factory->uuid(),
            'client_id' => $clientId,
            'employer_name' => Crypt::encryptString($this->factory->employer()),
            'position' => Crypt::encryptString($this->factory->position()),
            'country' => Crypt::encryptString($this->factory->country()),
            'start_date' => $start,
            'end_date' => $isSecond ? null : sprintf('%d-%02d-%02d', $startYear + $this->factory->int(1, 3), $this->factory->int(1, 12), $this->factory->int(1, 28)),
            'last_country' => Crypt::encryptString($this->factory->country()),
            'last_position' => Crypt::encryptString($this->factory->position()),
            'date_of_arrival' => $clientCreated->format('Y-m-d'),
            'created_at' => $created,
            'updated_at' => $created,
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null,
        ];
    }

    private function kinRow(array $client, bool $primary, int $sortOrder): array
    {
        $sex = $this->factory->sex();
        $firstName = $this->factory->firstName($sex);
        $lastName = $this->factory->surname();
        $location = $client['_location'];
        $created = $this->noLaterThan($client['created_at']->copy()->addMinutes($this->factory->int(5, 700)));

        return [
            'id' => $this->factory->uuid(),
            'client_id' => $client['id'],
            'first_name' => $firstName,
            'middle_name' => $this->factory->middleName(),
            'last_name' => $lastName,
            'is_primary' => $primary,
            'relationship' => $this->factory->relationship(),
            'phone_number' => Crypt::encryptString($this->factory->contactNumber()),
            'email' => Crypt::encryptString($this->factory->emailFor($firstName, $lastName)),
            'full_address' => Crypt::encryptString(
                $this->factory->int(1, 999).' '.$client['_barangay'].' St, '.$location['city'].', '.$location['province']
            ),
            'region' => $this->factory->regionForProvince($location['province']),
            'province' => $location['province'],
            'city_municipality' => $location['city'],
            'barangay' => $client['_barangay'],
            'street' => $this->factory->streetAddress(),
            'sort_order' => $sortOrder,
            'created_at' => $created,
            'updated_at' => $created,
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null,
        ];
    }

    // ------------------------------------------------------------------
    // T5 — cases
    // ------------------------------------------------------------------

    private function seedCases(): void
    {
        $monthKeys = $this->monthKeys();
        $perMonth = $this->time->monthlyDistribution(VolumeModel::CASES, $monthKeys);
        $categoryIds = DB::table('case_categories')->pluck('id')->all();
        $issueIds = DB::table('case_issues')->pluck('id')->all();

        $caseRows = [];
        $pivotRows = [];
        $usedTrackers = [];
        $seqByPeriod = [];

        foreach ($monthKeys as $monthKey) {
            [$year, $month] = array_map('intval', explode('-', $monthKey));
            $period = $year * 100 + $month;

            for ($i = 0; $i < ($perMonth[$monthKey] ?? 0); $i++) {
                // Intake skews Mon–Wed: redraw Thu–Sun stamps half the time.
                // intakeDay() additionally keeps draws inside the anchor date
                // so the partial current month never draws future days.
                $day = $this->intakeDay($year, $month);
                if ($day->dayOfWeek > Carbon::WEDNESDAY && $this->factory->boolean()) {
                    $day = $this->intakeDay($year, $month);
                }

                $selfFiled = $this->factory->boolean(self::SELF_FILED_RATE);
                $created = $this->noLaterThan(
                    $selfFiled ? $this->time->clientDateTime($day) : $this->time->agencyDateTime($day)
                );

                $seqByPeriod[$period] = ($seqByPeriod[$period] ?? 0) + 1;

                do {
                    $tracker = $this->factory->trackerNumber();
                } while (isset($usedTrackers[$tracker]));
                $usedTrackers[$tracker] = true;

                $hasCategory = $this->factory->boolean(self::CASE_CATEGORY_RATE);
                $categoryId = $hasCategory && $categoryIds !== [] ? $this->factory->pick($categoryIds) : null;

                $caseId = $this->factory->uuid();
                $caseRows[] = [
                    'id' => $caseId,
                    'case_number' => $this->factory->caseNumber($period, $seqByPeriod[$period]),
                    'client_type' => $this->factory->clientType(),
                    'vulnerability_indicator' => $this->factory->vulnerability(),
                    'nok_vulnerability_indicator' => null,
                    'tracker_number' => $tracker,
                    'summary' => 'Assistance request for returning OFW — intake '.$monthKey,
                    'status' => 'OPEN',
                    'closed_at' => null,
                    'consent_given_at' => $this->noLaterThan($created->copy()->addMinutes($this->factory->int(10, 300))),
                    'user_id' => $this->caseManagerId,
                    'client_id' => null,
                    'category_id' => $categoryId,
                    'case_issue_id' => $this->factory->boolean(self::CASE_ISSUE_RATE) && $issueIds !== []
                        ? $this->factory->pick($issueIds)
                        : null,
                    'draft_client_data' => null,
                    'source' => $selfFiled ? 'self_filed' : 'internal',
                    'intake_reviewed_by' => $selfFiled ? $this->caseManagerId : null,
                    'created_at' => $created,
                    'updated_at' => $created,
                    'is_deleted' => false,
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'deletion_reason' => null,
                    '_email' => null,
                    '_name' => 'Walk-in client',
                ];

                if ($categoryId !== null) {
                    $pivotRows[] = [
                        'id' => $this->factory->uuid(),
                        'case_id' => $caseId,
                        'case_category_id' => $categoryId,
                        'created_at' => $created,
                        'updated_at' => $created,
                    ];
                }
            }
        }

        // Status by age: oldest 720 close out (oldest 240 ARCHIVED, next 480
        // CLOSED so closure lags fit the window), newest 150 stay DRAFT,
        // the middle band stays OPEN.
        usort($caseRows, fn ($a, $b) => $a['created_at']->getTimestamp() <=> $b['created_at']->getTimestamp());

        // Owner assignment by creation rank: the i-th oldest case pairs with
        // the i-th oldest client. Combined with the pre-window client cohort,
        // owners almost always predate their cases; residual inversions are
        // nudged forward (≤24 h, rarely crossing a month boundary).
        $sortedClients = $this->clientShells;
        usort($sortedClients, fn ($a, $b) => $a['created_at']->getTimestamp() <=> $b['created_at']->getTimestamp());

        foreach ($caseRows as $index => &$row) {
            $owner = $sortedClients[$index % count($sortedClients)];

            if ($row['created_at']->lt($owner['created_at'])) {
                $row['created_at'] = $this->noLaterThan(
                    $owner['created_at']->copy()->addMinutes($this->factory->int(30, 1440))
                );
                $row['updated_at'] = $row['created_at'];
                $row['consent_given_at'] = $this->noLaterThan($row['created_at']->copy()->addMinutes($this->factory->int(10, 300)));
            }

            $row['client_id'] = $owner['id'];
            $row['_email'] = $owner['email'];
            $row['_name'] = $owner['name'];
        }
        unset($row);

        $total = count($caseRows);
        $draftCount = (int) round($total * 0.10);
        $archivedCount = 240;
        $closedCount = 480;

        foreach ($caseRows as $index => &$row) {
            if ($index < $archivedCount) {
                $row['status'] = 'ARCHIVED';
            } elseif ($index < $archivedCount + $closedCount) {
                $row['status'] = 'CLOSED';
            } elseif ($index >= $total - $draftCount) {
                $row['status'] = 'DRAFT';
                $row['consent_given_at'] = null;
                if ($this->factory->boolean(self::CLIENTLESS_DRAFT_RATE)) {
                    $row['client_id'] = null;
                    $row['_email'] = null;
                    $row['_name'] = 'Walk-in client';
                }
            } else {
                $row['status'] = 'OPEN';
            }
        }
        unset($row);

        // A small realistic soft-deleted subset (OPEN cases only, so closure
        // chains stay explainable).
        $deleted = 0;
        foreach ($caseRows as &$row) {
            if ($row['status'] === 'OPEN' && $deleted < 15 && $this->factory->boolean(0.03)) {
                $row['is_deleted'] = true;
                $row['deleted_at'] = $this->noLaterThan($row['updated_at']->copy()->addDays($this->factory->int(1, 20)));
                $row['deleted_by'] = $this->adminId;
                $row['deletion_reason'] = 'Duplicate intake record';
                $deleted++;
            }
        }
        unset($row);

        $this->chunkInsert('cases', array_map(fn ($row) => $this->stripMeta($row, ['_email', '_name']), $caseRows));
        $this->chunkInsert('case_category', $pivotRows);

        foreach ($caseRows as $row) {
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
        }

        unset($caseRows, $pivotRows);
    }

    // ------------------------------------------------------------------
    // T6 — referrals + milestones + compliance requirements
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

            $agencyPair = $this->factory->shuffle($this->agencies);
            $agencyPair = array_slice($agencyPair, 0, 2);

            foreach ([0, 1] as $position) {
                $referral = $this->buildReferral($caseId, $case, $agencyPair[$position], $position);
                $referralRows[] = $this->stripMeta($referral, ['_service', '_service_id', '_processing_days', '_terminal']);
                [$requirementRows, $requirementNames] = $this->buildServiceRequirements($referral);
                $serviceReqRows = array_merge($serviceReqRows, $requirementRows);
                $milestoneRows = array_merge($milestoneRows, $this->buildMilestones($referral, $requirementNames));
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
                    'processing_days' => $referral['_processing_days'],
                    'status' => $referral['status'],
                    'created_at' => $referral['created_at'],
                    'updated_at' => $referral['updated_at'],
                    'completed_at' => $referral['_terminal'],
                    'is_deleted' => false,
                ];

                // Case stamps follow the referral activity.
                if ($referral['updated_at']->gt($this->cases[$caseId]['updated_at'])) {
                    $this->cases[$caseId]['updated_at'] = $referral['updated_at'];
                }
            }
        }

        $this->chunkInsert('referrals', $referralRows, 500);
        $this->chunkInsert('milestones', $milestoneRows, 500);
        $this->chunkInsert('referral_service_requirements', $serviceReqRows, 500);
        $this->chunkInsert('referral_services', $serviceLinkRows, 500);

        // Persist case updated_at movement + closed_at for CLOSED/ARCHIVED.
        foreach ($this->cases as $caseId => $case) {
            $update = ['updated_at' => $case['updated_at']];

            if (in_array($case['status'], ['CLOSED', 'ARCHIVED'], true)) {
                $completion = $this->latestCompletion($caseId) ?? $case['created_at']->copy()->addDays(60);
                $update['closed_at'] = $this->noLaterThan($this->time->caseClosedAt($completion));
                if ($update['closed_at']->gt($case['updated_at'])) {
                    $update['updated_at'] = $update['closed_at'];
                    $this->cases[$caseId]['updated_at'] = $update['updated_at'];
                }
            }

            DB::table('cases')->where('id', $caseId)->update($update);
        }

        // Small soft-deleted subset of referrals on OPEN cases.
        $deleted = 0;
        foreach ($this->referrals as $referralId => $referral) {
            if ($deleted >= 20) {
                break;
            }
            if ($this->cases[$referral['case_id']]['status'] !== 'OPEN') {
                continue;
            }
            if ($this->factory->boolean(0.02)) {
                DB::table('referrals')->where('id', $referralId)->update([
                    'is_deleted' => true,
                    'deleted_at' => $this->noLaterThan($referral['updated_at']->copy()->addDays($this->factory->int(1, 10))),
                    'deleted_by' => $this->adminId,
                ]);
                $this->referrals[$referralId]['is_deleted'] = true;
                $deleted++;
            }
        }

        unset($referralRows, $milestoneRows, $serviceReqRows, $serviceLinkRows);
    }

    /**
     * @param  array{id: string, slug: string}  $agency
     * @return array<string, mixed>
     */
    private function buildReferral(string $caseId, array $case, array $agency, int $position): array
    {
        $service = $this->serviceForAgency($agency);
        $created = $this->agencyAtOrAfter(
            $case['created_at']->copy()->addMinutes($this->factory->int(30, 4320))
        );
        $assigned = $this->agencyAtOrAfter($created->copy()->addMinutes($this->factory->int(10, 480)));

        $status = $this->referralStatus($case['status'], $position);

        // Runway guard: a referral created within a few business days of the
        // anchor cannot honestly have advanced mid-chain — its SLA hops
        // (PROCESSING +1–3bd, FOR_COMPLIANCE +3–8bd total) would outrun the
        // window and either leak into the future or pile onto the anchor.
        // Downgrade to the stage that fits; the RNG draw above is kept so
        // the stream stays deterministic.
        if ($case['status'] === 'OPEN' && in_array($status, ['PROCESSING', 'FOR_COMPLIANCE'], true)) {
            $runway = $this->businessDaysBetween($created, $this->cap);

            if ($status === 'FOR_COMPLIANCE' && $runway < 3) {
                $status = $runway >= 1 ? 'PROCESSING' : 'PENDING';
            } elseif ($status === 'PROCESSING' && $runway < 1) {
                $status = 'PENDING';
            }
        }

        $firstAction = null;
        $decision = null;
        $decisionComment = null;
        $terminal = null;
        $updated = $created;

        switch ($status) {
            case 'PROCESSING':
                $firstAction = $this->noLaterThan($this->time->pendingToProcessing($created));
                $updated = $firstAction;
                break;
            case 'FOR_COMPLIANCE':
                $firstAction = $this->noLaterThan($this->time->pendingToProcessing($created));
                $updated = $this->noLaterThan($this->time->processingToCompliance($firstAction));
                break;
            case 'COMPLETED':
                $firstAction = $this->noLaterThan($this->time->pendingToProcessing($created));
                $terminal = $this->time->complianceToCompleted(
                    $this->time->processingToCompliance($firstAction),
                    $service['processing_days']
                );
                $terminal = $this->noLaterThan($terminal);
                $decision = 'ACCEPT';
                $updated = $terminal;
                break;
            case 'REJECTED':
                $updated = $this->noLaterThan($this->time->rejectionAt($created));
                $decision = 'REJECT';
                $decisionComment = 'Requirements not met';
                break;
            default: // PENDING
                // Minute offsets can push the stamp past 17:00 (or midnight),
                // so re-snap into agency hours instead of storing raw.
                $updated = $this->agencyAtOrAfter($created->copy()->addMinutes($this->factory->int(30, 600)));
                break;
        }

        // Monotonicity guard: SLA chains on young cases can outrun the cap.
        foreach (['assigned' => $assigned, 'firstAction' => $firstAction, 'terminal' => $terminal] as $stamp) {
            if ($stamp !== null && $stamp->lt($created)) {
                throw new \RuntimeException('Referral SLA chain moved backwards — temporal engine misuse.');
            }
        }

        return [
            'id' => $this->factory->uuid(),
            'required_services' => $service['name'],
            'notes' => $this->factory->boolean(0.6) ? 'Referral for '.$case['status'].' case — '.$service['name'] : null,
            'status' => $status,
            'decision' => $decision,
            'decision_comment' => $decisionComment,
            'case_id' => $caseId,
            'agcy_id' => $agency['id'],
            'first_action_at' => $firstAction,
            'referral_assigned_at' => $status === 'PENDING' ? null : $assigned,
            'created_at' => $created,
            'updated_at' => $updated,
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null,
            '_service' => $service['name'],
            '_service_id' => $service['id'],
            '_processing_days' => $service['processing_days'],
            '_terminal' => $terminal,
        ];
    }

    private function referralStatus(string $caseStatus, int $position): string
    {
        if (in_array($caseStatus, ['CLOSED', 'ARCHIVED'], true)) {
            // First referral always completes (explains the closure); the
            // second is occasionally rejected.
            return $position === 0 || $this->factory->boolean(0.90) ? 'COMPLETED' : 'REJECTED';
        }

        $roll = $this->factory->int(1, 1000);

        if ($roll <= self::OPEN_PENDING_MAX) {
            return 'PENDING';
        }

        return $roll <= self::OPEN_PROCESSING_MAX ? 'PROCESSING' : 'FOR_COMPLIANCE';
    }

    /**
     * @param  array<string, mixed>  $referral
     * @param  list<string>  $requirementNames
     * @return list<array<string, mixed>>
     */
    private function buildMilestones(array $referral, array $requirementNames = []): array
    {
        $templates = self::MILESTONE_TEMPLATES[$referral['status']] ?? [];
        $rows = [];
        $previous = $referral['created_at'];
        $terminal = $referral['updated_at'];

        foreach ($templates as $template) {
            $at = $this->agencyAtOrAfter($previous->copy()->addMinutes($this->factory->int(60, 2880)));

            if ($at->gt($terminal)) {
                $at = $terminal->copy();
            }

            $rows[] = [
                'id' => $this->factory->uuid(),
                'title' => $template['title'],
                'description' => $template['description'],
                'requirements' => in_array($template['title'], ['Documents Submitted', 'Compliance Check'], true) && $requirementNames !== []
                    ? json_encode($requirementNames)
                    : null,
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

        return $rows;
    }

    /**
     * Two service-requirement rows per referral for the CURRENT schema
     * (referral_service_requirements; the old referral_compliance_requirements
     * table was dropped by 2026_07_18_000001). Returns [rows, names] — the
     * names also feed milestones.requirements JSON above.
     *
     * @param  array<string, mixed>  $referral
     * @return array{list<array<string, mixed>>, list<string>}
     */
    private function buildServiceRequirements(array $referral): array
    {
        $names = array_slice($this->requirementNames($referral), 0, 2);
        $rows = [];

        foreach ($names as $sort => $name) {
            $created = $this->noLaterThan($referral['created_at']->copy()->addMinutes($this->factory->int(5, 300)));

            $rows[] = [
                'id' => $this->factory->uuid(),
                'referral_id' => $referral['id'],
                'service_id' => $referral['_service_id'],
                'name' => $name,
                'description' => null,
                'is_required' => $this->factory->boolean(0.80),
                'sort_order' => $sort,
                'is_deleted' => false,
                'deleted_by' => null,
                'deleted_at' => null,
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }

        return [$rows, $names];
    }

    // ------------------------------------------------------------------
    // T7 — comments, attachments, documents, notifications
    // ------------------------------------------------------------------

    private function seedCollaboration(): void
    {
        $commentRows = [];
        $attachmentRows = [];
        $documentRows = [];
        $notificationRows = [];

        $staffComments = [
            'Coordinated with the agency focal for documentary requirements.',
            'Client confirmed receipt of the referral slip.',
            'Followed up on the pending medical certificate.',
            'Verified the submitted identification documents.',
            'Endorsed the request to the processing unit.',
            'Agency acknowledged receipt; awaiting action.',
            'Reminded the client of the compliance deadline.',
            'Updated the case timeline after agency feedback.',
            'Confirmed the documentary requirements are complete.',
            'Requested clarification on the submitted payslip.',
        ];
        $replyComments = [
            'Noted, proceeding with the next step.',
            'Acknowledged. Will update once received.',
            'Copy. Coordinating with the agency now.',
            'Thanks — documents look complete.',
        ];

        foreach ($this->referrals as $referralId => $referral) {
            $count = 1 + ($this->factory->boolean(0.5) ? 1 : 0);
            $parentId = null;

            for ($i = 0; $i < $count; $i++) {
                $isReply = $i > 0 && $this->factory->boolean(0.30);
                $at = $this->agencyAtOrAfter(
                    $referral['created_at']->copy()->addMinutes($this->factory->int(30, 10080))
                );
                $at = $this->noLaterThan($at);
                $commentDeleted = $this->factory->boolean(0.01);

                $commentRows[] = [
                    'id' => $this->factory->uuid(),
                    'refr_id' => $referralId,
                    'parent_id' => $isReply ? $parentId : null,
                    'content' => $isReply
                        ? $this->factory->pick($replyComments)
                        : $this->factory->pick($staffComments),
                    'visibility' => $this->factory->boolean(0.70) ? 'INTERNAL' : 'AGY_ONLY',
                    'is_edited' => $this->factory->boolean(0.10),
                    'user_id' => $this->actorForAgency($referral['agency_id']),
                    'created_at' => $at,
                    'updated_at' => $at,
                    'is_deleted' => $commentDeleted,
                    'deleted_at' => $commentDeleted ? $this->noLaterThan($at->copy()->addDays($this->factory->int(1, 10))) : null,
                    'deleted_by' => $commentDeleted ? $this->adminId : null,
                ];

                if ($i === 0) {
                    $parentId = $commentRows[count($commentRows) - 1]['id'];
                }
            }

            if ($this->factory->boolean(0.62)) {
                $firstId = $this->factory->uuid();
                $created = $this->agencyAtOrAfter(
                    $referral['created_at']->copy()->addMinutes($this->factory->int(60, 8640))
                );
                $created = $this->noLaterThan($created);
                $file = $this->attachmentFile();
                $attachmentDeleted = $this->factory->boolean(0.01);

                $attachmentRows[] = [
                    'id' => $firstId,
                    'referral_id' => $referralId,
                    'file_name' => $file['name'],
                    'file_path' => 'staging/referral-attachments/'.$firstId.'.'.$file['ext'],
                    'file_type' => $file['mime'],
                    'size' => $this->factory->int(50000, 4000000),
                    'user_id' => $this->actorForAgency($referral['agency_id']),
                    'replaces_id' => null,
                    'version_group_id' => null,
                    'is_archived' => false,
                    'created_at' => $created,
                    'updated_at' => $created,
                    'is_deleted' => $attachmentDeleted,
                    'deleted_at' => $attachmentDeleted ? $this->noLaterThan($created->copy()->addDays($this->factory->int(1, 10))) : null,
                    'deleted_by' => $attachmentDeleted ? $this->adminId : null,
                ];

                if ($this->factory->boolean(0.20)) {
                    $secondId = $this->factory->uuid();
                    $versionGroup = $this->factory->uuid();
                    $revised = $this->noLaterThan($created->copy()->addDays($this->factory->int(1, 7)));
                    $file = $this->attachmentFile();

                    $attachmentRows[count($attachmentRows) - 1]['is_archived'] = true;
                    $attachmentRows[count($attachmentRows) - 1]['version_group_id'] = $versionGroup;
                    $attachmentRows[] = [
                        'id' => $secondId,
                        'referral_id' => $referralId,
                        'file_name' => $file['name'],
                        'file_path' => 'staging/referral-attachments/'.$secondId.'.'.$file['ext'],
                        'file_type' => $file['mime'],
                        'size' => $this->factory->int(50000, 4000000),
                        'user_id' => $this->actorForAgency($referral['agency_id']),
                        'replaces_id' => $firstId,
                        'version_group_id' => $versionGroup,
                        'is_archived' => false,
                        'created_at' => $revised,
                        'updated_at' => $revised,
                        'is_deleted' => false,
                        'deleted_at' => null,
                        'deleted_by' => null,
                    ];
                }
            }
        }

        $docNames = [
            ['Passport.pdf', 'application/pdf', 'identification'],
            ['Employment-Contract.pdf', 'application/pdf', 'contract'],
            ['Medical-Certificate.pdf', 'application/pdf', 'medical'],
            ['OEC.pdf', 'application/pdf', 'travel'],
            ['Birth-Certificate.pdf', 'application/pdf', 'civil-registry'],
            ['NBI-Clearance.pdf', 'application/pdf', 'identification'],
            ['Payslip.jpg', 'image/jpeg', 'contract'],
            ['Barangay-Clearance.pdf', 'application/pdf', 'civil-registry'],
        ];

        $referralsByCase = [];
        foreach ($this->referrals as $referralId => $referral) {
            $referralsByCase[$referral['case_id']][] = $referralId;
        }

        foreach ($this->cases as $caseId => $case) {
            $docCount = ($this->factory->boolean(0.95) ? 1 : 0)
                + ($this->factory->boolean(0.50) ? 1 : 0)
                + (in_array($case['status'], ['CLOSED', 'ARCHIVED'], true) && $this->factory->boolean(0.30) ? 1 : 0);

            $caseReferrals = $referralsByCase[$caseId] ?? [];

            for ($i = 0; $i < $docCount; $i++) {
                $doc = $this->factory->pick($docNames);
                $linkedReferral = $caseReferrals !== [] && $this->factory->boolean(0.40)
                    ? $this->factory->pick($caseReferrals)
                    : null;
                $base = $linkedReferral !== null
                    ? $this->referrals[$linkedReferral]['created_at']
                    : $case['created_at'];
                $created = $this->agencyAtOrAfter($base->copy()->addMinutes($this->factory->int(60, 5760)));
                $created = $this->noLaterThan($created);
                $docId = $this->factory->uuid();
                $deleted = $this->factory->boolean(0.02);

                $documentRows[] = [
                    'id' => $docId,
                    'file_name' => $doc[0],
                    'file_path' => 'staging/case-documents/'.$docId.'.'.pathinfo($doc[0], PATHINFO_EXTENSION),
                    'file_type' => $doc[1],
                    'category' => $doc[2],
                    'size' => $this->factory->int(80000, 6000000),
                    'case_id' => $caseId,
                    'referral_id' => $linkedReferral,
                    'user_id' => $case['user_id'],
                    'created_at' => $created,
                    'updated_at' => $created,
                    'is_deleted' => $deleted,
                    'deleted_at' => $deleted ? $this->noLaterThan($created->copy()->addDays($this->factory->int(1, 30))) : null,
                    'deleted_by' => $deleted ? $this->adminId : null,
                ];
            }

            if ($case['email'] === null) {
                continue;
            }

            $notificationRows[] = $this->notificationRow(
                $caseId, $case, 'case.created', 'Case filed',
                'Your assistance request '.$case['tracker'].' has been received and is under review.',
                $case['created_at']
            );

            if ($caseReferrals !== []) {
                $firstReferral = $this->referrals[$caseReferrals[0]];
                $notificationRows[] = $this->notificationRow(
                    $caseId, $case, 'referral.created', 'Referral sent to agency',
                    'Your case has been referred to an agency for '.$firstReferral['service'].'.',
                    $firstReferral['created_at']
                );
            }

            $notificationRows[] = $this->notificationRow(
                $caseId, $case, 'case.status_updated', 'Case update',
                'There is a new update on your case '.$case['tracker'].'.',
                $case['updated_at']
            );

            if ($this->factory->boolean(0.15)) {
                $notificationRows[] = $this->notificationRow(
                    $caseId, $case, 'case.reminder', 'Gentle reminder',
                    'Please check your case inbox for any pending requirements.',
                    $this->noLaterThan($case['updated_at']->copy()->addDays($this->factory->int(2, 14)))
                );
            }
        }

        $this->chunkInsert('referral_comments', $commentRows, 500);
        $this->chunkInsert('referral_attachments', $attachmentRows, 500);
        $this->chunkInsert('case_documents', $documentRows, 500);
        $this->chunkInsert('case_notifications', $notificationRows, 500);

        unset($commentRows, $attachmentRows, $documentRows, $notificationRows);
    }

    /**
     * @return array{name: string, ext: string, mime: string}
     */
    private function attachmentFile(): array
    {
        // file_type is varchar(50): only short MIME types fit (a .docx
        // MIME is 71 chars and would overflow — same as production uploads).
        $roll = $this->factory->int(1, 100);

        if ($roll <= 70) {
            return ['name' => 'Supporting-Document.pdf', 'ext' => 'pdf', 'mime' => 'application/pdf'];
        }

        if ($roll <= 90) {
            return ['name' => 'Supporting-Photo.jpg', 'ext' => 'jpg', 'mime' => 'image/jpeg'];
        }

        if ($roll <= 97) {
            return ['name' => 'Supporting-Photo.png', 'ext' => 'png', 'mime' => 'image/png'];
        }

        return ['name' => 'Supporting-Document.doc', 'ext' => 'doc', 'mime' => 'application/msword'];
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    private function notificationRow(string $caseId, array $case, string $type, string $title, string $message, Carbon $at): array
    {
        $read = $this->factory->boolean(0.70);

        return [
            'id' => $this->factory->uuid(),
            'case_id' => $caseId,
            'client_email' => $case['email'],
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => json_encode(['case_id' => $caseId, 'event' => $type]),
            'related_url' => '/cases/'.$caseId,
            'read_at' => $read ? $this->noLaterThan($at->copy()->addMinutes($this->factory->int(30, 4320))) : null,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    // ------------------------------------------------------------------
    // T7 — client request flow
    // ------------------------------------------------------------------

    private function seedClientRequests(): void
    {
        $requestRows = [];
        $itemRows = [];
        $linkRows = [];
        $messageRows = [];

        $titles = [
            'DOCUMENT_REQUEST' => ['Submission of lacking requirements', 'Additional documents needed', 'Compliance documents request'],
            'QUESTION' => ['Clarification on employment details', 'Confirmation of contact information', 'Verification of submitted documents'],
            'INFORMATION_UPDATE' => ['Update your contact details', 'Update your employment record', 'Correct your personal information'],
        ];
        $itemLabels = [
            'Passport bio page', 'Employment contract', 'Overseas Employment Certificate',
            'Medical certificate', 'Birth certificate', 'Marriage certificate',
            'Barangay clearance', 'NBI clearance', 'Proof of billing', 'Recent 2x2 photo',
        ];
        $agencyBodies = [
            'Good day! Please submit the following documents at your earliest convenience.',
            'This is a follow-up on our request. Kindly comply before the due date.',
            'Kindly confirm receipt of this message.',
            'Please upload clear copies of the listed requirements.',
        ];
        $clientBodies = [
            'Good day! I have submitted the requested documents. Thank you!',
            'Please confirm if the copies I sent are clear enough.',
            'Good evening, I already complied with the request.',
            'Thank you for the update. Requirements attached.',
        ];

        foreach ($this->referrals as $referralId => $referral) {
            $case = $this->cases[$referral['case_id']];

            // Client-write gating: only live PROCESSING/FOR_COMPLIANCE
            // referrals on OPEN, non-deleted cases.
            if (! in_array($referral['status'], ['PROCESSING', 'FOR_COMPLIANCE'], true)) {
                continue;
            }
            if ($case['status'] !== 'OPEN' || $case['is_deleted'] || $referral['is_deleted']) {
                continue;
            }

            $requestCount = 1 + ($this->factory->boolean(0.60) ? 1 : 0) + ($this->factory->boolean(0.25) ? 1 : 0);

            for ($r = 0; $r < $requestCount; $r++) {
                $typeRoll = $this->factory->int(1, 100);
                $type = $typeRoll <= 50 ? 'DOCUMENT_REQUEST' : ($typeRoll <= 80 ? 'QUESTION' : 'INFORMATION_UPDATE');
                $statusRoll = $this->factory->int(1, 100);
                $status = $statusRoll <= 30 ? 'OPEN' : ($statusRoll <= 60 ? 'IN_PROGRESS' : ($statusRoll <= 85 ? 'CLIENT_RESPONDED' : 'COMPLETED'));
                $created = $this->agencyAtOrAfter(
                    $referral['created_at']->copy()->addDays($this->factory->int(1, 10))
                );
                $created = $this->noLaterThan($created);
                $creator = $this->actorForAgency($referral['agency_id']);
                $requestId = $this->factory->uuid();

                $requestRows[] = [
                    'id' => $requestId,
                    'referral_id' => $referralId,
                    'creator_user_id' => $creator,
                    'type' => $type,
                    'title' => $this->factory->pick($titles[$type]),
                    // 'encrypted' cast set-path is Crypt::encrypt($v, false):
                    // the default serialize=true would store (and return)
                    // a PHP-serialized string instead of the plaintext.
                    'instructions' => Crypt::encrypt('Please comply with each item below before the due date.', false),
                    'status' => $status,
                    'due_at' => $created->copy()->addDays($this->factory->int(7, 14)),
                    'created_at' => $created,
                    'updated_at' => $created,
                    'is_deleted' => false,
                    'deleted_at' => null,
                    'deleted_by' => null,
                ];

                for ($sort = 0; $sort < 2; $sort++) {
                    $itemRows[] = [
                        'id' => $this->factory->uuid(),
                        'request_id' => $requestId,
                        'label' => $this->factory->pick($itemLabels),
                        'sort_order' => $sort,
                        'created_at' => $created,
                        'updated_at' => $created,
                        'is_deleted' => false,
                        'deleted_at' => null,
                        'deleted_by' => null,
                    ];
                }

                $rawToken = str_replace('-', '', $this->factory->uuid());
                $linkId = $this->factory->uuid();
                $used = $this->factory->boolean(0.70);
                $firstUsed = $used ? $this->time->clientDateTime($created->copy()->addDays($this->factory->int(1, 5))) : null;
                $firstUsed = $firstUsed !== null ? $this->noLaterThan($firstUsed) : null;
                $revoked = $this->factory->boolean(0.03);

                $linkRows[] = [
                    'id' => $linkId,
                    'request_id' => $requestId,
                    'token_hash' => hash('sha256', $rawToken),
                    'expires_at' => $created->copy()->addDays(30),
                    'revoked_at' => $revoked ? $this->noLaterThan($created->copy()->addDays($this->factory->int(5, 20))) : null,
                    'revoked_by' => $revoked ? $creator : null,
                    'issued_by' => $creator,
                    // 'encrypted:array' cast set-path: json_encode first,
                    // then Crypt::encrypt($json, false) — mirroring
                    // ReferralClientRequestController::issueForClient().
                    'recipient_snapshot' => Crypt::encrypt(json_encode([
                        'name' => $case['name'],
                        'email' => $case['email'],
                    ]), false),
                    'first_used_at' => $firstUsed,
                    'last_used_at' => $firstUsed !== null && $this->factory->boolean(0.5)
                        ? $this->noLaterThan($firstUsed->copy()->addDays($this->factory->int(0, 3)))
                        : $firstUsed,
                    'use_count' => $firstUsed !== null ? $this->factory->int(1, 5) : 0,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];

                $messageRows[] = $this->requestMessage($requestId, 'AGENCY_USER', $creator, null, $this->factory->pick($agencyBodies), $created);

                $replyAt = $this->noLaterThan($this->time->clientDateTime($created->copy()->addDays($this->factory->int(1, 5))));
                $messageRows[] = $this->requestMessage($requestId, 'CLIENT_ACCESS', null, $linkId, $this->factory->pick($clientBodies), $replyAt);

                $thirdAt = $this->noLaterThan($replyAt->copy()->addDays($this->factory->int(1, 3)));
                if ($this->factory->boolean(0.60)) {
                    // Agency follow-ups keep agency hours even when the
                    // client reply they follow came in the evening.
                    $messageRows[] = $this->requestMessage($requestId, 'AGENCY_USER', $creator, null, $this->factory->pick($agencyBodies), $this->agencyAtOrAfter($thirdAt));
                } else {
                    $clientAt = $this->noLaterThan($this->time->clientDateTime($replyAt->copy()->addDays($this->factory->int(1, 3))));
                    $messageRows[] = $this->requestMessage($requestId, 'CLIENT_ACCESS', null, $linkId, $this->factory->pick($clientBodies), $clientAt);
                }

                $this->pushEmail(
                    $case['email'],
                    'New request: '.$requestRows[count($requestRows) - 1]['title'],
                    'App\\Mail\\ClientRequestMail',
                    $created
                );
            }
        }

        $this->chunkInsert('referral_client_requests', $requestRows, 500);
        $this->chunkInsert('referral_client_request_items', $itemRows, 500);
        $this->chunkInsert('referral_client_access_links', $linkRows, 500);
        $this->chunkInsert('referral_client_messages', $messageRows, 500);

        unset($requestRows, $itemRows, $linkRows, $messageRows);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestMessage(string $requestId, string $senderKind, ?string $userId, ?string $linkId, string $body, Carbon $at): array
    {
        return [
            'id' => $this->factory->uuid(),
            'request_id' => $requestId,
            // 'encrypted' cast set-path: Crypt::encrypt($value, false).
            // Bulk inserts bypass the cast, so encrypt here — plaintext
            // breaks every model read with a DecryptException.
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
    // T8 — surveys + feedback
    // ------------------------------------------------------------------

    private function seedSurveysAndFeedback(): void
    {
        $formRows = [];
        $questionRows = [];
        $invitationRows = [];
        $responseRows = [];
        $feedbackRows = [];
        $servqualRows = [];

        $activeByAgency = [];

        foreach ($this->agencies as $agency) {
            $formId = $this->factory->uuid();
            $formRows[] = [
                'id' => $formId,
                'agency_id' => $agency['id'],
                'title' => $this->agencySurveyTitle($agency['slug']),
                'description' => 'Help us improve our services by answering this short survey.',
                'is_active' => true,
                'activated_at' => $this->windowStart,
                'created_at' => $this->windowStart,
                'updated_at' => $this->windowStart,
            ];
            $activeByAgency[$agency['id']] = $formId;
        }

        // Two extra INACTIVE forms (partial unique index allows only one
        // active form per agency).
        foreach ([
            ['slug' => 'dmw', 'title' => 'DMW Post-Repatriation Follow-up Survey'],
            ['slug' => 'owwa', 'title' => 'OWWA Reintegration Program Survey'],
        ] as $extra) {
            $agencyId = $this->agencyIdBySlug($extra['slug']);
            if ($agencyId === null) {
                continue;
            }
            $formRows[] = [
                'id' => $this->factory->uuid(),
                'agency_id' => $agencyId,
                'title' => $extra['title'],
                'description' => 'Pilot survey instrument (inactive).',
                'is_active' => false,
                'activated_at' => null,
                'created_at' => $this->windowStart,
                'updated_at' => $this->windowStart,
            ];
        }

        $questionTypes = ['likert', 'likert', 'likert', 'likert', 'rating', 'rating', 'text', 'text', 'radio', 'checkbox'];
        $firstQuestionByForm = [];

        foreach ($formRows as $form) {
            foreach ($questionTypes as $order => $type) {
                $questionId = $this->factory->uuid();
                $questionRows[] = [
                    'id' => $questionId,
                    'survey_form_id' => $form['id'],
                    'type' => $type,
                    'label' => $this->surveyQuestionLabel($type, $order),
                    'options' => in_array($type, ['radio', 'checkbox'], true)
                        ? json_encode(['Staff courtesy', 'Timeliness', 'Clear process', 'Follow-up support'])
                        : null,
                    'is_required' => ! ($type === 'text' && $order === 7),
                    'order' => $order,
                    'created_at' => $this->windowStart,
                    'updated_at' => $this->windowStart,
                ];

                if ($order === 0) {
                    $firstQuestionByForm[$form['id']] = [
                        'id' => $questionId,
                        'type' => $type,
                        'label' => $this->surveyQuestionLabel($type, $order),
                    ];
                }
            }
        }

        $this->chunkInsert('survey_forms', $formRows);
        $this->chunkInsert('survey_questions', $questionRows);

        $usedTokens = [];
        $completedReferralIds = [];

        foreach ($this->referrals as $referralId => $referral) {
            $case = $this->cases[$referral['case_id']];
            $formId = $activeByAgency[$referral['agency_id']] ?? null;

            if ($formId === null || $case['email'] === null) {
                continue;
            }

            // No invites on soft-deleted rows (client-write gating).
            if ($case['is_deleted'] || $referral['is_deleted']) {
                continue;
            }

            do {
                $rawToken = str_replace('-', '', $this->factory->uuid());
            } while (isset($usedTokens[$rawToken]));
            $usedTokens[$rawToken] = true;

            $created = $this->noLaterThan($referral['updated_at']->copy()->addDays($this->factory->int(0, 3)));
            $invitationId = $this->factory->uuid();
            $submittedAt = null;

            if ($this->factory->boolean(VolumeModel::SURVEY_RESPONSE_RATE)) {
                $candidate = $this->time->clientDateTime($created->copy()->addDays($this->factory->int(1, 14)));
                $candidate = $this->noLaterThan($candidate);

                if ($candidate->gt($created)) {
                    $submittedAt = $candidate;
                }
            }

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
                    'id' => $this->factory->uuid(),
                    'survey_invitation_id' => $invitationId,
                    'survey_question_id' => $question['id'],
                    'answer' => $this->surveyAnswer($question['type']),
                    'selected_options' => null,
                    'created_at' => $submittedAt,
                ];
            }

            if ($this->factory->boolean(0.50)) {
                $this->pushEmail(
                    $case['email'],
                    'We value your feedback on '.$referral['service'],
                    'App\\Mail\\SurveyRequestMail',
                    $created
                );
            }

            if ($referral['status'] === 'COMPLETED') {
                $completedReferralIds[] = $referralId;
            }
        }

        $this->chunkInsert('survey_invitations', $invitationRows, 500);
        $this->chunkInsert('survey_responses', $responseRows, 500);

        // Feedback on a distinct subset of COMPLETED referrals (UNIQUE
        // case/agency/referral guard holds by construction).
        $completedReferralIds = $this->factory->shuffle($completedReferralIds);
        $feedbackTargets = array_slice($completedReferralIds, 0, VolumeModel::FEEDBACK);
        $servqualQuestions = $this->servqualQuestions();

        foreach ($feedbackTargets as $referralId) {
            $referral = $this->referrals[$referralId];
            $case = $this->cases[$referral['case_id']];

            if ($case['is_deleted'] || $referral['is_deleted']) {
                continue;
            }

            $created = $this->time->clientDateTime(
                $referral['completed_at']->copy()->addDays($this->factory->int(1, 10))
            );
            $created = $this->noLaterThan($created);

            if ($created->lt($referral['completed_at'])) {
                $created = $referral['completed_at']->copy();
            }

            $feedbackId = $this->factory->uuid();
            $feedbackRows[] = [
                'id' => $feedbackId,
                'case_id' => $referral['case_id'],
                'agency_id' => $referral['agency_id'],
                'referral_id' => $referralId,
                'service_name' => $referral['service'],
                'overall_rating' => $this->factory->int(1, 100) <= 20 ? 3 : $this->factory->pick([4, 5]),
                'comments' => $this->factory->feedbackComment(),
                'created_at' => $created,
                'updated_at' => $created,
            ];

            foreach ($servqualQuestions as $item) {
                // A response session can run up to 2h; keep every item
                // inside client hours (07:00–21:00) instead of spilling
                // past 21:00 on late-evening feedback.
                $eveningEnd = $created->copy()->setTime(21, 0, 0);
                $at = $this->noLaterThan($created->copy()->addMinutes($this->factory->int(1, 120)));

                if ($at->gt($eveningEnd)) {
                    $at = $eveningEnd->copy();
                }
                $servqualRows[] = [
                    'id' => $this->factory->uuid(),
                    'feedback_id' => $feedbackId,
                    'question_id' => $this->factory->uuid(),
                    'question_text' => $item['question'],
                    'dimension' => $item['dimension'],
                    'expectation' => $this->factory->int(3, 5),
                    'perception' => $this->factory->int(1, 5),
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
            }

            $this->pushEmail(
                $case['email'],
                'Thank you for your feedback',
                'App\\Mail\\ClientUpdateMail',
                $created
            );
        }

        $this->chunkInsert('feedback', $feedbackRows, 500);
        $this->chunkInsert('feedback_servqual_responses', $servqualRows, 500);

        unset($formRows, $questionRows, $invitationRows, $responseRows, $feedbackRows, $servqualRows);
    }

    private function agencySurveyTitle(string $slug): string
    {
        return strtoupper($slug).' Client Satisfaction Survey';
    }

    private function agencyIdBySlug(string $slug): ?string
    {
        foreach ($this->agencies as $agency) {
            if ($agency['slug'] === $slug) {
                return $agency['id'];
            }
        }

        return null;
    }

    private function surveyQuestionLabel(string $type, int $order): string
    {
        $labels = [
            'likert' => [
                'The staff treated me with courtesy and respect.',
                'The assistance I received met my needs.',
                'The process was explained to me clearly.',
                'I am satisfied with the overall service.',
            ],
            'rating' => [
                'Rate the timeliness of the assistance.',
                'Rate the quality of the facilities.',
            ],
            'text' => [
                'What did you appreciate most about the service?',
                'What can we improve in our service delivery?',
            ],
            'radio' => ['Was your concern fully resolved?'],
            'checkbox' => ['Which aspects of the service stood out? (Select all that apply)'],
        ];

        $pool = $labels[$type] ?? ['Please share your experience.'];

        return $pool[$order % count($pool)] ?? $pool[0];
    }

    private function surveyAnswer(string $type): ?string
    {
        return match ($type) {
            'likert', 'rating' => (string) $this->factory->int(3, 5),
            'radio' => $this->factory->pick(['Staff courtesy', 'Timeliness']),
            'text' => $this->factory->feedbackComment(),
            default => null,
        };
    }

    /**
     * SERVQUAL items: system setting wins when populated, otherwise the
     * built-in 22-item instrument.
     *
     * @return list<array{question: string, dimension: string}>
     */
    private function servqualQuestions(): array
    {
        $raw = DB::table('system_settings')->where('key', 'default_servqual_questions')->value('value');
        $decoded = json_decode($raw ?? '[]', true);

        if (is_array($decoded) && $decoded !== []) {
            return array_values(array_filter(
                $decoded,
                fn ($item) => is_array($item) && isset($item['question'])
                    && isset($item['dimension'])
            ));
        }

        return self::SERVQUAL_INSTRUMENT;
    }

    // ------------------------------------------------------------------
    // T9 — email logs
    // ------------------------------------------------------------------

    private function seedEmailLogs(): void
    {
        // Event-driven rows were collected by earlier sections; case-created
        // and referral-completed sends land here so T9 owns the whole table.
        foreach ($this->cases as $case) {
            if ($case['email'] === null || $case['status'] === 'DRAFT') {
                continue;
            }

            $this->pushEmail(
                $case['email'],
                'Your case '.$case['tracker'].' has been filed',
                'App\\Mail\\IntakePublishedMail',
                $case['created_at']
            );
        }

        foreach ($this->referrals as $referral) {
            if ($referral['status'] !== 'COMPLETED' || $referral['completed_at'] === null) {
                continue;
            }

            $case = $this->cases[$referral['case_id']];

            if ($case['email'] === null) {
                continue;
            }

            $this->pushEmail(
                $case['email'],
                'Your referral for '.$referral['service'].' is complete',
                'App\\Mail\\ClientUpdateMail',
                $referral['completed_at']
            );
        }

        $this->chunkInsert('email_logs', $this->emailRows, 500);
        $this->emailRows = [];
    }

    /**
     * Queue one email-log row for T9. Sampling (sent vs failed) happens here
     * so the table stays near its ~6,000 target.
     */
    private function pushEmail(?string $to, string $subject, string $mailable, Carbon $eventAt): void
    {
        if ($to === null) {
            return;
        }

        $sent = $this->factory->boolean(0.95);
        $sentAt = $sent ? $this->noLaterThan($eventAt->copy()->addMinutes($this->factory->int(1, 30))) : null;

        $this->emailRows[] = [
            'id' => $this->factory->uuid(),
            'to_email' => $to,
            'subject' => $subject,
            'mailable_type' => $mailable,
            'status' => $sent ? 'sent' : 'failed',
            'job_uuid' => null,
            'error_message' => $sent ? null : $this->factory->pick(['Connection timed out', 'Mailbox unavailable', 'Message rejected by provider']),
            'provider_message_id' => $sent && $this->factory->boolean(0.60)
                ? 'resend-'.$this->factory->uuid()
                : null,
            'sent_at' => $sentAt,
            'delivered_at' => $sent && $this->factory->boolean(0.80) && $sentAt !== null
                ? $this->noLaterThan($sentAt->copy()->addMinutes($this->factory->int(1, 60)))
                : null,
            'created_at' => $eventAt,
            'updated_at' => $sentAt ?? $eventAt,
        ];
    }

    // ------------------------------------------------------------------
    // T10 — event-driven audit rows (written LAST, chained via AuditChainWriter)
    // ------------------------------------------------------------------

    /**
     * Build the full audit timeline and hash-chain it in ONE finalize() pass
     * (single jsonb-normalisation sweep, inserts in chunks of 200).
     *
     * Event pattern follows TestingSeeder (Case CREATE → Referral CREATE →
     * Milestone CREATEs → Referral UPDATE → Case UPDATE, plus collaboration,
     * client-request, feedback, survey and auth events), but every stamp is
     * anchored to an ACTUAL seeded entity timestamp read from the in-memory
     * maps (cases, referrals, client shells) or re-queried from the database
     * in deterministic (created_at, id) order — never an independent random
     * draw. Audit stamps add a small logging lag on top of the entity stamp,
     * which is the realistic direction (the log entry is written after the
     * event it records).
     *
     * old_value/new_value appear on UPDATE/DELETE rows only, as small status
     * snapshots — never PII plaintext. Actions/modules use the AuditAction /
     * AuditModule enum values so the CHECK constraint always holds.
     */
    private function seedAuditTrail(): void
    {
        // Empty table after truncate → chain root (NULL prev_hash), chained
        // off the tail otherwise (re-entrant when truncate is skipped).
        $writer = AuditChainWriter::createFromCurrentTail();

        $this->seedAuthAudit($writer);
        $this->seedClientAudit($writer);
        $this->seedCaseAudit($writer);
        $this->seedReferralAudit($writer);
        $this->seedTimelineAudit($writer);
        $this->seedRequestAudit($writer);
        $this->seedFeedbackSurveyAudit($writer);

        $inserted = $writer->finalize();
        $this->stats['audit_logs'] = ($this->stats['audit_logs'] ?? 0) + $inserted;
        $this->command?->info("Audit trail chained: {$inserted} rows.");
    }

    /**
     * Buffer one audit row. $old/$new are status snapshots for UPDATE/DELETE
     * rows (null for CREATE and auth events).
     *
     * The stamp is normalised to UTC before buffering: chainDigest() feeds
     * timestamp->toIso8601String() into the hash, and the verifier hydrates
     * rows through Eloquent in the APP timezone (UTC) — so the digest must
     * be computed from the UTC rendering of the same instant, or every row
     * would mismatch on the +08:00 offset alone. (This is exactly why fix-4
     * works in TestingSeeder: its stamps are UTC already.)
     */
    private function pushAudit(
        AuditChainWriter $writer,
        string $action,
        string $module,
        ?string $entityId,
        ?string $userId,
        Carbon $at,
        ?array $old = null,
        ?array $new = null,
        ?string $ip = '127.0.0.1'
    ): void {
        $writer->push([
            'id' => $this->factory->uuid(),
            'action' => $action,
            'module' => $module,
            'entity_id' => $entityId,
            'description' => null,
            'old_value' => $old,
            'new_value' => $new,
            'user_id' => $userId,
            'timestamp' => $this->noLaterThan($at)->timezone('UTC'),
            'ip_address' => $ip,
        ]);
    }

    /**
     * Entity stamp plus a small logging lag (1–$maxMinutes), capped.
     */
    private function auditLag(Carbon $base, int $maxMinutes = 5): Carbon
    {
        return $this->noLaterThan($base->copy()->addMinutes($this->factory->int(1, $maxMinutes)));
    }

    /**
     * Weekday LOGIN/LOGOUT pairs for every seeded user across the window
     * (agency hours), plus a handful of failed sign-ins.
     */
    private function seedAuthAudit(AuditChainWriter $writer): void
    {
        $users = DB::table('users')->select('id')->orderBy('email')->get();
        $days = (int) $this->windowStart->diffInDays($this->now);

        foreach ($users as $user) {
            for ($offset = 0; $offset <= $days; $offset++) {
                $day = $this->windowStart->copy()->addDays($offset);

                if (! $this->time->isBusinessDay($day) || ! $this->factory->boolean(0.80)) {
                    continue;
                }

                $loginAt = $this->time->agencyDateTime($day);
                $this->pushAudit($writer, AuditAction::LOGIN->value, AuditModule::AUTH->value, $user->id, $user->id, $loginAt);
                $this->pushAudit(
                    $writer,
                    AuditAction::LOGOUT->value,
                    AuditModule::AUTH->value,
                    $user->id,
                    $user->id,
                    $loginAt->copy()->addMinutes($this->factory->int(60, 540))
                );
            }
        }

        $userIds = $users->pluck('id')->all();

        for ($i = 0; $i < 50; $i++) {
            $userId = $this->factory->pick($userIds);
            $day = $this->time->randomDayInMonth(...$this->windowMonth($this->factory->int(0, 5)));
            $this->pushAudit(
                $writer,
                AuditAction::LOGIN_FAILED->value,
                AuditModule::AUTH->value,
                $userId,
                null,
                $this->time->agencyDateTime($day),
                null,
                null,
                '203.177.42.5'
            );
        }
    }

    /**
     * Client-domain CREATEs: clients (in-memory shells) + addresses,
     * employments and NOK re-queried in deterministic order.
     */
    private function seedClientAudit(AuditChainWriter $writer): void
    {
        $shells = $this->clientShells;
        usort($shells, fn ($a, $b) => $a['created_at']->getTimestamp() <=> $b['created_at']->getTimestamp());

        foreach ($shells as $client) {
            $this->pushAudit(
                $writer,
                AuditAction::CREATE->value,
                AuditModule::CLIENT->value,
                $client['id'],
                $this->caseManagerId,
                $this->auditLag($client['created_at'])
            );
        }

        foreach (['client_addresses' => AuditModule::CLIENT_ADDRESS, 'client_employments' => AuditModule::CLIENT_EMPLOYMENT, 'next_of_kin' => AuditModule::NEXT_OF_KIN] as $table => $module) {
            DB::table($table)->select('id', 'created_at')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer, $module) {
                foreach ($rows as $row) {
                    $this->pushAudit(
                        $writer,
                        AuditAction::CREATE->value,
                        $module->value,
                        $row->id,
                        $this->caseManagerId,
                        $this->auditLag(Carbon::parse($row->created_at), 3)
                    );
                }
            });
        }
    }

    /**
     * Case CREATEs (chronological), UPDATEs on closure, DELETEs on the
     * soft-deleted subset.
     */
    private function seedCaseAudit(AuditChainWriter $writer): void
    {
        $cases = $this->cases;
        uasort($cases, fn ($a, $b) => $a['created_at']->getTimestamp() <=> $b['created_at']->getTimestamp());

        foreach ($cases as $caseId => $case) {
            $this->pushAudit(
                $writer,
                AuditAction::CREATE->value,
                AuditModule::CASE->value,
                $caseId,
                $case['user_id'],
                $this->auditLag($case['created_at'], 3)
            );
        }

        foreach ($cases as $caseId => $case) {
            if (! in_array($case['status'], ['CLOSED', 'ARCHIVED'], true)) {
                continue;
            }

            $this->pushAudit(
                $writer,
                AuditAction::UPDATE->value,
                AuditModule::CASE->value,
                $caseId,
                $case['user_id'],
                $this->auditLag($case['updated_at']),
                ['status' => 'OPEN'],
                ['status' => $case['status']]
            );
        }

        foreach ($cases as $caseId => $case) {
            if (! $case['is_deleted']) {
                continue;
            }

            $this->pushAudit(
                $writer,
                AuditAction::DELETE->value,
                AuditModule::CASE->value,
                $caseId,
                $this->adminId,
                $this->auditLag($case['updated_at']),
                ['is_deleted' => false],
                ['is_deleted' => true]
            );
        }
    }

    /**
     * Referral CREATEs (chronological), terminal UPDATEs, DELETEs on the
     * soft-deleted subset.
     */
    private function seedReferralAudit(AuditChainWriter $writer): void
    {
        $referrals = $this->referrals;
        uasort($referrals, fn ($a, $b) => $a['created_at']->getTimestamp() <=> $b['created_at']->getTimestamp());

        foreach ($referrals as $referralId => $referral) {
            $this->pushAudit(
                $writer,
                AuditAction::CREATE->value,
                AuditModule::REFERRAL->value,
                $referralId,
                $this->agencyUserByAgency[$referral['agency_id']] ?? $this->caseManagerId,
                $this->auditLag($referral['created_at'], 3)
            );
        }

        foreach ($referrals as $referralId => $referral) {
            if (! in_array($referral['status'], ['COMPLETED', 'REJECTED'], true)) {
                continue;
            }

            $this->pushAudit(
                $writer,
                AuditAction::UPDATE->value,
                AuditModule::REFERRAL->value,
                $referralId,
                $this->agencyUserByAgency[$referral['agency_id']] ?? $this->caseManagerId,
                $this->auditLag($referral['updated_at']),
                ['status' => 'PROCESSING'],
                array_filter([
                    'status' => $referral['status'],
                    'decision' => $referral['status'] === 'COMPLETED' ? 'ACCEPT' : 'REJECT',
                ])
            );
        }

        foreach ($referrals as $referralId => $referral) {
            if (! $referral['is_deleted']) {
                continue;
            }

            $this->pushAudit(
                $writer,
                AuditAction::DELETE->value,
                AuditModule::REFERRAL->value,
                $referralId,
                $this->adminId,
                $this->auditLag($referral['updated_at']),
                ['is_deleted' => false],
                ['is_deleted' => true]
            );
        }
    }

    /**
     * Milestone / compliance / comment / attachment / document CREATEs (+
     * document DELETEs), re-queried in deterministic order. Acting users
     * resolve through the referral/case maps; stamps lag the real row stamp.
     */
    private function seedTimelineAudit(AuditChainWriter $writer): void
    {
        $agencyUserForReferral = fn (string $referralId): string => $this->agencyUserByAgency[$this->referrals[$referralId]['agency_id'] ?? ''] ?? $this->caseManagerId;

        DB::table('milestones')->select('id', 'refr_id', 'created_at')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer, $agencyUserForReferral) {
            foreach ($rows as $row) {
                $this->pushAudit($writer, AuditAction::CREATE->value, AuditModule::MILESTONE->value, $row->id, $agencyUserForReferral($row->refr_id), $this->auditLag(Carbon::parse($row->created_at), 10));
            }
        });

        DB::table('referral_service_requirements')->select('id', 'referral_id', 'created_at')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer, $agencyUserForReferral) {
            foreach ($rows as $row) {
                $this->pushAudit($writer, AuditAction::CREATE->value, AuditModule::REFERRAL_SERVICE_REQUIREMENT->value, $row->id, $agencyUserForReferral($row->referral_id), $this->auditLag(Carbon::parse($row->created_at), 10));
            }
        });

        DB::table('referral_comments')->select('id', 'refr_id', 'created_at')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer, $agencyUserForReferral) {
            foreach ($rows as $row) {
                $this->pushAudit($writer, AuditAction::CREATE->value, AuditModule::REFERRAL_COMMENT->value, $row->id, $agencyUserForReferral($row->refr_id), $this->auditLag(Carbon::parse($row->created_at), 10));
            }
        });

        DB::table('referral_attachments')->select('id', 'referral_id', 'created_at')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer, $agencyUserForReferral) {
            foreach ($rows as $row) {
                $this->pushAudit($writer, AuditAction::CREATE->value, AuditModule::REFERRAL_ATTACHMENT->value, $row->id, $agencyUserForReferral($row->referral_id), $this->auditLag(Carbon::parse($row->created_at), 10));
            }
        });

        DB::table('case_documents')->select('id', 'case_id', 'created_at', 'is_deleted')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer) {
            foreach ($rows as $row) {
                $userId = $this->cases[$row->case_id]['user_id'] ?? $this->caseManagerId;
                $this->pushAudit($writer, AuditAction::CREATE->value, AuditModule::CASE_DOCUMENT->value, $row->id, $userId, $this->auditLag(Carbon::parse($row->created_at), 10));

                if ($row->is_deleted) {
                    $this->pushAudit(
                        $writer,
                        AuditAction::DELETE->value,
                        AuditModule::CASE_DOCUMENT->value,
                        $row->id,
                        $this->adminId,
                        $this->auditLag(Carbon::parse($row->created_at), 60),
                        ['is_deleted' => false],
                        ['is_deleted' => true]
                    );
                }
            }
        });
    }

    /**
     * Client-request CREATEs (+ UPDATEs on response/completion) and message
     * CREATEs. Client-sent messages carry no user_id/ip — the actor is the
     * access link, not a seeded user.
     */
    private function seedRequestAudit(AuditChainWriter $writer): void
    {
        DB::table('referral_client_requests')->select('id', 'created_at', 'status', 'creator_user_id')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer) {
            foreach ($rows as $row) {
                $created = Carbon::parse($row->created_at);
                $this->pushAudit($writer, AuditAction::CREATE->value, AuditModule::REFERRAL_CLIENT_REQUEST->value, $row->id, $row->creator_user_id, $this->auditLag($created, 10));

                if (in_array($row->status, ['CLIENT_RESPONDED', 'COMPLETED'], true)) {
                    $this->pushAudit(
                        $writer,
                        AuditAction::UPDATE->value,
                        AuditModule::REFERRAL_CLIENT_REQUEST->value,
                        $row->id,
                        $row->creator_user_id,
                        $this->noLaterThan($created->copy()->addDays($this->factory->int(1, 7))),
                        ['status' => 'OPEN'],
                        ['status' => $row->status]
                    );
                }
            }
        });

        DB::table('referral_client_messages')->select('id', 'created_at', 'sender_kind', 'user_id')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer) {
            foreach ($rows as $row) {
                $isClient = $row->sender_kind === 'CLIENT_ACCESS';
                $this->pushAudit(
                    $writer,
                    AuditAction::CREATE->value,
                    AuditModule::REFERRAL_CLIENT_MESSAGE->value,
                    $row->id,
                    $isClient ? null : $row->user_id,
                    $this->auditLag(Carbon::parse($row->created_at), 10),
                    null,
                    null,
                    $isClient ? null : '127.0.0.1'
                );
            }
        });
    }

    /**
     * Feedback submits, survey invitation sends and client responses —
     * client-side events, so no user_id/ip is attributed.
     */
    private function seedFeedbackSurveyAudit(AuditChainWriter $writer): void
    {
        DB::table('feedback')->select('id', 'created_at')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer) {
            foreach ($rows as $row) {
                $this->pushAudit($writer, AuditAction::CREATE->value, AuditModule::FEEDBACK->value, $row->id, null, $this->auditLag(Carbon::parse($row->created_at), 30), null, null, null);
            }
        });

        DB::table('survey_invitations')->select('id', 'created_at')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer) {
            foreach ($rows as $row) {
                $this->pushAudit($writer, AuditAction::CREATE->value, AuditModule::SURVEY_INVITATION->value, $row->id, null, $this->auditLag(Carbon::parse($row->created_at), 30), null, null, null);
            }
        });

        DB::table('survey_responses')->select('id', 'created_at')->orderBy('created_at')->orderBy('id')->chunk(1000, function ($rows) use ($writer) {
            foreach ($rows as $row) {
                $this->pushAudit($writer, AuditAction::CREATE->value, AuditModule::SURVEY_RESPONSE->value, $row->id, null, $this->auditLag(Carbon::parse($row->created_at), 30), null, null, null);
            }
        });
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    /**
     * @return list<string> 6 'Y-m' keys ending with the current month
     */
    private function monthKeys(): array
    {
        $keys = [];
        $cursor = $this->windowStart->copy()->startOfMonth();

        for ($i = 0; $i < 6; $i++) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }

    /**
     * @return array{int, int}
     */
    private function windowMonth(int $offset): array
    {
        $cursor = $this->windowStart->copy()->addMonths($offset);

        return [(int) $cursor->format('Y'), (int) $cursor->format('n')];
    }

    /**
     * Day-quantized run anchor: the most recent business-day 17:00:00
     * (Asia/Manila) at or before (wall now − 5 min). Quantizing the wall
     * clock to the day makes re-runs within the same calendar day
     * byte-identical under the fixed RNG seed, while guaranteeing no stamp
     * can leak into the future. 17:00 on a weekday sits inside both the
     * agency (08:00–17:00) and client (07:00–21:00) windows, so clamped
     * stamps never violate the hour invariants.
     */
    private function resolveAnchor(Carbon $now): Carbon
    {
        $reference = $now->copy()->subMinutes(5);
        $anchor = $reference->copy()->setTime(17, 0, 0);

        while ($anchor->gt($reference) || ! $this->time->isBusinessDay($anchor)) {
            $anchor->subDay()->setTime(17, 0, 0);
        }

        return $anchor->copy();
    }

    /**
     * Clamp a stamp to the run cap (never the future). min() against a
     * constant preserves chain monotonicity.
     */
    private function noLaterThan(Carbon $stamp): Carbon
    {
        return $stamp->gt($this->cap) ? $this->cap->copy() : $stamp;
    }

    /**
     * Whole business days strictly after $from's calendar day up to and
     * including $to's calendar day. Pure calendar math (no RNG), used to
     * decide whether an SLA chain fits before the run cap.
     */
    private function businessDaysBetween(Carbon $from, Carbon $to): int
    {
        $count = 0;
        $cursor = $from->copy()->startOfDay()->addDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if ($this->time->isBusinessDay($cursor)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    /**
     * Intake-day draw for a window month, clamped to the anchor date: the
     * current (partial) month must not draw days after the anchor, which
     * would otherwise pile capped stamps onto the anchor. Bounded redraws
     * keep the RNG stream deterministic.
     */
    private function intakeDay(int $year, int $month): Carbon
    {
        $capDay = $this->cap->copy()->startOfDay();
        $tries = 0;

        do {
            $day = $this->time->randomDayInMonth($year, $month);
            $tries++;
        } while ($day->gt($capDay) && $tries < 12);

        return $day->gt($capDay) ? $capDay->copy() : $day;
    }

    /**
     * Agency-hours stamp at or after $minimum: same-day weighted time when
     * it still clears the floor, otherwise the next business day.
     */
    private function agencyAtOrAfter(Carbon $minimum): Carbon
    {
        $candidate = $this->time->agencyDateTime($minimum);

        if ($candidate->lt($minimum)) {
            $candidate = $this->time->agencyDateTime($this->time->nextBusinessDay($minimum));
        }

        return $this->noLaterThan($candidate);
    }

    /**
     * @param  array{id: string, agency_id: string}  $agency
     * @return array{id: string, name: string, processing_days: int}
     */
    private function serviceForAgency(array $agency): array
    {
        // PHP quirk: $this->servicesByAgency[$agency['id']] may be unset for
        // agencies without seeded services — fall back to any service.
        $pool = $this->servicesByAgency[$agency['id']] ?? [];

        if ($pool === []) {
            foreach ($this->servicesByAgency as $services) {
                $pool = array_merge($pool, $services);
            }
        }

        return $this->factory->pick($pool);
    }

    /**
     * Requirement names for the referral's service (service_requirements
     * rows where present, topped up from the generic pool so every referral
     * carries exactly 2 — otherwise single-requirement services would drag
     * the table below its ~5,400 target).
     *
     * @return list<string>
     */
    private function requirementNames(array $referral): array
    {
        $names = $this->requirementsByService[$referral['_service_id']] ?? [];
        $names = count($names) >= 2
            ? $this->factory->shuffle($names)
            : array_merge($names, $this->factory->shuffle(self::GENERIC_REQUIREMENTS));

        return array_values(array_unique($names));
    }

    /**
     * Staff actor for an agency: its agency user (70%) or the case manager.
     */
    private function actorForAgency(string $agencyId): string
    {
        $agencyUser = $this->agencyUserByAgency[$agencyId] ?? null;

        if ($agencyUser !== null && $this->factory->boolean(0.70)) {
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

    /**
     * Bulk insert in chunks; tracks actual per-table counts for the report.
     *
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

    /**
     * Drop underscore-prefixed bookkeeping keys before insert.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $extra
     * @return array<string, mixed>
     */
    private function stripMeta(array $row, array $extra = []): array
    {
        foreach (array_merge($extra, ['_location', '_barangay', '_service', '_service_id', '_processing_days', '_terminal']) as $key) {
            unset($row[$key]);
        }

        return $row;
    }

    private function report(): void
    {
        $elapsed = $this->startedAt > 0 ? round(microtime(true) - $this->startedAt) : 0;
        $lines = [
            'StagingSeeder complete — actual inserted rows (audit:verify passed):',
        ];

        foreach ($this->stats as $table => $count) {
            $lines[] = sprintf('  %-38s %d', $table, $count);
        }

        $lines[] = sprintf('  %-38s %d', 'referrals COMPLETED', count(array_filter(
            $this->referrals,
            fn ($referral) => $referral['status'] === 'COMPLETED'
        )));
        $lines[] = sprintf('  %-38s %d', 'estimated total', array_sum($this->stats));
        $lines[] = "Elapsed: {$elapsed}s (expected 1–3 min on staging hardware). Post-seed step: php artisan chatbot:index (plan §12).";

        $this->command?->info(implode(PHP_EOL, $lines));
    }
}
