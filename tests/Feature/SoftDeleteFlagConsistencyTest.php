<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseStatus;
use App\Models\Referral;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Soft-delete semantics must agree with SoftDeleteFlag::runSoftDelete():
 * `delete()` writes is_deleted, deleted_by AND deleted_at together, because
 * the SoftDeletes global scope every default-scope query relies on looks at
 * deleted_at — an is_deleted-only write leaves a "deleted" row visible to
 * everything that never opts into the flag explicitly.
 */
class SoftDeleteFlagConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    public function test_agency_destroy_sets_deleted_at_and_is_deleted(): void
    {
        $agency = Agency::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.agencies.destroy', $agency->id));

        $response->assertRedirect(route('admin.agencies.index'));
        $response->assertSessionHas('success');

        // Hidden from the default scope...
        $this->assertNull(Agency::find($agency->id));

        // ...but fully flagged through the soft-delete mechanism.
        $trashed = Agency::withTrashed()->find($agency->id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());
        $this->assertNotNull($trashed->deleted_at);
        $this->assertTrue($trashed->is_deleted);
        $this->assertFalse($trashed->is_active);
        $this->assertSame($this->admin->id, $trashed->deleted_by);
    }

    public function test_case_status_destroy_sets_deleted_at_and_is_deleted(): void
    {
        $status = CaseStatus::create([
            'name' => 'Emergency Review',
            'slug' => 'EMERGENCY_REVIEW',
            'type' => 'referral',
            'color' => '#ef4444',
            'sort_order' => 10,
            'is_active' => true,
            'is_system' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.case-statuses.destroy', $status->id));

        $response->assertRedirect(route('admin.case-statuses.index'));
        $response->assertSessionHas('success');

        $this->assertNull(CaseStatus::find($status->id));

        $trashed = CaseStatus::withTrashed()->find($status->id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());
        $this->assertNotNull($trashed->deleted_at);
        $this->assertTrue($trashed->is_deleted);
        $this->assertFalse($trashed->is_active);
    }

    public function test_user_deactivate_hides_from_default_scope_and_reactivate_restores_fully(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user->id));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $this->assertNull(User::find($user->id));

        $trashed = User::withTrashed()->find($user->id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());
        $this->assertNotNull($trashed->deleted_at);
        $this->assertTrue($trashed->is_deleted);
        $this->assertFalse($trashed->is_active);
        $this->assertSame($this->admin->id, $trashed->deleted_by);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.users.reactivate', $user->id));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        // Refresh() re-reads by primary key without the soft-delete scope, so
        // it can pull the row this test just restored.
        $user->refresh();

        $this->assertTrue($user->is_active);
        $this->assertFalse($user->is_deleted);
        $this->assertNull($user->deleted_at);
        $this->assertNull($user->deleted_by);

        // Restored rows are live to the default scope again.
        $this->assertNotNull(User::find($user->id));
    }

    public function test_agency_logo_update_rejects_non_image_upload(): void
    {
        $agency = Agency::factory()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.agencies.update', $agency->id), [
                'name' => 'Logo Test Agency',
                'short' => 'LOGO',
                'logo_url' => UploadedFile::fake()->create('logo.pdf', 100),
            ]);

        $response->assertSessionHasErrors(['logo_url' => 'The logo url field must be an image.']);

        $this->assertNull($agency->refresh()->logo_url);
    }

    public function test_agency_logo_update_rejects_oversized_upload(): void
    {
        $agency = Agency::factory()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.agencies.update', $agency->id), [
                'name' => 'Logo Test Agency',
                'short' => 'LOGO',
                'logo_url' => UploadedFile::fake()->create('logo.png', 3000),
            ]);

        $response->assertSessionHasErrors(['logo_url' => 'The logo url field must not be greater than 2048 kilobytes.']);

        $this->assertNull($agency->refresh()->logo_url);
    }

    public function test_deleting_service_with_active_referral_is_blocked(): void
    {
        $agency = Agency::factory()->create();
        $service = Service::create(['name' => 'Legal Consultation', 'agcy_id' => $agency->id]);

        $referral = Referral::factory()->pending()->create(['agcy_id' => $agency->id]);
        $referral->services()->attach($service->id);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.services.destroy', $service->id));

        $response->assertRedirect(route('admin.services.index'));
        $this->assertStringContainsString(
            'active referral still uses this service',
            (string) $response->getSession()->get('error'),
        );

        // Untouched: still live to the default scope, no soft-delete written.
        $service->refresh();
        $this->assertNull($service->deleted_at);
        $this->assertFalse($service->is_deleted);
        $this->assertNotNull(Service::find($service->id));
    }

    public function test_agency_user_deleting_service_with_active_referral_is_blocked(): void
    {
        $agency = Agency::factory()->create();
        $service = Service::create(['name' => 'Skilled Assessment', 'agcy_id' => $agency->id]);

        $referral = Referral::factory()->processing()->create(['agcy_id' => $agency->id]);
        $referral->services()->attach($service->id);

        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)
            ->delete(route('agency.services.destroy', $service->id));

        $response->assertSessionHasErrors(['service']);
        $this->assertNotNull(Service::find($service->id));
    }

    public function test_deleting_service_without_active_referrals_succeeds(): void
    {
        $agency = Agency::factory()->create();
        $service = Service::create(['name' => 'Wrapper Liberation', 'agcy_id' => $agency->id]);

        // Only terminal-state referrals — COMPLETED and REJECTED end the
        // workflow and no longer need the service's display or requirement
        // tracking, so they must not block the delete.
        foreach ([Referral::factory()->completed(), Referral::factory()->rejected()] as $factory) {
            $factory->create(['agcy_id' => $agency->id])->services()->attach($service->id);
        }

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.services.destroy', $service->id));

        $response->assertRedirect(route('admin.services.index'));
        $response->assertSessionHas('success');

        $this->assertNull(Service::find($service->id));

        $trashed = Service::withTrashed()->find($service->id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());
        $this->assertNotNull($trashed->deleted_at);
        $this->assertTrue($trashed->is_deleted);
    }

    public function test_agency_user_deleting_service_without_active_referrals_succeeds(): void
    {
        $agency = Agency::factory()->create();
        $service = Service::create(['name' => 'Medical Clearance', 'agcy_id' => $agency->id]);

        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)
            ->delete(route('agency.services.destroy', $service->id));

        $response->assertRedirect(route('agency.services.index'));
        $response->assertSessionHas('success');

        $trashed = Service::withTrashed()->find($service->id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());
        $this->assertNotNull($trashed->deleted_at);
        $this->assertTrue($trashed->is_deleted);
    }
}
