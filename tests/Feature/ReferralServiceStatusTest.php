<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Service;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReferralServiceStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_transitions_assigned_service_through_processing_to_completed(): void
    {
        [$referral, $service, $agencyUser] = $this->serviceFixture();
        $referral->services()->attach($service->id);
        $logic = app(ReferralService::class);

        $logic->updateServiceStatus($referral, $service->id, 'PROCESSING', $agencyUser->id);
        $pivot = $referral->services()->first()->pivot;
        $this->assertSame('PROCESSING', $pivot->status);
        $this->assertNotNull($pivot->started_at);
        $this->assertNull($pivot->completed_at);
        $this->assertSame($agencyUser->id, $pivot->updated_by);

        $logic->updateServiceStatus($referral, $service->id, 'COMPLETED', $agencyUser->id);
        $pivot = $referral->fresh()->services()->first()->pivot;
        $this->assertSame('COMPLETED', $pivot->status);
        $this->assertNotNull($pivot->started_at);
        $this->assertNotNull($pivot->completed_at);
        $this->assertDatabaseHas('milestones', ['refr_id' => $referral->id, 'title' => 'Service processing: '.$service->name]);
        $this->assertDatabaseHas('milestones', ['refr_id' => $referral->id, 'title' => 'Service completed: '.$service->name]);
    }

    public function test_http_status_route_enforces_role_and_updates_service(): void
    {
        [$referral, $service, $agencyUser] = $this->serviceFixture();
        $referral->services()->attach($service->id);

        $this->actingAsWithMfa($agencyUser)->patch(route('referrals.services.status', [$referral, $service]), ['status' => 'PROCESSING'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('referral_services', ['referral_id' => $referral->id, 'service_id' => $service->id, 'status' => 'PROCESSING']);

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAsWithMfa($admin)->patch(route('referrals.services.status', [$referral, $service]), ['status' => 'COMPLETED'])
            ->assertForbidden();
    }

    public function test_duplicate_status_does_not_create_duplicate_milestone_or_audit(): void
    {
        [$referral, $service, $agencyUser] = $this->serviceFixture();
        $referral->services()->attach($service->id);
        $logic = app(ReferralService::class);
        $logic->updateServiceStatus($referral, $service->id, 'PROCESSING', $agencyUser->id);
        $milestones = $referral->milestones()->count();
        $audits = AuditLog::where('entity_id', $referral->id)->count();

        $logic->updateServiceStatus($referral, $service->id, 'PROCESSING', $agencyUser->id);
        $this->assertSame($milestones, $referral->milestones()->count());
        $this->assertSame($audits, AuditLog::where('entity_id', $referral->id)->count());
    }

    public function test_parent_can_complete_after_all_services_are_completed_and_zero_service_behavior_remains(): void
    {
        [$referral, $service, $agencyUser] = $this->serviceFixture();
        $logic = app(ReferralService::class);
        $logic->updateStatus($referral->id, 'COMPLETED', null, null, $agencyUser->id);
        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'status' => 'COMPLETED']);

        [$withService, $assigned, $actor] = $this->serviceFixture();
        $withService->services()->attach($assigned->id);
        $logic->updateServiceStatus($withService, $assigned->id, 'PROCESSING', $actor->id);
        $logic->updateServiceStatus($withService, $assigned->id, 'COMPLETED', $actor->id);
        $logic->updateStatus($withService->id, 'COMPLETED', null, null, $actor->id);
        $this->assertDatabaseHas('referrals', ['id' => $withService->id, 'status' => 'COMPLETED']);
    }

    public function test_completed_service_cannot_be_detached(): void
    {
        [$referral, $service, $agencyUser] = $this->serviceFixture();
        $referral->services()->attach($service->id, ['status' => 'COMPLETED']);

        $this->expectException(\InvalidArgumentException::class);
        app(ReferralService::class)->removeService($referral, $service->id);
    }

    public function test_migration_backfills_completed_parent_without_inventing_milestones(): void
    {
        [$referral, $service] = $this->serviceFixture();
        $referral->services()->attach($service->id);
        $referral->forceFill(['status' => 'COMPLETED'])->saveQuietly();

        $migration = require database_path('migrations/2026_11_28_000001_add_status_to_referral_services.php');
        $migration->down();
        $migration->up();

        $pivot = (array) DB::table('referral_services')->where('referral_id', $referral->id)->first();
        $this->assertSame('COMPLETED', $pivot['status']);
        $this->assertNull($pivot['started_at']);
        $this->assertNull($pivot['completed_at']);
        $this->assertNull($pivot['updated_by']);
        $this->assertSame(0, $referral->milestones()->count());
    }

    public function test_service_transition_rejects_skips_and_wrong_referral_state_and_completion_waits_for_assignments(): void
    {
        [$referral, $service, $agencyUser] = $this->serviceFixture();
        $referral->services()->attach($service->id);
        $logic = app(ReferralService::class);

        try {
            $logic->updateServiceStatus($referral, $service->id, 'COMPLETED', $agencyUser->id);
            $this->fail('Skipped transition accepted.');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseHas('referral_services', ['referral_id' => $referral->id, 'status' => 'ASSIGNED']);
        }

        $this->expectException(\InvalidArgumentException::class);
        $logic->updateStatus($referral->id, 'COMPLETED', null, null, $agencyUser->id);
    }

    public function test_timeline_projects_service_metadata_from_event_without_actor_id(): void
    {
        [$referral, $service, $agencyUser] = $this->serviceFixture();
        $referral->services()->attach($service->id);
        $logic = app(ReferralService::class);
        $logic->updateServiceStatus($referral, $service->id, 'PROCESSING', $agencyUser->id);
        $timeline = $logic->getReferralTimeline($referral->fresh(['milestones.user']));
        $event = collect($timeline)->firstWhere('service_id', $service->id);

        $this->assertSame('PROCESSING', $event['service_status']);
        $this->assertSame($service->name, $event['service_name']);
        $this->assertArrayNotHasKey('user_id', $event);
    }

    public function test_service_actor_must_own_receiving_agency(): void
    {
        [$referral, $service] = $this->serviceFixture();
        $referral->services()->attach($service->id);
        $otherAgency = Agency::factory()->create();
        $otherUser = User::factory()->create(['agcy_id' => $otherAgency->id, 'role' => UserRole::AGENCY->value, 'is_active' => true]);

        $this->expectException(HttpException::class);
        app(ReferralService::class)->updateServiceStatus($referral, $service->id, 'PROCESSING', $otherUser->id);
    }

    private function serviceFixture(): array
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->withIntake()->create(['user_id' => $manager->id, 'status' => 'OPEN']);
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['agcy_id' => $agency->id, 'role' => UserRole::AGENCY->value, 'is_active' => true]);
        $referral = Referral::create(['required_services' => '', 'status' => 'PROCESSING', 'case_id' => $case->id, 'agcy_id' => $agency->id]);
        $service = Service::create(['name' => 'Legal Assistance', 'agcy_id' => $agency->id]);

        return [$referral, $service, $agencyUser];
    }
}
