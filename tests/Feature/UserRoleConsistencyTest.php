<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The role strings live in four places: users.role (with a database CHECK
 * constraint), the route middleware, config/mfa.enrollment_enforced_roles and
 * UserRole. A new role or a typo in any one of them fails silently — in_array()
 * and match() both fall through to "no" rather than throwing.
 *
 * These tests are the tripwire.
 */
class UserRoleConsistencyTest extends TestCase
{
    public function test_enum_covers_exactly_the_four_roles(): void
    {
        $this->assertSame(
            ['CASE_MANAGER', 'AGENCY', 'ADMIN', 'OFW'],
            UserRole::values(),
        );
    }

    public function test_every_role_used_in_routes_is_a_real_enum_case(): void
    {
        $roleLiterals = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! str_starts_with($middleware, 'role:')) {
                    continue;
                }

                foreach (explode(',', substr($middleware, strlen('role:'))) as $role) {
                    $roleLiterals[] = trim($role);
                }
            }
        }

        $this->assertNotEmpty($roleLiterals, 'no role middleware found — did the collector change?');

        $unknown = array_diff(array_unique($roleLiterals), UserRole::values());
        $this->assertSame([], $unknown, 'routes reference roles the UserRole enum does not define');
    }

    public function test_mfa_enforced_roles_are_real_enum_cases(): void
    {
        $roles = config('mfa.enrollment_enforced_roles');

        $this->assertNotEmpty($roles);
        $this->assertSame([], array_diff($roles, UserRole::values()));
    }

    public function test_each_role_round_trips_through_the_model(): void
    {
        foreach (UserRole::values() as $value) {
            $user = new User(['role' => $value]);

            $this->assertSame($value, $user->roleEnum()?->value);
            $this->assertTrue($user->hasRole(UserRole::from($value)));
        }
    }

    public function test_unknown_role_returns_null_rather_than_throwing(): void
    {
        $this->assertNull((new User(['role' => 'NOT_A_ROLE']))->roleEnum());
        $this->assertNull((new User(['role' => null]))->roleEnum());
    }

    public function test_staff_roles_exclude_ofw(): void
    {
        $this->assertSame(['CASE_MANAGER', 'AGENCY', 'ADMIN'], UserRole::staffValues());
        $this->assertFalse(UserRole::OFW->isStaff());
        $this->assertTrue(UserRole::ADMIN->isStaff());
    }

    public function test_the_user_factory_only_produces_known_roles(): void
    {
        $role = User::factory()->create()->role;

        $this->assertContains($role, UserRole::values());
    }
}
