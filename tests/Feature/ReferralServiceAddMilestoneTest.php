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
