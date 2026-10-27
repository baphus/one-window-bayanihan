<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
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
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $manager1 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $manager2 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        CaseFile::factory(3)->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        CaseFile::factory(2)->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($admin);
        $cases = $this->service->getCases();

        $this->assertCount(5, $cases);
    }

    public function test_case_manager_sees_all_cases(): void
    {
        $manager1 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $manager2 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        CaseFile::factory(3)->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        CaseFile::factory(2)->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($manager1);
        $cases = $this->service->getCases();

        $this->assertCount(5, $cases);
    }

    public function test_case_manager_sees_other_managers_cases(): void
    {
        $manager1 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $manager2 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $case1 = CaseFile::factory()->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        $case2 = CaseFile::factory()->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($manager1);
        $cases = $this->service->getCases();

        $this->assertCount(2, $cases);
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
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);

        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

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
            'role' => UserRole::AGENCY->value,
            'agcy_id' => null,
        ]);

        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        CaseFile::factory(3)->create(['user_id' => $manager->id, 'status' => 'OPEN']);

        $this->actingAs($agencyUser);
        $cases = $this->service->getCases();

        $this->assertCount(0, $cases);
    }

    public function test_case_manager_can_filter_by_user_id(): void
    {
        $manager1 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $manager2 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        CaseFile::factory(2)->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        CaseFile::factory(3)->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($manager1);
        // CASE_MANAGER sees all cases but can filter by user_id to narrow down
        $cases = $this->service->getCases(['user_id' => $manager2->id]);

        $this->assertCount(3, $cases);
        foreach ($cases as $case) {
            $this->assertEquals($manager2->id, $case->user_id);
        }
    }

    public function test_pending_review_cases_are_excluded_from_get_cases(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        CaseFile::factory(3)->create(['user_id' => $manager->id, 'status' => 'OPEN']);
        // A case awaiting review belongs to the review queue, not the case list.
        CaseFile::factory(2)->create(['user_id' => $manager->id, 'status' => 'PENDING_REVIEW']);

        $this->actingAs($manager);
        $cases = $this->service->getCases();

        $this->assertCount(3, $cases);
        foreach ($cases as $case) {
            $this->assertNotEquals('PENDING_REVIEW', $case->status);
        }
    }

    public function test_admin_can_filter_by_user_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $manager1 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $manager2 = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        CaseFile::factory(2)->create(['user_id' => $manager1->id, 'status' => 'OPEN']);
        CaseFile::factory(3)->create(['user_id' => $manager2->id, 'status' => 'OPEN']);

        $this->actingAs($admin);
        $cases = $this->service->getCases(['user_id' => $manager1->id]);

        $this->assertCount(2, $cases);
    }

    public function test_other_filters_still_work_with_role_scope(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $ofwClient = Client::factory()->create(['type' => 'OFW']);
        $nokClient = Client::factory()->create(['type' => 'NEXT_OF_KIN']);

        CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'client_id' => $ofwClient->id,
        ]);
        CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'client_id' => $nokClient->id,
        ]);

        $this->actingAs($manager);
        $cases = $this->service->getCases();

        $this->assertCount(2, $cases);

        // ponytail: getCases() has no client_type filter (type lives on
        // clients.type now); canonical path is CaseFile::ofClientType().
        $ofwCases = CaseFile::ofClientType('OFW')->get();
        $this->assertCount(1, $ofwCases);
        $this->assertSame('OFW', $ofwCases->first()->client->type);

        $nextOfKinCases = CaseFile::ofClientType(CaseFile::CLIENT_TYPE_NEXT_OF_KIN)->get();
        $this->assertCount(1, $nextOfKinCases);
        $this->assertSame('NEXT_OF_KIN', $nextOfKinCases->first()->client->type);
    }

    public function test_client_type_is_restored_from_clients_type_in_case_payloads(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $ofwClient = Client::factory()->create(['type' => 'OFW']);
        $nokClient = Client::factory()->create(['type' => 'NEXT_OF_KIN']);

        $ofwCase = CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'client_id' => $ofwClient->id,
        ]);
        $nokCase = CaseFile::factory()->create([
            'user_id' => $manager->id,
            'status' => 'OPEN',
            'client_id' => $nokClient->id,
        ]);

        // cases.client_type was dropped; the UI and the case-report PDF still
        // read it, so the accessor must answer on the model and on toArray()
        // (the shape Inertia hands to Case/Index, Case/Show, Referral/* and
        // Client/Index).
        $this->assertSame('OFW', $ofwCase->client_type);
        $this->assertSame('NEXT_OF_KIN', $nokCase->client_type);
        $this->assertSame('OFW', $ofwCase->toArray()['client_type']);
        $this->assertSame('NEXT_OF_KIN', $nokCase->toArray()['client_type']);

        $this->actingAs($manager);
        $cases = $this->service->getCases();

        $byNumber = $cases->keyBy('case_number');
        $this->assertSame('OFW', $byNumber[$ofwCase->case_number]->toArray()['client_type']);
        $this->assertSame('NEXT_OF_KIN', $byNumber[$nokCase->case_number]->toArray()['client_type']);
    }

    public function test_client_type_is_null_without_a_client(): void
    {
        $case = CaseFile::factory()->create();

        $this->assertNull($case->client_type);
        $this->assertNull($case->toArray()['client_type']);
    }
}
