<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralServiceRequirement;
use App\Models\Service;
use App\Models\ServiceRequirement;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferralServiceAddMilestoneTest extends TestCase
{
    use RefreshDatabase;

    private User $caseManager;

    private CaseFile $case;

    private Agency $agency;

    private User $agencyUser;

    private Service $service;

    private ReferralService $referralService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $client = Client::factory()->create(['email' => 'ofw@example.com']);
        $this->case = CaseFile::factory()->create([
            'user_id' => $this->caseManager->id,
            'client_id' => $client->id,
            'status' => 'OPEN',
        ]);

        $this->agency = Agency::factory()->create();
        $this->agencyUser = User::factory()->create([
            'agcy_id' => $this->agency->id,
            'role' => UserRole::AGENCY->value,
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'name' => 'Legal Assistance',
            'agcy_id' => $this->agency->id,
        ]);

        $this->referralService = app(ReferralService::class);
    }

    private function makeReferral(): Referral
    {
        return Referral::create([
            'id' => fake()->uuid(),
            'required_services' => '',
            'status' => 'PENDING',
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
        ]);
    }

    public function test_stale_referral_cannot_be_deleted_after_completion(): void
    {
        $referral = $this->makeReferral();
        Referral::whereKey($referral->id)->update(['status' => 'COMPLETED']);

        try {
            $this->referralService->deleteReferral($referral);
            $this->fail('Expected completed referral deletion to fail.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('A completed referral cannot be deleted.', $exception->getMessage());
        }

        $this->assertNotSoftDeleted($referral);
    }

    public function test_cascade_failure_rolls_back_all_referral_deletions(): void
    {
        $referral = $this->makeReferral();
        $first = Milestone::create(['refr_id' => $referral->id, 'user_id' => $this->agencyUser->id, 'title' => 'First']);
        $second = Milestone::create(['refr_id' => $referral->id, 'user_id' => $this->agencyUser->id, 'title' => 'Second']);
        $dispatcher = Milestone::getEventDispatcher();
        Milestone::setEventDispatcher(clone $dispatcher);
        $deleted = 0;
        Milestone::deleted(function () use (&$deleted) {
            if (++$deleted === 2) {
                throw new \RuntimeException('Cascade failed');
            }
        });

        try {
            $this->referralService->deleteReferral($referral);
            $this->fail('Expected cascade failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cascade failed', $exception->getMessage());
        } finally {
            Milestone::setEventDispatcher($dispatcher);
        }

        $this->assertNotSoftDeleted($referral);
        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    }

    public function test_milestone_reads_referral_under_row_lock(): void
    {
        $referral = $this->makeReferral();
        $locked = false;
        DB::listen(function ($query) use (&$locked) {
            if (str_contains($query->sql, '"referrals"') && str_contains($query->sql, 'for update')) {
                $locked = true;
            }
        });

        $this->referralService->addMilestone($referral->id, 'Update', null, $this->agencyUser->id);

        $this->assertTrue($locked);
        $this->assertDatabaseHas('milestones', ['refr_id' => $referral->id, 'title' => 'Update']);
    }

    public function test_requirement_description_can_be_cleared_without_changing_omitted_fields(): void
    {
        $referral = $this->makeReferral();
        $requirement = ReferralServiceRequirement::create([
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
            'name' => 'ID',
            'description' => 'Old description',
            'is_required' => true,
        ]);

        $this->referralService->updateRequirement($referral, $this->service->id, $requirement->id, ['description' => null, 'is_required' => false]);

        $this->assertDatabaseHas('referral_service_requirements', [
            'id' => $requirement->id, 'name' => 'ID', 'description' => null, 'is_required' => false,
        ]);
    }

    public function test_overdue_uses_newest_live_activity_and_ignores_deleted_milestones(): void
    {
        $recentStatus = $this->makeReferral();
        $recentStatus->forceFill(['created_at' => now()->subDays(30), 'updated_at' => now()->subDay()])->saveQuietly();
        $oldMilestone = Milestone::create(['refr_id' => $recentStatus->id, 'user_id' => $this->agencyUser->id, 'title' => 'Old']);
        $oldMilestone->forceFill(['created_at' => now()->subDays(20)])->saveQuietly();
        $oldStatus = $this->makeReferral();
        $oldStatus->forceFill(['created_at' => now()->subDays(30), 'updated_at' => now()->subDays(20)])->saveQuietly();
        $deleted = Milestone::create(['refr_id' => $oldStatus->id, 'user_id' => $this->agencyUser->id, 'title' => 'Deleted']);
        $deleted->delete();

        $ids = $this->referralService->overdueReferralsQuery(UserRole::ADMIN->value, null, null)->pluck('id')->all();

        $this->assertNotContains($recentStatus->id, $ids);
        $this->assertContains($oldStatus->id, $ids);
    }

    public function test_search_matches_partial_uuid_with_joined_sort(): void
    {
        $referral = $this->makeReferral();
        $this->makeReferral();

        $results = $this->referralService->getReferrals([
            'search' => strtoupper(substr($referral->id, 0, 18)),
            'sort' => 'case_number',
        ]);

        $this->assertSame([$referral->id], $results->pluck('id')->all());
        $this->assertSame(0, $this->referralService->getReferrals(['search' => "' OR 1=1 --"])->total());
    }

    public function test_stale_referral_cannot_add_service_after_completion(): void
    {
        $referral = $this->makeReferral();
        ServiceRequirement::create(['service_id' => $this->service->id, 'name' => 'Valid ID', 'is_required' => true]);
        Referral::whereKey($referral->id)->update(['status' => 'COMPLETED']);

        try {
            $this->referralService->addService($referral, $this->service->id, $this->agencyUser->id);
            $this->fail('Expected completed referral to reject service addition.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Cannot modify services on a completed referral.', $e->getMessage());
        }

        $this->assertDatabaseMissing('referral_services', ['referral_id' => $referral->id]);
        $this->assertDatabaseMissing('referral_service_requirements', ['referral_id' => $referral->id]);
        $this->assertDatabaseMissing('milestones', ['refr_id' => $referral->id]);
        $this->assertSame('', $referral->fresh()->required_services);
    }

    public function test_stale_referral_cannot_remove_service_after_completion(): void
    {
        $referral = $this->makeReferral();
        $referral->services()->attach($this->service->id);
        $referral->update(['required_services' => $this->service->name]);
        $requirement = ReferralServiceRequirement::create([
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
            'name' => 'Valid ID',
        ]);
        Referral::whereKey($referral->id)->update(['status' => 'COMPLETED']);

        try {
            $this->referralService->removeService($referral, $this->service->id);
            $this->fail('Expected completed referral to reject service removal.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Cannot modify services on a completed referral.', $e->getMessage());
        }

        $this->assertDatabaseHas('referral_services', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);
        $this->assertNotSoftDeleted($requirement);
        $this->assertSame('Legal Assistance', $referral->fresh()->required_services);
    }

    public function test_assigning_service_creates_milestone_with_expected_attributes(): void
    {
        $referral = $this->makeReferral();

        $this->referralService->addService($referral, $this->service->id, $this->agencyUser->id);

        $this->assertDatabaseHas('milestones', [
            'refr_id' => $referral->id,
            'title' => 'Service assigned: Legal Assistance',
            'user_id' => $this->agencyUser->id,
        ]);
    }

    public function test_assigning_service_attaches_service_and_surfaces_milestone_on_timeline(): void
    {
        $referral = $this->makeReferral();

        $result = $this->referralService->addService($referral, $this->service->id, $this->agencyUser->id);

        // Service ends up attached
        $this->assertTrue($result->services->contains('id', $this->service->id));
        $this->assertDatabaseHas('referral_services', [
            'referral_id' => $referral->id,
            'service_id' => $this->service->id,
        ]);

        // Timeline surfaces the milestone entry
        $timeline = $this->referralService->getReferralTimeline($referral->refresh());

        $milestoneEntries = array_values(array_filter(
            $timeline,
            fn (array $entry) => ($entry['type'] ?? null) === 'milestone'
                && ($entry['title'] ?? null) === 'Service assigned: Legal Assistance'
        ));

        $this->assertNotEmpty($milestoneEntries, 'Expected a milestone timeline entry for the service assignment.');
        $this->assertSame($this->agencyUser->name, $milestoneEntries[0]['actor']);

        // Milestone row exists for cross-check
        $this->assertSame(1, Milestone::where('refr_id', $referral->id)->count());
    }

    public function test_reassigning_same_service_does_not_create_second_milestone(): void
    {
        $referral = $this->makeReferral();

        $this->referralService->addService($referral, $this->service->id, $this->agencyUser->id);
        $this->referralService->addService($referral, $this->service->id, $this->agencyUser->id);

        $this->assertSame(1, Milestone::where('refr_id', $referral->id)->count());
        $this->assertDatabaseHas('milestones', [
            'refr_id' => $referral->id,
            'title' => 'Service assigned: Legal Assistance',
            'user_id' => $this->agencyUser->id,
        ]);
    }
}
