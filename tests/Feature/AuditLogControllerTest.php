<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\AuditLog;
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
    public function it_searches_descriptions()
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
                'description' => 'Client requested assistance',
                'timestamp' => $now,
                'is_deleted' => false,
            ],
            [
                'id' => $id2,
                'user_id' => $this->user->id,
                'action' => 'CREATE',
                'module' => 'clients',
                'description' => 'Internal review completed',
                'timestamp' => $now,
                'is_deleted' => false,
            ],
        ]);

        $response = $this->actingAs($actor)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs?search=Client');

        $response->assertStatus(200);
        $data = $response->json('props.logs.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Client requested assistance', $data[0]['description']);
    }
}
