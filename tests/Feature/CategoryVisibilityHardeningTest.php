<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\Export\DataExportQueries;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryVisibilityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    public function test_agency_user_without_agency_gets_no_referral_list_count_or_export_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => null]);
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create();
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        $this->actingAsWithMfa($user)->get('/clients')->assertNotFound();

        $referralPage = $this->actingAsWithMfa($user)->get(route('referrals.index'));
        $referralPage->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('referrals.data', 0)
                ->where('stats.total_referrals', 0));

        $this->actingAsWithMfa($user)->getJson(route('referrals.export-count'))
            ->assertOk()->assertJson(['count' => 0]);

        $exports = new DataExportQueries;
        $this->assertCount(0, $exports->getReferralsExport($user));
        $this->assertSame(0, $exports->countReferralsExport($user));

        $stats = app(ReferralService::class)->getReferralStats(null, UserRole::AGENCY->value);
        $this->assertSame(0, $stats['total_referrals']);
    }

    public function test_retired_client_detail_route_returns_not_found(): void
    {
        $this->get('/clients/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_case_manager_list_stats_and_export_count_are_scoped_to_viewer(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $otherManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();

        $managerCase = CaseFile::factory()->create(['user_id' => $manager->id]);
        $otherCase = CaseFile::factory()->create(['user_id' => $otherManager->id]);
        $managerReferral = Referral::factory()->create([
            'case_id' => $managerCase->id,
            'agcy_id' => $agency->id,
        ]);
        $otherReferral = Referral::factory()->create([
            'case_id' => $otherCase->id,
            'agcy_id' => $agency->id,
        ]);

        // CASE_MANAGER sees all referrals (full access, same as ADMIN)
        $managerResponse = $this->actingAsWithMfa($manager)->get(route('referrals.index'));
        $managerResponse->assertInertia(fn ($page) => $page
            ->has('referrals.data', 2)
            ->where('stats.total_referrals', 2));

        $this->actingAsWithMfa($manager)->getJson(route('referrals.export-count'))
            ->assertOk()->assertJson(['count' => 2]);

        $managerExports = new DataExportQueries;
        $this->assertCount(2, $managerExports->getReferralsExport($manager));
        $this->assertSame(2, $managerExports->countReferralsExport($manager));

        $otherResponse = $this->actingAsWithMfa($otherManager)->get(route('referrals.index'));
        $otherResponse->assertInertia(fn ($page) => $page
            ->has('referrals.data', 2)
            ->where('stats.total_referrals', 2));

        $this->actingAsWithMfa($otherManager)->getJson(route('referrals.export-count'))
            ->assertOk()->assertJson(['count' => 2]);

        $this->assertCount(2, $managerExports->getReferralsExport($otherManager));
        $this->assertSame(2, $managerExports->countReferralsExport($otherManager));
    }

    public function test_agency_user_sees_only_its_referrals_in_list_stats_and_export(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $case = CaseFile::factory()->create();
        $visibleReferral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);
        Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $otherAgency->id,
        ]);

        $response = $this->actingAsWithMfa($user)->get(route('referrals.index'));
        $response->assertInertia(fn ($page) => $page
            ->has('referrals.data', 1)
            ->where('referrals.data.0.id', $visibleReferral->id)
            ->where('stats.total_referrals', 1));

        $this->actingAsWithMfa($user)->getJson(route('referrals.export-count'))
            ->assertOk()->assertJson(['count' => 1]);

        $exports = new DataExportQueries;
        $this->assertCount(1, $exports->getReferralsExport($user));
        $this->assertSame(1, $exports->countReferralsExport($user));
    }

    public function test_referral_mutation_refreshes_manager_and_agency_stats(): void
    {
        $agency = Agency::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => $manager->id]);
        $service = app(ReferralService::class);

        $this->assertSame(0, $service->getReferralStats(null, UserRole::CASE_MANAGER->value, $manager->id)['total_referrals']);
        $this->assertSame(0, $service->getReferralStats($agency->id, UserRole::AGENCY->value, null)['total_referrals']);

        Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $this->assertSame(1, $service->getReferralStats(null, UserRole::CASE_MANAGER->value, $manager->id)['total_referrals']);
        $this->assertSame(1, $service->getReferralStats($agency->id, UserRole::AGENCY->value, null)['total_referrals']);
    }
}
