<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\Referral;
use Illuminate\Support\Collection;

/**
 * Case-manager-only proportional-time swimlane read model.
 *
 * This payload is served exclusively through CaseController (case-manager
 * surface, behind auth + role middleware). It must never be merged into
 * TrackingService: that output is also the public /track payload and the
 * OFW portal payload, where raw internal referral status codes must not leak.
 *
 * The model is computed on demand from the already-eager-loaded case
 * ($case->caseEvents plus $case->referrals with ->agency and ->milestones).
 * The internal builder is deliberately NOT cached:
 * TrackingService::invalidateTrackingCache() must not be modified, so a new
 * cached key would go stale on referral and case mutations. Recomputing from
 * loaded relations keeps it always fresh. The client builder below IS cached
 * under its own versioned key and IS invalidated alongside the tracking
 * payload in TrackingService::invalidateTrackingCache().
 */
class CaseSwimlaneService
{
    /**
     * Versioned cache key for the client swimlane payload. Versioned so a
     * future shape change can roll out without stale-key collisions, and
     * distinct from tracking:data:{id} so the two payloads never share cache.
     */
    public static function clientCacheKey(string $caseId): string
    {
        return 'tracking:swimlane-client:'.$caseId.':v1';
    }

    /**
     * Build the swimlane timeline for a case.
     *
     * @return array{
     *   caseOpenedAt: ?string,
     *   caseClosedAt: ?string,
     *   generatedAt: string,
     *   referrals: list<array{
     *     id: string,
     *     agency: ?string,
     *     service: ?string,
     *     status: string,
     *     statusLabel: string,
     *     sentAt: string,
     *     isTerminal: bool,
     *     terminalAt: ?string,
     *     segmentCount: int,
     *     milestoneCount: int,
     *     segments: list<array{status: string, label: string, start: string, end: ?string, milestoneCount: int, isOpen: bool}>,
     *     milestones: list<array{at: string, title: string, description: ?string}>
     *   }>,
     *   caseManagerLane: array{
     *     segments: list<array{status: string, label: string, start: string, end: ?string, milestoneCount: int, isOpen: bool}>,
     *     receivedCount: int,
     *     readyToClose: bool,
     *     closedAt: ?string
     *   },
     *   totals: array{referrals: int, active: int, terminal: int, milestones: int}
     * }
     */
    public function buildSwimlaneTimeline(CaseFile $case): array
    {
        // No-op when CaseService::getCase() already eager-loaded these, so
        // the controller path issues no extra queries.
        $case->loadMissing(['caseEvents', 'referrals.agency', 'referrals.milestones']);

        $events = $this->orderedEvents($case->caseEvents);

        $caseOpenedAt = $this->firstEventOfType($events, CaseEvent::TYPE_CASE_OPENED)?->occurred_at;
        $caseClosedAt = $this->resolveCaseClosedAt($case, $events);

        $lanes = $this->sortedReferralLanes($case, $events);
        $lanes = array_map(function (array $lane): array {
            unset($lane['createdAt']);

            return $lane;
        }, $lanes);

        $caseManagerLane = $this->buildCaseManagerLane($events, $caseClosedAt);

        // readyToClose mirrors CaseService::canClose(): every referral is in a
        // terminal state (COMPLETED or REJECTED — rejections count as
        // closable, they do not disqualify), there is at least one referral,
        // and the case is not already closed.
        $referralCount = count($lanes);
        $terminalCount = count(array_filter($lanes, fn (array $lane): bool => $lane['isTerminal']));
        $readyToClose = $referralCount > 0 && $terminalCount === $referralCount && $caseClosedAt === null;

        return [
            'caseOpenedAt' => $caseOpenedAt?->toISOString(),
            'caseClosedAt' => $caseClosedAt?->toISOString(),
            'generatedAt' => now()->toISOString(),
            'referrals' => $lanes,
            'caseManagerLane' => [
                'segments' => $caseManagerLane['segments'],
                'receivedCount' => $caseManagerLane['receivedCount'],
                'readyToClose' => $readyToClose,
                'closedAt' => $caseClosedAt?->toISOString(),
            ],
            'totals' => [
                'referrals' => $referralCount,
                'active' => $referralCount - $terminalCount,
                'terminal' => $terminalCount,
                'milestones' => array_sum(array_map(fn (array $lane): int => $lane['milestoneCount'], $lanes)),
            ],
        ];
    }

