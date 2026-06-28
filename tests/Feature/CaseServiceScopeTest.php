<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseServiceScopeTest extends TestCase
{
    use RefreshDatabase;

    private CaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CaseService::class);
    }

    public function test_admin_sees_all_non_draft_cases(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $manager1 = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager2 = User::factory()->create(['role' => 'CASE_MANAGER']);

        CaseFile::factory(3)->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        CaseFile::factory(2)->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($admin);
        $cases = $this->service->getCases();

        $this->assertCount(5, $cases);
    }

    public function test_case_manager_sees_only_own_cases(): void
    {
        $manager1 = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager2 = User::factory()->create(['role' => 'CASE_MANAGER']);

        CaseFile::factory(3)->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        CaseFile::factory(2)->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($manager1);
        $cases = $this->service->getCases();

        $this->assertCount(3, $cases);
        foreach ($cases as $case) {
            $this->assertEquals($manager1->id, $case->user_id);
        }
    }

    public function test_case_manager_does_not_see_other_managers_cases(): void
    {
        $manager1 = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager2 = User::factory()->create(['role' => 'CASE_MANAGER']);

        $case1 = CaseFile::factory()->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        CaseFile::factory()->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($manager1);
        $cases = $this->service->getCases();

        $this->assertCount(1, $cases);
        $this->assertEquals($case1->id, $cases->first()->id);
    }

    public function test_agency_sees_cases_referred_to_their_agency(): void
    {
        $agency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Test Agency',
            'short' => 'TA',
            'slug' => 'test-agency',
        ]);

        $otherAgency = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Other Agency',
            'short' => 'OA',
            'slug' => 'other-agency',
        ]);

        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);

        // Case with referral to agency user's agency — should be visible
        $caseWithReferral = CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
        ]);
        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Test service',
            'status' => 'PENDING',
            'case_id' => $caseWithReferral->id,
            'agcy_id' => $agency->id,
        ]);

        // Case with referral to a different agency — should NOT be visible
        $otherCase = CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
        ]);
        Referral::create([
            'id' => fake()->uuid(),
            'required_services' => 'Other service',
            'status' => 'PENDING',
            'case_id' => $otherCase->id,
            'agcy_id' => $otherAgency->id,
        ]);

        // Case with no referral at all — should NOT be visible
        CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
        ]);

        $this->actingAs($agencyUser);
        $cases = $this->service->getCases();

        $this->assertCount(1, $cases);
        $this->assertEquals($caseWithReferral->id, $cases->first()->id);
    }

    public function test_agency_without_agency_id_sees_no_cases(): void
    {
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => null,
        ]);

        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        CaseFile::factory(3)->create(['user_id' => $manager->id, 'status' => 'OPEN']);

        $this->actingAs($agencyUser);
        $cases = $this->service->getCases();

        $this->assertCount(0, $cases);
    }

    public function test_case_manager_cannot_filter_by_other_user_id(): void
    {
        $manager1 = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager2 = User::factory()->create(['role' => 'CASE_MANAGER']);

        CaseFile::factory(2)->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        CaseFile::factory(3)->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($manager1);
        // Passing another user's ID should have no effect — CASE_MANAGER is scoped to self
        $cases = $this->service->getCases(['user_id' => $manager2->id]);

        $this->assertCount(2, $cases);
        foreach ($cases as $case) {
            $this->assertEquals($manager1->id, $case->user_id);
        }
    }

    public function test_drafts_are_excluded_from_get_cases(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);

        CaseFile::factory(3)->create(['user_id' => $manager->id, 'status' => 'OPEN']);
        CaseFile::factory(2)->create(['user_id' => $manager->id, 'status' => 'DRAFT']);

        $this->actingAs($manager);
        $cases = $this->service->getCases();

        $this->assertCount(3, $cases);
        foreach ($cases as $case) {
            $this->assertNotEquals('DRAFT', $case->status);
        }
    }

    public function test_admin_can_filter_by_user_id(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $manager1 = User::factory()->create(['role' => 'CASE_MANAGER']);
        $manager2 = User::factory()->create(['role' => 'CASE_MANAGER']);

        CaseFile::factory(2)->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        CaseFile::factory(3)->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($admin);
        $cases = $this->service->getCases(['user_id' => $manager1->id]);

        $this->assertCount(2, $cases);
    }

    public function test_other_filters_still_work_with_role_scope(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);

        CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'client_type' => 'OFW',
        ]);
        CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'client_type' => 'NOK',
        ]);

        $this->actingAs($manager);
        $cases = $this->service->getCases(['client_type' => 'OFW']);

        $this->assertCount(1, $cases);
        $this->assertEquals('OFW', $cases->first()->client_type);
    }
}
