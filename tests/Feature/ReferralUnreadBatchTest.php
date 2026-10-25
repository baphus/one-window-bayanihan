<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralMessage;
use App\Models\User;
use App\Services\ReferralMessageService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Batch unread counts for the "Other Agencies on This Case" cards: the counts
 * must keep the pair/read semantics of the single-thread counter while costing
 * a fixed number of queries no matter how many cards are rendered.
 */
class ReferralUnreadBatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One case with three agencies, each holding one referral, plus an extra
     * referral for agency X so the viewer's own agency has two anchors.
     *
     * @return array<string, mixed>
     */
    private function fixtures(): array
    {
        $agencyX = Agency::factory()->create();
        $agencyY = Agency::factory()->create();
        $agencyZ = Agency::factory()->create();
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => $caseManager->id]);

        $referralX = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyX->id]);
        $referralX2 = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyX->id]);
        $referralY = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyY->id]);
        $referralZ = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyZ->id]);

        $viewer = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agencyX->id,
            'is_active' => true,
        ]);
        $colleague = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agencyX->id,
            'is_active' => true,
        ]);
        $peerY = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agencyY->id,
            'is_active' => true,
        ]);
        $peerZ = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agencyZ->id,
            'is_active' => true,
        ]);

        return compact(
            'case',
            'referralX',
            'referralX2',
            'referralY',
            'referralZ',
            'viewer',
            'colleague',
            'peerY',
            'peerZ',
        );
    }

    private function marker(string $caseId, string $userId, string $peerAgencyId, $lastReadAt): void
    {
        DB::table('agency_thread_reads')->insert([
            'user_id' => $userId,
            'case_id' => $caseId,
            'peer_agency_id' => $peerAgencyId,
            'last_read_at' => $lastReadAt,
        ]);
    }

    #[Test]
    public function counts_peer_messages_on_both_anchors_without_a_read_marker(): void
    {
        $f = $this->fixtures();

        // Peer Y writes on the viewer's own referral (shared X<->Y thread)...
        ReferralMessage::create([
            'referral_id' => $f['referralX']->id,
            'sender_user_id' => $f['peerY']->id,
            'body' => 'Question from Y.',
        ]);
        // ...and on the viewer's second own referral.
        ReferralMessage::create([
            'referral_id' => $f['referralX2']->id,
            'sender_user_id' => $f['peerY']->id,
            'body' => 'Second note from Y.',
        ]);
        // Peer Z writes on Z's own card.
        ReferralMessage::create([
            'referral_id' => $f['referralZ']->id,
            'sender_user_id' => $f['peerZ']->id,
            'body' => 'Hello from Z.',
        ]);

        $counts = app(ReferralMessageService::class)->unreadCountsByReferral(
            [$f['referralX']->id, $f['referralY']->id, $f['referralZ']->id],
            $f['viewer'],
        );

        // The X card is the viewer's own agency: their own thread is never
        // badged, and the Z message belongs to Z's pair only.
        $this->assertSame(0, $counts[$f['referralX']->id]);
        // Both Y messages sit in the shared X<->Y conversation.
        $this->assertSame(2, $counts[$f['referralY']->id]);
        $this->assertSame(1, $counts[$f['referralZ']->id]);
    }

    #[Test]
    public function read_markers_clear_only_the_pair_they_cover(): void
    {
        $f = $this->fixtures();

        $readAt = now();

        // Y's messages on the viewer's own referral (the shared X<->Y thread),
        // created through the model and then timestamped relative to the marker.
        $beforeMarker = ReferralMessage::create([
            'referral_id' => $f['referralX']->id,
            'sender_user_id' => $f['peerY']->id,
            'body' => 'Message from Y before the marker.',
        ]);
        $beforeMarker->forceFill(['created_at' => $readAt->copy()->subMinutes(5)])->saveQuietly();

        $afterMarker = ReferralMessage::create([
            'referral_id' => $f['referralX']->id,
            'sender_user_id' => $f['peerY']->id,
            'body' => 'Message from Y after the marker.',
        ]);
        $afterMarker->forceFill(['created_at' => $readAt->copy()->addMinutes(5)])->saveQuietly();

        ReferralMessage::create([
            'referral_id' => $f['referralZ']->id,
            'sender_user_id' => $f['peerZ']->id,
            'body' => 'Message from Z with no marker.',
        ]);

        $this->marker($f['case']->id, $f['viewer']->id, $f['referralY']->agcy_id, $readAt);

        $counts = app(ReferralMessageService::class)->unreadCountsByReferral(
            [$f['referralY']->id, $f['referralZ']->id],
            $f['viewer'],
        );

        // Y: only the message after the marker. Z: no marker, so it counts.
        $this->assertSame(1, $counts[$f['referralY']->id]);
        $this->assertSame(1, $counts[$f['referralZ']->id]);
    }

    #[Test]
    public function own_agency_messages_are_never_unread_and_foreign_pairs_are_ignored(): void
    {
        $f = $this->fixtures();

        // A colleague at the viewer's own agency writes on Y's card.
        ReferralMessage::create([
            'referral_id' => $f['referralY']->id,
            'sender_user_id' => $f['colleague']->id,
            'body' => 'Update from our own agency.',
        ]);
        // A message between Y and Z does not involve the viewer's agency.
        ReferralMessage::create([
            'referral_id' => $f['referralZ']->id,
            'sender_user_id' => $f['peerY']->id,
            'body' => 'Y talking to Z.',
        ]);
        // A deleted peer message never counts.
        ReferralMessage::create([
            'referral_id' => $f['referralZ']->id,
            'sender_user_id' => $f['peerZ']->id,
            'body' => 'Removed.',
            'is_deleted' => true,
        ]);

        $counts = app(ReferralMessageService::class)->unreadCountsByReferral(
            [$f['referralY']->id, $f['referralZ']->id],
            $f['viewer'],
        );

        $this->assertSame(0, $counts[$f['referralY']->id]);
        $this->assertSame(0, $counts[$f['referralZ']->id]);
    }

    #[Test]
    public function unknown_and_duplicate_ids_get_a_zero_default(): void
    {
        $f = $this->fixtures();

        $counts = app(ReferralMessageService::class)->unreadCountsByReferral(
            [$f['referralY']->id, $f['referralY']->id, (string) str()->uuid()],
            $f['viewer'],
        );

        $this->assertSame(0, $counts[$f['referralY']->id]);
        $this->assertArrayNotHasKey($f['referralX']->id, $counts);
    }

    #[Test]
    public function non_agency_actors_get_no_counts(): void
    {
        $f = $this->fixtures();

        ReferralMessage::create([
            'referral_id' => $f['referralZ']->id,
            'sender_user_id' => $f['peerZ']->id,
            'body' => 'Hello from Z.',
        ]);

        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $counts = app(ReferralMessageService::class)->unreadCountsByReferral(
            [$f['referralZ']->id],
            $caseManager,
        );

        $this->assertSame(0, $counts[$f['referralZ']->id]);
    }

    #[Test]
    public function related_referral_cards_expose_matching_unread_counts(): void
    {
        $f = $this->fixtures();

        ReferralMessage::create([
            'referral_id' => $f['referralX']->id,
            'sender_user_id' => $f['peerY']->id,
            'body' => 'Question from Y.',
        ]);
        ReferralMessage::create([
            'referral_id' => $f['referralZ']->id,
            'sender_user_id' => $f['peerZ']->id,
            'body' => 'Hello from Z.',
        ]);

        // The viewer opens Z's card, so Y is the related peer card.
        $related = app(ReferralService::class)->getRelatedReferrals($f['referralZ'], $f['viewer']);
        $byId = $related->keyBy('id');

        // Y's card carries the message Y anchored on the viewer's own referral,
        // and the viewer's own agency card is listed but not messaged.
        $this->assertSame(1, $byId[$f['referralY']->id]->unread_count);
        $this->assertTrue($byId[$f['referralY']->id]->can_message);
        $this->assertFalse($related->contains('id', $f['referralZ']->id));
        $this->assertFalse($byId[$f['referralX']->id]->can_message);
        $this->assertSame(0, $byId[$f['referralX']->id]->unread_count);
    }

    /**
     * The batch tally and the single-thread tally must be the same number for
     * the same card, because they are the same conversation.
     *
     * They used to key the read marker differently — the batch counter on the
     * message SENDER's agency, the single counter on the CARD's agency. That
     * only agreed while a peer could not own the card being counted. This test
     * pins the two to each other over a case where both sides of the thread
     * carry messages.
     */
    #[Test]
    public function batch_and_single_thread_counters_agree(): void
    {
        $f = $this->fixtures();

        $readAt = now();

        // Y writes on the viewer's own referral: before and after the marker.
        $beforeMarker = ReferralMessage::create([
            'referral_id' => $f['referralX']->id,
            'sender_user_id' => $f['peerY']->id,
            'body' => 'Y before the marker.',
        ]);
        $beforeMarker->forceFill(['created_at' => $readAt->copy()->subMinutes(5)])->saveQuietly();

        $afterMarker = ReferralMessage::create([
            'referral_id' => $f['referralX']->id,
            'sender_user_id' => $f['peerY']->id,
            'body' => 'Y after the marker.',
        ]);
        $afterMarker->forceFill(['created_at' => $readAt->copy()->addMinutes(5)])->saveQuietly();

        // And the viewer replies on Y's card, so the thread has traffic on the
        // anchor whose agency is not the sender.
        $viewerReply = ReferralMessage::create([
            'referral_id' => $f['referralY']->id,
            'sender_user_id' => $f['viewer']->id,
            'body' => 'Our reply.',
        ]);
        $viewerReply->forceFill(['created_at' => $readAt->copy()->addMinutes(7)])->saveQuietly();

        $this->marker($f['case']->id, $f['viewer']->id, $f['referralY']->agcy_id, $readAt);

        $service = app(ReferralMessageService::class);
        $batch = $service->unreadCountsByReferral([$f['referralY']->id], $f['viewer']);

        $this->assertSame(
            $service->getUnreadCount($f['referralY'], $f['viewer']),
            $batch[$f['referralY']->id],
            'The batch counter and the single-thread counter must agree for the same card',
        );
        $this->assertSame(1, $batch[$f['referralY']->id]);
    }

    #[Test]
    public function query_count_does_not_scale_with_the_number_of_cards(): void
    {
        $f = $this->fixtures();

        // Fill the case with peer agencies and unread messages from each, so a
        // counter that touched the database once per card would show it plainly.
        for ($i = 0; $i < 8; $i++) {
            $agency = Agency::factory()->create();
            $extra = Referral::factory()->create(['case_id' => $f['case']->id, 'agcy_id' => $agency->id]);
            $peer = User::factory()->create([
                'role' => UserRole::AGENCY->value,
                'agcy_id' => $agency->id,
                'is_active' => true,
            ]);
            ReferralMessage::create([
                'referral_id' => $extra->id,
                'sender_user_id' => $peer->id,
                'body' => 'Message '.$i,
            ]);
        }

        $ids = Referral::where('case_id', $f['case']->id)->pluck('id')->all();
        $this->assertGreaterThanOrEqual(8, count($ids));

        $service = app(ReferralMessageService::class);

        $service->unreadCountsByReferral($ids, $f['viewer']); // warm caches (model discovery, config)

        DB::enableQueryLog();
        $counts = $service->unreadCountsByReferral($ids, $f['viewer']);
        $queries = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        $this->assertCount(count($ids), $counts);
        // A per-card counter would need at least one query per card, so the
        // cost must stay far below that.
        $this->assertLessThan(
            count($ids),
            $queries,
            'Unread counting for '.count($ids).' cards must not cost one query per card',
        );
    }
}