    /**
     * Client-safe swimlane timeline for OFW and public surfaces.
     *
     * Allowlist-built from the same append-only event log via the shared
     * internal segment walk, then mapped to a code-free shape at the
     * boundary: no raw status codes (per referral or per segment), no ids,
     * no workflow booleans, and no case-manager convergence concept. A
     * terminal referral's final state folds into its last segment's label
     * and end — the label text itself ('Completed' / 'Unable to assist')
     * marks the finished state for the frontend.
     *
     * Cached under a versioned client key (90s, like the tracking payload)
     * and invalidated alongside it in
     * TrackingService::invalidateTrackingCache().
     *
     * @return array{
     *   caseOpenedAt: ?string,
     *   resolvedAt: ?string,
     *   generatedAt: string,
     *   referrals: list<array{
     *     agency: ?string,
     *     service: ?string,
     *     sentAt: string,
     *     statusLabel: string,
     *     isCurrent: bool,
     *     segments: list<array{label: string, start: string, end: ?string, milestoneCount: int}>,
     *     milestones: list<array{at: string, title: string, description: ?string}>,
     *     milestoneCount: int
     *   }>,
     *   totals: array{referrals: int, active: int, milestones: int}
     * }
     */
    public function buildClientSwimlaneTimeline(CaseFile $case): array
    {
        return CacheHelper::safeRemember(self::clientCacheKey($case->id), 90, function () use ($case) {
            // No-op when the caller already eager-loaded these, so the
            // controller and mail paths issue no extra queries.
            $case->loadMissing(['caseEvents', 'referrals.agency', 'referrals.milestones']);

            $events = $this->orderedEvents($case->caseEvents);

            $caseOpenedAt = $this->firstEventOfType($events, CaseEvent::TYPE_CASE_OPENED)?->occurred_at;
            $resolvedAt = $this->resolveCaseClosedAt($case, $events);

            $internalLanes = $this->sortedReferralLanes($case, $events);
            $lanes = array_map(
                fn (array $lane): array => $this->toClientLane($lane),
                $internalLanes
            );

            $referralCount = count($lanes);
            $activeCount = count(array_filter(
                $internalLanes,
                fn (array $lane): bool => ! $lane['isTerminal']
            ));

            return [
                'caseOpenedAt' => $caseOpenedAt?->toISOString(),
                'resolvedAt' => $resolvedAt?->toISOString(),
                'generatedAt' => now()->toISOString(),
                'referrals' => $lanes,
                'totals' => [
                    'referrals' => $referralCount,
                    'active' => $activeCount,
                    'milestones' => array_sum(array_map(fn (array $lane): int => $lane['milestoneCount'], $lanes)),
                ],
            ];
        });
    }

    /**
     * Map one internal lane to its client-safe shape. Every staff-only key
     * (id, status, isTerminal, terminalAt, segmentCount, createdAt) is
     * dropped here, and every label is re-mapped through the client
     * vocabulary — the internal statusLabel is never reused, because its
     * REJECTED suffix carries rejection_reason and decision_comment.
     *
     * @param  array{agency: ?string, service: ?string, status: string, sentAt: string, isTerminal: bool, segmentCount: int, milestoneCount: int, segments: list<array{status: string, label: string, start: string, end: ?string, milestoneCount: int, isOpen: bool}>, milestones: list<array{at: string, title: string, description: ?string}>}  $lane
     * @return array{agency: ?string, service: ?string, sentAt: string, statusLabel: string, isCurrent: bool, segments: list<array{label: string, start: string, end: ?string, milestoneCount: int}>, milestones: list<array{at: string, title: string, description: ?string}>, milestoneCount: int}
     */
    private function toClientLane(array $lane): array
    {
        return [
            'agency' => $lane['agency'],
            'service' => $lane['service'],
            'sentAt' => $lane['sentAt'],
            'statusLabel' => ReferralStatusPresentation::clientLabel($lane['status']),
            'isCurrent' => true,
            'segments' => array_map(fn (array $segment): array => [
                'label' => ReferralStatusPresentation::clientLabel($segment['status']),
                'start' => $segment['start'],
                'end' => $segment['end'],
                'milestoneCount' => $segment['milestoneCount'],
            ], $lane['segments']),
            'milestones' => $lane['milestones'],
            'milestoneCount' => $lane['milestoneCount'],
        ];
    }

