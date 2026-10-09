<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\ReferralServiceRequirement;
use App\Models\Service;
use App\Models\ServiceRequirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralServiceAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $caseManager;

    private CaseFile $case;

    private Agency $agency;

    private User $agencyUser;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create(['email' => 'ofw@example.com']);
        $this->case = CaseFile::factory()->create([
            'user_id' => $this->caseManager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);

        $this->agency = Agency::factory()->create();
        $this->agencyUser = User::factory()->create([
            'agcy_id' => $this->agency->id,
            'role' => 'AGENCY',
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'name' => 'Legal Assistance',
            'agcy_id' => $this->agency->id,
        ]);
    }

    // ── Creation without services ────────────────────────────────

    public function test_case_manager_creates_referral_with_no_services(): void
    {
        $response = $this->actingAs($this->caseManager)->post(route('referrals.store'), [
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $response->assertRedirect();

        $referral = Referral::where('case_id', $this->case->id)->first();
        $this->assertNotNull($referral);
        $this->assertSame('', $referral->required_services);
        $this->assertCount(0, $referral->services);
    }

    // ── Add service (AGENCY focal) ──────────────────────────────

    public function test_agency_focal_adds_service_to_referral(): void
    {
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $response = $this->actingAs($this->agencyUser)->post(
            route('referrals.services.add', $referral),
            ['service_id' => $this->service->id]
        );

        $response->assertRedirect();

        // Pivot row created
        $this->assertDatabaseHas('referral_services', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);

        // required_services text updated to the service name
        $referral->refresh();
        $this->assertSame('Legal Assistance', $referral->required_services);
    }

    public function test_adding_service_copies_global_requirements_to_referral(): void
    {
        ServiceRequirement::create([
            'service_id' => $this->service->id,
            'name' => 'Valid ID',
            'description' => 'Government-issued ID',
            'is_required' => true,
            'sort_order' => 0,
        ]);
        ServiceRequirement::create([
            'service_id' => $this->service->id,
            'name' => 'Proof of Employment',
            'description' => 'Certificate of employment',
            'is_required' => false,
            'sort_order' => 1,
        ]);

        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $this->actingAs($this->agencyUser)->post(
            route('referrals.services.add', $referral),
            ['service_id' => $this->service->id]
        );

        $requirements = ReferralServiceRequirement::where('referral_id', $referral->id)
            ->where('service_id', $this->service->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $requirements);
        $this->assertSame('Valid ID', $requirements[0]->name);
        $this->assertTrue($requirements[0]->is_required);
        $this->assertSame('Proof of Employment', $requirements[1]->name);
        $this->assertFalse($requirements[1]->is_required);
    }

    public function test_adding_multiple_services_updates_required_services_text(): void
    {
        $serviceB = Service::create([
            'name' => 'Financial Assistance',
            'agcy_id' => $this->agency->id,
        ]);

        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $acting = $this->actingAs($this->agencyUser);
        $acting->post(route('referrals.services.add', $referral), ['service_id' => $this->service->id]);
        $acting->post(route('referrals.services.add', $referral), ['service_id' => $serviceB->id]);

        $referral->refresh();
        // Services are ordered alphabetically by name
        $this->assertSame('Financial Assistance, Legal Assistance', $referral->required_services);
    }

    // ── Remove service (AGENCY focal) ───────────────────────────

    public function test_agency_focal_removes_service_from_referral(): void
    {
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $acting = $this->actingAs($this->agencyUser);
        $acting->post(route('referrals.services.add', $referral), ['service_id' => $this->service->id]);

        $referral->refresh();
        $this->assertSame('Legal Assistance', $referral->required_services);

        $response = $acting->delete(
            route('referrals.services.remove', [$referral, $this->service])
        );

        $response->assertRedirect();

        // Pivot row removed
        $this->assertDatabaseMissing('referral_services', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);

        // required_services text is now empty
        $referral->refresh();
        $this->assertSame('', $referral->required_services);
    }

    public function test_removing_service_deletes_referral_service_requirements(): void
    {
        ServiceRequirement::create([
            'service_id' => $this->service->id,
            'name' => 'Valid ID',
            'is_required' => true,
        ]);

        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $acting = $this->actingAs($this->agencyUser);
        $acting->post(route('referrals.services.add', $referral), ['service_id' => $this->service->id]);

        $this->assertDatabaseHas('referral_service_requirements', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);

        $acting->delete(route('referrals.services.remove', [$referral, $this->service]));

        // Requirements are soft-deleted (SoftDeleteFlag trait) — check deleted_at is set
        $this->assertSoftDeleted('referral_service_requirements', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);
    }

    // ── Non-AGENCY role rejected ────────────────────────────────

    public function test_case_manager_cannot_add_service(): void
    {
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $response = $this->actingAs($this->caseManager)->post(
            route('referrals.services.add', $referral),
            ['service_id' => $this->service->id]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Service was NOT added to the pivot
        $this->assertDatabaseMissing('referral_services', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);
    }

    public function test_admin_cannot_add_service(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $response = $this->actingAs($admin)->post(
            route('referrals.services.add', $referral),
            ['service_id' => $this->service->id]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('referral_services', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);
    }

    public function test_case_manager_cannot_remove_service(): void
    {
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        // Agency focal adds the service first
        $this->actingAs($this->agencyUser)->post(
            route('referrals.services.add', $referral),
            ['service_id' => $this->service->id]
        );

        $this->assertDatabaseHas('referral_services', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);

        $response = $this->actingAs($this->caseManager)->delete(
            route('referrals.services.remove', [$referral, $this->service])
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Service is still attached
        $this->assertDatabaseHas('referral_services', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);
    }

    public function test_admin_cannot_remove_service(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $referral = Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);

        $this->actingAs($this->agencyUser)->post(
            route('referrals.services.add', $referral),
            ['service_id' => $this->service->id]
        );

        $response = $this->actingAs($admin)->delete(
            route('referrals.services.remove', [$referral, $this->service])
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('referral_services', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);
    }
}
