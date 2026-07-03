<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\NextOfKin;
use App\Models\PhilippineAddress;
use App\Models\User;
use App\Services\CaseService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_tracker_number_format(): void
    {
        $service = app(CaseService::class);
        $reflection = new \ReflectionMethod($service, 'generateTrackerNumber');
        $reflection->setAccessible(true);
        $number = $reflection->invoke($service);
        $this->assertMatchesRegularExpression('/^OWBAP-[A-Z0-9]{7}$/', $number);
    }

    public function test_generate_tracker_number_unique(): void
    {
        $service = app(CaseService::class);
        $reflection = new \ReflectionMethod($service, 'generateTrackerNumber');
        $reflection->setAccessible(true);
        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $numbers[] = $reflection->invoke($service);
        }
        $this->assertCount(10, array_unique($numbers));
    }

    public function test_user_can_delete_own_draft(): void
    {
        $user = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
        ]);
        $client = Client::factory()->create();
        $case->client_id = $client->id;
        $case->save();

        $auditCount = AuditLog::count();

        $service = app(CaseService::class);
        $service->deleteDraft($case->id, $user->id);

        $this->assertDatabaseMissing('cases', ['id' => $case->id]);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertEquals($auditCount, AuditLog::count());
    }

    public function test_user_cannot_delete_another_users_draft(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $userA->id,
        ]);

        $service = app(CaseService::class);

        $this->expectException(AuthorizationException::class);
        $service->deleteDraft($case->id, $userB->id);
    }

    public function test_cannot_delete_non_draft_case(): void
    {
        $user = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'OPEN',
            'user_id' => $user->id,
        ]);

        $service = app(CaseService::class);

        $this->expectException(ModelNotFoundException::class);
        $service->deleteDraft($case->id, $user->id);
    }

    public function test_delete_draft_removes_related_client(): void
    {
        $user = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
        ]);
        $client = Client::factory()->create();
        $case->client_id = $client->id;
        $case->save();
        $address = ClientAddress::create([
            'client_id' => $client->id,
            'region' => 'Region VII',
            'province' => 'Cebu',
        ]);
        $employment = ClientEmployment::create([
            'client_id' => $client->id,
            'employer_name' => 'Test Corp',
            'position' => 'Worker',
            'country' => 'UAE',
        ]);
        $nextOfKin = NextOfKin::create([
            'client_id' => $client->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'relationship' => 'Spouse',
        ]);

        $service = app(CaseService::class);
        $service->deleteDraft($case->id, $user->id);

        $this->assertDatabaseMissing('cases', ['id' => $case->id]);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertDatabaseMissing('client_addresses', ['id' => $address->id]);
        $this->assertDatabaseMissing('client_employments', ['id' => $employment->id]);
        $this->assertDatabaseMissing('next_of_kin', ['id' => $nextOfKin->id]);
    }

    public function test_delete_draft_does_not_create_audit_log(): void
    {
        $user = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
        ]);
        $client = Client::factory()->create();
        $case->client_id = $client->id;
        $case->save();

        $beforeCount = AuditLog::count();

        $service = app(CaseService::class);
        $service->deleteDraft($case->id, $user->id);

        $this->assertEquals($beforeCount, AuditLog::count());
    }

    public function test_user_can_get_own_drafts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        CaseFile::factory(3)->create([
            'status' => 'DRAFT',
            'user_id' => $userA->id,
        ]);
        CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $userB->id,
        ]);

        $service = app(CaseService::class);
        $drafts = $service->getUserDrafts($userA->id);

        $this->assertCount(3, $drafts);
    }

    public function test_get_drafts_does_not_return_others_drafts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $draftA = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $userA->id,
        ]);
        CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $userB->id,
        ]);

        $service = app(CaseService::class);
        $drafts = $service->getUserDrafts($userA->id);

        $this->assertCount(1, $drafts);
        $this->assertEquals($draftA->id, $drafts->first()->id);
    }

    public function test_get_drafts_does_not_return_non_draft_cases(): void
    {
        $user = User::factory()->create();

        CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
        ]);
        CaseFile::factory()->create([
            'status' => 'OPEN',
            'user_id' => $user->id,
        ]);

        $service = app(CaseService::class);
        $drafts = $service->getUserDrafts($user->id);

        $this->assertCount(1, $drafts);
    }

    public function test_user_can_publish_own_draft(): void
    {
        $user = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'category_id' => $category->id,
            'client_type' => 'OFW',
            'draft_client_data' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'date_of_birth' => '1990-01-01',
                'sex' => 'Male',
                'email' => 'juan@example.test',
                'contact_number' => '+639171234567',
                'address' => [
                    'region' => 'Region VII',
                    'province' => 'Cebu',
                    'city_municipality' => 'Cebu City',
                    'barangay' => 'Lahug',
                ],
                'consent' => true,
            ],
        ]);

        $service = app(CaseService::class);
        $result = $service->publishDraft($case->id, $user->id);

        $this->assertEquals('OPEN', $result->status);
        $this->assertDatabaseHas('cases', [
            'id' => $case->id,
            'status' => 'OPEN',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $case->id,
            'action' => 'PUBLISH',
            'module' => 'CASE',
        ]);
    }

    public function test_incomplete_draft_cannot_be_published(): void
    {
        $user = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'category_id' => null,
            'draft_client_data' => [
                'first_name' => 'Juan',
            ],
        ]);

        $service = app(CaseService::class);

        $this->expectException(ValidationException::class);
        $service->publishDraft($case->id, $user->id);
    }

    public function test_ofw_draft_requires_ofw_email_before_publish(): void
    {
        $user = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'category_id' => $category->id,
            'client_type' => 'OFW',
            'draft_client_data' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'date_of_birth' => '1990-01-01',
                'sex' => 'Male',
                'contact_number' => '+639171234567',
                'address' => [
                    'region' => 'Region VII',
                    'province' => 'Cebu',
                    'city_municipality' => 'Cebu City',
                    'barangay' => 'Lahug',
                ],
                'consent' => true,
            ],
        ]);

        $service = app(CaseService::class);

        $this->expectException(ValidationException::class);
        $service->publishDraft($case->id, $user->id);
    }

    public function test_next_of_kin_draft_uses_selected_nok_email_before_publish(): void
    {
        $user = User::factory()->create();
        $category = CaseCategory::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'category_id' => $category->id,
            'client_type' => 'NEXT_OF_KIN',
            'draft_client_data' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'date_of_birth' => '1990-01-01',
                'sex' => 'Male',
                'email' => null,
                'contact_number' => '+639171234567',
                'address' => [
                    'region' => 'Region VII',
                    'province' => 'Cebu',
                    'city_municipality' => 'Cebu City',
                    'barangay' => 'Lahug',
                ],
                'next_of_kin' => [[
                    'first_name' => 'Maria',
                    'last_name' => 'Dela Cruz',
                    'relationship' => 'Spouse',
                    'email' => 'maria@example.test',
                ]],
                'selected_nok_index' => 0,
                'consent' => true,
            ],
        ]);

        $service = app(CaseService::class);
        $result = $service->publishDraft($case->id, $user->id);

        $this->assertEquals('OPEN', $result->status);
        $this->assertDatabaseHas('clients', [
            'id' => $result->client_id,
            'email' => null,
        ]);
        $this->assertDatabaseHas('next_of_kin', [
            'client_id' => $result->client_id,
            'email' => 'maria@example.test',
        ]);
    }

    public function test_user_cannot_publish_another_users_draft(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $userA->id,
        ]);

        $service = app(CaseService::class);

        $this->expectException(AuthorizationException::class);
        $service->publishDraft($case->id, $userB->id);
    }

    // ─── updateDraft tests ─────────────────────────────────────────────────

    public function test_update_draft_updates_fields(): void
    {
        $user = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_type' => 'OFW',
            'vulnerability_indicator' => 'Low',
            'summary' => 'Original summary',
        ]);
        $client = Client::factory()->create();
        $case->client_id = $client->id;
        $case->save();

        $service = app(CaseService::class);
        $result = $service->updateDraft($case->id, [
            'client_type' => 'NOK',
            'vulnerability_indicator' => 'High',
            'summary' => 'Updated summary',
        ], $user->id);

        $this->assertDatabaseHas('cases', [
            'id' => $case->id,
            'client_type' => 'NOK',
            'vulnerability_indicator' => 'High',
            'summary' => 'Updated summary',
        ]);
        $this->assertEquals('NOK', $result->client_type);
        $this->assertEquals('High', $result->vulnerability_indicator);
    }

    public function test_update_draft_no_case_number_regen(): void
    {
        $user = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'case_number' => 'CASE-20260603-0001',
            'tracker_number' => 'OWBAP-ABC1234',
        ]);
        $client = Client::factory()->create();
        $case->client_id = $client->id;
        $case->save();

        $service = app(CaseService::class);
        $result = $service->updateDraft($case->id, [
            'client_type' => 'NOK',
        ], $user->id);

        $this->assertEquals('CASE-20260603-0001', $result->case_number);
        $this->assertEquals('OWBAP-ABC1234', $result->tracker_number);
    }

    public function test_update_draft_no_audit_log(): void
    {
        $user = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
        ]);
        $client = Client::factory()->create();
        $case->client_id = $client->id;
        $case->save();

        $beforeCount = AuditLog::count();

        $service = app(CaseService::class);
        $service->updateDraft($case->id, [
            'client_type' => 'NOK',
        ], $user->id);

        $this->assertEquals($beforeCount, AuditLog::count());
    }

    public function test_update_draft_updates_draft_client_data(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'draft_client_data' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'contact_number' => '09170000000',
                'client_type' => 'OFW',
                'selected_client_id' => null,
            ],
        ]);
        $case->client_id = $client->id;
        $case->save();

        $service = app(CaseService::class);
        $result = $service->updateDraft($case->id, [
            'client' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
                'contact_number' => '09171111111',
            ],
        ], $user->id);

        $result->refresh();
        $this->assertEquals('Jane', $result->draft_client_data['first_name']);
        $this->assertEquals('Smith', $result->draft_client_data['last_name']);
        $this->assertEquals('jane@example.com', $result->draft_client_data['email']);

        // Original client record must NOT have been modified
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }

    public function test_update_draft_updates_existing_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'draft_client_data' => [
                'selected_client_id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
            ],
        ]);
        $case->client_id = $client->id;
        $case->save();

        // Pre-populate address, employment, NOK
        $address = ClientAddress::create([
            'client_id' => $client->id,
            'region' => 'Region VII',
            'province' => 'Cebu',
        ]);
        $employment = ClientEmployment::create([
            'client_id' => $client->id,
            'employer_name' => 'Old Employer',
            'position' => 'Worker',
            'country' => 'UAE',
        ]);
        $nok = NextOfKin::create([
            'client_id' => $client->id,
            'first_name' => 'Old NOK',
            'relationship' => 'Spouse',
        ]);

        $service = app(CaseService::class);
        $result = $service->updateDraft($case->id, [
            'selected_client_id' => $client->id,
            'address' => [
                'region' => 'NCR',
                'province' => 'Metro Manila',
                'city_municipality' => 'Manila',
                'barangay' => 'Barangay 1',
                'street' => 'Street 123',
            ],
            'employment' => [
                'employer_name' => 'New Employer',
                'position' => 'Manager',
                'country' => 'USA',
            ],
            'next_of_kin' => [
                'first_name' => 'New NOK',
                'relationship' => 'Sibling',
                'phone_number' => '09170000000',
            ],
        ], $user->id);

        // Assert address updated
        $this->assertDatabaseHas('client_addresses', [
            'id' => $address->id,
            'region' => 'NCR',
            'province' => 'Metro Manila',
        ]);
        // Assert employment updated
        $this->assertDatabaseHas('client_employments', [
            'id' => $employment->id,
            'employer_name' => 'New Employer',
            'country' => 'USA',
        ]);
        // Assert next of kin updated
        $this->assertDatabaseHas('next_of_kin', [
            'id' => $nok->id,
            'first_name' => 'New NOK',
            'relationship' => 'Sibling',
        ]);
    }

    public function test_cannot_update_another_users_draft(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $userA->id,
        ]);
        $client = Client::factory()->create();
        $case->client_id = $client->id;
        $case->save();

        $service = app(CaseService::class);

        $this->expectException(AuthorizationException::class);
        $service->updateDraft($case->id, [
            'client_type' => 'NOK',
        ], $userB->id);
    }

    // ─── getUserDrafts search/filter/sort tests ────────────────────────────

    public function test_get_drafts_search_by_name(): void
    {
        $user = User::factory()->create();

        $clientA = Client::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $draftA = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $clientA->id,
        ]);

        $clientB = Client::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $clientB->id,
        ]);

        $service = app(CaseService::class);
        $results = $service->getUserDrafts($user->id, ['search' => 'Alice']);

        $this->assertCount(1, $results);
        $this->assertEquals($draftA->id, $results->first()->id);
    }

    public function test_get_drafts_search_by_case_number(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $draftA = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $client->id,
            'case_number' => 'CASE-20260603-0001',
        ]);
        CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $client->id,
            'case_number' => 'CASE-20260603-0002',
        ]);

        $service = app(CaseService::class);
        $results = $service->getUserDrafts($user->id, ['search' => '0001']);

        $this->assertCount(1, $results);
        $this->assertEquals($draftA->id, $results->first()->id);
    }

    public function test_get_drafts_filter_by_date_range(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        // Use explicit date strings for consistent date filtering
        $day10ago = now()->subDays(10)->format('Y-m-d H:i:s');
        $day8ago = now()->subDays(8)->format('Y-m-d H:i:s');
        $day5ago = now()->subDays(5)->format('Y-m-d H:i:s');
        $day4ago = now()->subDays(4)->format('Y-m-d H:i:s');
        $day3ago = now()->subDays(3)->format('Y-m-d H:i:s');
        $now = now()->format('Y-m-d H:i:s');

        // Draft updated 10 days ago
        $old = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);
        CaseFile::where('id', $old->id)->update(['updated_at' => $day10ago]);

        // Draft updated 3 days ago
        $mid = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);
        CaseFile::where('id', $mid->id)->update(['updated_at' => $day3ago]);

        // Draft updated today
        $recent = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);
        CaseFile::where('id', $recent->id)->update(['updated_at' => $now]);

        $service = app(CaseService::class);

        // Filter from 5 days ago onward → should get mid (3d) and recent (now)
        $results = $service->getUserDrafts($user->id, [
            'date_from' => $day5ago,
        ]);
        $this->assertCount(2, $results);
        $ids = $results->pluck('id')->toArray();
        $this->assertContains($mid->id, $ids);
        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);

        // Filter up to 5 days ago → only old (10d ago) qualifies (mid=3d ago is more recent than 5d ago)
        $results = $service->getUserDrafts($user->id, [
            'date_to' => $day5ago,
        ]);
        $this->assertCount(1, $results);
        $this->assertEquals($old->id, $results->first()->id);

        // Filter from 8 days ago to 4 days ago → no drafts qualify (old=10d, mid=3d, recent=now)
        $results = $service->getUserDrafts($user->id, [
            'date_from' => $day8ago,
            'date_to' => $day4ago,
        ]);
        $this->assertCount(0, $results);
    }

    public function test_get_drafts_ordered_by_updated_at(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $now = Carbon::now();

        // Draft A — updated 5 days ago
        $draftA = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);
        CaseFile::where('id', $draftA->id)->update(['updated_at' => $now->copy()->subDays(5)]);

        // Draft B — updated 1 day ago (more recent)
        $draftB = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);
        CaseFile::where('id', $draftB->id)->update(['updated_at' => $now->copy()->subDays(1)]);

        $service = app(CaseService::class);
        $results = $service->getUserDrafts($user->id);

        $this->assertCount(2, $results);
        // Draft B (updated more recently) should appear first
        $this->assertEquals($draftB->id, $results->first()->id);
    }

    public function test_get_drafts_pagination(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        CaseFile::factory(20)->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        $service = app(CaseService::class);
        $results = $service->getUserDrafts($user->id, [], 5);

        $this->assertCount(5, $results);
        $this->assertEquals(5, $results->perPage());
        $this->assertEquals(20, $results->total());
    }

    // ─── Address code-to-name resolution tests ─────────────────────────────

    public function test_create_case_resolves_address_codes_to_names(): void
    {
        $service = app(CaseService::class);
        $user = User::factory()->create();
        $client = Client::factory()->create();

        // Seed philippine_addresses with a hierarchy
        PhilippineAddress::create([
            'code' => 'REG01', 'name' => 'Test Region', 'type' => 'region', 'parent_code' => null,
        ]);
        PhilippineAddress::create([
            'code' => 'PROV01', 'name' => 'Test Province', 'type' => 'province', 'parent_code' => 'REG01',
        ]);

        $result = $service->createCase([
            'client_type' => 'OFW',
            'client' => ['first_name' => 'John', 'last_name' => 'Doe'],
            'selected_client_id' => $client->id,
            'address' => ['region' => 'REG01', 'province' => 'PROV01', 'city_municipality' => '', 'barangay' => '', 'street' => '123 Main'],
        ], $user->id);

        $address = $client->addresses()->first();
        $this->assertEquals('Test Region', $address->region);
        $this->assertEquals('Test Province', $address->province);
    }

    public function test_update_draft_resolves_address_codes_to_names(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
            'draft_client_data' => [
                'selected_client_id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
            ],
        ]);
        $case->client_id = $client->id;
        $case->save();

        // Seed philippine_addresses
        PhilippineAddress::create([
            'code' => 'REG01', 'name' => 'Test Region', 'type' => 'region', 'parent_code' => null,
        ]);
        PhilippineAddress::create([
            'code' => 'PROV01', 'name' => 'Test Province', 'type' => 'province', 'parent_code' => 'REG01',
        ]);

        $service = app(CaseService::class);
        $service->updateDraft($case->id, [
            'selected_client_id' => $client->id,
            'address' => [
                'region' => 'REG01',
                'province' => 'PROV01',
                'city_municipality' => '',
                'barangay' => '',
                'street' => '456 Oak St',
            ],
        ], $user->id);

        $address = $client->addresses()->first();
        $this->assertEquals('Test Region', $address->region);
        $this->assertEquals('Test Province', $address->province);
    }

    public function test_normalize_nok_data_resolves_codes_to_names(): void
    {
        $service = app(CaseService::class);
        $reflection = new \ReflectionMethod($service, 'normalizeNokData');
        $reflection->setAccessible(true);

        // Seed philippine_addresses
        PhilippineAddress::create([
            'code' => 'REG01', 'name' => 'Test Region', 'type' => 'region', 'parent_code' => null,
        ]);
        PhilippineAddress::create([
            'code' => 'PROV01', 'name' => 'Test Province', 'type' => 'province', 'parent_code' => 'REG01',
        ]);
        PhilippineAddress::create([
            'code' => 'CIT01', 'name' => 'Test City', 'type' => 'city', 'parent_code' => 'PROV01',
        ]);
        PhilippineAddress::create([
            'code' => 'BRG01', 'name' => 'Test Barangay', 'type' => 'barangay', 'parent_code' => 'CIT01',
        ]);

        // Test flat format (codes at the top level)
        $flatData = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'relationship' => 'Spouse',
            'region' => 'REG01',
            'province' => 'PROV01',
            'city_municipality' => 'CIT01',
            'barangay' => 'BRG01',
            'full_address' => '123 Main St, Test City',
        ];

        $result = $reflection->invoke($service, $flatData);
        $this->assertEquals('Test Region', $result['region']);
        $this->assertEquals('Test Province', $result['province']);
        $this->assertEquals('Test City', $result['city_municipality']);
        $this->assertEquals('Test Barangay', $result['barangay']);
        $this->assertEquals('123 Main St, Test City', $result['full_address']);

        // Test nested format (codes in nok_address sub-array)
        $nestedData = [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'relationship' => 'Sibling',
            'nok_address' => [
                'region' => 'REG01',
                'province' => 'PROV01',
                'city_municipality' => 'CIT01',
                'barangay' => 'BRG01',
            ],
            'full_address' => '456 Oak Ave, Test Barangay',
        ];

        $result = $reflection->invoke($service, $nestedData);
        $this->assertEquals('Test Region', $result['region']);
        $this->assertEquals('Test Province', $result['province']);
        $this->assertEquals('Test City', $result['city_municipality']);
        $this->assertEquals('Test Barangay', $result['barangay']);
        $this->assertEquals('456 Oak Ave, Test Barangay', $result['full_address']);
    }
}
