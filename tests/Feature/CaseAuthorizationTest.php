<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CaseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'ADMIN']);
        Role::create(['name' => 'CASE_MANAGER']);
        Role::create(['name' => 'AGENCY_FOCAL_PERSON']);
    }

    public function test_agency_without_referral_cannot_view_case(): void
    {
        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $agencyUser->id,
        ]);

        $response = $this->actingAs($agencyUser)->get("/cases/{$case->id}");

        $response->assertStatus(403);
    }

    public function test_agency_with_active_referral_can_view_case(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager->assignRole('CASE_MANAGER');

        $case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $manager->id,
        ]);

        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $response = $this->actingAs($agencyUser)->get("/cases/{$case->id}");

        $response->assertStatus(200);
    }

    public function test_case_manager_can_view_any_case(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager->assignRole('CASE_MANAGER');

        $case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->get("/cases/{$case->id}");

        $response->assertStatus(200);
    }

    public function test_admin_can_view_any_case(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $admin->assignRole('ADMIN');

        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager->assignRole('CASE_MANAGER');

        $case = CaseFile::create([
            'id' => fake()->uuid(),
            'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'client_type' => 'OFW',
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'status' => 'OPEN',
            'user_id' => $manager->id,
        ]);

        $response = $this->actingAs($admin)->get("/cases/{$case->id}");

        $response->assertStatus(200);
    }
}
