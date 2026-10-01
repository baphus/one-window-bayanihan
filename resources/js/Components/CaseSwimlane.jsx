import { useLayoutEffect, useMemo, useRef, useState } from 'react';
import { TimelineEmptyState, formatTimelineAbsolute, formatTimelineRelative } from './Timeline';

/**
 * CaseSwimlane — a multi-lane, proportional-time swimlane timeline for the
 * case detail page.
 *
 * One fixed lane per referral (stacked in ascending `sentAt` order) plus a
 * final case-manager lane pinned to the bottom. Every segment is positioned
 * and sized by real elapsed time against one shared horizontal axis.
 *
 * All geometry math lives in the pure `buildSwimlaneLayout()` helper below
 * so it can be unit-tested; the component itself is presentational.
 */

// ---------------------------------------------------------------------------
// Layout constants
// ---------------------------------------------------------------------------

/** Width of the pinned agency/label column (px). */
export const SWIMLANE_LABEL_COL_PX = 208;
/** Height of the shared date ruler (px). */
export const SWIMLANE_RULER_PX = 34;
/** Minimum lane height — every row stays visible and clickable (px). */
export const SWIMLANE_LANE_PX = 46;
/** Vertical gap between lanes (px). */
export const SWIMLANE_LANE_GAP_PX = 10;
/** Breathing room reserved for the convergence connectors above the manager lane (px). */
export const SWIMLANE_CONVERGE_GAP_PX = 30;
/** Minimum rendered width of any segment — short spans never collapse (px). */
export const SWIMLANE_MIN_SEGMENT_PX = 8;
/** Minimum time-axis width before the track scrolls instead of squashing (px). */
export const SWIMLANE_MIN_TRACK_PX = 620;
/** Fallback track width used when neither `width` nor a measured width exists (px). */
export const SWIMLANE_DEFAULT_TRACK_PX = 760;

const DAY_MS = 86400000;
const HOUR_MS = 3600000;

// ---------------------------------------------------------------------------
// Status → colour metadata (same hues as StatusBadge / tailwind palette).
// Colour is never the only carrier: every status also ships a text label,
// an icon, and a description.
// ---------------------------------------------------------------------------

export const SWIMLANE_STATUS_META = {
    PENDING: {
        shortLabel: 'Pending',
        description: 'Sent to agency — awaiting response',
        icon: 'schedule',
        bar: 'bg-amber-300 border-amber-500',
        dot: 'bg-amber-500',
        text: 'text-amber-800',
        chip: 'bg-amber-50 border-amber-200 text-amber-700',
    },
    PROCESSING: {
        shortLabel: 'Processing',
        description: 'Accepted — now processing',
        icon: 'sync',
        bar: 'bg-blue-300 border-blue-600',
        dot: 'bg-blue-600',
        text: 'text-blue-800',
        chip: 'bg-blue-50 border-blue-200 text-blue-700',
    },
    FOR_COMPLIANCE: {
        shortLabel: 'For Compliance',
        description: 'Set as For Compliance',
        icon: 'fact_check',
        bar: 'bg-orange-300 border-orange-600',
        dot: 'bg-orange-600',
        text: 'text-orange-800',
        chip: 'bg-orange-50 border-orange-200 text-orange-700',
    },
    COMPLETED: {
        shortLabel: 'Completed',
        description: 'Completed',
        icon: 'check_circle',
        bar: 'bg-emerald-300 border-emerald-600',
        dot: 'bg-emerald-600',
        text: 'text-emerald-800',
        chip: 'bg-emerald-50 border-emerald-200 text-emerald-700',
    },
    REJECTED: {
        shortLabel: 'Rejected',
        description: 'Rejected',
        icon: 'cancel',
        bar: 'bg-rose-300 border-rose-500',
        dot: 'bg-rose-500',
        text: 'text-rose-800',
        chip: 'bg-rose-50 border-rose-200 text-rose-700',
    },
    CASE_MANAGER: {
        shortLabel: 'Case Manager',
        description: 'Case manager review',
        icon: 'manage_accounts',
        bar: 'bg-indigo-200 border-indigo-500',
        dot: 'bg-indigo-500',
        text: 'text-indigo-800',
        chip: 'bg-indigo-50 border-indigo-200 text-indigo-700',
    },
};

