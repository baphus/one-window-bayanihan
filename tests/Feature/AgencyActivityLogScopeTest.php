<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agency Focal activity log is scoped to their own agency's referrals and the
 * cases those referrals belong to — never another agency's activity.
 */
class AgencyActivityLogScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    private function log(string $module, string $entityId, string $description): void
    {
        AuditLog::create([
            'action' => 'UPDATE',
            'module' => $module,
            'entity_id' => $entityId,
            'description' => $description,
            // Explicit so the row isn't auto-stamped 'system' (null actor +
            // console context in tests), which the default view hides.
            'category' => 'data',
            'timestamp' => now(),
        ]);
    }

    public function test_agency_sees_only_its_referrals_and_their_cases(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $focalA = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agencyA->id]);

        $caseA = CaseFile::factory()->create();
        $referralA = Referral::factory()->create(['case_id' => $caseA->id, 'agcy_id' => $agencyA->id]);

        $caseB = CaseFile::factory()->create();
        $referralB = Referral::factory()->create(['case_id' => $caseB->id, 'agcy_id' => $agencyB->id]);

        // Controlled log set (drop the observer-generated create rows first).
        AuditLog::truncate();
        $this->log('referral', $referralA->id, 'AGENCY A REFERRAL');
        $this->log('case', $caseA->id, 'AGENCY A PARENT CASE');
        $this->log('referral', $referralB->id, 'AGENCY B REFERRAL');
        $this->log('case', $caseB->id, 'OTHER CASE');

        $data = $this->actingAs($focalA)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs')
            ->json('props.logs.data');

        $descriptions = collect($data)->pluck('message')->all();

        $this->assertContains('AGENCY A REFERRAL', $descriptions);
        $this->assertContains('AGENCY A PARENT CASE', $descriptions);
        $this->assertNotContains('AGENCY B REFERRAL', $descriptions);
        $this->assertNotContains('OTHER CASE', $descriptions);
        $this->assertCount(2, $data);
    }

    public function test_agency_without_an_agency_sees_nothing(): void
    {
        $orphan = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => null]);

        $case = CaseFile::factory()->create();
        AuditLog::truncate();
        $this->log('case', $case->id, 'SOME CASE');

        $data = $this->actingAs($orphan)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs')
            ->json('props.logs.data');

        $this->assertSame([], $data);
    }

    public function test_view_title_is_activity_log_for_agency_and_audit_logs_for_admin(): void
    {
        $agency = Agency::factory()->create();
        $focal = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $agencyTitle = $this->actingAs($focal)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs')
            ->json('props.viewTitle');

        $adminTitle = $this->actingAs($admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/audit-logs')
            ->json('props.viewTitle');

        $this->assertSame('Activity Log', $agencyTitle);
        $this->assertSame('Audit Logs', $adminTitle);
    }
}
