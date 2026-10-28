<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agency Focal activity feed is scoped to their own agency's referrals.
 */
class AgencyActivityLogScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    private function log(string $module, string $entityId, string $description): string
    {
        return (string) AuditLog::create([
            'action' => 'UPDATE',
            'module' => $module,
            'entity_id' => $entityId,
            'description' => $description,
            // Explicit so the row isn't auto-stamped 'system' (null actor +
            // console context in tests), which the default view hides.
            'category' => 'data',
            'timestamp' => now(),
        ])->id;
    }

    public function test_agency_without_an_agency_sees_nothing(): void
    {
        $orphan = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => null]);

        $case = CaseFile::factory()->create();
        AuditLog::truncate();
        $this->log('case', $case->id, 'SOME CASE');

        $data = $this->actingAs($orphan)
            ->withHeader('X-Inertia', 'true')
            ->get('/activity')
            ->json('props.events.data');

        $this->assertSame([], $data);
    }

    public function test_activity_title_is_shown_for_staff_and_admin_reaches_audit_logs(): void
    {
        $agency = Agency::factory()->create();
        $focal = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->assertSame(
            'Activity',
            $this->actingAs($focal)
                ->withHeader('X-Inertia', 'true')
                ->get('/activity')
                ->json('props.viewTitle'),
        );
        $this->assertSame(
            'Activity',
            $this->actingAs($caseManager)
                ->withHeader('X-Inertia', 'true')
                ->get('/activity')
                ->json('props.viewTitle'),
        );
        $this->actingAs($admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs')
            ->assertOk();
    }
}