export function swimlaneStatusMeta(status) {
    return SWIMLANE_STATUS_META[status] ?? {
        shortLabel: String(status ?? 'Unknown').replace(/_/g, ' '),
        description: String(status ?? 'Unknown').replace(/_/g, ' '),
        icon: 'circle',
        bar: 'bg-slate-300 border-slate-400',
        dot: 'bg-slate-400',
        text: 'text-slate-700',
        chip: 'bg-slate-50 border-slate-200 text-slate-600',
    };
}

// ---------------------------------------------------------------------------
// Small pure helpers
// ---------------------------------------------------------------------------

function toMs(value) {
    if (value === null || value === undefined || value === '') return null;
    const time = new Date(value).getTime();
    return Number.isNaN(time) ? null : time;
}

/** Human duration label, e.g. "45 min", "3 hr", "12 days". Never throws. */
export function formatSwimlaneDuration(durationMs) {
    if (!Number.isFinite(durationMs) || durationMs < 0) return '';
    const minutes = Math.round(durationMs / 60000);
    if (minutes < 1) return 'under a minute';
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        const rest = minutes % 60;
        return rest === 0 ? `${hours} hr` : `${hours} hr ${rest} min`;
    }
    const days = Math.floor(hours / 24);
    if (days < 30) return days === 1 ? '1 day' : `${days} days`;
    const months = Math.floor(days / 30);
    if (months < 12) return months === 1 ? 'about a month' : `about ${months} months`;
    const years = Math.floor(months / 12);
    return years === 1 ? 'about a year' : `about ${years} years`;
}

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/**
 * Tick label chosen for the overall span. Short spans show day + hour,
 * medium spans show day, long spans collapse to month/year. UTC keeps the
 * ruler deterministic regardless of viewer timezone.
 */
export function formatTickLabel(valueMs, spanMs) {
    const date = new Date(valueMs);
    if (Number.isNaN(date.getTime())) return '';
    const month = MONTH_NAMES[date.getUTCMonth()];
    const day = date.getUTCDate();
    const year = date.getUTCFullYear();
    if (spanMs <= 3 * DAY_MS) {
        let hour = date.getUTCHours();
        const suffix = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12;
        if (hour === 0) hour = 12;
        return `${month} ${day}, ${hour} ${suffix}`;
    }
    if (spanMs <= 95 * DAY_MS) return `${month} ${day}`;
    if (spanMs <= 2 * 365 * DAY_MS) return `${month} ${year}`;
    return `${year}`;
}

const TICK_STEPS_MS = [
    6 * HOUR_MS,
    12 * HOUR_MS,
    DAY_MS,
    2 * DAY_MS,
    3 * DAY_MS,
    7 * DAY_MS,
    14 * DAY_MS,
    30 * DAY_MS,
    61 * DAY_MS,
    92 * DAY_MS,
    183 * DAY_MS,
    365 * DAY_MS,
];

/**
 * Pick tick positions for [startMs, endMs] that fit legibly in `widthPx`
 * (roughly one label per 90px). Never returns more ticks than fit.
 */
export function generateTimeTicks(startMs, endMs, widthPx) {
    if (!Number.isFinite(startMs) || !Number.isFinite(endMs) || endMs <= startMs) return [];
    const width = Number.isFinite(widthPx) && widthPx > 0 ? widthPx : SWIMLANE_DEFAULT_TRACK_PX;
    const maxTicks = Math.max(2, Math.floor(width / 90));
    const span = endMs - startMs;
    let step = TICK_STEPS_MS[TICK_STEPS_MS.length - 1];
    for (const candidate of TICK_STEPS_MS) {
        if (span / candidate <= maxTicks) {
            step = candidate;
            break;
        }
    }
    const ticks = [];
    // Align month-scale steps to the first of the month, smaller steps to a
    // clean step boundary, so labels always read as round dates.
    let first;
    if (step >= 30 * DAY_MS) {
        const anchor = new Date(startMs);
        first = Date.UTC(anchor.getUTCFullYear(), anchor.getUTCMonth(), 1);
        if (first < startMs) {
            const cursor = new Date(first);
            do {
                cursor.setUTCMonth(cursor.getUTCMonth() + Math.max(1, Math.round(step / (30 * DAY_MS))));
                first = cursor.getTime();
            } while (first < startMs);
        }
    } else {
        first = Math.ceil(startMs / step) * step;
    }
    const scale = width / span;
    for (let value = first; value <= endMs; value += step) {
        // Month stepping is calendar-based, not fixed-ms.
        if (step >= 30 * DAY_MS && ticks.length > 0) {
            const cursor = new Date(ticks[ticks.length - 1].value);
            cursor.setUTCMonth(cursor.getUTCMonth() + Math.max(1, Math.round(step / (30 * DAY_MS))));
            value = cursor.getTime();
            if (value > endMs) break;
        }
        ticks.push({ value, label: formatTickLabel(value, span), x: (value - startMs) * scale });
        if (ticks.length > maxTicks + 1) break;
    }
    return ticks;
}

