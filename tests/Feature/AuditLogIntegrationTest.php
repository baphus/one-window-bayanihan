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
        $this->user = User::factory()->create(['role' => 'CASE_MANAGER']);
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
        $this->assertArrayHasKey('availableActions', $props);
        $this->assertArrayHasKey('availableModules', $props);
        $this->assertArrayHasKey('filterValues', $props);
        $this->assertArrayHasKey('availableModulesLabels', $props);

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

        $filteredProps = $filtered->json('props');
        $this->assertSame('CREATE', $filteredProps['filterValues']['action']);
        $this->assertSame('case_files', $filteredProps['filterValues']['module']);
        $this->assertSame($this->user->id, $filteredProps['filterValues']['user_id']);
        $this->assertSame('15', (string) $filteredProps['filterValues']['per_page']);
        $this->assertNotEmpty($filteredProps['logs']['data']);
    }

    public function test_backfill_dry_run_shows_count(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'VIEW',
            'module' => 'clients',
            'timestamp' => now(),
        ]);

        $this->artisan('audit:backfill-descriptions', ['--dry-run' => true])
            ->expectsOutputToContain('Would backfill 1 descriptions. Use without --dry-run to execute.')
            ->assertExitCode(0);
    }
}
