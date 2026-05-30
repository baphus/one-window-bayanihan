<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuditLogScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->client = Client::factory()->create();

        AuditLog::truncate();
    }

    public function test_scope_returns_client_audit_logs(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'entity_id' => $this->client->id,
            'description' => 'Updated client contact info',
            'timestamp' => now(),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'referrals',
            'entity_id' => 'unrelated-id',
            'description' => 'Some other update',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient($this->client->id)->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('clients', $logs->first()->module);
    }

    public function test_scope_returns_case_logs_when_case_id_given(): void
    {
        $caseId = $this->client->case_id;

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

        $logs = AuditLog::forClient($this->client->id, $caseId)->get();

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

        $logs = AuditLog::forClient($this->client->id, null, [$referralId])->get();

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
            'entity_id' => $this->client->id,
            'description' => 'Our client update',
            'timestamp' => now(),
        ]);

        $otherClient = Client::factory()->create();
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'entity_id' => $otherClient->id,
            'description' => 'Other client update',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient($this->client->id)->get();

        $this->assertCount(1, $logs);
        $this->assertEquals($this->client->id, $logs->first()->entity_id);
    }

    public function test_scope_orders_by_timestamp_descending(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'entity_id' => $this->client->id,
            'description' => 'Second action',
            'timestamp' => now()->subHour(),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'clients',
            'entity_id' => $this->client->id,
            'description' => 'First action',
            'timestamp' => now()->subHours(2),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'clients',
            'entity_id' => $this->client->id,
            'description' => 'Most recent action',
            'timestamp' => now(),
        ]);

        $logs = AuditLog::forClient($this->client->id)->get();

        $this->assertCount(3, $logs);
        $this->assertEquals('Most recent action', $logs[0]->description);
        $this->assertEquals('Second action', $logs[1]->description);
        $this->assertEquals('First action', $logs[2]->description);
    }

    public function test_scope_limits_to_50_results(): void
    {
        for ($i = 0; $i < 55; $i++) {
            AuditLog::create([
                'user_id' => $this->user->id,
                'action' => 'UPDATE',
                'module' => 'clients',
                'entity_id' => $this->client->id,
                'description' => "Log entry {$i}",
                'timestamp' => now()->subMinutes(55 - $i),
            ]);
        }

        $logs = AuditLog::forClient($this->client->id)->get();

        $this->assertCount(50, $logs);
    }

    public function test_client_related_audit_logs_includes_client_case_and_referral(): void
    {
        $caseId = $this->client->case_id;
        $referralId = '00000000-0000-0000-0000-000000000002';

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'entity_id' => $this->client->id,
            'description' => 'Client updated',
            'timestamp' => now()->subMinutes(10),
        ]);

        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'CASE',
            'entity_id' => $caseId,
            'description' => 'Case updated',
            'timestamp' => now()->subMinutes(5),
        ]);

        $logs = $this->client->relatedAuditLogs();

        $this->assertCount(2, $logs);
    }

    public function test_returns_empty_when_no_matching_logs(): void
    {
        $logs = AuditLog::forClient('00000000-0000-0000-0000-000000000000')->get();

        $this->assertCount(0, $logs);
    }
}
