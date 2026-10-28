<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([HandleInertiaRequests::class, SetPostgresSession::class]);
    }

    public function test_non_admin_roles_cannot_access_audit_log_routes(): void
    {
        foreach ([UserRole::CASE_MANAGER, UserRole::AGENCY, UserRole::OFW] as $role) {
            $user = User::factory()->create(['role' => $role->value]);

            foreach (['/audit-logs', '/audit-logs/export', '/audit-logs/actors'] as $uri) {
                $this->assertNotSame(200, $this->actingAs($user)->get($uri)->status());
            }
        }
    }

    public function test_admin_can_access_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)->get('/audit-logs')->assertOk();
    }
}
