<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SetPostgresSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_sets_session_vars_when_authenticated(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS session var tests require PostgreSQL.');
        }

        $user = User::factory()->create([
            'role' => 'CASE_MANAGER',
        ]);

        // Make a request through the global middleware (any authenticated route)
        $this->actingAs($user)->get('/dashboard');

        // Verify the middleware set session variables
        $currentUserId = DB::scalar("SELECT current_setting('app.current_user_id', TRUE)");
        $currentUserRole = DB::scalar("SELECT current_setting('app.user_role', TRUE)");

        $this->assertEquals((string) $user->id, $currentUserId);
        $this->assertEquals($user->role, $currentUserRole);
    }

    public function test_middleware_gracefully_skips_when_unauthenticated(): void
    {
        // Make an unauthenticated request that goes through the global middleware
        // The middleware should silently skip (no DB::statement calls) and return 200
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_middleware_class_exists(): void
    {
        $this->assertTrue(class_exists('App\Http\Middleware\SetPostgresSession'));
    }

    public function test_middleware_has_handle_method(): void
    {
        $reflection = new \ReflectionClass('App\Http\Middleware\SetPostgresSession');
        $this->assertTrue($reflection->hasMethod('handle'));
    }
}
