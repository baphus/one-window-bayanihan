<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NOTE: AuditLog is created manually via AuditLog::create([...]) rather than
 * through an AuditLogFactory. This pattern is intentional — no AuditLogFactory
 * exists because audit logs have complex relationships and the manual creation
 * keeps test setup explicit and readable. See AuditLog model for fillable fields:
 * action, module, entity_id, description, old_value, new_value, user_id, timestamp.
 */
class ClientAuditLogScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CaseFile $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->case = CaseFile::factory()->create(['user_id' => $this->user->id]);
        AuditLog::truncate();
    }

    public function test_historical_client_module_rows_remain_distinct_audit_data(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'entity_id' => '00000000-0000-0000-0000-000000000101',
            'description' => 'Historical client contact update',
            'timestamp' => now(),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'referrals',
            'entity_id' => '00000000-0000-0000-0000-000000000101',
            'description' => 'Unrelated module',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101')->get();

        $this->assertCount(1, $logs);
        $this->assertSame('clients', $logs->first()->module);
    }

    public function test_scope_returns_case_logs_when_case_id_given(): void
    {
        $caseId = $this->case->id;

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'CASE',
            'entity_id' => $caseId,
            'description' => 'Case status updated',
            'timestamp' => now(),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'cases',
            'entity_id' => $caseId,
            'description' => 'Case created via observer',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101', $caseId)->get();

        $this->assertCount(2, $logs);
        $modules = $logs->pluck('module')->sort()->values()->toArray();
        $this->assertContains('CASE', $modules);
        $this->assertContains('cases', $modules);
    }

    public function test_scope_returns_referral_logs_when_referral_ids_given(): void
    {
        $referralId = '00000000-0000-0000-0000-000000000001';

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'REFERRAL',
            'entity_id' => $referralId,
            'description' => 'Referral approved',
            'timestamp' => now(),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'referrals',
            'entity_id' => $referralId,
            'description' => 'Referral created via observer',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101', null, [$referralId])->get();

        $this->assertCount(2, $logs);
        $modules = $logs->pluck('module')->sort()->values()->toArray();
        $this->assertContains('REFERRAL', $modules);
        $this->assertContains('referrals', $modules);
    }

    public function test_scope_excludes_logs_from_other_entities(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'entity_id' => '00000000-0000-0000-0000-000000000101',
            'description' => 'Our client update',
            'timestamp' => now(),
        ]);

        $otherClientId = '00000000-0000-0000-0000-000000000102';
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'entity_id' => $otherClientId,
            'description' => 'Other client update',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101')->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('00000000-0000-0000-0000-000000000101', $logs->first()->entity_id);
    }

    public function test_scope_orders_by_timestamp_descending(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'entity_id' => '00000000-0000-0000-0000-000000000101',
            'description' => 'Second action',
            'timestamp' => now()->subHour(),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'clients',
            'entity_id' => '00000000-0000-0000-0000-000000000101',
            'description' => 'First action',
            'timestamp' => now()->subHours(2),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'clients',
            'entity_id' => '00000000-0000-0000-0000-000000000101',
            'description' => 'Most recent action',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101')->get();

        $this->assertCount(3, $logs);
        $this->assertEquals('Most recent action', $logs[0]->description);
        $this->assertEquals('Second action', $logs[1]->description);
        $this->assertEquals('First action', $logs[2]->description);
    }

    public function test_scope_has_no_hidden_limit(): void
    {
        for ($i = 0; $i < 55; $i++) {
            AuditLog::create([
                'user_id' => $this->user->id,
                'action' => 'UPDATE',
                'module' => 'clients',
                'entity_id' => '00000000-0000-0000-0000-000000000101',
                'description' => "Log entry {$i}",
                'timestamp' => now()->subMinutes(55 - $i),
            ]);
        }

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101')->get();

        $this->assertCount(55, $logs);
    }

    public function test_case_related_audit_query_has_no_hidden_limit(): void
    {
        for ($i = 0; $i < 55; $i++) {
            AuditLog::create([
                'user_id' => $this->user->id,
                'action' => 'UPDATE',
                'module' => 'case',
                'entity_id' => $this->case->id,
                'description' => "Log entry {$i}",
                'timestamp' => now()->subMinutes(55 - $i),
            ]);
        }

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101', $this->case->id)->get();

        $this->assertCount(55, $logs);
    }

    public function test_case_audit_scope_includes_historical_client_and_case_rows(): void
    {
        $caseId = $this->case->id;

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'entity_id' => '00000000-0000-0000-0000-000000000101',
            'description' => 'Historical client update',
            'timestamp' => now()->subMinutes(10),
        ]);
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'case',
            'entity_id' => $caseId,
            'description' => 'Case updated',
            'timestamp' => now()->subMinutes(5),
        ]);

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101', $caseId)->get();

        $this->assertCount(2, $logs);
    }

    public function test_scope_returns_client_logs_with_new_module_name(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'client',
            'entity_id' => '00000000-0000-0000-0000-000000000101',
            'description' => 'Client audit log with new module name',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101')->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('client', $logs->first()->module);
    }

    public function test_scope_returns_case_logs_with_new_module_name(): void
    {
        $caseId = $this->case->id;

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'case',
            'entity_id' => $caseId,
            'description' => 'Case audit log with new module name',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101', $caseId)->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('case', $logs->first()->module);
    }

    public function test_scope_returns_referral_logs_with_new_module_name(): void
    {
        $referralId = '00000000-0000-0000-0000-000000000003';

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'referral',
            'entity_id' => $referralId,
            'description' => 'Referral audit log with new module name',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000101', null, [$referralId])->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('referral', $logs->first()->module);
    }

    public function test_returns_empty_when_no_matching_logs(): void
    {
        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000103')->get();

        $this->assertCount(0, $logs);
    }
}