// ---------------------------------------------------------------------------
// The pure layout builder — every pixel decision lives here.
// ---------------------------------------------------------------------------

function clampTrackWidth(width) {
    return Number.isFinite(width) && width > 0 ? width : SWIMLANE_DEFAULT_TRACK_PX;
}

function collectSegmentTimes(segments, generatedAtMs) {
    let earliest = null;
    let latest = null;
    for (const segment of segments ?? []) {
        const start = toMs(segment?.start);
        // An open segment (end === null) is still running — extend it to now
        // (generatedAt) so the domain always covers the present.
        const end = toMs(segment?.end) ?? (start !== null ? generatedAtMs : null);
        if (start !== null && (earliest === null || start < earliest)) earliest = start;
        if (end !== null && (latest === null || end > latest)) latest = end;
    }
    return { earliest, latest };
}

function positionSegment(segment, domainStartMs, scale, trackWidth) {
    const startMs = toMs(segment?.start);
    const endMs = toMs(segment?.end);
    const safeStart = startMs ?? domainStartMs;
    const rawX = (safeStart - domainStartMs) * scale;
    const rawWidth = endMs !== null && endMs !== undefined && startMs !== null
        ? Math.max(0, (endMs - startMs) * scale)
        : 0;
    // Floor short spans so they stay visible and clickable; clamp the pair
    // so the floor can never push a segment past the right edge.
    let x = Math.max(0, Math.min(rawX, trackWidth - SWIMLANE_MIN_SEGMENT_PX));
    let width = Math.max(rawWidth, SWIMLANE_MIN_SEGMENT_PX);
    if (x + width > trackWidth) width = Math.max(SWIMLANE_MIN_SEGMENT_PX, trackWidth - x);
    const durationMs = startMs !== null ? Math.max(0, (endMs ?? startMs) - startMs) : 0;
    return {
        status: segment?.status ?? null,
        label: segment?.label ?? swimlaneStatusMeta(segment?.status).description,
        start: segment?.start ?? null,
        end: segment?.end ?? null,
        isOpen: segment?.isOpen ?? endMs === null,
        milestoneCount: segment?.milestoneCount ?? 0,
        x,
        width,
        durationMs,
        durationLabel: formatSwimlaneDuration(durationMs),
    };
}

/**
 * Turn a `swimlaneTimeline` payload into positioned lanes, ticks and the
 * shared time domain. Pure — no DOM, no hooks.
 *
 * @param {object|null} data   The `swimlaneTimeline` prop payload.
 * @param {object} [options]   `{ width }` — the time-axis track width in px.
 * @returns {object|null} Layout, or null when there is no payload.
 */
