<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\CheckUserActive;
use App\Models\Agency;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Batch-3 LOW findings: the last-active-admin guard (destroy route), the
 * requirements payload on the admin service store route, and the removal of
 * dead Inertia props (admin agency index + Welcome).
 */
class AdminCrudLowsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function destroy_rejects_deactivating_the_last_active_admin(): void
    {
        // The actor is an ADMIN whose account is flagged inactive: the route's
        // role check still passes (users.role is ADMIN), but the guard must
        // count only ACTIVE administrators when deciding whether the target
        // is the last one. Counting the inactive admin instead would let the
        // deactivation through and leave the app with no usable admin.
        $lastActiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);
        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => false,
        ]);

        $response = $this->withoutMiddleware([CheckUserActive::class])
            ->actingAs($inactiveAdmin)
            ->delete(route('admin.users.destroy', $lastActiveAdmin->id));

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', 'Cannot delete the only admin user.');

        $lastActiveAdmin->refresh();
        $this->assertTrue($lastActiveAdmin->is_active);
        $this->assertNull($lastActiveAdmin->deleted_at);
    }

    #[Test]
    public function destroy_deactivates_an_active_admin_when_another_active_admin_exists(): void
    {
        $actingAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);
        $otherActiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);

        $response = $this->actingAs($actingAdmin)
            ->delete(route('admin.users.destroy', $otherActiveAdmin->id));

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'User deactivated successfully.');

        $otherActiveAdmin->refresh();
        $this->assertFalse($otherActiveAdmin->is_active);
        $this->assertNotNull($otherActiveAdmin->deleted_at);
    }

    #[Test]
    public function update_rejects_demoting_the_last_active_admin(): void
    {
        $lastActiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);
        User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => false,
        ]);

        $response = $this->actingAs($lastActiveAdmin)
            ->patch(route('admin.users.update', $lastActiveAdmin->id), [
                'name' => $lastActiveAdmin->name,
                'email' => $lastActiveAdmin->email,
                'role' => UserRole::CASE_MANAGER->value,
            ]);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', 'Cannot demote or deactivate the last active administrator.');

        $lastActiveAdmin->refresh();
        $this->assertSame(UserRole::ADMIN->value, $lastActiveAdmin->role);
        $this->assertTrue($lastActiveAdmin->is_active);
    }

    #[Test]
    public function update_rejects_deactivating_the_last_active_admin(): void
    {
        $lastActiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);
        User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => false,
        ]);

        $response = $this->actingAs($lastActiveAdmin)
            ->patch(route('admin.users.update', $lastActiveAdmin->id), [
                'name' => $lastActiveAdmin->name,
                'email' => $lastActiveAdmin->email,
                'role' => UserRole::ADMIN->value,
                'is_active' => false,
            ]);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', 'Cannot demote or deactivate the last active administrator.');

        $lastActiveAdmin->refresh();
        $this->assertTrue($lastActiveAdmin->is_active);
        $this->assertNull($lastActiveAdmin->deleted_at);
    }

    #[Test]
    public function update_demotes_an_admin_when_another_active_admin_exists(): void
    {
        $actingAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);
        $target = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);

        $response = $this->actingAs($actingAdmin)
            ->patch(route('admin.users.update', $target->id), [
                'name' => $target->name,
                'email' => $target->email,
                'role' => UserRole::CASE_MANAGER->value,
            ]);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'User updated successfully.');

        $target->refresh();
        $this->assertSame(UserRole::CASE_MANAGER->value, $target->role);
    }

    #[Test]
    public function service_store_accepts_and_persists_the_requirements_payload(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agency = Agency::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.services.store'), [
            'name' => 'Passport Assistance',
            'description' => 'Facilitates passport processing.',
            'agcy_id' => $agency->id,
            'processing_days' => 5,
            'requirements' => [
                [
                    'name' => 'Valid passport',
                    'description' => 'Expired passports need an official explanation letter.',
                    'is_required' => true,
                ],
                [
                    'name' => 'Affidavit of undertaking',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.services.index'))
            ->assertSessionHas('success', 'Service created successfully.');

        // The service row itself must not have swallowed `requirements`: the
        // model exposes requirements as a relation, not a column, so the raw
        // payload reaching Service::create() would break in PostgreSQL strict
        // mass-assignment mode.
        $service = Service::where('name', 'Passport Assistance')->firstOrFail();
        $this->assertSame(5, $service->processing_days);

        $this->assertDatabaseHas('service_requirements', [
            'service_id' => $service->id,
            'name' => 'Valid passport',
        ]);

        $requirements = $service->requirements()->get();
        $this->assertSame(2, $requirements->count());

        $required = $requirements->firstWhere('name', 'Valid passport');
        $this->assertNotNull($required);
        $this->assertSame('Expired passports need an official explanation letter.', $required->description);
        $this->assertTrue($required->is_required);

        $optional = $requirements->firstWhere('name', 'Affidavit of undertaking');
        $this->assertNotNull($optional);
        $this->assertSame('', $optional->description);
        $this->assertFalse($optional->is_required);
    }

    #[Test]
    public function admin_agency_index_no_longer_sends_total_users(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        Agency::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Agency/Index')
                ->missing('total_users')
                ->has('stats.total')
            );
    }

    #[Test]
    public function welcome_no_longer_sends_can_login_or_can_register(): void
    {
        Agency::factory()->create(['is_active' => true]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->missing('canLogin')
                ->missing('canRegister')
            );
    }
}
