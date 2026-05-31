<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\NextOfKin;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $client = Client::factory()->create(['case_id' => $case->id]);

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
        $client = Client::factory()->create(['case_id' => $case->id]);
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
        Client::factory()->create(['case_id' => $case->id]);

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
        $case = CaseFile::factory()->create([
            'status' => 'DRAFT',
            'user_id' => $user->id,
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
}
