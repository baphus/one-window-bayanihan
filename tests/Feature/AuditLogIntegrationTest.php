<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->user = User::factory()->create(['role' => 'ADMIN']);
    }

    public function test_backfill_command_populates_descriptions(): void
    {
        $log1 = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'new_value' => ['case_number' => 'CAS-001', 'status' => 'OPEN'],
            'timestamp' => now()->subDay(),
        ]);

        $log2 = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'referrals',
            'old_value' => ['status' => 'PENDING'],
            'new_value' => ['status' => 'COMPLETED'],
            'timestamp' => now()->subHours(2),
        ]);

        $this->assertNull($log1->fresh()->description);
        $this->assertNull($log2->fresh()->description);

        $this->artisan('audit:backfill-descriptions')
            ->expectsOutputToContain('Backfilled')
            ->assertExitCode(0);

        $this->assertNotNull($log1->fresh()->description);
        $this->assertNotNull($log2->fresh()->description);
        $this->assertStringContainsString('opened', strtolower($log1->fresh()->description));
        $this->assertStringContainsString('changed to completed', strtolower($log2->fresh()->description));
    }

    public function test_audit_logs_page_renders_with_filters(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'description' => 'Test user created Case case_files',
            'new_value' => ['case_number' => 'CAS-001'],
            'timestamp' => now()->subDay(),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'referrals',
            'description' => 'Test user updated Referral',
            'old_value' => ['status' => 'PENDING'],
            'new_value' => ['status' => 'COMPLETED'],
            'timestamp' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs');

        $response->assertStatus(200);

        $props = $response->json('props');

        $this->assertArrayHasKey('logs', $props);
        $this->assertArrayHasKey('filterValues', $props);

        $this->assertNotEmpty($props['logs']['data']);

        foreach ($props['logs']['data'] as $log) {
            $this->assertNotNull($log['description']);
        }

        $this->assertArrayHasKey('current_page', $props['logs']);
        $this->assertArrayHasKey('last_page', $props['logs']);
        $this->assertArrayHasKey('total', $props['logs']);

        $filtered = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?action=CREATE&module=case_files&user_id='.$this->user->id.'&per_page=15');

        $filtered->assertStatus(200);

        $filteredProps = $filtered->json('props');
        $this->assertArrayHasKey('filterValues', $filteredProps);
        $this->assertSame('CREATE', $filteredProps['filterValues']['action']);
        $this->assertSame('case_files', $filteredProps['filterValues']['module']);
        $this->assertSame($this->user->id, $filteredProps['filterValues']['user_id']);
        $this->assertSame('15', (string) $filteredProps['filterValues']['per_page']);
        $this->assertNotEmpty($filteredProps['logs']['data']);

        // Lazy props require Inertia partial reload headers.
        // Note: withHeader() persists across requests in Laravel tests,
        // so partial-reload requests must come after all non-partial assertions.
        $lazyResponse = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Partial-Data', 'availableActions,availableModules,availableModulesLabels')
            ->withHeader('X-Inertia-Partial-Component', 'AuditLog/Index')
            ->get('/audit-logs');

        $lazyProps = $lazyResponse->json('props');
        $this->assertArrayHasKey('availableActions', $lazyProps);
        $this->assertArrayHasKey('availableModules', $lazyProps);
        $this->assertArrayHasKey('availableModulesLabels', $lazyProps);
    }

    public function test_backfill_dry_run_shows_count(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'LOGIN',
            'module' => 'clients',
            'timestamp' => now(),
        ]);

        $this->artisan('audit:backfill-descriptions', ['--dry-run' => true])
            ->expectsOutputToContain('Would backfill 1 descriptions. Use without --dry-run to execute.')
            ->assertExitCode(0);
    }

    public function test_mixed_old_and_new_module_names_display_correctly(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'description' => 'Created case file record',
            'new_value' => ['case_number' => 'CAS-001'],
            'timestamp' => now()->subHours(4),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'referrals',
            'description' => 'Updated referral status',
            'old_value' => ['status' => 'PENDING'],
            'new_value' => ['status' => 'COMPLETED'],
            'timestamp' => now()->subHours(3),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'case',
            'description' => 'Created new case',
            'new_value' => ['case_number' => 'CAS-002'],
            'timestamp' => now()->subHours(2),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'referral',
            'description' => 'Updated referral details',
            'old_value' => ['status' => 'ACTIVE'],
            'new_value' => ['status' => 'CLOSED'],
            'timestamp' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs');

        $response->assertStatus(200);

        $props = $response->json('props');
        $modules = collect($props['logs']['data'])->pluck('module')->toArray();

        $this->assertContains('case_files', $modules);
        $this->assertContains('referrals', $modules);
        $this->assertContains('case', $modules);
        $this->assertContains('referral', $modules);
        $this->assertGreaterThanOrEqual(4, count($props['logs']['data']));
    }

    public function test_filter_matches_both_old_and_new_modules(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'description' => 'Old module name entry',
            'new_value' => ['case_number' => 'CAS-001'],
            'timestamp' => now()->subHour(),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'case',
            'description' => 'New module name entry',
            'old_value' => ['status' => 'OPEN'],
            'new_value' => ['status' => 'CLOSED'],
            'timestamp' => now(),
        ]);

        // Filter by old module name — should return both old and new
        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?module=case_files');

        $response->assertStatus(200);
        $props = $response->json('props');
        $modules = collect($props['logs']['data'])->pluck('module')->toArray();

        $this->assertContains('case_files', $modules);
        $this->assertContains('case', $modules);
        $this->assertCount(2, $props['logs']['data']);

        // Filter by new module name — should also return both
        $response2 = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?module=case');

        $response2->assertStatus(200);
        $props2 = $response2->json('props');
        $modules2 = collect($props2['logs']['data'])->pluck('module')->toArray();

        $this->assertContains('case_files', $modules2);
        $this->assertContains('case', $modules2);
        $this->assertCount(2, $props2['logs']['data']);
    }
}
