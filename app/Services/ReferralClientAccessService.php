<?php

namespace App\Services;

use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Issues and validates opaque client-request access tokens.
 *
 * The raw token is returned only to the immediate caller. It is never put on
 * an Eloquent model, in an audit record, or in a log/context value.
 */
class ReferralClientAccessService
{
    private const TOKEN_BYTES = 32;

    private const LINK_LIFETIME_DAYS = 7;

    /**
     * @return array{link: ReferralClientAccessLink, raw_token: string, expires_at: \DateTimeInterface}
     */
    public function issue(
        ReferralClientRequest $request,
        User $issuer,
        ?array $recipientSnapshot = null,
    ): array {
        return DB::transaction(function () use ($request, $issuer, $recipientSnapshot): array {
            $request = ReferralClientRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertIssuable($request);

            $now = now();
            $expiresAt = $now->copy()->addDays(self::LINK_LIFETIME_DAYS);
            $rawToken = rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');

            // Lock the request row above, then revoke all currently usable
            // links while holding the same transaction lock.
            $request->accessLinks()
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->update([
                    'revoked_at' => $now,
                    'revoked_by' => $issuer->id,
                ]);

            $link = $request->accessLinks()->create([
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => $expiresAt,
                'issued_by' => $issuer->id,
                'recipient_snapshot' => $recipientSnapshot,
            ]);

            $this->invalidateTrackingCaches($request);

            return [
                'link' => $link,
                'raw_token' => $rawToken,
                'expires_at' => $expiresAt,
            ];
        });
    }

    /** Reissue is deliberately the same atomic revoke-then-issue operation. */
    public function reissue(
        ReferralClientRequest $request,
        User $issuer,
        ?array $recipientSnapshot = null,
    ): array {
        return $this->issue($request, $issuer, $recipientSnapshot);
    }

    /**
     * Atomically record a successful controller-level token use.
     *
     * Token resolution remains non-mutating; callers should resolve first and
     * call this method only after the request has been authorized for the
     * session being established.
     */
    public function recordUse(ReferralClientAccessLink $link): ReferralClientAccessLink
    {
        $linkId = $link->id;

        DB::transaction(function () use ($linkId): void {
            $locked = ReferralClientAccessLink::query()
                ->lockForUpdate()
                ->findOrFail($linkId);
            $locked->load(['request.referral.caseFile']);

            if ($locked->revoked_at !== null
                || ! $locked->expires_at
                || ! $locked->expires_at->isFuture()
                || ! $this->isRequestUsable($locked->request)) {
                throw new LogicException('This access link is no longer usable.');
            }

            $locked->update([
                'use_count' => ((int) $locked->use_count) + 1,
                'first_used_at' => $locked->first_used_at ?? now(),
                'last_used_at' => now(),
            ]);
        });

        return ReferralClientAccessLink::query()
            ->with(['request.referral.caseFile'])
            ->findOrFail($linkId);
    }

    /**
     * Resolve an opaque token without disclosing whether it was malformed,
     * expired, revoked, mismatched, deleted, or attached to a closed request.
     */
    public function resolveUsableToken(string $rawToken): ?ReferralClientAccessLink
    {
        if ($rawToken === '' || strlen($rawToken) < 40) {
            return null;
        }

        $link = ReferralClientAccessLink::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->usable()
            ->with(['request.referral.caseFile'])
            ->first();

        if (! $link || ! $this->isRequestUsable($link->request)) {
            return null;
        }

        return $link;
    }

    /** Validate a session-bound link without mutating its usage fields. */
    public function isUsableLink(ReferralClientAccessLink $link): bool
    {
        $link->loadMissing('request.referral.caseFile');

        return $link->revoked_at === null
            && $link->expires_at?->isFuture() === true
            && $this->isRequestUsable($link->request);
    }

    public function revoke(ReferralClientAccessLink $link, ?User $actor = null): ReferralClientAccessLink
    {
        DB::transaction(function () use ($link, $actor): void {
            $locked = ReferralClientAccessLink::query()->lockForUpdate()->findOrFail($link->id);
            if ($locked->revoked_at === null) {
                $locked->update(['revoked_at' => now(), 'revoked_by' => $actor?->id]);
            }
            $link->setRawAttributes($locked->getAttributes());
        });

        $link->loadMissing('request.referral.caseFile');
        $this->invalidateTrackingCaches($link->request);

        return $link->fresh(['request', 'revoker']);
    }

    private function assertIssuable(ReferralClientRequest $request): void
    {
        $request->loadMissing('referral.caseFile');
        if (! $this->isRequestUsable($request)) {
            throw new LogicException('Client access cannot be issued for this request.');
        }
    }

    private function isRequestUsable(?ReferralClientRequest $request): bool
    {
        if (! $request || $request->is_deleted || in_array($request->status, [
            ReferralClientRequest::STATUS_COMPLETED,
            ReferralClientRequest::STATUS_CANCELLED,
        ], true)) {
            return false;
        }

        $referral = $request->referral;
        $case = $referral?->caseFile;

        return $referral
            && ! $referral->is_deleted
            && ! in_array($referral->status, ['COMPLETED', 'REJECTED'], true)
            && $case
            && ! $case->is_deleted
            && $case->closed_at === null
            && ! in_array($case->status, ['CLOSED', 'ARCHIVED'], true);
    }

    private function invalidateTrackingCaches(ReferralClientRequest $request): void
    {
        $request->loadMissing('referral.caseFile');
        $caseId = $request->referral?->case_id;
        if ($caseId) {
            Cache::forget('tracking:data:'.$caseId);
            Cache::forget('tracking:milestones:'.$caseId.':'.$request->referral->id);
        }
    }
}
