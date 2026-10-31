<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\Reports\ReportsExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
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
        DB::table('audit_logs')->truncate();
        $this->resetRlsSession();
    }

    protected function tearDown(): void
    {
        $this->resetRlsSession();
        parent::tearDown();
    }

    private function resetRlsSession(): void
    {
        DB::statement("SELECT set_config('app.current_user_id', '', false);");
        DB::statement("SELECT set_config('app.user_role', '', false);");
    }

    private function setRlsSession(?string $userId = null, ?string $role = null): void
    {
        DB::statement('SELECT set_config(?, ?, ?)', ['app.current_user_id', $userId ?? '', false]);
        DB::statement('SELECT set_config(?, ?, ?)', ['app.user_role', $role ?? '', false]);
    }

    private function criteriaFor(User $user, array $overrides = []): array
    {
        // Window edges are Manila calendar days (see App\Support\ReportCalendar):
        // at 20:00 UTC the Manila date is already tomorrow.
        $today = now()->setTimezone('Asia/Manila')->toDateString();

        return array_merge([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $user->role,
            'agency_id' => null,
            'user_agcy_id' => $user->agcy_id,
            'from' => now()->subYear()->setTimezone('Asia/Manila')->toDateString(),
            'to' => $today,
            'dateScope' => 'case_created_at',
            'province' => null,
            'city' => null,
        ], $overrides);
    }

    /**
     * Seed n cases so a demographic bucket can be pushed above or below the
     * suppression threshold on demand.
     */
    private function seedCases(User $owner, int $count, array $caseAttributes = [], ?string $clientType = 'OFW'): void
    {
        $agency = Agency::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $client = Client::factory()->create(['type' => $clientType]);
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

    private function payloadFor(User $user, array $overrides = []): array
    {
        $query = $this->criteriaFor($user, $overrides);
        $this->setRlsSession($user->id, $user->role);

        return $this->service->buildPdfPayloadFromCriteria($query);
    }

    private function excelSheetsFor(User $user, array $overrides = []): array
    {
        $query = $this->criteriaFor($user, $overrides);
        $this->setRlsSession($user->id, $user->role);

        return $this->service->buildExcelSheetsFromCriteria($query);
    }

    private function preflightFor(User $user, string $format = 'xlsx', array $overrides = []): array
    {
        $query = $this->criteriaFor($user, $overrides);
        $this->setRlsSession($user->id, $user->role);

        return $this->service->preflight($query, $format);
    }

    /**
     * Hit the controller endpoint so audit logging fires, then assert 200.
     */
    private function exportPdfViaHttp(User $user, array $overrides = []): void
    {
        $query = $this->criteriaFor($user, $overrides);
        $response = $this->actingAs($user)->get(route('reports.export-pdf', $query));
        $response->assertSuccessful();
    }

    #[Test]
    public function print_view_uses_suppressed_aggregates_without_record_details(): void
    {
        config(['reports.suppression_threshold' => 5]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 2);

        $this->actingAs($admin)->get(route('reports.print'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Reports/Print')
            ->where('suppression.applied', true)
            ->where('clientTypeDistribution.data', [0])
            ->missing('topCases')
            ->missing('topReferrals')
            ->missing('appendix')
            ->missing('sheets'));
    }

    #[Test]
    public function print_view_refuses_an_agency_without_an_agency_link(): void
    {
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => null]);
        $this->actingAs($user)->get(route('reports.print'))->assertForbidden();
    }

    #[Test]
    public function print_view_rejects_reversed_dates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin)->get(route('reports.print', ['from' => '2026-02-01', 'to' => '2026-01-01']))
            ->assertRedirect(route('reports.index'))->assertSessionHasErrors('to');
    }

    #[Test]
    public function buckets_below_the_threshold_are_withheld_from_demographic_sections(): void
    {
        config(['reports.suppression_threshold' => 5]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        // Two cases is below the threshold of five.
        $this->seedCases($admin, 2);

        $payload = $this->payloadFor($admin);

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

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 6);

        $payload = $this->payloadFor($admin);

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

        foreach ([UserRole::ADMIN->value, UserRole::CASE_MANAGER->value] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->seedCases($user, 2);

            $payload = $this->payloadFor($user);

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

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 6);

        $payload = $this->payloadFor($admin);

        // "Next of Kin" is zero here, and an empty bucket identifies nobody.
        $this->assertContains(0, $payload['clientTypeDistribution']['data']);
    }

    #[Test]
    public function a_single_withheld_bucket_is_not_recoverable_by_subtraction(): void
    {
        config(['reports.suppression_threshold' => 5]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        // Two client types: one below the threshold (OFW=2), one well above (NEXT_OF_KIN=20).
        $this->seedCases($admin, 2, [], 'OFW');
        $this->seedCases($admin, 20, [], 'NEXT_OF_KIN');

        $payload = $this->payloadFor($admin);
        $clientType = $payload['clientTypeDistribution'];

        $this->assertNotContains(2, $clientType['data'], 'The small bucket must be withheld');
        $this->assertNotContains(
            20,
            $clientType['data'],
            'Publishing the only remaining bucket alongside the total discloses the withheld one'
        );
        $this->assertSame(2, $payload['suppression']['sections']['clientTypeDistribution']);
    }

    #[Test]
    public function an_agency_user_without_an_agency_gets_no_data_in_the_export(): void
    {
        $orphan = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => null]);
        $other = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($other, 8);

        $payload = $this->payloadFor($orphan, ['agency_id' => null]);

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
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $this->seedCases($agencyUser, 2);

        $sheets = $this->excelSheetsFor($agencyUser, ['agency_id' => $agency->id]);

        // Agencies see overdue referrals on their performance tab, so the
        // sheet must carry a real scoped count, not a hard-coded zero.
        $this->assertContains('Overdue Referrals', array_column($sheets, 'title'));
    }

    #[Test]
    public function the_appendix_total_counts_rows_beyond_the_fetched_page(): void
    {
        config(['reports.pdf_appendix_rows' => 2]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 7);

        $appendix = $this->payloadFor($admin)['appendix'];

        // Only 2 rows are hydrated, but the total must still reflect all 7 —
        // it comes from a COUNT, not from the length of the fetched page.
        $this->assertCount(2, $appendix['cases']);
        $this->assertSame(7, $appendix['casesTotal']);
    }

    #[Test]
    public function the_whole_manila_calendar_day_is_covered_late_in_the_utc_day(): void
    {
        // 20:00 UTC is already 04:00 the next day in Manila; a window ending on
        // the Manila "today" must still include rows created in that instant.
        Carbon::setTestNow(Carbon::parse('2026-03-10 20:00:00', 'UTC'));

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 3);

        $preflight = $this->preflightFor($admin);
        $this->assertSame(3, $preflight['cases'], 'Pre-flight must see rows created just after Manila midnight');
        $this->assertSame(3, $preflight['referrals']);

        $payload = $this->payloadFor($admin);

        // The reconciliation the reader performs: the appendix total and the
        // KPI card are two counts of the same window.
        $this->assertSame(
            $payload['kpis']['totalCases'],
            $payload['appendix']['casesTotal'],
            'Summary and detail figures must be counted over one window, not two'
        );
        $this->assertSame($payload['kpis']['totalReferrals'], $payload['appendix']['referralsTotal']);
        $this->assertCount(3, $payload['appendix']['cases']);
        $this->assertNotEmpty($payload['topCases']);

        Carbon::setTestNow();
    }

    #[Test]
    public function risk_ranking_survives_being_pushed_into_sql(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 4);

        $payload = $this->payloadFor($admin);
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
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 3);

        $result = $this->preflightFor($admin);

        $this->assertSame(3, $result['cases']);
        $this->assertSame(3, $result['referrals']);
        $this->assertFalse($result['exceeds']);
    }

    #[Test]
    public function preflight_respects_the_active_limit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        // 12 cases exceeds the default active limit of 10.
        $this->seedCases($admin, 12);

        $result = $this->preflightFor($admin);

        $this->assertFalse($result['exceeds']);
        $this->assertSame(12, $result['cases']);
    }

    #[Test]
    public function preflight_passes_when_below_the_limit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 5);

        $result = $this->preflightFor($admin);

        $this->assertFalse($result['exceeds']);
    }

    #[Test]
    public function audit_log_records_each_export_attempt(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 3);

        $this->exportPdfViaHttp($admin);

        $this->assertSame(2, AuditLog::where('user_id', $admin->id)->count());
        $log = AuditLog::where('user_id', $admin->id)->latest('timestamp')->first();
        $this->assertSame('EXPORT', $log->action);
        $this->assertSame('data_export', $log->module);
        $this->assertStringContainsString('PDF', strtoupper($log->description));
    }

    #[Test]
    public function audit_log_records_export_success_with_small_appendix(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 3);

        // pdf_appendix_rows=0 still renders successfully (empty appendix table).
        // The controller writes ATTEMPTED + COMPLETED = 2 rows.
        config(['reports.pdf_appendix_rows' => 0]);

        $this->exportPdfViaHttp($admin);

        $this->assertSame(2, AuditLog::where('user_id', $admin->id)->count());
        // ATTEMPTED and COMPLETED share the same timestamp, so latest() is a
        // coin flip — pick the COMPLETED row explicitly.
        $log = AuditLog::where('user_id', $admin->id)->get()
            ->first(fn ($l) => str_contains(strtolower((string) $l->description), 'completed'));
        $this->assertNotNull($log);
        $this->assertSame('EXPORT', $log->action);
        $this->assertStringContainsString('completed', strtolower($log->description));
    }

    #[Test]
    public function export_audit_has_correct_user_reference(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 3);

        $this->exportPdfViaHttp($admin);

        $log = AuditLog::where('user_id', $admin->id)->latest('timestamp')->first();
        $this->assertSame($admin->id, $log->user_id);
    }

    #[Test]
    public function export_audit_has_correct_ip_address(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 3);

        $this->exportPdfViaHttp($admin);

        $log = AuditLog::where('user_id', $admin->id)->latest('timestamp')->first();
        $this->assertNotNull($log->ip_address);
    }

    #[Test]
    public function export_audit_has_correct_user_agent(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 3);

        $this->exportPdfViaHttp($admin);

        $log = AuditLog::where('user_id', $admin->id)->latest('timestamp')->first();
        $this->assertNotNull($log->user_agent);
    }

    #[Test]
    public function export_audit_has_correct_request_payload(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->seedCases($admin, 3);

        $this->exportPdfViaHttp($admin);

        $log = AuditLog::where('user_id', $admin->id)->latest('timestamp')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString($admin->name, $log->description);
        $this->assertStringContainsString('PDF', strtoupper($log->description));
    }
}
