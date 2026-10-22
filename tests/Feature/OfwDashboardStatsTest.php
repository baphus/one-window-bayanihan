<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * OFW dashboard stat-card contract.
 *
 * The stat buckets are keyed by the canonical case statuses (PENDING_REVIEW,
 * OPEN, CLOSED, ARCHIVED — the set CaseService::updateCase/getCaseStats drive,
 * see also the case-type case_statuses reference rows). DRAFT is excluded from
 * every count by the clientVisible() scope, so the three buckets must always
 * sum to the total. The staff dashboard payload must no longer carry the dead
 * caseTrends dataset removed in batch 2.
 */
class OfwDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    #[Test]
    public function ofw_stat_buckets_sum_to_the_visible_total(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        // One case for every client-visible status, weighted so an accidental
        // status-independent count (a bare COUNT(*)) cannot pass.
        CaseFile::factory()->open()->create(['client_id' => $client->id]);
        CaseFile::factory()->pendingReview()->create(['client_id' => $client->id]);
        CaseFile::factory()->pendingReview()->create(['client_id' => $client->id]);
        CaseFile::factory()->closed()->create(['client_id' => $client->id]);
        CaseFile::factory()->closed()->create(['client_id' => $client->id]);
        CaseFile::factory()->archived()->create(['client_id' => $client->id]);

        // A draft of the same client: clientVisible() excludes it, so it must
        // not inflate the total and leave the buckets short.
        CaseFile::factory()->draft()->create(['client_id' => $client->id]);

        // Another client's case must never leak into the OFW's stats.
        $otherClient = Client::factory()->create();
        CaseFile::factory()->open()->create(['client_id' => $otherClient->id]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get('/my-cases');

        $response->assertOk();

        $stats = $response->json('props.caseStats');

        // Exactly the four keys OFW/Dashboard.jsx reads off its StatRow cards
        // (total, under_review, in_progress, completed) — no extra bucket may
        // appear and none may go missing.
        $this->assertSame(['total', 'under_review', 'in_progress', 'completed'], array_keys($stats));
        $this->assertSame(6, $stats['total']);
        $this->assertSame(0, $stats['total'] - ($stats['under_review'] + $stats['in_progress'] + $stats['completed']));
        $this->assertSame(2, $stats['under_review']);
        $this->assertSame(1, $stats['in_progress']);
        // Archived cases remain client-visible and count as completed, so the
        // completed bucket keeps every finalized case in exactly one bucket.
        $this->assertSame(3, $stats['completed']);
    }

    #[Test]
    public function ofw_stats_are_empty_but_consistent_when_the_client_has_no_cases(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        CaseFile::factory()->draft()->create(['client_id' => $client->id]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get('/my-cases');

        $response->assertOk();

        $stats = $response->json('props.caseStats');

        $this->assertSame(['total', 'under_review', 'in_progress', 'completed'], array_keys($stats));
        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['under_review']);
        $this->assertSame(0, $stats['in_progress']);
        $this->assertSame(0, $stats['completed']);
    }

    #[Test]
    public function staff_dashboard_payload_no_longer_contains_case_trends(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'email_verified_at' => now(),
        ]);

        // The dashboard payload is deferred; a partial reload that requests the
        // dashboard group resolves it — the same round-trip the SPA performs.
        // HandleInertiaRequests is disabled in setUp, so no version check and no
        // X-Inertia-Version header are needed here.
        $response = $this->actingAs($manager)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'Dashboard',
                'X-Inertia-Partial-Data' => 'dashboard',
            ])
            ->get('/dashboard');

        $response->assertOk();
        $this->assertArrayNotHasKey('caseTrends', $response->json('props.dashboard'));

        // The heavy Trends query behind the removed injection must not run.
        $this->assertFalse(Cache::has('dashboard:admin_case_trends'));
    }
}
