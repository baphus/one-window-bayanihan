<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);
        $this->user = User::factory()->create(['role' => 'ADMIN']);
    }

    #[Test]
    public function it_returns_paginated_audit_logs()
    {
        foreach (range(1, 5) as $i) {
            AuditLog::create([
                'user_id' => $this->user->id,
                'action' => 'UPDATE',
                'module' => 'clients',
                'timestamp' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?per_page=15');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('props', $data);
        $this->assertNotNull($data['props']['logs']['data'] ?? null);
    }

    #[Test]
    public function it_does_not_expose_raw_audit_payloads_or_request_context_in_the_activity_log(): void
    {
        $sensitiveValue = 'private-case-note-must-not-reach-the-browser';

        $log = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'case',
            'category' => 'data',
            'description' => "Case note changed to {$sensitiveValue}",
            'old_value' => ['private_note' => 'previous private note'],
            'new_value' => ['private_note' => $sensitiveValue],
            'timestamp' => now(),
            'ip_address' => '203.0.113.50',
            'user_agent' => 'Sensitive Test User Agent',
            'request_id' => (string) Str::uuid(),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs');

        $response->assertOk();

        $entry = collect($response->json('props.logs.data'))->firstWhere('id', $log->id);

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('message', $entry);
        $this->assertArrayHasKey('changes', $entry);

        foreach (['description', 'old_value', 'new_value', 'entity_id', 'ip_address', 'user_agent', 'request_id', 'prev_hash', 'user'] as $field) {
            $this->assertArrayNotHasKey($field, $entry, "{$field} must not reach the browser");
        }

        $response->assertDontSee($sensitiveValue);
        $response->assertDontSee('203.0.113.50');
        $response->assertDontSee('Sensitive Test User Agent');
    }

    #[Test]
    public function it_does_not_expose_raw_audit_payloads_from_case_or_referral_activity_apis(): void
    {
        $case = CaseFile::factory()->create(['user_id' => $this->user->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $sensitiveValue = 'private-referral-note-must-not-reach-the-api';

        $log = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'referral',
            'category' => 'data',
            'entity_id' => $referral->id,
            'description' => "Referral note changed to {$sensitiveValue}",
            'old_value' => ['private_note' => 'previous private note'],
            'new_value' => ['private_note' => $sensitiveValue],
            'timestamp' => now(),
            'ip_address' => '203.0.113.71',
            'user_agent' => 'Sensitive API Test Agent',
            'request_id' => (string) Str::uuid(),
        ]);

        foreach ([
            "/api/cases/{$case->id}/audit-logs",
            "/api/referrals/{$referral->id}/audit-logs",
        ] as $url) {
            $response = $this->actingAs($this->user)->get($url);

            $response->assertOk();
            $entry = collect($response->json('data'))->firstWhere('id', $log->id);

            $this->assertNotNull($entry);
            foreach (['description', 'old_value', 'new_value', 'entity_id', 'ip_address', 'user_agent', 'request_id', 'prev_hash', 'user'] as $field) {
                $this->assertArrayNotHasKey($field, $entry, "{$field} must not reach {$url}");
            }
            $response->assertDontSee($sensitiveValue);
            $response->assertDontSee('203.0.113.71');
            $response->assertDontSee('Sensitive API Test Agent');
        }
    }

    #[Test]
    public function it_advances_cursor_and_honours_per_page(): void
    {
        foreach (range(1, 17) as $i) {
            AuditLog::create([
                'user_id' => $this->user->id,
                'action' => $i === 1 ? 'DELETE' : 'UPDATE',
                'module' => 'clients',
                'timestamp' => now()->subMinutes($i),
            ]);
        }

        $first = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?action=UPDATE&per_page=15');

        $first->assertStatus(200);
        $payload = $first->json('props.logs');
        $this->assertCount(15, $payload['data']);
        $this->assertNotEmpty($payload['next_page_url']);
        $this->assertStringContainsString('action=UPDATE', $payload['next_page_url']);
        $this->assertStringContainsString('per_page=15', $payload['next_page_url']);

        $second = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get($payload['next_page_url']);

        $second->assertStatus(200);
        $secondPayload = $second->json('props.logs');
        $this->assertCount(1, $secondPayload['data']);
        $this->assertStringContainsString('action=UPDATE', $secondPayload['prev_page_url']);
        $this->assertStringContainsString('per_page=15', $secondPayload['prev_page_url']);
        $this->assertNotSame($payload['data'][0]['id'], $second->json('props.logs.data.0.id'));

        $previous = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get($secondPayload['prev_page_url']);
        $this->assertSame($payload['data'][0]['id'], $previous->json('props.logs.data.0.id'));
    }

    #[Test]
    public function it_filters_by_action()
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'timestamp' => now()->subMinute(),
        ]);
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'DELETE',
            'module' => 'case_files',
            'timestamp' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?action=CREATE');

        $response->assertStatus(200);
        $data = $response->json('props.logs.data');
        // One CREATE entry from this test. The user-factory CREATE from setUp
        // is unattributed console activity (category: system) and is excluded
        // by the viewer's default category filter.
        $this->assertCount(1, $data);
        $this->assertEquals('CREATE', $data[0]['action']);
    }

    #[Test]
    public function it_filters_by_date_range()
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'timestamp' => '2026-05-01 10:00:00',
        ]);
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'clients',
            'timestamp' => '2026-05-20 10:00:00',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?date_from=2026-05-15&date_to=2026-05-25');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('props.logs.data'));
    }

    #[Test]
    public function it_filters_by_user()
    {
        $otherUser = User::factory()->create(['role' => 'CASE_MANAGER']);
        AuditLog::create(['user_id' => $this->user->id, 'action' => 'UPDATE', 'module' => 'clients', 'timestamp' => now()->subMinute()]);
        AuditLog::create(['user_id' => $otherUser->id, 'action' => 'UPDATE', 'module' => 'clients', 'timestamp' => now()]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?user_id='.$this->user->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('props.logs.data'));
    }

    #[Test]
    public function it_returns_available_filters()
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'timestamp' => now()->subMinutes(2),
        ]);
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'referrals',
            'timestamp' => now()->subMinute(),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Partial-Data', 'availableActions,availableModules')
            ->withHeader('X-Inertia-Partial-Component', 'AuditLog/Index')
            ->get('/audit-logs');

        $response->assertStatus(200);
        $props = $response->json('props');
        $this->assertEqualsCanonicalizing(['CREATE', 'UPDATE'], $props['availableActions']);
        $this->assertEqualsCanonicalizing(['case', 'referral', 'user'], $props['availableModules']);
    }

    #[Test]
    public function it_returns_available_modules_from_both_old_and_new_data(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'timestamp' => now()->subMinutes(2),
        ]);
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'case',
            'timestamp' => now()->subMinute(),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Partial-Data', 'availableActions,availableModules')
            ->withHeader('X-Inertia-Partial-Component', 'AuditLog/Index')
            ->get('/audit-logs');

        $response->assertStatus(200);
        $props = $response->json('props');
        $this->assertEqualsCanonicalizing(['case', 'user'], $props['availableModules']);
    }

    #[Test]
    public function it_filters_by_module_using_either_old_or_new_name(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATE',
            'module' => 'case_files',
            'timestamp' => now()->subMinutes(3),
        ]);
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'UPDATE',
            'module' => 'case',
            'timestamp' => now()->subMinutes(2),
        ]);
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'DELETE',
            'module' => 'referrals',
            'timestamp' => now()->subMinute(),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?module=case_files');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('props.logs.data'));

        $response2 = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?module=case');

        $response2->assertStatus(200);
        $this->assertCount(2, $response2->json('props.logs.data'));
    }

    #[Test]
    public function it_searches_safe_audit_metadata()
    {
        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();
        $now = now();

        // Use an admin actor to bypass the CASE_MANAGER entity_id scope filter in the controller
        // while the audit_log records reference $this->user->id as the performer
        $actor = User::factory()->create(['role' => 'ADMIN']);

        DB::table('audit_logs')->insert([
            [
                'id' => $id1,
                'user_id' => $this->user->id,
                'action' => 'CREATE',
                'module' => 'clients',
                'description' => 'Private phrase one',
                'timestamp' => $now,
                'is_deleted' => false,
            ],
            [
                'id' => $id2,
                'user_id' => $this->user->id,
                'action' => 'CREATE',
                'module' => 'referrals',
                'description' => 'Private phrase two',
                'timestamp' => $now,
                'is_deleted' => false,
            ],
        ]);

        $response = $this->actingAs($actor)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?search=clients');

        $response->assertStatus(200);
        $data = $response->json('props.logs.data');
        $this->assertCount(1, $data);
        $this->assertSame('CREATE', $data[0]['action']);
        $this->assertArrayNotHasKey('description', $data[0]);
    }
}
