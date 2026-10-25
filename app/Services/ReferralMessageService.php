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

    /**
     * Batch unread counts for many referral cards without per-referral work.
     *
     * A card's tally is the number of unread messages in its (case, viewer agency
     * <-> card agency) conversation. One message pass and one read-marker lookup
     * serve every card, so the query count does not grow with the number of
     * cards. Messages sent by the viewer's own agency are never unread.
     */
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

        $counts = $this->pairUnreadCounts(
            $cards->pluck('case_id')->unique()->values()->all(),
            (string) $actor->agcy_id,
            (string) $actor->id,
        );

        foreach ($cards as $card) {
            $unread[$card->id] = $counts[$card->case_id.':'.$card->agcy_id] ?? 0;
        }

        return $unread;
    }

    /**
     * Count unread peer messages for every (case, peer agency) pair on the cases.
     *
     * Rows are keyed "{case_id}:{peer_agency_id}". Every pair that appears in a
     * message on those cases is returned, so a missing key means zero.
     *
     * The viewer agency's conversation with a peer is anchored on either side:
     * messages may hang off the peer's referrals or off the viewer's own. Both
     * anchors belong to the same thread, so both are counted. Messages written by
     * the viewer's own agency are never unread to that viewer.
     *
     * @param  array<int, string>  $caseIds
     * @return array<string, int>
     */
    public function pairUnreadCounts(array $caseIds, string $viewerAgencyId, string $viewerUserId): array
    {
        if ($caseIds === []) {
            return [];
        }

        // One pass over every message anchored on the involved cases.
        $rows = DB::table('referral_messages as m')
            ->join('referrals as anchor', 'anchor.id', '=', 'm.referral_id')
            ->join('users as sender', 'sender.id', '=', 'm.sender_user_id')
            ->where('m.is_deleted', false)
            ->whereIn('anchor.case_id', $caseIds)
            ->whereNotNull('sender.agcy_id')
            ->selectRaw('anchor.case_id as case_id')
            ->selectRaw('anchor.agcy_id as anchor_agency_id')
            ->selectRaw('sender.agcy_id as sender_agency_id')
            ->selectRaw('m.created_at as created_at')
            ->get();

        // One lookup of the viewer's read markers for those cases.
        $markers = DB::table('agency_thread_reads')
            ->where('user_id', $viewerUserId)
            ->whereIn('case_id', $caseIds)
            ->get(['case_id', 'peer_agency_id', 'last_read_at'])
            ->mapWithKeys(fn ($row) => [
                $row->case_id.':'.$row->peer_agency_id => Carbon::parse($row->last_read_at),
            ]);

        $counts = [];

        foreach ($rows as $row) {
            $senderAgency = (string) $row->sender_agency_id;

            // Own-agency messages are never unread for this viewer. This must not
            // depend on a read marker: markers are per user, so a colleague's
            // message would otherwise badge for everyone else at the agency.
            if ($senderAgency === $viewerAgencyId) {
                continue;
            }

            $anchorAgency = (string) $row->anchor_agency_id;

            // Only anchors on the viewer's agency or on the sender's agency belong
            // to this pair; anything else is a different conversation.
            if ($anchorAgency !== $viewerAgencyId && $anchorAgency !== $senderAgency) {
                continue;
            }

            $key = $row->case_id.':'.$senderAgency;
            $counts[$key] ??= 0;

            $marker = $markers->get($key);

            if ($marker === null || Carbon::parse($row->created_at)->gt($marker)) {
                $counts[$key]++;
            }
        }

        return $counts;
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