    /**
     * Internal referral lanes in ascending sentAt order (lane stacking order
     * = referral-sent order; ties fall back to created_at then id). Shared
     * by the internal and client builders so both order identically.
     *
     * @param  Collection<int, CaseEvent>  $events  All case events, pre-ordered.
     * @return list<array{id: string, agency: ?string, service: ?string, status: string, statusLabel: string, sentAt: string, createdAt: string, isTerminal: bool, terminalAt: ?string, segmentCount: int, milestoneCount: int, segments: list<array{status: string, label: string, start: string, end: ?string, milestoneCount: int, isOpen: bool}>, milestones: list<array{at: string, title: string, description: ?string}>}>
     */
    private function sortedReferralLanes(CaseFile $case, Collection $events): array
    {
        return $case->referrals
            ->map(fn (Referral $referral) => $this->buildReferralLane($referral, $events))
            ->sort(function (array $a, array $b): int {
                if ($a['sentAt'] !== $b['sentAt']) {
                    return $a['sentAt'] <=> $b['sentAt'];
                }
                if ($a['createdAt'] !== $b['createdAt']) {
                    return $a['createdAt'] <=> $b['createdAt'];
                }

                return $a['id'] <=> $b['id'];
            })
            ->values()
            ->all();
    }

    /**
     * Order events ascending by (occurred_at, sequence, id), matching the
     * read order used by TrackingService::buildTrackingData().
     *
     * @param  Collection<int, CaseEvent>  $events
     * @return Collection<int, CaseEvent>
     */
    private function orderedEvents(Collection $events): Collection
    {
        return $events->sort(function (CaseEvent $a, CaseEvent $b): int {
            $compared = ($a->occurred_at?->getTimestamp() ?? 0) <=> ($b->occurred_at?->getTimestamp() ?? 0);
            if ($compared !== 0) {
                return $compared;
            }

            $compared = (int) ($a->sequence ?? 0) <=> (int) ($b->sequence ?? 0);
            if ($compared !== 0) {
                return $compared;
            }

            return (string) $a->id <=> (string) $b->id;
        })->values();
    }

    /**
     * @param  Collection<int, CaseEvent>  $events
     */
    private function firstEventOfType(Collection $events, string $type): ?CaseEvent
    {
        return $events->firstWhere('type', $type);
    }

    /**
     * Resolve when the case became closed: the last case_closed event not
     * followed by a case_reopened event. Falls back to the case model's
     * closed_at only when the log holds no close/reopen events at all
     * (legacy rows predating the event log).
     *
     * @param  Collection<int, CaseEvent>  $events
     */
    private function resolveCaseClosedAt(CaseFile $case, Collection $events): ?\DateTimeInterface
    {
        $closedAt = null;
        $sawCloseOrReopen = false;

        foreach ($events as $event) {
            if ($event->type === CaseEvent::TYPE_CASE_CLOSED) {
                $sawCloseOrReopen = true;
                $closedAt = $event->occurred_at;
            } elseif ($event->type === CaseEvent::TYPE_CASE_REOPENED) {
                $sawCloseOrReopen = true;
                $closedAt = null;
            }
        }

        if ($closedAt === null && ! $sawCloseOrReopen && $case->status === 'CLOSED') {
            return $case->closed_at;
        }

        return $closedAt;
    }

