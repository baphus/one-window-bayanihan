<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    use RefreshDatabase;

    private function agency(): Agency
    {
        return Agency::create(['id' => fake()->uuid(), 'name' => 'Agency', 'short' => 'AG', 'slug' => fake()->unique()->slug()]);
    }

    private function caseFile(): CaseFile
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        return CaseFile::create(['id' => fake()->uuid(), 'case_number' => 'TEST-'.fake()->unique()->numberBetween(1000, 9999), 'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')), 'status' => 'OPEN', 'user_id' => $user->id]);
    }

    private function referral(CaseFile $case, Agency $agency): Referral
    {
        return Referral::create(['id' => fake()->uuid(), 'required_services' => 'Support', 'status' => 'PENDING', 'case_id' => $case->id, 'agcy_id' => $agency->id]);
    }

    #[Test]
    public function case_leases_require_staff_and_access_and_refresh_deduplicated_viewers(): void
    {
        Cache::flush();
        $agency = $this->agency();
        $case = $this->caseFile();
        $this->referral($case, $agency);
        $staff = User::factory()->create(['name' => 'Zed', 'role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id, 'onboarding_completed_at' => now(), 'profile_completed_at' => now(), 'mfa_enabled_at' => now()]);
        $this->actingAsWithMfa($staff)->postJson("/cases/{$case->id}/presence")->assertOk()->assertJsonPath('viewers.0.name', 'Zed')->assertJsonPath('expires_in', 60)->assertHeader('Cache-Control', 'no-store, private');
        $this->travel(50)->seconds();
        $this->actingAsWithMfa($staff)->postJson("/cases/{$case->id}/presence")->assertJsonCount(1, 'viewers');
        $this->travel(61)->seconds();
        $other = User::factory()->create(['name' => 'Amy', 'role' => UserRole::CASE_MANAGER->value]);
        $this->actingAsWithMfa($other)->postJson("/cases/{$case->id}/presence")->assertJsonPath('viewers.0.name', 'Amy');
    }

    #[Test]
    public function agency_case_presence_requires_active_own_referral(): void
    {
        $agency = $this->agency();
        $case = $this->caseFile();
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $this->actingAsWithMfa($user)->postJson("/cases/{$case->id}/presence")->assertForbidden();

        $completed = $this->referral($case, $agency);
        $completed->update(['status' => 'COMPLETED']);
        $this->actingAsWithMfa($user)->postJson("/cases/{$case->id}/presence")->assertForbidden();

        $activeCase = $this->caseFile();
        $this->referral($activeCase, $agency);
        $this->actingAsWithMfa($user)->postJson("/cases/{$activeCase->id}/presence")->assertOk();
    }

    #[Test]
    public function client_and_next_of_kin_cannot_join_either_presence_roster(): void
    {
        $agency = $this->agency();
        $case = $this->caseFile();
        $referral = $this->referral($case, $agency);
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'agcy_id' => $agency->id]);
        $this->actingAsWithMfa($ofw)->postJson("/cases/{$case->id}/presence")->assertForbidden();
        $this->actingAsWithMfa($ofw)->postJson("/referrals/{$referral->id}/presence")->assertForbidden();

        $nokClient = Client::factory()->create(['type' => 'NEXT_OF_KIN']);
        $nok = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id, 'client_id' => $nokClient->id]);
        $this->actingAsWithMfa($nok)->postJson("/cases/{$case->id}/presence")->assertForbidden();
        $this->actingAsWithMfa($nok)->postJson("/referrals/{$referral->id}/presence")->assertForbidden();
    }

    #[Test]
    public function agency_referral_presence_requires_matching_agency(): void
    {
        $agency = $this->agency();
        $referral = $this->referral($this->caseFile(), $agency);
        $same = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $this->actingAsWithMfa($same)->postJson("/referrals/{$referral->id}/presence")->assertOk();
        $other = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $this->agency()->id]);
        $this->actingAsWithMfa($other)->postJson("/referrals/{$referral->id}/presence")->assertForbidden();
    }

    #[Test]
    public function stale_cached_viewers_with_changed_role_or_agency_are_filtered(): void
    {
        Cache::flush();
        $agency = $this->agency();
        $referral = $this->referral($this->caseFile(), $agency);
        $viewer = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $this->actingAsWithMfa($viewer)->postJson("/referrals/{$referral->id}/presence")->assertOk();
        $viewer->update(['agcy_id' => $this->agency()->id]);
        $next = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $response = $this->actingAsWithMfa($next)->postJson("/referrals/{$referral->id}/presence")->assertOk();
        $response->assertJsonCount(1, 'viewers')->assertJsonPath('viewers.0.id', $next->id);
        $next->update(['role' => UserRole::OFW->value]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value, 'agcy_id' => $agency->id]);
        $this->actingAsWithMfa($admin)->postJson("/referrals/{$referral->id}/presence")->assertJsonCount(1, 'viewers')->assertJsonPath('viewers.0.id', $admin->id);
    }

    #[Test]
    public function missing_presence_resources_return_not_found(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->actingAsWithMfa($user)->postJson('/cases/'.fake()->uuid().'/presence')->assertNotFound();
        $this->actingAsWithMfa($user)->postJson('/referrals/'.fake()->uuid().'/presence')->assertNotFound();
    }

    #[Test]
    public function referral_presence_requires_exact_agency_for_all_staff_and_denies_clients(): void
    {
        $agency = $this->agency();
        $referral = $this->referral($this->caseFile(), $agency);
        foreach ([UserRole::ADMIN, UserRole::CASE_MANAGER] as $role) {
            $matching = User::factory()->create(['role' => $role->value, 'agcy_id' => $agency->id, 'onboarding_completed_at' => now(), 'profile_completed_at' => now(), 'mfa_enabled_at' => now()]);
            $this->actingAsWithMfa($matching)->postJson("/referrals/{$referral->id}/presence")->assertOk();
            $wrong = User::factory()->create(['role' => $role->value, 'agcy_id' => $this->agency()->id, 'onboarding_completed_at' => now(), 'profile_completed_at' => now(), 'mfa_enabled_at' => now()]);
            $this->actingAsWithMfa($wrong)->postJson("/referrals/{$referral->id}/presence")->assertForbidden();
        }
        $client = User::factory()->create(['role' => UserRole::OFW->value, 'agcy_id' => $agency->id]);
        $this->actingAsWithMfa($client)->postJson("/referrals/{$referral->id}/presence")->assertForbidden();
    }
}
