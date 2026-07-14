<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);

        $this->admin = User::factory()->create(['role' => 'ADMIN']);
        AuditLog::truncate();
    }

    private function seedLogs(int $count): void
    {
        foreach (range(1, $count) as $i) {
            AuditLog::create([
                'action' => 'UPDATE',
                'module' => 'referral',
                'user_id' => $this->admin->id,
                'description' => "Referral change {$i}",
                'old_value' => ['status' => 'PENDING'],
                'new_value' => ['status' => 'PROCESSING'],
                'timestamp' => now()->subHours($i),
            ]);
        }
    }

    public function test_case_manager_cannot_export(): void
    {
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $this->actingAs($caseManager)
            ->get('/audit-logs/export?date_from='.now()->subDays(7)->toDateString().'&date_to='.now()->toDateString())
            ->assertStatus(403);
    }

    public function test_agency_cannot_access_audit_logs_at_all(): void
    {
        $agencyUser = User::factory()->create(['role' => 'AGENCY']);

        $this->actingAs($agencyUser)->get('/audit-logs')->assertStatus(403);
        $this->actingAs($agencyUser)->get('/audit-logs/export')->assertStatus(403);
    }

    public function test_missing_date_range_is_rejected_and_self_logged(): void
    {
        $this->actingAs($this->admin)
            ->get('/audit-logs/export')
            ->assertStatus(422);

        $entry = AuditLog::where('action', 'EXPORT')->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('missing explicit date range', $entry->description);
    }

    public function test_range_beyond_retention_is_rejected(): void
    {
        $from = now()->subDays((int) config('audit.retention_days') + 30)->toDateString();

        $this->actingAs($this->admin)
            ->get('/audit-logs/export?date_from='.$from.'&date_to='.now()->toDateString())
            ->assertStatus(422);

        $this->assertStringContainsString(
            'retention window',
            AuditLog::where('action', 'EXPORT')->first()->description
        );
    }

    public function test_row_limit_rejection_is_self_logged(): void
    {
        config(['audit.export.max_rows' => 2]);
        $this->seedLogs(3);

        $this->actingAs($this->admin)
            ->get('/audit-logs/export?date_from='.now()->subDays(7)->toDateString().'&date_to='.now()->toDateString())
            ->assertStatus(422);

        $entry = AuditLog::where('action', 'EXPORT')->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('row limit', str_replace('-row limit', ' row limit', $entry->description));
    }

    public function test_successful_export_streams_csv_and_is_self_logged(): void
    {
        $this->seedLogs(3);

        $response = $this->actingAs($this->admin)
            ->get('/audit-logs/export?date_from='.now()->subDays(7)->toDateString().'&date_to='.now()->toDateString());

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Timestamp (UTC)', $csv);
        $this->assertStringContainsString('Referral change 1', $csv);
        $this->assertStringContainsString('Referral change 3', $csv);

        $entry = AuditLog::where('action', 'EXPORT')->first();
        $this->assertNotNull($entry);
        $this->assertSame('admin', $entry->category);
        $this->assertStringContainsString('exported 3 rows', $entry->description);
        $this->assertSame($this->admin->id, $entry->user_id);
    }

    public function test_export_applies_active_filters(): void
    {
        $this->seedLogs(2);
        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'client',
            'user_id' => $this->admin->id,
            'description' => 'Client added',
            'timestamp' => now()->subHours(3),
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/audit-logs/export?module=referral&date_from='.now()->subDays(7)->toDateString().'&date_to='.now()->toDateString());

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Referral change 1', $csv);
        $this->assertStringNotContainsString('Client added', $csv);
    }

    public function test_default_category_filter_hides_system_entries_in_viewer(): void
    {
        // Unattributed console write → system category
        AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'referral',
            'description' => 'Automated system sweep',
            'timestamp' => now(),
        ]);
        // Attributed write → data category
        AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'referral',
            'user_id' => $this->admin->id,
            'description' => 'Human referral change',
            'timestamp' => now(),
        ]);

        $default = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs')
            ->json('props.logs.data');

        $this->assertCount(1, $default);
        $this->assertSame('Human referral change', $default[0]['description']);

        $withSystem = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?category=security,data,admin,system')
            ->json('props.logs.data');

        $this->assertCount(2, $withSystem);
    }

    public function test_legacy_module_aliases_still_match_module_filter(): void
    {
        foreach (['CASE', 'cases', 'case_files', 'case'] as $i => $alias) {
            AuditLog::create([
                'action' => 'UPDATE',
                'module' => $alias,
                'user_id' => $this->admin->id,
                'description' => "Entry for {$alias}",
                'timestamp' => now()->subMinutes($i),
            ]);
        }

        $data = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?module=case')
            ->json('props.logs.data');

        $this->assertCount(4, $data);
    }
}
