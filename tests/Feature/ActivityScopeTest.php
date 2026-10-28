<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([HandleInertiaRequests::class, SetPostgresSession::class]);
    }

    private function log(string $module, string $action, string $entityId, string $description, ?string $userId = null): AuditLog
    {
        return AuditLog::create([
            'module' => $module,
            'action' => $action,
            'entity_id' => $entityId,
            'description' => $description,
            'user_id' => $userId,
            'timestamp' => now(),
        ]);
    }

    private function activityDescriptions(User $user): array
    {
        return collect($this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->get('/activity')
            ->assertOk()
            ->json('props.events.data'))
            ->pluck('description')
            ->all();
    }

    public function test_case_manager_sees_only_owned_case_and_referral_events(): void
    {
        $managerA = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $managerB = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $caseA = CaseFile::factory()->create(['user_id' => $managerA->id]);
        $caseB = CaseFile::factory()->create(['user_id' => $managerB->id]);
        $referralA = Referral::factory()->create(['case_id' => $caseA->id, 'agcy_id' => $agencyA->id]);
        $referralB = Referral::factory()->create(['case_id' => $caseB->id, 'agcy_id' => $agencyB->id]);

        $this->log('case', 'CLOSE', $caseA->id, 'CM-A CASE', $managerA->id);
        $this->log('referral', 'UPDATE', $referralA->id, 'CM-A REFERRAL', $managerA->id);
        $this->log('case', 'CLOSE', $caseB->id, 'CM-B CASE', $managerB->id);
        $this->log('referral', 'UPDATE', $referralB->id, 'CM-B REFERRAL', $managerB->id);

        $descriptions = $this->activityDescriptions($managerA);

        $this->assertContains('CM-A CASE', $descriptions);
        $this->assertContains('CM-A REFERRAL', $descriptions);
        $this->assertNotContains('CM-B CASE', $descriptions);
        $this->assertNotContains('CM-B REFERRAL', $descriptions);
    }

    public function test_case_without_owner_is_invisible_to_case_manager(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => null]);
        $this->log('case', 'CLOSE', $case->id, 'UNOWNED CASE');

        $this->assertNotContains('UNOWNED CASE', $this->activityDescriptions($manager));
    }

    public function test_agency_sees_only_its_referral_events(): void
    {
        $agencyX = Agency::factory()->create();
        $agencyY = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agencyX->id,
        ]);
        $caseX = CaseFile::factory()->create();
        $caseY = CaseFile::factory()->create();
        $referralX = Referral::factory()->create(['case_id' => $caseX->id, 'agcy_id' => $agencyX->id]);
        $referralY = Referral::factory()->create(['case_id' => $caseY->id, 'agcy_id' => $agencyY->id]);

        $this->log('referral', 'UPDATE', $referralX->id, 'AGENCY-X REFERRAL');
        $this->log('referral', 'UPDATE', $referralY->id, 'AGENCY-Y REFERRAL');
        $this->log('case', 'CLOSE', $caseX->id, 'PARENT CASE EVENT');
        $this->log('client', 'UPDATE', $caseX->id, 'CLIENT EVENT');
        $this->log('case_document', 'DOWNLOAD', $caseX->id, 'DOCUMENT EVENT');

        $descriptions = $this->activityDescriptions($agencyUser);

        $this->assertContains('AGENCY-X REFERRAL', $descriptions);
        $this->assertNotContains('AGENCY-Y REFERRAL', $descriptions);
        $this->assertNotContains('PARENT CASE EVENT', $descriptions);
        $this->assertNotContains('CLIENT EVENT', $descriptions);
        $this->assertNotContains('DOCUMENT EVENT', $descriptions);
    }

    public function test_admin_feed_contains_both_agencies_visible_events(): void
    {
        $agencyX = Agency::factory()->create();
        $agencyY = Agency::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $referralX = Referral::factory()->create(['agcy_id' => $agencyX->id]);
        $referralY = Referral::factory()->create(['agcy_id' => $agencyY->id]);

        $this->log('referral', 'UPDATE', $referralX->id, 'ADMIN AGENCY-X EVENT');
        $this->log('referral', 'UPDATE', $referralY->id, 'ADMIN AGENCY-Y EVENT');

        $descriptions = $this->activityDescriptions($admin);

        $this->assertContains('ADMIN AGENCY-X EVENT', $descriptions);
        $this->assertContains('ADMIN AGENCY-Y EVENT', $descriptions);
    }
}
