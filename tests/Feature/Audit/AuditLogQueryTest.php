<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogQueryTest extends TestCase
{
    use RefreshDatabase;

    private function createStatusChangeLog(User $user, string $referralId, int $index, string $from = 'PENDING', string $to = 'PROCESSING'): AuditLog
    {
        return AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'referral',
            'entity_id' => $referralId,
            'old_value' => ['status' => $from],
            'new_value' => ['status' => $to],
            'user_id' => $user->id,
            'timestamp' => now()->subMinutes(60 - $index),
        ]);
    }

    public function test_audit_log_paginated(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $referral = Referral::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->createStatusChangeLog($user, $referral->id, $i);
        }

        $service = app(ReferralService::class);
        $page1 = $service->getReferralAuditLogs($referral->id);

        // Referral creation itself emits an observer-written CREATE audit log,
        // so the total is the 30 UPDATE logs plus any observer rows.
        $expectedTotal = AuditLog::forReferral($referral->id)->count();
        $this->assertGreaterThanOrEqual(30, $expectedTotal);

        $this->assertInstanceOf(LengthAwarePaginator::class, $page1);
        $this->assertSame(25, $page1->perPage());
        $this->assertSame($expectedTotal, $page1->total());
        $this->assertCount(min(25, $expectedTotal), $page1->items());

        // Newest first: timestamps must descend across the page.
        $timestamps = collect($page1->items())->map(fn ($log) => $log->timestamp->toIso8601String())->all();
        $sorted = collect($timestamps)->sortDesc()->values()->all();
        $this->assertSame($sorted, array_values($timestamps));

        $page2 = $service->getReferralAuditLogs($referral->id, 25);
        $page2->setPageName('page');
        $second = AuditLog::forReferral($referral->id)->withUser()->latest('timestamp')->paginate(25, ['*'], 'page', 2);
        $this->assertCount($expectedTotal - 25, $second->items());
        $this->assertSame($expectedTotal, $second->total());
    }

    public function test_audit_log_query_count(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $referral = Referral::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->createStatusChangeLog($user, $referral->id, $i);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $logs = app(ReferralService::class)->getReferralAuditLogs($referral->id);

        // Touch the eager-loaded user on every row: must not fire extra queries.
        foreach ($logs as $log) {
            $this->assertTrue($log->relationLoaded('user'));
            $log->user?->name;
        }

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // count(*) + page select + eager-loaded users = 3 queries max.
        $this->assertLessThanOrEqual(3, $queryCount, "Expected <= 3 queries, got {$queryCount}");
    }

    public function test_status_change_filtered_in_sql(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $referral = Referral::factory()->create();
        $other = Referral::factory()->create();

        // One genuine status change.
        $this->createStatusChangeLog($user, $referral->id, 10, 'PENDING', 'PROCESSING');

        // Same status on both sides: must be excluded by the SQL predicate.
        AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'referral',
            'entity_id' => $referral->id,
            'old_value' => ['status' => 'PROCESSING', 'note' => 'internal remark'],
            'new_value' => ['status' => 'PROCESSING', 'note' => 'edited remark'],
            'user_id' => $user->id,
            'timestamp' => now()->subMinutes(5),
        ]);

        // Same referral id shape but different entity: must not leak in.
        $this->createStatusChangeLog($user, $other->id, 11);

        // Scope isolates the referral (UPDATE rows only: creation also emits
        // an observer-written CREATE log for each referral).
        $this->assertSame(2, AuditLog::forReferral($referral->id)->where('action', 'UPDATE')->count());
        $this->assertSame(1, AuditLog::forReferral($other->id)->where('action', 'UPDATE')->count());

        // Timeline surfaces only the genuine status change.
        $timeline = app(ReferralService::class)->getReferralTimeline($referral);
        $statusEvents = collect($timeline)->where('type', 'referral_status')->values();
        $this->assertCount(1, $statusEvents);
        $this->assertSame('Referral status updated', $statusEvents->first()['title']);
    }
}