export function buildSwimlaneLayout(data, options = {}) {
    if (!data || typeof data !== 'object') return null;
    const trackWidth = clampTrackWidth(options.width);
    const generatedAtMs = toMs(data.generatedAt) ?? Date.now();
    const openedAtMs = toMs(data.caseOpenedAt);
    const closedAtMs = toMs(data.caseClosedAt);

    const referrals = Array.isArray(data.referrals) ? data.referrals : [];
    const managerSegments = data.caseManagerLane?.segments ?? [];

    const allSegments = [
        ...referrals.flatMap((referral) => referral?.segments ?? []),
        ...managerSegments,
    ];
    const { earliest: earliestSegMs, latest: latestSegMs } = collectSegmentTimes(allSegments, generatedAtMs);

    // Domain = min(caseOpenedAt, earliest segment start) →
    //          max(caseClosedAt ?? generatedAt, latest segment end ?? generatedAt).
    const startCandidates = [openedAtMs, earliestSegMs].filter((value) => value !== null);
    const domainStartMs = startCandidates.length > 0 ? Math.min(...startCandidates) : generatedAtMs;
    const endCandidates = [closedAtMs ?? generatedAtMs, latestSegMs ?? generatedAtMs];
    let domainEndMs = Math.max(...endCandidates);
    if (!(domainEndMs > domainStartMs)) domainEndMs = domainStartMs + DAY_MS;

    const spanMs = domainEndMs - domainStartMs;
    const scale = trackWidth / spanMs;
    const xOf = (iso) => {
        const ms = toMs(iso);
        if (ms === null) return null;
        return Math.max(0, Math.min((ms - domainStartMs) * scale, trackWidth));
    };

    // One permanent lane per referral, stacked in ascending sentAt order.
    const sorted = [...referrals].sort((a, b) => {
        const aTime = toMs(a?.sentAt) ?? Number.NEGATIVE_INFINITY;
        const bTime = toMs(b?.sentAt) ?? Number.NEGATIVE_INFINITY;
        return aTime - bTime;
    });

    const lanes = sorted.map((referral) => {
        const terminalX = referral?.isTerminal ? xOf(referral?.terminalAt) : null;
        return {
            id: referral?.id ?? null,
            agency: referral?.agency ?? null,
            displayAgency: referral?.agency || 'Agency not specified',
            service: referral?.service ?? null,
            status: referral?.status ?? null,
            statusLabel: referral?.statusLabel ?? swimlaneStatusMeta(referral?.status).description,
            sentAt: referral?.sentAt ?? null,
            isTerminal: referral?.isTerminal ?? false,
            terminalAt: referral?.terminalAt ?? null,
            terminalX,
            segmentCount: referral?.segmentCount ?? (referral?.segments?.length ?? 0),
            milestoneCount: referral?.milestoneCount ?? (referral?.milestones?.length ?? 0),
            segments: (referral?.segments ?? []).map((segment) => positionSegment(segment, domainStartMs, scale, trackWidth)),
            milestones: (referral?.milestones ?? []).map((milestone) => ({
                at: milestone?.at ?? null,
                title: milestone?.title ?? 'Update',
                description: milestone?.description ?? null,
                x: xOf(milestone?.at),
            })),
            raw: referral,
        };
    });

    const manager = {
        segments: managerSegments.map((segment) => positionSegment(segment, domainStartMs, scale, trackWidth)),
        receivedCount: data.caseManagerLane?.receivedCount ?? 0,
        readyToClose: data.caseManagerLane?.readyToClose ?? false,
        closedAt: data.caseManagerLane?.closedAt ?? data.caseClosedAt ?? null,
    };

    // Convergence anchors: every terminal referral bends down into the
    // manager lane at its terminalAt position.
    const convergence = lanes
        .map((lane, laneIndex) => ({ laneIndex, laneId: lane.id, x: lane.terminalX }))
        .filter((anchor) => anchor.x !== null);

    return {
        domainStartMs,
        domainEndMs,
        domainStartIso: new Date(domainStartMs).toISOString(),
        domainEndIso: new Date(domainEndMs).toISOString(),
        spanMs,
        scale,
        trackWidth,
        ticks: generateTimeTicks(domainStartMs, domainEndMs, trackWidth),
        lanes,
        manager,
        convergence,
        totals: data.totals ?? {
            referrals: referrals.length,
            active: referrals.filter((referral) => !referral?.isTerminal).length,
            terminal: referrals.filter((referral) => referral?.isTerminal).length,
            milestones: lanes.reduce((sum, lane) => sum + lane.milestones.length, 0),
        },
    };
}

// ---------------------------------------------------------------------------
// Measured-width hook without layout jank: useLayoutEffect measures before
// paint, and the component renders the fallback width synchronously on the
// very first pass so there is no flash of wrong geometry.
// ---------------------------------------------------------------------------

function useMeasuredWidth(ref, explicitWidth) {
    const [measured, setMeasured] = useState(null);
    useLayoutEffect(() => {
        if (typeof explicitWidth === 'number' && explicitWidth > 0) return;
        const node = ref.current;
        if (!node || typeof ResizeObserver === 'undefined') return;
        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];
            if (entry) setMeasured(entry.contentRect.width);
        });
        observer.observe(node);
        setMeasured(node.getBoundingClientRect().width || null);
        return () => observer.disconnect();
    }, [ref, explicitWidth]);
    if (typeof explicitWidth === 'number' && explicitWidth > 0) return explicitWidth;
    return measured && measured > 0 ? measured : SWIMLANE_DEFAULT_TRACK_PX + SWIMLANE_LABEL_COL_PX;
}

// ---------------------------------------------------------------------------
// Presentational pieces
// ---------------------------------------------------------------------------

