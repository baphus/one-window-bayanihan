<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\ReferralMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Coordinates private agency-to-agency message threads on a case.
 *
 * Each thread belongs to a (case, agency pair) and is surfaced through the
 * referral cards in the "Other Agencies on This Case" section. A message is
 * anchored on the recipient's referral; the conversation itself is scoped to
 * the agency pair across the whole case, so both parties see the same merged
 * thread no matter which of the two referrals they opened.
 *
 * Participation is restricted to AGENCY users whose agency is working on the
 * case (it holds at least one referral on it). Case managers and admins are
 * deliberately excluded — referral comments remain the CM <-> agency channel.
 */
class ReferralMessageService
{
    /** Whether the user's agency may converse with the referral's agency. */
    public function canAccessThread(Referral $referral, User $actor): bool
    {
        return $actor->isAgency()
            && $actor->is_active
            && $actor->agcy_id !== null
            && $actor->agcy_id !== $referral->agcy_id
            && Referral::query()
                ->where('case_id', $referral->case_id)
                ->where('agcy_id', $actor->agcy_id)
                ->exists();
    }

    /** Ensure the supplied user may read and write the referral's thread. */
    public function assertCanAccessThread(Referral $referral, User $actor): void
    {
        if (! $this->canAccessThread($referral, $actor)) {
            throw new AuthorizationException('You may not access this referral message thread.');
        }
    }

