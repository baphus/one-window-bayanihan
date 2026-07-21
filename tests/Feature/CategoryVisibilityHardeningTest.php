<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Client;
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

    public function test_agency_user_without_agency_gets_no_client_or_referral_list_count_or_export_data(): void
    {
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]);
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        $clientPage = $this->actingAs($user)->get(route('clients.index'));
        $clientPage->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('clients.data', 0)
                ->where('stats.total_clients', 0)
                ->where('stats.total_referrals', 0)
                ->where('exportRowCount', 0));

        $referralPage = $this->actingAs($user)->get(route('referrals.index'));
        $referralPage->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('referrals.data', 0)
                ->where('stats.total_referrals', 0)
                ->where('exportRowCount', 0));

        $exports = new DataExportQueries;
        $this->assertCount(0, $exports->getClientsExport($user));
        $this->assertSame(0, $exports->countClientsExport($user));
        $this->assertCount(0, $exports->getReferralsExport($user));
        $this->assertSame(0, $exports->countReferralsExport($user));

        $stats = app(ReferralService::class)->getReferralStats(null, 'AGENCY');
        $this->assertSame(0, $stats['total_referrals']);
    }

    public function test_client_detail_uses_authorized_case_and_excludes_foreign_dependents(): void
    {
        $agency = Agency::factory()->create();
        $foreignAgency = Agency::factory()->create();
        $viewer = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $foreignManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();

        $visibleCase = CaseFile::factory()->create([
            'client_id' => $client->id,
            'created_at' => now()->subDay(),
        ]);
        $foreignNewestCase = CaseFile::factory()->create([
            'client_id' => $client->id,
            'user_id' => $foreignManager->id,
            'created_at' => now(),
        ]);

        $visibleReferral = Referral::factory()->create([
            'case_id' => $visibleCase->id,
            'agcy_id' => $agency->id,
        ]);
        $foreignReferral = Referral::factory()->create([
            'case_id' => $foreignNewestCase->id,
            'agcy_id' => $foreignAgency->id,
        ]);

        $this->audit($viewer, 'case', $visibleCase->id, 'Visible case audit');
        $this->audit($viewer, 'referral', $visibleReferral->id, 'Visible referral audit');
        $this->audit($viewer, 'case', $foreignNewestCase->id, 'Foreign case audit');
        $this->audit($viewer, 'referral', $foreignReferral->id, 'Foreign referral audit');

        $response = $this->actingAs($viewer)
            ->get(route('clients.show', $client));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('client.case_file.id', $visibleCase->id)
                ->where('client.case_file.referrals', function ($referrals) use ($visibleReferral, $foreignReferral) {
                    $ids = collect($referrals)->pluck('id')->all();

                    return $ids === [$visibleReferral->id]
                        && ! in_array($foreignReferral->id, $ids, true);
                })
                ->where('auditLogs', function ($logs) use ($foreignNewestCase, $foreignReferral) {
                    $serialized = json_encode($logs);

                    return ! str_contains($serialized, $foreignNewestCase->id)
                        && ! str_contains($serialized, $foreignReferral->id)
                        && str_contains($serialized, 'Visible case audit')
                        && str_contains($serialized, 'Visible referral audit');
                }));
    }

    public function test_case_manager_list_stats_and_export_count_are_scoped_to_viewer(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $otherManager = User::factory()->create(['role' => 'CASE_MANAGER']);
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
        $managerResponse = $this->actingAs($manager)->get(route('referrals.index'));
        $managerResponse->assertInertia(fn ($page) => $page
            ->has('referrals.data', 2)
            ->where('stats.total_referrals', 2)
            ->where('exportRowCount', 2));

        $managerExports = new DataExportQueries;
        $this->assertCount(2, $managerExports->getReferralsExport($manager));
        $this->assertSame(2, $managerExports->countReferralsExport($manager));

        $otherResponse = $this->actingAs($otherManager)->get(route('referrals.index'));
        $otherResponse->assertInertia(fn ($page) => $page
            ->has('referrals.data', 2)
            ->where('stats.total_referrals', 2)
            ->where('exportRowCount', 2));

        $this->assertCount(2, $managerExports->getReferralsExport($otherManager));
        $this->assertSame(2, $managerExports->countReferralsExport($otherManager));
    }

    public function test_agency_user_sees_only_its_referrals_in_list_stats_and_export(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $case = CaseFile::factory()->create();
        $visibleReferral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);
        Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $otherAgency->id,
        ]);

        $response = $this->actingAs($user)->get(route('referrals.index'));
        $response->assertInertia(fn ($page) => $page
            ->has('referrals.data', 1)
            ->where('referrals.data.0.id', $visibleReferral->id)
            ->where('stats.total_referrals', 1)
            ->where('exportRowCount', 1));

        $exports = new DataExportQueries;
        $this->assertCount(1, $exports->getReferralsExport($user));
        $this->assertSame(1, $exports->countReferralsExport($user));
    }

    public function test_referral_mutation_refreshes_manager_and_agency_stats(): void
    {
        $agency = Agency::factory()->create();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $manager->id]);
        $service = app(ReferralService::class);

        $this->assertSame(0, $service->getReferralStats(null, 'CASE_MANAGER', $manager->id)['total_referrals']);
        $this->assertSame(0, $service->getReferralStats($agency->id, 'AGENCY', null)['total_referrals']);

        Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $this->assertSame(1, $service->getReferralStats(null, 'CASE_MANAGER', $manager->id)['total_referrals']);
        $this->assertSame(1, $service->getReferralStats($agency->id, 'AGENCY', null)['total_referrals']);
    }

    private function audit(User $user, string $module, string $entityId, string $description): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'UPDATE',
            'module' => $module,
            'entity_id' => $entityId,
            'description' => $description,
            'timestamp' => now(),
        ]);
    }
}