function MilestoneDot({ milestone, meta }) {
    const label = `${milestone.title}${milestone.description ? ` — ${milestone.description}` : ''}${milestone.at ? ` · ${formatTimelineAbsolute(milestone.at)}` : ''}`;
    return (
        <span className="group absolute top-1/2 z-10 -translate-x-1/2 -translate-y-1/2">
            <button
                type="button"
                aria-label={`Milestone: ${label}`}
                title={label}
                className={`block h-2.5 w-2.5 rounded-full border-2 border-white ${meta.dot} shadow outline-none transition-transform hover:scale-150 focus-visible:scale-150 focus-visible:ring-2 focus-visible:ring-indigo-500`}
            />
            <span className="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-52 -translate-x-1/2 rounded-md border border-slate-200 bg-white p-2 text-left shadow-lg group-hover:block group-focus-within:block">
                <span className="block text-[11px] font-bold text-slate-800">{milestone.title}</span>
                {milestone.description && (
                    <span className="mt-0.5 block text-[11px] leading-5 text-slate-600">{milestone.description}</span>
                )}
                {milestone.at && (
                    <span className="mt-0.5 block text-[10px] text-slate-400">{formatTimelineAbsolute(milestone.at)}</span>
                )}
            </span>
        </span>
    );
}

function LaneSegments({ segments, agency, onSelectReferral, referral }) {
    const interactive = typeof onSelectReferral === 'function';
    return (
        <>
            {segments.map((segment, index) => {
                const meta = swimlaneStatusMeta(segment.status);
                const rangeLabel = `${formatTimelineAbsolute(segment.start)} → ${segment.end ? formatTimelineAbsolute(segment.end) : 'ongoing'}`;
                const ariaLabel = `${agency} — ${segment.label}, duration ${segment.durationLabel || 'unknown'}`;
                const showInlineLabel = segment.width >= 64;
                return (
                    <div
                        key={`${segment.status}-${segment.start}-${index}`}
                        data-testid="swimlane-segment"
                        role={interactive ? 'button' : 'img'}
                        tabIndex={interactive ? 0 : undefined}
                        aria-label={ariaLabel}
                        title={`${segment.label} · ${rangeLabel} (${formatTimelineRelative(segment.start)})`}
                        onClick={interactive ? () => onSelectReferral(referral) : undefined}
                        onKeyDown={
                            interactive
                                ? (event) => {
                                      if (event.key === 'Enter' || event.key === ' ') {
                                          event.preventDefault();
                                          onSelectReferral(referral);
                                      }
                                  }
                                : undefined
                        }
                        style={{ left: segment.x, width: segment.width }}
                        className={`absolute top-1/2 flex h-7 -translate-y-1/2 items-center gap-1 overflow-hidden rounded-md border px-1.5 shadow-sm transition-filter hover:brightness-95 ${meta.bar} ${interactive ? 'cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500' : ''}`}
                    >
                        <span aria-hidden="true" className="material-symbols-outlined shrink-0 text-[14px] text-slate-700">
                            {meta.icon}
                        </span>
                        {showInlineLabel ? (
                            <span className="truncate text-[10px] font-bold uppercase tracking-wide text-slate-800">
                                {segment.label}
                            </span>
                        ) : (
                            <span className="sr-only">{segment.label}</span>
                        )}
                        {segment.isOpen && (
                            <span className="ml-auto flex shrink-0 items-center gap-0.5 text-[9px] font-bold uppercase text-slate-700">
                                <span className="relative flex h-1.5 w-1.5">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-slate-600 opacity-60" />
                                    <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-slate-700" />
                                </span>
                                Live
                            </span>
                        )}
                    </div>
                );
            })}
        </>
    );
}

// ---------------------------------------------------------------------------
// The component
// ---------------------------------------------------------------------------

/**
 * @param {object|null} props.swimlaneTimeline The backend payload (may be null while loading).
 * @param {number} [props.width]               Total available width; falls back to measured width.
 * @param {string} [props.className]           Extra classes for the wrapper.
 * @param {func} [props.onSelectReferral]      Optional `(referral) => void` — makes segments focusable/clickable.
 * @param {string} [props.agencyFilter]        Optional agency name — non-matching lanes are dimmed, never removed.
 * @param {Array} [props.axisMarkers]          Optional `[{ at, type, title }]` event highlights drawn on the ruler.
 */