    /** Return the pair's conversation on the case as an allow-listed projection, oldest first. */
    public function listMessages(Referral $referral, User $actor): array
    {
        $this->assertCanAccessThread($referral, $actor);

        return $this->pairQuery($referral, $actor)
            ->with('sender.agency')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ReferralMessage $message) => $this->payload($message))
            ->values()
            ->all();
    }

    /** Append a message to the pair conversation and mark it read for the sender. */
    public function sendMessage(Referral $referral, User $actor, string $body): array
    {
        $this->assertCanAccessThread($referral, $actor);

        if (trim($body) === '') {
            throw new InvalidArgumentException('Message body is required.');
        }

        $message = DB::transaction(function () use ($referral, $actor, $body) {
            $message = ReferralMessage::create([
                'referral_id' => $referral->id,
                'sender_user_id' => $actor->id,
                'body' => $body,
            ]);

            $this->upsertReadMarker($referral->case_id, $actor->id, $referral->agcy_id);

            return $message;
        });

        return $this->payload($message->load('sender.agency'));
    }

    /** Mark the pair's conversation read for the user. */
    public function markThreadRead(Referral $referral, User $actor): void
    {
        $this->assertCanAccessThread($referral, $actor);

        $this->upsertReadMarker($referral->case_id, $actor->id, $referral->agcy_id);
    }

    /** Count pair messages on the case posted after the user's last read marker. */
    public function getUnreadCount(Referral $referral, User $actor): int
    {
        return $this->pairUnreadCount($referral, $actor);
    }

    /** Batch unread counts for many referral cards (avoids N+1). */
    public function unreadCountsByReferral(array $referralIds, User $actor): array
    {
        $referralIds = array_values(array_unique($referralIds));
        $unread = array_fill_keys($referralIds, 0);

        if ($referralIds === [] || ! $actor->isAgency() || $actor->agcy_id === null) {
            return $unread;
        }

        $cards = Referral::query()
            ->whereIn('id', $referralIds)
            ->get(['id', 'case_id', 'agcy_id']);

        if ($cards->isEmpty()) {
            return $unread;
        }

        // Read markers per (case, peer agency) for this user.
        $caseIds = $cards->pluck('case_id')->unique();
        $peerIds = $cards->pluck('agcy_id')->unique();

        $markers = [];
        $markerRows = DB::table('agency_thread_reads')
            ->where('user_id', $actor->id)
            ->whereIn('case_id', $caseIds)
            ->whereIn('peer_agency_id', $peerIds)
            ->get(['case_id', 'peer_agency_id', 'last_read_at']);

        foreach ($markerRows as $row) {
            $markers[$row->case_id.':'.$row->peer_agency_id] = Carbon::parse($row->last_read_at);
        }

        // All referrals on the involved cases — including anchors on the page's own
        // referral that are not part of the related-card list — so pair messages can
        // be counted wherever they were anchored.
        $caseReferrals = Referral::query()
            ->whereIn('case_id', $caseIds)
            ->get(['id', 'case_id', 'agcy_id'])
            ->keyBy('id');

        $pair = [$actor->agcy_id];

        $messages = ReferralMessage::query()
            ->where('is_deleted', false)
            ->whereIn('referral_id', $caseReferrals->keys())
            ->with('sender')
            ->get(['referral_id', 'sender_user_id', 'created_at']);

        $byReferral = $messages->groupBy('referral_id');

        foreach ($cards as $card) {
            $pair[1] = $card->agcy_id;

            // Messages anchored on the card itself...
            foreach ($byReferral->get($card->id, collect()) as $message) {
                $this->accumulateUnread($unread, $card, $message, $pair, $markers, $caseReferrals);
            }

            // ...and messages anchored on the actor's own referrals (e.g. the page
            // itself): both anchors hold the same pair conversation.
            foreach ($caseReferrals->where('agcy_id', $actor->agcy_id) as $actorReferral) {
                foreach ($byReferral->get($actorReferral->id, collect()) as $message) {
                    $this->accumulateUnread($unread, $card, $message, $pair, $markers, $caseReferrals);
                }
            }
        }

        return $unread;
    }

    /** Bump the card's unread tally when the message belongs to the card's pair. */
    private function accumulateUnread(
        array &$unread,
        Referral $card,
        ReferralMessage $message,
        array $pair,
        array $markers,
        $caseReferrals,
    ): void {
        $anchor = $caseReferrals->get($message->referral_id);

        // The message must be anchored on one of the pair's referrals and sent by
        // one of the pair's agencies.
        if ($anchor === null
            || ! in_array($anchor->agcy_id, $pair, true)
            || ! in_array($message->sender?->agcy_id, $pair, true)) {
            return;
        }

        $marker = $markers[$card->case_id.':'.$card->agcy_id] ?? null;

        if ($marker === null || $message->created_at->gt($marker)) {
            $unread[$card->id]++;
        }
    }

    private function pairQuery(Referral $referral, User $actor)
    {
        $pair = [$actor->agcy_id, $referral->agcy_id];

        return ReferralMessage::query()
            ->where('is_deleted', false)
            ->whereHas('referral', fn ($q) => $q->where('case_id', $referral->case_id))
            ->whereHas('sender', fn ($q) => $q->whereIn('agcy_id', $pair))
            ->whereHas('referral', fn ($q) => $q->whereIn('agcy_id', $pair));
    }

    private function pairUnreadCount(Referral $referral, User $actor): int
    {
        $marker = DB::table('agency_thread_reads')
            ->where('user_id', $actor->id)
            ->where('case_id', $referral->case_id)
            ->where('peer_agency_id', $referral->agcy_id)
            ->value('last_read_at');

        $query = $this->pairQuery($referral, $actor);

        if ($marker !== null) {
            $query->where('created_at', '>', Carbon::parse($marker));
        }

        return $query->count();
    }

    private function upsertReadMarker(string $caseId, string $userId, string $peerAgencyId): void
    {
        DB::table('agency_thread_reads')->updateOrInsert(
            ['user_id' => $userId, 'case_id' => $caseId, 'peer_agency_id' => $peerAgencyId],
            ['last_read_at' => now()],
        );
    }

    private function payload(ReferralMessage $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'role' => $message->sender->role,
                'agency' => $message->sender->agency ? [
                    'id' => $message->sender->agency->id,
                    'name' => $message->sender->agency->name,
                ] : null,
            ] : null,
        ];
    }
}
