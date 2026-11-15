<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\ActivityEvent;
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

    /**
     * The activity page renders audit sentences, not stored descriptions, so
     * scope is asserted on the mirrored event id rather than on copy.
     */
    private function mirroredEventId(AuditLog $log): string
    {
        return (string) ActivityEvent::query()->where('audit_log_id', $log->getKey())->value('id');
    }

    private function visibleEventIds(User $user): array
    {
        return collect($this->actingAsWithMfa($user)
            ->withHeader('X-Inertia', 'true')
            ->get('/activity')
            ->assertOk()
            ->json('props.events.data'))
            ->pluck('id')
            ->all();
    }

    public function test_case_manager_sees_all_case_and_referral_events(): void
    {
        // Case managers have system-wide awareness: they see activity across all
        // cases and referrals regardless of who owns them, so they can track who
        // referred what case to which agency, approvals by other managers, etc.
        $managerA = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $managerB = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $caseA = CaseFile::factory()->create(['user_id' => $managerA->id]);
        $caseB = CaseFile::factory()->create(['user_id' => $managerB->id]);
        $referralA = Referral::factory()->create(['case_id' => $caseA->id, 'agcy_id' => $agencyA->id]);
        $referralB = Referral::factory()->create(['case_id' => $caseB->id, 'agcy_id' => $agencyB->id]);

        $caseALog = $this->log('case', 'CLOSE', $caseA->id, 'CM-A CASE', $managerA->id);
        $referralALog = $this->log('referral', 'UPDATE', $referralA->id, 'CM-A REFERRAL', $managerA->id);
        $caseBLog = $this->log('case', 'CLOSE', $caseB->id, 'CM-B CASE', $managerB->id);
        $referralBLog = $this->log('referral', 'UPDATE', $referralB->id, 'CM-B REFERRAL', $managerB->id);

        $ids = $this->visibleEventIds($managerA);

        // Manager A sees their own activity.
        $this->assertContains($this->mirroredEventId($caseALog), $ids);
        $this->assertContains($this->mirroredEventId($referralALog), $ids);
        // Manager A also sees Manager B's activity — cross-manager visibility is intentional.
        $this->assertContains($this->mirroredEventId($caseBLog), $ids);
        $this->assertContains($this->mirroredEventId($referralBLog), $ids);
    }

    public function test_case_manager_sees_unowned_case_events(): void
    {
        // Cases with no assigned manager (e.g. OFW self-filed, not yet reviewed)
        // are still visible to all case managers so the queue can be monitored.
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => null]);
        $log = $this->log('case', 'CLOSE', $case->id, 'UNOWNED CASE');

        $this->assertContains($this->mirroredEventId($log), $this->visibleEventIds($manager));
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

        $ownLog = $this->log('referral', 'UPDATE', $referralX->id, 'AGENCY-X REFERRAL');
        $otherAgencyLog = $this->log('referral', 'UPDATE', $referralY->id, 'AGENCY-Y REFERRAL');
        $parentCaseLog = $this->log('case', 'CLOSE', $caseX->id, 'PARENT CASE EVENT');
        $clientLog = $this->log('client', 'UPDATE', $caseX->id, 'CLIENT EVENT');
        $documentLog = $this->log('case_document', 'DOWNLOAD', $caseX->id, 'DOCUMENT EVENT');

        $ids = $this->visibleEventIds($agencyUser);

        $this->assertContains($this->mirroredEventId($ownLog), $ids);
        $this->assertNotContains($this->mirroredEventId($otherAgencyLog), $ids);
        $this->assertNotContains($this->mirroredEventId($parentCaseLog), $ids);
        $this->assertNotContains($this->mirroredEventId($clientLog), $ids);
        $this->assertNotContains($this->mirroredEventId($documentLog), $ids);
    }

    public function test_admin_feed_contains_both_agencies_visible_events(): void
    {
        $agencyX = Agency::factory()->create();
        $agencyY = Agency::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $referralX = Referral::factory()->create(['agcy_id' => $agencyX->id]);
        $referralY = Referral::factory()->create(['agcy_id' => $agencyY->id]);

        $logX = $this->log('referral', 'UPDATE', $referralX->id, 'ADMIN AGENCY-X EVENT');
        $logY = $this->log('referral', 'UPDATE', $referralY->id, 'ADMIN AGENCY-Y EVENT');

        $ids = $this->visibleEventIds($admin);

        $this->assertContains($this->mirroredEventId($logX), $ids);
        $this->assertContains($this->mirroredEventId($logY), $ids);
    }
}
