<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\User;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Page;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Finding H-a: internal referral notes leaked to client-facing tracking pages.
 *
 * TrackingService::buildAgencyMilestonesData() shipped a `notes` key holding
 * staff free-text off the referral record. Its only two server-side consumers
 * are client surfaces — the anonymous public /track milestones route (gated
 * solely by the tracking-number OTP session binding) and the authenticated OFW
 * portal's equivalent route — so that key published internal agency text to
 * clients. It also sat behind a 120s server-side cache, so a deploy that only
 * changed the payload would keep serving the old notes until the entry expired.
 *
 * These tests pin the fix on all three axes:
 *   1. the served public tracking props contain no `notes` key at any depth;
 *   2. the served OFW portal props likewise;
 *   3. the staff path (Referral/Show) still reads notes from the referral
 *      record, unchanged;
 * plus the cache-key version bump that orphans already-cached payloads
 * written by the pre-fix release.
 *
 * Companion to TrackingClientSanitizationTest, which covers the same invariant
 * for the buildTrackingData() agency cards.
 */
class TrackingMilestonesNotesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sentinel placed on the referral record. Its presence anywhere in a
     * client payload is a leak; its absence must not be an accident of the
     * test not finding the field.
     */
    private const INTERNAL_NOTE = 'INTERNAL ONLY: case manager suspects the client is misrepresenting income.';

    /**
     * Payload keys the milestones page receives from the service builder.
     * Only these are scanned — shared Inertia props are not service output and
     * may legitimately contain unrelated keys.
     *
     * @var list<string>
     */
    private const CLIENT_PAYLOAD_KEYS = ['trackingId', 'trackedCase', 'agencyMilestones'];

    /**
     * The pre-fix cache key, before the payload version suffix was introduced.
     */
    private function legacyCacheKey(CaseFile $case, Referral $referral): string
    {
        return 'tracking:milestones:'.$case->id.':'.$referral->id;
    }

    /**
     * A case with a client, a staff owner, and one referral carrying internal
     * notes plus a milestone — so the leak has somewhere to travel to.
     *
     * @return array{case: CaseFile, referral: Referral, client: Client, owner: User}
     */
    private function makeCase(): array
    {
        $client = Client::factory()->create();
        $owner = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'is_active' => true,
        ]);
        $agency = Agency::factory()->create();

        $case = CaseFile::factory()->open()->create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
        ]);

        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'status' => 'PROCESSING',
            'notes' => self::INTERNAL_NOTE,
        ]);

        Milestone::factory()->create(['refr_id' => $referral->id]);

        return [
            'case' => $case->fresh(),
            'referral' => $referral,
            'client' => $client,
            'owner' => $owner,
        ];
    }

    /**
     * Bind the anonymous tracking session for this case/referral pair.
     */
    private function withTrackingSession(CaseFile $case, Client $client): self
    {
        return $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => $case->tracker_number,
                'email' => $client->email,
            ],
        ]);
    }

    /**
     * Recursively assert a client payload carries no notes-like key and none of
     * the sentinel text, at any depth and under any nesting (list or map).
     *
     * @param  array<mixed>  $node
     */
    private function assertClientSafe(array $node, string $path = 'props'): void
    {
        foreach ($node as $key => $value) {
            $here = $path.'.'.$key;

            if (in_array(strtolower((string) $key), ['notes', 'note', 'internal_notes'], true)) {
                $this->fail("Client tracking payload leaked a notes key at {$here}.");
            }

            if (is_array($value)) {
                $this->assertClientSafe($value, $here);

                continue;
            }

            if (is_string($value) && str_contains($value, self::INTERNAL_NOTE)) {
                $this->fail("Client tracking payload leaked internal note text at {$here}.");
            }
        }
    }

    /**
     * Pull the service-produced slice out of a served page's Inertia props.
     *
     * @return array<mixed>
     */
    private function servedClientPayload(Page $page): array
    {
        $props = $page->toArray()['props'];

        return array_intersect_key($props, array_flip(self::CLIENT_PAYLOAD_KEYS));
    }

    #[Test]
    public function public_tracking_milestones_payload_carries_no_notes(): void
    {
        ['case' => $case, 'referral' => $referral, 'client' => $client] = $this->makeCase();

        $response = $this->withTrackingSession($case, $client)->get(route('track.milestones', [
            'tracker_number' => $case->tracker_number,
            'referral' => $referral->id,
        ]));

        $response->assertOk();
        $response->assertInertia(function (Page $page) use ($case, $referral) {
            $page->component('Tracking/AgencyMilestones')
                ->where('trackingId', $case->tracker_number)
                ->where('agencyMilestones.referralId', $referral->id)
                ->missing('agencyMilestones.notes')
                ->missing('agencyMilestones.note')
                ->missing('agencyMilestones.latestUpdate.notes')
                ->missing('agencyMilestones.milestones.0.notes');

            $this->assertClientSafe($this->servedClientPayload($page));
        });
    }

    #[Test]
    public function ofw_portal_milestones_payload_carries_no_notes(): void
    {
        ['case' => $case, 'referral' => $referral, 'client' => $client] = $this->makeCase();

        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($ofwUser)->get(route('ofw.case.milestones', [
            'case' => $case->id,
            'referral' => $referral->id,
        ]));

        $response->assertOk();
        $response->assertInertia(function (Page $page) use ($referral) {
            $page->component('Tracking/AgencyMilestones')
                ->where('agencyMilestones.referralId', $referral->id)
                ->missing('agencyMilestones.notes')
                ->missing('agencyMilestones.note')
                ->missing('agencyMilestones.latestUpdate.notes')
                ->missing('agencyMilestones.milestones.0.notes');

            $this->assertClientSafe($this->servedClientPayload($page));
        });
    }

    #[Test]
    public function milestones_builder_omits_notes_but_keeps_client_safe_fields(): void
    {
        ['case' => $case, 'referral' => $referral] = $this->makeCase();

        $payload = app(TrackingService::class)->buildAgencyMilestonesData($case, $referral);

        $this->assertArrayNotHasKey('notes', $payload['agencyMilestones']);
        $this->assertClientSafe($payload);

        // The deletion must not take the rest of the card with it.
        $this->assertSame($referral->agency->name, $payload['agencyMilestones']['agencyName']);
        $this->assertSame($referral->status, $payload['agencyMilestones']['status']);
        $this->assertArrayHasKey('requiredServices', $payload['agencyMilestones']);
        $this->assertSame(1, $payload['agencyMilestones']['milestoneCount']);
    }

    #[Test]
    public function staff_referral_show_still_exposes_internal_notes(): void
    {
        ['referral' => $referral, 'owner' => $owner] = $this->makeCase();

        // The staff source of truth is the referral record itself, not a
        // client payload. Removal from the tracking payload must not reach it.
        $this->assertSame(self::INTERNAL_NOTE, $referral->fresh()->notes);

        $this->actingAs($owner)
            ->get(route('referrals.show', $referral))
            ->assertOk()
            ->assertInertia(fn (Page $page) => $page
                ->component('Referral/Show')
                ->where('referral.id', $referral->id)
                ->where('referral.notes', self::INTERNAL_NOTE)
            );
    }

    #[Test]
    public function cache_key_is_versioned_so_pre_fix_entries_are_orphaned(): void
    {
        ['case' => $case, 'referral' => $referral] = $this->makeCase();

        $legacyKey = $this->legacyCacheKey($case, $referral);
        $currentKey = TrackingService::trackingMilestonesCacheKey($case->id, $referral->id);

        // A payload written by the pre-fix release under the old key.
        Cache::put($legacyKey, [
            'agencyMilestones' => ['referralId' => $referral->id, 'notes' => self::INTERNAL_NOTE],
        ], 120);

        $this->assertNotSame($legacyKey, $currentKey);
        $this->assertTrue(Cache::has($legacyKey));

        $payload = app(TrackingService::class)->buildAgencyMilestonesData($case, $referral);

        // The stale entry is orphaned: never read, and still present so a TTL
        // sweep can collect it.
        $this->assertArrayNotHasKey('notes', $payload['agencyMilestones']);
        $this->assertClientSafe($payload);
        $this->assertTrue(Cache::has($currentKey));

        // Re-reading from the versioned cache keeps the payload clean.
        $this->assertClientSafe(app(TrackingService::class)->buildAgencyMilestonesData($case, $referral));
    }
}