    /**
     * Build one referral lane by walking that referral's events in ascending
     * order while maintaining the current open segment.
     *
     * @param  Collection<int, CaseEvent>  $events  All case events, pre-ordered.
     * @return array{id: string, agency: ?string, service: ?string, status: string, statusLabel: string, sentAt: string, createdAt: string, isTerminal: bool, terminalAt: ?string, segmentCount: int, milestoneCount: int, segments: list<array{status: string, label: string, start: string, end: ?string, milestoneCount: int, isOpen: bool}>, milestones: list<array{at: string, title: string, description: ?string}>}
     */
    private function buildReferralLane(Referral $referral, Collection $events): array
    {
        $refEvents = $events->where('referral_id', $referral->id)->values();

        $segments = [];
        $openIndex = null;
        $milestones = [];
        $sentAt = $refEvents->first()?->occurred_at ?? $referral->created_at;
        $terminalAt = null;

        foreach ($refEvents as $event) {
            $at = $event->occurred_at?->toISOString() ?? now()->toISOString();
            $meta = is_array($event->meta) ? $event->meta : [];

            if ($event->type === CaseEvent::TYPE_REFERRAL_SENT) {
                // Data-repair edge: a second sent event must not overwrite the
                // existing lane — close any open segment, then start a new one.
                $openIndex = $this->closeOpenSegment($segments, $openIndex, $at);
                $status = is_string($meta['status'] ?? null) ? $meta['status'] : 'PENDING';
                $segments[] = $this->openSegment($status, $at);
                $openIndex = count($segments) - 1;
            } elseif ($event->type === CaseEvent::TYPE_REFERRAL_STATUS_CHANGED) {
                $to = is_string($meta['to'] ?? null)
                    ? $meta['to']
                    : ($openIndex !== null ? $segments[$openIndex]['status'] : 'PENDING');
                $openIndex = $this->closeOpenSegment($segments, $openIndex, $at);
                $segments[] = $this->openSegment($to, $at);
                $openIndex = count($segments) - 1;

                if (ReferralStatusPresentation::isTerminal($to)) {
                    $terminalAt = $event->occurred_at;
                    // The terminal segment starts and ends at the same instant
                    // so a terminal referral never has an open segment.
                    $openIndex = $this->closeOpenSegment($segments, $openIndex, $at);
                }
            } elseif ($event->type === CaseEvent::TYPE_MILESTONE_ADDED) {
                $milestones[] = [
                    'at' => $at,
                    'title' => $event->title,
                    'description' => $event->description,
                ];
                if ($openIndex !== null) {
                    $segments[$openIndex]['milestoneCount']++;
                }
            }
        }

        usort($milestones, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        $isTerminal = ReferralStatusPresentation::isTerminal($referral->status);

        if ($isTerminal && $terminalAt === null) {
            // Terminal by current status but no terminal event was recorded
            // (legacy data, or a lane with zero events). Anchor terminalAt to
            // the latest known instant so terminalAt is never null on a
            // terminal referral, and leave no segment open.
            $terminalAt = $refEvents->last()?->occurred_at ?? $referral->updated_at ?? $referral->created_at;
            $openIndex = $this->closeOpenSegment($segments, $openIndex, $terminalAt?->toISOString());
        }

        return [
            'id' => $referral->id,
            'agency' => $referral->agency?->name,
            'service' => $this->serviceLabel($referral),
            'status' => $referral->status,
            'statusLabel' => ReferralStatusPresentation::staffLabel($referral->status, $referral),
            'sentAt' => $sentAt?->toISOString() ?? now()->toISOString(),
            'createdAt' => $referral->created_at?->toISOString() ?? '',
            'isTerminal' => $isTerminal,
            'terminalAt' => $terminalAt?->toISOString(),
            'segmentCount' => count($segments),
            'milestoneCount' => count($milestones),
            'segments' => $segments,
            'milestones' => $milestones,
        ];
    }

    /**
     * Build the shared convergence lane from terminal referral events plus
     * case_closed / case_reopened events.
     *
     * @param  Collection<int, CaseEvent>  $events  All case events, pre-ordered.
     * @return array{segments: list<array{status: string, label: string, start: string, end: ?string, milestoneCount: int, isOpen: bool}>, receivedCount: int}
     */
    private function buildCaseManagerLane(Collection $events, ?\DateTimeInterface $caseClosedAt): array
    {
        $segments = [];
        $openIndex = null;
        $receivedCount = 0;

        foreach ($events as $event) {
            $at = $event->occurred_at?->toISOString() ?? now()->toISOString();

            if ($event->type === CaseEvent::TYPE_REFERRAL_STATUS_CHANGED) {
                $meta = is_array($event->meta) ? $event->meta : [];
                if (! is_string($meta['to'] ?? null) || ! ReferralStatusPresentation::isTerminal($meta['to'])) {
                    continue;
                }

                // In well-formed data each terminal referral contributes
                // exactly one terminal event, so the event count equals the
                // number of terminal referrals that converged here.
                $receivedCount++;
                if ($openIndex === null) {
                    $segments[] = $this->openSegment('CASE_MANAGER', $at);
                    $openIndex = count($segments) - 1;
                }
            } elseif ($event->type === CaseEvent::TYPE_CASE_CLOSED) {
                $openIndex = $this->closeOpenSegment($segments, $openIndex, $at);
            } elseif ($event->type === CaseEvent::TYPE_CASE_REOPENED) {
                // A reopen after close starts a fresh convergence segment, but
                // with no terminal referrals yet the lane stays empty.
                if ($openIndex === null && $receivedCount > 0) {
                    $segments[] = $this->openSegment('CASE_MANAGER', $at);
                    $openIndex = count($segments) - 1;
                }
            }
        }

        // A legacy closed case (closed_at on the model, no close event in the
        // log) still terminates the visual lane at the known closed instant.
        if ($caseClosedAt !== null && $openIndex !== null) {
            $hasCloseEvent = $events->firstWhere('type', CaseEvent::TYPE_CASE_CLOSED) !== null;
            if (! $hasCloseEvent) {
                $openIndex = $this->closeOpenSegment($segments, $openIndex, $caseClosedAt->toISOString());
            }
        }

        return [
            'segments' => $segments,
            'receivedCount' => $receivedCount,
        ];
    }

    /**
     * @return array{status: string, label: string, start: string, end: ?string, milestoneCount: int, isOpen: bool}
     */
    private function openSegment(string $status, string $start): array
    {
        // CASE_MANAGER is a lane concept, not a referral state, so it keeps
        // its local label; every real status resolves canonically.
        $label = $status === 'CASE_MANAGER'
            ? 'Case Manager'
            : ReferralStatusPresentation::staffLabel($status);

        return [
            'status' => $status,
            'label' => $label,
            'start' => $start,
            'end' => null,
            'milestoneCount' => 0,
            'isOpen' => true,
        ];
    }

    /**
     * Close the open segment at the given instant. Returns null so callers
     * can assign the result straight back to their open-segment index.
     *
     * @param  list<array{status: string, label: string, start: string, end: ?string, milestoneCount: int, isOpen: bool}>  $segments
     */
    private function closeOpenSegment(array &$segments, ?int $openIndex, ?string $at): ?int
    {
        if ($openIndex !== null && isset($segments[$openIndex])) {
            $segments[$openIndex]['end'] = $at;
            $segments[$openIndex]['isOpen'] = false;
        }

        return null;
    }

    /**
     * Primary / required service label for a referral lane: the first linked
     * service when the relation is loaded, otherwise the required_services
     * text captured at referral creation.
     */
    private function serviceLabel(Referral $referral): ?string
    {
        if ($referral->relationLoaded('services') && $referral->services->isNotEmpty()) {
            return $referral->services->first()->name ?? null;
        }

        $required = is_string($referral->required_services) ? trim($referral->required_services) : '';

        return $required !== '' ? $required : null;
    }
}