export default function CaseSwimlane({
    swimlaneTimeline,
    width,
    className = '',
    onSelectReferral,
    agencyFilter = 'ALL',
    axisMarkers = [],
}) {
    const outerRef = useRef(null);
    const totalWidth = useMeasuredWidth(outerRef, width);

    const layout = useMemo(
        () => buildSwimlaneLayout(swimlaneTimeline, { width: Math.max(totalWidth - SWIMLANE_LABEL_COL_PX, SWIMLANE_MIN_TRACK_PX) }),
        [swimlaneTimeline, totalWidth],
    );

    // Calm empty state while loading (same visual language as TimelineEmptyState).
    if (!swimlaneTimeline || !layout) {
        return (
            <div className={className} data-testid="swimlane-empty">
                <TimelineEmptyState icon="timeline" title="No timeline data yet — it will appear here once the case loads." />
            </div>
        );
    }

    // Valid payload but nothing referred yet — guidance, not an error.
    if (layout.lanes.length === 0) {
        return (
            <div className={className} data-testid="swimlane-empty">
                <TimelineEmptyState
                    icon="forward_to_inbox"
                    title="No referrals yet — refer this case to an agency and each agency's progress will appear here as its own lane."
                />
            </div>
        );
    }

    const { trackWidth, ticks, lanes, manager, convergence } = layout;
    const caseClosed = Boolean(swimlaneTimeline.caseClosedAt);
    const lanesBlockHeight = lanes.length * (SWIMLANE_LANE_PX + SWIMLANE_LANE_GAP_PX);
    const managerTop = lanesBlockHeight + SWIMLANE_CONVERGE_GAP_PX;
    const totalHeight = managerTop + SWIMLANE_LANE_PX;

    const rulerDescription = `Shared time axis from ${formatTimelineAbsolute(layout.domainStartIso)} to ${formatTimelineAbsolute(layout.domainEndIso)}, ${lanes.length} referral lanes.`;

    return (
        <div ref={outerRef} className={className}>
            <p className="sr-only">{rulerDescription}</p>

            {/* Status legend — colour always paired with a text label. */}
            <div className="mb-3 flex flex-wrap items-center gap-1.5" aria-label="Status legend">
                {Object.entries(SWIMLANE_STATUS_META).map(([status, meta]) => (
                    <span
                        key={status}
                        className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold ${meta.chip}`}
                    >
                        <span aria-hidden="true" className={`h-2 w-2 rounded-full ${meta.dot}`} />
                        {meta.shortLabel}
                    </span>
                ))}
                <span className="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold text-slate-500">
                    <span aria-hidden="true" className="block h-2 w-2 rounded-full border-2 border-white bg-slate-500 shadow" />
                    Milestone
                </span>
            </div>

            {/* Ready-to-close / closed banner for the manager lane. */}
            {caseClosed ? (
                <div className="mb-3 flex items-start gap-2 rounded-md border border-slate-300 bg-slate-100 px-3 py-2.5">
                    <span aria-hidden="true" className="material-symbols-outlined mt-px text-[16px] text-slate-500">lock</span>
                    <div>
                        <p className="text-[11px] font-bold text-slate-700">Case closed</p>
                        <p className="text-[11px] leading-5 text-slate-500">
                            Closed {formatTimelineAbsolute(swimlaneTimeline.caseClosedAt)} ({formatTimelineRelative(swimlaneTimeline.caseClosedAt)}).
                        </p>
                    </div>
                </div>
            ) : (
                manager.readyToClose && (
                    <div className="mb-3 flex items-start gap-2 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2.5">
                        <span aria-hidden="true" className="material-symbols-outlined mt-px text-[16px] text-emerald-600">check_circle</span>
                        <div>
                            <p className="text-[11px] font-bold text-emerald-900">Ready to close</p>
                            <p className="text-[11px] leading-5 text-emerald-700">
                                All referrals are resolved{manager.receivedCount > 0 ? ` (${manager.receivedCount} received back)` : ''} — review the manager lane below and close the case.
                            </p>
                        </div>
                    </div>
                )
            )}

            <div className="flex overflow-hidden rounded-lg border border-slate-200 bg-white">
                {/* Pinned agency/label column — never scrolls away. */}
                <div className="w-52 shrink-0 border-r border-slate-200 bg-slate-50/70" style={{ width: SWIMLANE_LABEL_COL_PX }}>
                    <div className="flex items-center border-b border-slate-200 px-3 text-[9px] font-extrabold uppercase tracking-[0.12em] text-slate-400" style={{ height: SWIMLANE_RULER_PX }}>
                        Agency lanes
                    </div>
                    {lanes.map((lane) => {
                        const meta = swimlaneStatusMeta(lane.status);
                        const dimmed = agencyFilter !== 'ALL' && lane.agency !== agencyFilter;
                        return (
                            <div
                                key={lane.id ?? lane.displayAgency}
                                data-testid="swimlane-lane-label"
                                style={{ height: SWIMLANE_LANE_PX, marginBottom: SWIMLANE_LANE_GAP_PX }}
                                className={`flex flex-col justify-center gap-1 overflow-hidden border-b border-slate-100 px-3 transition-opacity ${dimmed ? 'opacity-40 saturate-50' : ''}`}
                            >
                                <p className="truncate text-[12px] font-bold leading-tight text-slate-800" title={lane.displayAgency}>
                                    {lane.displayAgency}
                                </p>
                                <span className="flex items-center gap-1.5">
                                    <span aria-hidden="true" className={`h-2 w-2 shrink-0 rounded-full ${meta.dot}`} />
                                    <span className="truncate text-[10px] font-semibold text-slate-500" title={lane.statusLabel}>
                                        {lane.statusLabel}
                                    </span>
                                </span>
                            </div>
                        );
                    })}
                    <div style={{ height: SWIMLANE_CONVERGE_GAP_PX }} className="flex items-center px-3">
                        <span className="text-[9px] font-extrabold uppercase tracking-[0.12em] text-slate-400">Converges ↓</span>
                    </div>
                    <div className="flex flex-col justify-center gap-1 overflow-hidden bg-indigo-50/50 px-3" style={{ height: SWIMLANE_LANE_PX }}>
                        <p className="flex items-center gap-1 text-[12px] font-bold leading-tight text-indigo-900">
                            <span aria-hidden="true" className="material-symbols-outlined text-[15px]">manage_accounts</span>
                            Case Manager
                        </p>
                        <span className="truncate text-[10px] font-semibold text-indigo-400">
                            {caseClosed ? 'Case closed' : manager.readyToClose ? 'Ready to close' : `${manager.receivedCount} received`}
                        </span>
                    </div>
                </div>

                {/* Scrollable time-axis area — ruler and lanes scroll as one so they stay in sync. */}
                <div className="min-w-0 flex-1 overflow-x-auto">
                    <div style={{ width: trackWidth }}>
                        {/* Shared date ruler. */}
                        <div
                            className="relative border-b border-slate-200 bg-white"
                            style={{ height: SWIMLANE_RULER_PX }}
                            role="img"
                            aria-label={rulerDescription}
                            data-testid="swimlane-ruler"
                        >
                            {ticks.map((tick) => (
                                <span key={tick.value} data-testid="swimlane-tick" className="absolute top-0 flex h-full flex-col" style={{ left: tick.x }}>
                                    <span aria-hidden="true" className="mt-1 h-1.5 w-px bg-slate-300" />
                                    <span className="mt-0.5 -translate-x-1 whitespace-nowrap text-[9px] font-bold uppercase tracking-wide text-slate-500">
                                        {tick.label}
                                    </span>
                                </span>
                            ))}
                            {axisMarkers.map((marker, index) => {
                                const time = new Date(marker.at).getTime();
                                if (Number.isNaN(time)) return null;
                                const x = Math.max(0, Math.min((time - layout.domainStartMs) * layout.scale, trackWidth));
                                return (
                                    <span
                                        key={`${marker.type}-${marker.at}-${index}`}
                                        data-testid="swimlane-axis-marker"
                                        title={`${marker.title ?? marker.type} · ${formatTimelineAbsolute(marker.at)}`}
                                        aria-label={`Highlighted event: ${marker.title ?? marker.type}`}
                                        className="absolute top-1 h-2 w-2 -translate-x-1/2 rotate-45 rounded-[1px] border border-white bg-indigo-500 shadow"
                                        style={{ left: x }}
                                    />
                                );
                            })}
                        </div>

                        {/* Lanes + convergence + manager lane. */}
                        <div className="relative bg-white px-0 py-0" style={{ height: totalHeight }}>
                            {/* Vertical gridlines across the full lane height. */}
                            <div aria-hidden="true" className="pointer-events-none absolute inset-0">
                                {ticks.map((tick) => (
                                    <span key={tick.value} className="absolute inset-y-0 w-px bg-slate-100" style={{ left: tick.x }} />
                                ))}
                            </div>

                            {lanes.map((lane, laneIndex) => {
                                const dimmed = agencyFilter !== 'ALL' && lane.agency !== agencyFilter;
                                return (
                                    <div
                                        key={lane.id ?? laneIndex}
                                        data-testid="swimlane-lane"
                                        data-agency={lane.agency ?? ''}
                                        className={`relative rounded-md bg-slate-50 transition-opacity ${dimmed ? 'opacity-40 saturate-50' : ''}`}
                                        style={{ height: SWIMLANE_LANE_PX, marginBottom: SWIMLANE_LANE_GAP_PX }}
                                    >
                                        <LaneSegments segments={lane.segments} agency={lane.displayAgency} onSelectReferral={onSelectReferral} referral={lane.raw} />
                                        {lane.milestones.map((milestone, milestoneIndex) =>
                                            milestone.x === null ? null : (
                                                <span key={`${milestone.at}-${milestoneIndex}`} className="absolute inset-y-0" style={{ left: milestone.x }}>
                                                    <MilestoneDot milestone={milestone} meta={swimlaneStatusMeta(lane.segments[lane.segments.length - 1]?.status ?? lane.status)} />
                                                </span>
                                            ),
                                        )}
                                        {lane.service && (
                                            <span className="pointer-events-none absolute bottom-0.5 right-1 max-w-[45%] truncate text-[9px] font-medium text-slate-400" title={lane.service}>
                                                {lane.service}
                                            </span>
                                        )}
                                    </div>
                                );
                            })}

                            {/* Dashed convergence connectors into the manager lane. */}
                            <svg
                                aria-hidden="true"
                                className="pointer-events-none absolute left-0 top-0"
                                width={trackWidth}
                                height={totalHeight}
                                data-testid="swimlane-convergence-layer"
                            >
                                {convergence.map((anchor) => {
                                    const y1 = anchor.laneIndex * (SWIMLANE_LANE_PX + SWIMLANE_LANE_GAP_PX) + SWIMLANE_LANE_PX / 2;
                                    const y2 = managerTop;
                                    return (
                                        <g key={anchor.laneId ?? anchor.laneIndex} data-testid="swimlane-convergence">
                                            <path
                                                d={`M ${anchor.x} ${y1} C ${anchor.x} ${y1 + 16}, ${anchor.x} ${y2 - 16}, ${anchor.x} ${y2}`}
                                                fill="none"
                                                stroke="#94a3b8"
                                                strokeWidth="1.5"
                                                strokeDasharray="4 3"
                                            />
                                            <circle cx={anchor.x} cy={y1} r="3" fill="#fff" stroke="#64748b" strokeWidth="1.5" />
                                            <circle cx={anchor.x} cy={y2} r="2.5" fill="#6366f1" />
                                        </g>
                                    );
                                })}
                            </svg>

                            {/* Case-manager lane pinned to the bottom. */}
                            <div
                                data-testid="swimlane-manager-lane"
                                role="img"
                                aria-label={`Case manager lane — ${manager.readyToClose ? 'ready to close' : `${manager.receivedCount} referrals received back`}`}
                                className={`relative rounded-md border-2 border-dashed ${caseClosed ? 'border-slate-300 bg-slate-50' : manager.readyToClose ? 'border-emerald-400 bg-emerald-50/60' : 'border-indigo-300 bg-indigo-50/40'}`}
                                style={{ height: SWIMLANE_LANE_PX, marginTop: SWIMLANE_CONVERGE_GAP_PX }}
                            >
                                {manager.segments.length > 0 ? (
                                    <LaneSegments segments={manager.segments} agency="Case Manager" onSelectReferral={undefined} referral={null} />
                                ) : (
                                    <span className="absolute inset-0 flex items-center gap-1.5 px-2 text-[10px] font-bold uppercase tracking-wide text-indigo-400">
                                        <span aria-hidden="true" className="material-symbols-outlined text-[14px]">pending</span>
                                        {caseClosed ? 'Closed' : manager.readyToClose ? 'Ready to close' : 'Awaiting referral outcomes'}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p className="mt-2 text-[10px] text-slate-400">
                {layout.totals.referrals} referral{layout.totals.referrals === 1 ? '' : 's'} · {layout.totals.milestones} milestone{layout.totals.milestones === 1 ? '' : 's'} ·{' '}
                {formatTimelineAbsolute(layout.domainStartIso)} → {formatTimelineAbsolute(layout.domainEndIso)}
            </p>
        </div>
    );
}
