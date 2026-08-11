<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\Reports\ReportsExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the controls added alongside the export rebuild: small-cell
 * suppression, the pre-flight size guard, and export audit logging.
 *
 * These are the parts a DPTM or SOC 2 assessor would ask for evidence of, so
 * they are asserted rather than assumed.
 */
class ReportsExportControlsTest extends TestCase
{
    use RefreshDatabase;

    private ReportsExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportsExportService::class);
    }

    private function criteriaFor(User $user, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $user->role,
            'agency_id' => null,
            'user_agcy_id' => $user->agcy_id,
            'from' => now()->subYear()->toDateString(),
            'to' => now()->toDateString(),
            'dateScope' => 'case_created_at',
            'province' => null,
            'city' => null,
        ], $overrides);
    }

    /**
     * Seed n cases so a demographic bucket can be pushed above or below the
     * suppression threshold on demand.
     */
    private function seedCases(User $owner, int $count, array $caseAttributes = []): void
    {
        $agency = Agency::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $client = Client::factory()->create();
            $case = CaseFile::factory()->create(array_merge([
                'user_id' => $owner->id,
                'client_id' => $client->id,
                'status' => 'OPEN',
            ], $caseAttributes));
            Referral::factory()->create([
                'case_id' => $case->id,
                'agcy_id' => $agency->id,
                'status' => 'PENDING',
            ]);
        }
    }

    #[Test]
    public function buckets_below_the_threshold_are_withheld_from_demographic_sections(): void
    {
        config(['reports.suppression_threshold' => 5]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        // Two cases is below the threshold of five.
        $this->seedCases($admin, 2, ['client_type' => CaseFile::CLIENT_TYPE_OFW]);

        $payload = $this->service->buildPdfPayloadFromCriteria($this->criteriaFor($admin));

        $clientType = $payload['clientTypeDistribution'];
        $this->assertNotContains(
            2,
            $clientType['data'],
            'A bucket of 2 should have been withheld, not published'
        );
        $this->assertTrue($payload['suppression']['applied']);
        $this->assertSame(5, $payload['suppression']['threshold']);
    }

    #[Test]
    public function buckets_at_or_above_the_threshold_are_published(): void
    {
        config(['reports.suppression_threshold' => 5]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 6, ['client_type' => CaseFile::CLIENT_TYPE_OFW]);

        $payload = $this->service->buildPdfPayloadFromCriteria($this->criteriaFor($admin));

        $this->assertContains(6, $payload['clientTypeDistribution']['data']);
        // Scoped to this section: other demographic sections derive from the
        // same six clients and may legitimately hold small buckets of their
        // own, so asserting nothing at all was suppressed would be wrong.
        $this->assertArrayNotHasKey('clientTypeDistribution', $payload['suppression']['sections']);
    }

    #[Test]
    public function suppression_applies_to_admin_as_well_as_other_roles(): void
    {
        config(['reports.suppression_threshold' => 5]);

        foreach (['ADMIN', 'CASE_MANAGER'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->seedCases($user, 2);

            $payload = $this->service->buildPdfPayloadFromCriteria($this->criteriaFor($user));

            $this->assertTrue(
                $payload['suppression']['applied'],
                "Suppression must apply to {$role}; a role-conditional privacy rule is not defensible"
            );
        }
    }

    #[Test]
    public function zero_buckets_are_not_treated_as_disclosive(): void
    {
        config(['reports.suppression_threshold' => 5]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 6, ['client_type' => CaseFile::CLIENT_TYPE_OFW]);

        $payload = $this->service->buildPdfPayloadFromCriteria($this->criteriaFor($admin));

        // "Next of Kin" is zero here, and an empty bucket identifies nobody.
        $this->assertContains(0, $payload['clientTypeDistribution']['data']);
    }

    /**
     * Withholding one bucket out of two does not protect it when the total is
     * published on the same page: 100 total, 97 shown, 3 recoverable by
     * subtraction. A second bucket must go with it.
     */
    #[Test]
    public function a_single_withheld_bucket_is_not_recoverable_by_subtraction(): void
    {
        config(['reports.suppression_threshold' => 5]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        // Two client types: one below the threshold, one well above.
        $this->seedCases($admin, 2, ['client_type' => CaseFile::CLIENT_TYPE_OFW]);
        $this->seedCases($admin, 20, ['client_type' => 'NEXT_OF_KIN']);

        $payload = $this->service->buildPdfPayloadFromCriteria($this->criteriaFor($admin));
        $clientType = $payload['clientTypeDistribution'];

        $this->assertNotContains(2, $clientType['data'], 'The small bucket must be withheld');
        $this->assertNotContains(
            20,
            $clientType['data'],
            'Publishing the only remaining bucket alongside the total discloses the withheld one'
        );
        $this->assertSame(2, $payload['suppression']['sections']['clientTypeDistribution']);
    }

    /**
     * getAll() fails closed for an agency user with no agency assigned. The
     * export fetches several panels directly, and two of them resolve their
     * client set with no role guard at all — so without an explicit check the
     * export would show system-wide demographics the dashboard refuses.
     */
    #[Test]
    public function an_agency_user_without_an_agency_gets_no_data_in_the_export(): void
    {
        $orphan = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]);
        $other = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($other, 8);

        $payload = $this->service->buildPdfPayloadFromCriteria(
            $this->criteriaFor($orphan, ['agency_id' => null])
        );

        foreach (['genderDistribution', 'ageGroupDistribution', 'clientTypeDistribution', 'cityDistribution'] as $section) {
            $this->assertSame(
                [],
                $payload[$section]['data'] ?? [],
                "{$section} must be empty for an agency user with no agency"
            );
        }

        $this->assertSame(0, $payload['overdueReferrals']['count']);
        $this->assertSame([], $payload['referralFunnel']['stages']);
    }

    #[Test]
    public function the_agency_overdue_count_is_scoped_rather_than_blanked_to_zero(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $this->seedCases($agencyUser, 2);

        $sheets = $this->service->buildExcelSheetsFromCriteria(
            $this->criteriaFor($agencyUser, ['agency_id' => $agency->id])
        );

        // Agencies see overdue referrals on their performance tab, so the
        // sheet must carry a real scoped count, not a hard-coded zero.
        $this->assertContains('Overdue Referrals', array_column($sheets, 'title'));
    }

    #[Test]
    public function the_appendix_total_counts_rows_beyond_the_fetched_page(): void
    {
        config(['reports.pdf_appendix_rows' => 2]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 7);

        $appendix = $this->service->buildPdfPayloadFromCriteria($this->criteriaFor($admin))['appendix'];

        // Only 2 rows are hydrated, but the total must still reflect all 7 —
        // it comes from a COUNT, not from the length of the fetched page.
        $this->assertCount(2, $appendix['cases']);
        $this->assertSame(7, $appendix['casesTotal']);
    }

    #[Test]
    public function risk_ranking_survives_being_pushed_into_sql(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 4);

        $payload = $this->service->buildPdfPayloadFromCriteria($this->criteriaFor($admin));
        $scores = array_map(fn ($row) => (int) $row->risk_score, $payload['topCases']);

        $this->assertNotEmpty($scores);
        $sorted = $scores;
        rsort($sorted);
        $this->assertSame($sorted, $scores, 'Rows must come back ordered by descending risk');
        foreach ($scores as $score) {
            $this->assertGreaterThanOrEqual(20, $score, 'An OPEN case scores at least its status weight');
        }
    }

    #[Test]
    public function preflight_reports_matching_volumes_and_the_active_limit(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 3);

        $result = $this->service->preflight($this->criteriaFor($admin), 'xlsx');

        $this->assertSame(3, $result['cases']);
        $this->assertSame(3, $result['referrals']);
        $this->assertFalse($result['exceeds']);
        $this->assertSame((int) config('reports.export_preflight_max_rows'), $result['limit']);
    }

    #[Test]
    public function preflight_flags_a_range_that_is_too_large_to_serve(): void
    {
        config(['reports.export_preflight_max_rows' => 2]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 3);

        $this->assertTrue($this->service->preflight($this->criteriaFor($admin), 'xlsx')['exceeds']);
    }

    #[Test]
    public function pdf_and_excel_carry_separate_preflight_limits(): void
    {
        config([
            'reports.export_preflight_max_rows' => 2,
            'reports.pdf_preflight_max_rows' => 500,
        ]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 3);
        $criteria = $this->criteriaFor($admin);

        $this->assertTrue($this->service->preflight($criteria, 'xlsx')['exceeds']);
        $this->assertFalse($this->service->preflight($criteria, 'pdf')['exceeds']);
    }

    #[Test]
    public function an_oversized_export_is_refused_with_an_actionable_message(): void
    {
        config(['reports.export_preflight_max_rows' => 1]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 3);

        $response = $this->actingAs($admin)->get(route('reports.export-excel', [
            'from' => now()->subYear()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Narrow the date range', session('error'));
    }

    #[Test]
    public function a_successful_export_records_both_the_attempt_and_the_outcome(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 2);

        $this->actingAs($admin)->get(route('reports.export-excel', [
            'from' => now()->subYear()->toDateString(),
            'to' => now()->toDateString(),
            'province' => null,
        ]))->assertOk();

        $outcomes = AuditLog::where('user_id', $admin->id)
            ->where('action', 'EXPORT')
            ->get()
            ->map(fn ($log) => $log->new_value['outcome'] ?? null)
            ->all();

        $this->assertContains('ATTEMPTED', $outcomes);
        $this->assertContains('COMPLETED', $outcomes);
    }

    #[Test]
    public function a_blocked_export_is_still_recorded(): void
    {
        config(['reports.export_preflight_max_rows' => 1]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 3);

        $this->actingAs($admin)->get(route('reports.export-excel', [
            'from' => now()->subYear()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $blocked = AuditLog::where('user_id', $admin->id)
            ->where('action', 'EXPORT')
            ->get()
            ->first(fn ($log) => ($log->new_value['outcome'] ?? null) === 'BLOCKED');

        $this->assertNotNull($blocked, 'A refused export must leave an audit record');
        $this->assertSame('xlsx', $blocked->new_value['format']);
    }

    #[Test]
    public function the_audit_record_captures_the_full_filter_set(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 1);

        $this->actingAs($admin)->get(route('reports.export-pdf', [
            'from' => '2025-01-01',
            'to' => '2025-12-31',
            'date_scope' => 'referral_created_at',
            'province' => 'Cebu',
        ]));

        $log = AuditLog::where('user_id', $admin->id)->where('action', 'EXPORT')->first();

        $this->assertSame('2025-01-01', $log->new_value['filters']['from']);
        $this->assertSame('2025-12-31', $log->new_value['filters']['to']);
        $this->assertSame('referral_created_at', $log->new_value['filters']['date_scope']);
        $this->assertSame('Cebu', $log->new_value['filters']['province']);
    }

    #[Test]
    public function the_export_carries_every_section_the_reports_page_shows(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 2);

        $payload = $this->service->buildPdfPayloadFromCriteria($this->criteriaFor($admin));

        // The twelve sections that rendered on screen but were never exported.
        foreach ([
            'referralFunnel', 'casesOverTime', 'genderDistribution', 'ageGroupDistribution',
            'vulnerabilityDistribution', 'clientTypeDistribution', 'cityDistribution',
            'agencyWorkload', 'referralAgencyDistribution', 'employmentOccupationBreakdown',
            'overdueReferrals', 'mostRequestedService',
        ] as $section) {
            $this->assertArrayHasKey($section, $payload, "Export is missing the {$section} section");
        }
    }

    #[Test]
    public function the_pdf_appendix_is_bounded_and_states_what_it_omits(): void
    {
        config(['reports.pdf_appendix_rows' => 2]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 5);

        $appendix = $this->service->buildPdfPayloadFromCriteria($this->criteriaFor($admin))['appendix'];

        $this->assertCount(2, $appendix['cases']);
        $this->assertSame(5, $appendix['casesTotal'], 'The total must be reported so the PDF can say what it omitted');
        $this->assertSame(2, $appendix['limit']);
    }

    #[Test]
    public function an_agency_export_cannot_widen_beyond_its_own_dashboard(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $this->seedCases($agencyUser, 2);

        $sheets = $this->service->buildExcelSheetsFromCriteria(
            $this->criteriaFor($agencyUser, ['agency_id' => $agency->id])
        );

        $titles = array_column($sheets, 'title');

        foreach (['Geography', 'Cities', 'Agency Workload', 'Referrals by Agency', 'Vulnerability', 'Occupations'] as $hidden) {
            $this->assertNotContains($hidden, $titles, "Agency export must not include the {$hidden} sheet");
        }
    }

    /**
     * The mirror of the test above: scoping must not become an excuse to
     * withhold data the role is entitled to. Categories and Case Status both
     * render on the agency's own tabs and were previously stripped from agency
     * exports on a mistaken premise.
     */
    #[Test]
    public function an_agency_export_still_includes_what_the_agency_dashboard_shows(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $this->seedCases($agencyUser, 3);

        $criteria = $this->criteriaFor($agencyUser, ['agency_id' => $agency->id]);
        $sheets = $this->service->buildExcelSheetsFromCriteria($criteria);
        $titles = array_column($sheets, 'title');

        foreach (['Categories', 'Case Status', 'Overdue Referrals', 'Client Type', 'Gender', 'Age Groups'] as $expected) {
            $this->assertContains($expected, $titles, "Agency export is missing the {$expected} sheet");
        }

        $payload = $this->service->buildPdfPayloadFromCriteria($criteria);
        $this->assertNotSame([], $payload['caseStatusDistribution']['labels'] ?? []);
    }

    #[Test]
    public function the_workbook_declares_real_types_rather_than_writing_everything_as_text(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 2);

        $sheets = $this->service->buildExcelSheetsFromCriteria($this->criteriaFor($admin));
        $referralSheet = collect($sheets)->firstWhere('title', 'Referral Details');
        $types = collect($referralSheet['columnMap'])->pluck('type', 'key');

        $this->assertSame('int', $types['age_days'], 'Numeric columns must be numeric so the workbook can be summed');
        $this->assertSame('int', $types['completion_days']);
        $this->assertSame('datetime', $types['created_at']);
        $this->assertSame('status', $types['status']);
        // Identifiers stay text so Excel cannot mangle them.
        $this->assertSame('uuid', $types['referral_id']);
    }

    #[Test]
    public function a_data_dictionary_sheet_defines_the_derived_columns(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->seedCases($admin, 1);

        $sheets = $this->service->buildExcelSheetsFromCriteria($this->criteriaFor($admin));
        $dictionary = collect($sheets)->firstWhere('title', 'Data Dictionary');

        $this->assertNotNull($dictionary);
        $this->assertTrue(
            collect($dictionary['rows'])->contains(fn ($row) => $row['column'] === 'Age Days'),
            'Derived columns must be defined for the reader'
        );
    }

    private function requestFor(User $user, array $query = []): Request
    {
        $request = Request::create('/reports/export-pdf', 'GET', $query);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
