<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Purge isolation: one case held by a RESTRICT foreign key must not take the
 * rest of the batch down with it.
 *
 * The blocking chain is cases -> referrals -> referral_client_requests ->
 * referral_client_access_links, where the last hop is ON DELETE RESTRICT.
 */
class PurgeTrashedCasesIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_happy_path_purges_every_eligible_case_and_reports_the_count(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $service = app(CaseService::class);

        $this->actingAs($user);

        $purgeable = $this->trashedCase($service, $user);
        Referral::factory()->create(['case_id' => $purgeable->id, 'status' => 'COMPLETED']);
        $recent = $this->trashedCase($service, $user, backdate: false);

        $purged = $service->purgeTrashedCases(90);

        $this->assertSame(1, $purged);
        $this->assertNull(CaseFile::withTrashed()->find($purgeable->id));
        $this->assertNotNull(CaseFile::withTrashed()->find($recent->id));

        $audit = AuditLog::where('action', AuditAction::PURGE->value)->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertEquals(1, $audit->old_value['purged_count']);
        $this->assertEquals(0, $audit->old_value['skipped_count']);
        $this->assertEmpty($audit->old_value['skipped_case_ids']);
    }

    public function test_restrict_blocked_case_is_skipped_and_the_rest_of_the_batch_still_purges(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $service = app(CaseService::class);

        $this->actingAs($user);

        $blocked = $this->trashedCase($service, $user);
        $purgeable = $this->trashedCase($service, $user);

        // referral_client_requests -> referrals is RESTRICT, and an access link
        // on that request blocks the cascade force-delete of the request itself.
        $referral = Referral::factory()->create(['case_id' => $blocked->id, 'status' => 'COMPLETED']);
        $request = ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'creator_user_id' => $user->id,
        ]);
        $accessLink = ReferralClientAccessLink::factory()->create([
            'request_id' => $request->id,
            'issued_by' => $user->id,
        ]);

        $purged = $service->purgeTrashedCases(90);

        // Only the clean case went away.
        $this->assertSame(1, $purged);
        $this->assertNull(CaseFile::withTrashed()->find($purgeable->id));

        // The blocked case and every row behind it survived intact.
        $survivor = CaseFile::withTrashed()->find($blocked->id);
        $this->assertNotNull($survivor);
        $this->assertTrue($survivor->trashed());
        $this->assertDatabaseHas('referrals', ['id' => $referral->id]);
        $this->assertDatabaseHas('referral_client_requests', ['id' => $request->id]);
        $this->assertDatabaseHas('referral_client_access_links', ['id' => $accessLink->id]);
    }

    public function test_report_counts_purged_and_skipped_separately(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $service = app(CaseService::class);

        $this->actingAs($user);

        $blocked = $this->trashedCase($service, $user);
        $purgeable = $this->trashedCase($service, $user);

        $referral = Referral::factory()->create(['case_id' => $blocked->id, 'status' => 'COMPLETED']);
        $request = ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'creator_user_id' => $user->id,
        ]);
        ReferralClientAccessLink::factory()->create([
            'request_id' => $request->id,
            'issued_by' => $user->id,
        ]);

        $service->purgeTrashedCases(90);

        $audit = AuditLog::where('action', AuditAction::PURGE->value)->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertEquals(1, $audit->old_value['purged_count']);
        $this->assertEquals(1, $audit->old_value['skipped_count']);
        $this->assertSame([$blocked->id], $audit->old_value['skipped_case_ids']);
        $this->assertStringContainsString('Auto-purged 1 case(s)', $audit->description);
        $this->assertStringContainsString('skipped 1 held by a RESTRICT foreign key', $audit->description);
    }

    public function test_command_still_reports_the_purged_count_when_a_case_is_skipped(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $service = app(CaseService::class);

        $this->actingAs($user);

        $blocked = $this->trashedCase($service, $user);
        $purgeable = $this->trashedCase($service, $user);

        $referral = Referral::factory()->create(['case_id' => $blocked->id, 'status' => 'COMPLETED']);
        $request = ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'creator_user_id' => $user->id,
        ]);
        ReferralClientAccessLink::factory()->create([
            'request_id' => $request->id,
            'issued_by' => $user->id,
        ]);

        $this->artisan('cases:purge-trashed', ['--days' => 90])
            ->expectsOutputToContain('Purged 1 case(s)')
            ->assertExitCode(0);

        $this->assertNull(CaseFile::withTrashed()->find($purgeable->id));
        $this->assertNotNull(CaseFile::withTrashed()->find($blocked->id));
    }

    public function test_case_events_of_a_purged_case_are_removed(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $service = app(CaseService::class);

        $this->actingAs($user);

        $case = $this->trashedCase($service, $user);
        DB::table('case_events')->insert([
            'id' => (string) Str::uuid(),
            'case_id' => $case->id,
            'type' => 'STATUS_CHANGED',
            'title' => 'Seeded for purge coverage',
            'occurred_at' => now(),
        ]);

        $this->assertSame(1, $service->purgeTrashedCases(90));
        $this->assertDatabaseMissing('case_events', ['case_id' => $case->id]);
    }

    /**
     * Archive a case, soft-delete it through the service, and optionally push
     * its deleted_at past the retention cutoff.
     */
    private function trashedCase(CaseService $service, User $user, bool $backdate = true): CaseFile
    {
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED', 'user_id' => $user->id]);
        $service->deleteArchivedCase($case, 'Archived case awaiting the retention purge', $user->id);

        if ($backdate) {
            CaseFile::withTrashed()->where('id', $case->id)->update(['deleted_at' => now()->subDays(100)]);
        }

        return $case;
    }
}
