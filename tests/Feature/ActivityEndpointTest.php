<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\SetPostgresSession;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(SetPostgresSession::class);
    }

    private function log(string $module, string $action, string $entityId, string $description): AuditLog
    {
        return AuditLog::create([
            'module' => $module,
            'action' => $action,
            'entity_id' => $entityId,
            'description' => $description,
            'timestamp' => now(),
        ]);
    }

    private function rows(string $uri, User $user): array
    {
        return $this->actingAs($user)->get($uri)->assertOk()->json('data');
    }

    public function test_agency_can_read_activity_for_case_with_active_referral(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->processing()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);
        $this->log('referral', 'UPDATE', $referral->id, 'ACTIVE REFERRAL EVENT');

        $rows = $this->rows(route('api.cases.activity', $case->id), $user);

        $this->assertNotEmpty($rows);
        $this->assertSame(
            [],
            collect($rows)->pluck('module')->diff([
                'referral',
                'milestone',
                'referral_attachment',
                'referral_service_requirement',
                'referral_client_request',
                'referral_client_request_item',
                'referral_client_message',
                'referral_client_message_attachment',
                'referral_client_access_link',
                'referral_comment',
            ])->all(),
        );
    }

    public function test_foreign_agency_cannot_read_case_activity(): void
    {
        $ownerAgency = Agency::factory()->create();
        $foreignAgency = Agency::factory()->create();
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $foreignAgency->id]);
        $case = CaseFile::factory()->create();
        Referral::factory()->processing()->create(['case_id' => $case->id, 'agcy_id' => $ownerAgency->id]);

        $this->actingAs($user)->get(route('api.cases.activity', $case->id))->assertForbidden();
    }

    public function test_only_owning_agency_can_read_referral_activity(): void
    {
        $ownerAgency = Agency::factory()->create();
        $foreignAgency = Agency::factory()->create();
        $owner = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $ownerAgency->id]);
        $foreign = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $foreignAgency->id]);
        $referral = Referral::factory()->create(['agcy_id' => $ownerAgency->id]);

        $this->actingAs($owner)->get(route('api.referrals.activity', $referral->id))->assertOk();
        $this->actingAs($foreign)->get(route('api.referrals.activity', $referral->id))->assertForbidden();
    }

    public function test_activity_response_has_no_raw_audit_payload_keys(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $referral = Referral::factory()->create(['agcy_id' => $agency->id]);
        $this->log('referral', 'UPDATE', $referral->id, 'SAFE PRESENTED EVENT');

        foreach ([route('api.cases.activity', $referral->case_id), route('api.referrals.activity', $referral->id)] as $uri) {
            foreach ($this->rows($uri, $user) as $row) {
                foreach (['changes', 'old_value', 'new_value', 'email'] as $forbiddenKey) {
                    $this->assertArrayNotHasKey($forbiddenKey, $row);
                }
            }
        }
    }

    public function test_milestone_event_appears_in_referral_activity(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id]);
        $referral = Referral::factory()->create(['agcy_id' => $agency->id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);
        $this->log('milestone', 'CREATE', $milestone->id, 'MILESTONE ACTIVITY EVENT');

        $rows = $this->rows(route('api.referrals.activity', $referral->id), $user);

        $this->assertContains('MILESTONE ACTIVITY EVENT', collect($rows)->pluck('description')->all());
    }
}
