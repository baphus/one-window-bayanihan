import { useMemo } from 'react';
import { formatDisplayDateTime } from '@/lib/utils';
import { formatRelativeTime } from '@/lib/relativeTime';

/**
 * UnifiedTimeline — the single shared timeline component for the whole app.
 *
 * Every timeline renders bottom-up: the most recent event is on top and the
 * first event is on the bottom. Ordering is enforced inside this component
 * (sorted by date, newest first), so callers must NOT pre-sort or reverse
 * their items — pass them in any order.
 *
 * Variants:
 *   spine  (default) — single left spine with icon dots; used by case,
 *                       referral and agency-milestone timelines.
 *   ledger           — two-column ledger rows (fixed date column + entry);
 *                       used by the tracking and OFW case-history surfaces.
 *   plain            — no spine or ledger chrome; renders each item with
 *                       `renderItem`. Used by the audit card feed.
 *
 * Item shape (all fields optional except a date and something to show):
 *   { id, date | timestamp | created_at | createdAt, title, description,
 *     actor, agency, type, icon }
 */

export const TIMELINE_SORT_DESC = 'desc';
export const TIMELINE_SORT_ASC = 'asc';

/** Read the event date from any of the backend shapes (never changed server-side). */
export function resolveTimelineDate(item) {
    if (!item || typeof item !== 'object') return null;
    return item.date ?? item.timestamp ?? item.created_at ?? item.createdAt ?? null;
}

function parseTimelineTime(value) {
    if (!value) return NaN;
    const time = new Date(value).getTime();
    return Number.isNaN(time) ? NaN : time;
}

/**
 * Stable sort of timeline items by event date.
 * 'desc' (default) puts the most recent event first; items without a
 * parseable date keep their relative order at the end.
 */
export function sortTimelineItems(items, sortOrder = TIMELINE_SORT_DESC) {
    if (!Array.isArray(items)) return [];
    const direction = sortOrder === TIMELINE_SORT_ASC ? 1 : -1;
    return items
        .map((item, index) => ({ item, index }))
        .sort((a, b) => {
            const aTime = parseTimelineTime(resolveTimelineDate(a.item));
            const bTime = parseTimelineTime(resolveTimelineDate(b.item));
            const aValid = !Number.isNaN(aTime);
            const bValid = !Number.isNaN(bTime);
            if (aValid && bValid && aTime !== bTime) {
                return (aTime - bTime) * direction;
            }
            if (aValid !== bValid) {
                return aValid ? -1 : 1;
            }
            return a.index - b.index;
        })
        .map((entry) => entry.item);
}

/** Relative date label ("Today", "3 days ago"). Never throws on bad input. */
export function formatTimelineRelative(value) {
    if (!value) return '';
    try {
        return formatRelativeTime(value);
    } catch {
        return '';
    }
}

/** Absolute date label ("January 2, 2026, 03:04 PM"). Never throws on bad input. */
export function formatTimelineAbsolute(value) {
    if (!value) return '';
    try {
        return formatDisplayDateTime(value);
    } catch {
        return '';
    }
}

/** Short ledger date ("02 Jan 2026"). */
export function formatLedgerDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('en-PH', { day: '2-digit', month: 'short', year: 'numeric' });
}

/** Short ledger time ("3:04 PM"). */
export function formatLedgerTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true });
}

/** Compact age label for ledger rows ("today", "yesterday", "3 days ago"). */
export function formatLedgerAge(value) {
    const time = parseTimelineTime(value);
    if (Number.isNaN(time)) return '';
    // Clamp future dates to today: entries stamped ahead of the local clock
    // (skew or scheduled items) must never render a negative age.
    const diffDays = Math.max(0, Math.floor((Date.now() - time) / 86400000));
    if (diffDays <= 0) return 'today';
    if (diffDays === 1) return 'yesterday';
    if (diffDays < 30) return `${diffDays} days ago`;
    const months = Math.floor(diffDays / 30);
    return months === 1 ? 'about a month ago' : `about ${months} months ago`;
}

/** Machine-readable dateTime value for <time> elements (undefined when unparseable). */
function toDateTimeAttr(value) {
    const time = parseTimelineTime(value);
    return Number.isNaN(time) ? undefined : new Date(time).toISOString();
}

const DEFAULT_SPINE_DOT = 'bg-slate-100 border-slate-200 text-slate-500';
const DEFAULT_SPINE_ICON = 'circle';

/**
 * Default dot/icon config for spine rows (Material Symbols names).
 * Covers every event type used by the case, referral and milestone surfaces.
 * Callers can override per type through the `eventConfig` prop.
 */
export const DEFAULT_EVENT_CONFIG = {
    case_opened: { dot: 'bg-blue-50 border-blue-200 text-blue-600', icon: 'folder' },
    referral_sent: { dot: 'bg-purple-50 border-purple-200 text-purple-600', icon: 'forward_to_inbox' },
    referral_status_changed: { dot: 'bg-amber-50 border-amber-200 text-amber-600', icon: 'sync_alt' },
    milestone_added: { dot: 'bg-emerald-50 border-emerald-200 text-emerald-600', icon: 'flag' },
    case_closed: { dot: 'bg-slate-100 border-slate-200 text-slate-600', icon: 'lock' },
    case_reopened: { dot: 'bg-blue-50 border-blue-200 text-blue-600', icon: 'lock_open' },
    client_request: { dot: 'bg-blue-50 border-blue-200 text-blue-600', icon: 'outgoing_mail' },
    client_response: { dot: 'bg-cyan-50 border-cyan-200 text-cyan-700', icon: 'reply' },
    client_request_status: { dot: 'bg-indigo-50 border-indigo-200 text-indigo-600', icon: 'published_with_changes' },
};

/**
 * Legacy type names mapped to their canonical config key, so alternate
 * backend labels share one style object instead of duplicating it.
 */
const EVENT_TYPE_ALIASES = {
    referral_status: 'referral_status_changed',
    milestone: 'milestone_added',
};

/**
 * Default icon/tone config for ledger rows (tracking + OFW case history).
 * Shared by both surfaces so they can never drift apart again.
 */
export const LEDGER_EVENT_STYLE = {
    case_opened: { icon: 'folder_open', tone: 'text-blue-500' },
    referral_sent: { icon: 'send', tone: 'text-emerald-500' },
    referral_status_changed: { icon: 'sync_alt', tone: 'text-amber-500' },
    milestone_added: { icon: 'flag', tone: 'text-orange-500' },
    case_closed: { icon: 'verified', tone: 'text-green-600' },
    case_reopened: { icon: 'restart_alt', tone: 'text-purple-500' },
};

const DEFAULT_LEDGER_STYLE = { icon: 'flag', tone: 'text-slate-400' };

function resolveSpineConfig(item, eventConfig) {
    const type = item?.type;
    const canonical = EVENT_TYPE_ALIASES[type] ?? type;
    return (
        eventConfig?.[type] ??
        eventConfig?.[canonical] ??
        DEFAULT_EVENT_CONFIG[canonical] ?? {
            dot: DEFAULT_SPINE_DOT,
            icon: item?.icon ?? DEFAULT_SPINE_ICON,
        }
    );
}

function resolveLedgerStyle(item, eventConfig) {
    return (
        eventConfig?.[item?.type] ??
        LEDGER_EVENT_STYLE[item?.type] ?? {
            ...DEFAULT_LEDGER_STYLE,
            icon: item?.icon ?? DEFAULT_LEDGER_STYLE.icon,
        }
    );
}

function itemKey(item, index) {
    // Prefer backend ids; otherwise fall back to a stable date-plus-index
    // key so id-less rows keep their React state across re-sorts.
    if (item?.id !== undefined && item?.id !== null && item?.id !== '') {
        return item.id;
    }
    return `${resolveTimelineDate(item) ?? 'no-date'}-${index}`;
}

export function TimelineEmptyState({ icon = 'history', title = 'No timeline events recorded.', action = null }) {
    return (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-10 text-center">
            <span className="material-symbols-outlined mb-2 block text-4xl text-slate-300">{icon}</span>
            <p className="text-sm text-slate-500">{title}</p>
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}

function SpineRow({ item, config, showRelative, showAbsolute, formatRelative, formatAbsolute, renderItem }) {
    if (renderItem) {
        return renderItem({ item, config });
    }
    const dateValue = resolveTimelineDate(item);
    const relative = showRelative ? (formatRelative ?? formatTimelineRelative)(dateValue) : '';
    const absolute = showAbsolute ? (formatAbsolute ?? formatTimelineAbsolute)(dateValue) : '';
    const dateTime = toDateTimeAttr(dateValue);

    return (
        <li className="relative flex items-start gap-4">
            <div className={`z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border bg-white shadow-sm ${config.dot}`}>
                <span className="material-symbols-outlined text-[14px]">{config.icon}</span>
            </div>
            <div className="min-w-0 flex-1 pt-0.5">
                <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    {relative && (
                        <time dateTime={dateTime} className="text-[11px] font-bold uppercase tracking-tight text-slate-500">{relative}</time>
                    )}
                    {absolute && (
                        <time dateTime={dateTime} className="hidden text-[10px] font-medium text-slate-400 sm:inline">{absolute}</time>
                    )}
                    {item.agency && (
                        <span className="border-l border-slate-200 pl-2 text-[11px] font-semibold text-slate-400">
                            {item.agency}
                        </span>
                    )}
                </div>
                {item.title && <h4 className="mt-1 text-sm font-bold leading-snug text-slate-900">{item.title}</h4>}
                {item.description && (
                    <p className="mt-1 max-w-prose text-xs leading-relaxed text-slate-500">{item.description}</p>
                )}
                {item.actor && <p className="mt-0.5 text-[10px] text-slate-400">{item.actor}</p>}
            </div>
        </li>
    );
}

function LedgerRow({ item, style, renderItem }) {
    if (renderItem) {
        return renderItem({ item, config: style });
    }
    const dateValue = resolveTimelineDate(item);
    const dateTime = toDateTimeAttr(dateValue);

    return (
        <li className="grid grid-cols-1 gap-x-5 gap-y-0.5 border-t border-slate-200 py-3 first:border-t-0 sm:grid-cols-[7.5rem_1fr]">
            <div className="pt-0.5">
                <p className="font-mono text-xs tabular-nums text-slate-500"><time dateTime={dateTime}>{formatLedgerDate(dateValue)}</time></p>
                <p className="hidden font-mono text-[11px] tabular-nums text-slate-400 sm:block">
                    <time dateTime={dateTime}>{formatLedgerTime(dateValue)}</time>
                </p>
            </div>
            <div className="min-w-0">
                <div className="flex items-start gap-2">
                    <span aria-hidden="true" className={`material-symbols-outlined mt-px text-[16px] ${style.tone}`}>
                        {style.icon}
                    </span>
                    <div className="min-w-0">
                        <p className="text-sm font-semibold leading-snug text-slate-800">{item.title}</p>
                        {item.description && (
                            <p className="mt-0.5 max-w-prose text-[13px] leading-relaxed text-slate-600">
                                {item.description}
                            </p>
                        )}
                        <p className="mt-0.5 text-[11px] text-slate-400">{formatLedgerAge(dateValue)}</p>
                    </div>
                </div>
            </div>
        </li>
    );
}

/**
 * @param {Array}  props.items          Timeline items in any order (sorted newest-first here)
 * @param {string} [props.sortOrder]    'desc' (default, newest first) or 'asc'
 * @param {Object} [props.eventConfig]  Per-type { dot, icon, tone } overrides
 * @param {string} [props.variant]      'spine' | 'ledger' | 'plain'
 * @param {bool}   [props.showRelative] Show the relative date label (spine rows only;
 *                                      the plain variant leaves all date rendering to renderItem)
 * @param {bool}   [props.showAbsolute] Show the absolute date label (spine rows only;
 *                                      the plain variant leaves all date rendering to renderItem)
 * @param {node}   [props.filters]      Optional filter controls rendered above the list
 * @param {node}   [props.resultCount]  Optional "Showing X of Y" line above the list
 * @param {node}   [props.footerActions] Optional actions rendered below the list (e.g. + Add Milestone)
 * @param {node}   [props.headerActions] Deprecated alias of footerActions (kept for older callers)
 * @param {string} [props.emptyTitle]   Empty-state message
 * @param {string} [props.emptyIcon]    Empty-state Material Symbol
 * @param {node}   [props.emptyAction]  Optional action inside the empty state
 * @param {func}   [props.renderItem]   Custom row renderer ({ item, index, config })
 */
export default function UnifiedTimeline({
    items = [],
    sortOrder = TIMELINE_SORT_DESC,
    eventConfig = {},
    variant = 'spine',
    showRelative = true,
    showAbsolute = true,
    formatRelative,
    formatAbsolute,
    filters = null,
    resultCount = null,
    footerActions = null,
    headerActions = null,
    emptyTitle = 'No timeline events recorded.',
    emptyIcon = 'history',
    emptyAction = null,
    renderItem = null,
    className = '',
    listClassName = '',
}) {
    const sorted = useMemo(() => sortTimelineItems(items, sortOrder), [items, sortOrder]);
    // Deprecated alias: older callers pass headerActions for the same slot.
    const footer = footerActions ?? headerActions;

    if (sorted.length === 0) {
        return (
            <div className={className}>
                {filters}
                {resultCount}
                <div className="mt-4">
                    <TimelineEmptyState icon={emptyIcon} title={emptyTitle} action={emptyAction} />
                </div>
                {footer}
            </div>
        );
    }

    if (variant === 'ledger') {
        return (
            <div className={className}>
                {filters}
                {resultCount}
                <ul className={listClassName}>
                    {sorted.map((item, index) => (
                        <LedgerRow
                            key={itemKey(item, index)}
                            item={item}
                            style={resolveLedgerStyle(item, eventConfig)}
                            renderItem={renderItem ? (ctx) => renderItem({ ...ctx, index }) : null}
                        />
                    ))}
                </ul>
                {footer}
            </div>
        );
    }

    if (variant === 'plain') {
        return (
            <div className={className}>
                {filters}
                {resultCount}
                <ul className={`space-y-3 ${listClassName}`}>
                    {sorted.map((item, index) =>
                        renderItem ? (
                            <li key={itemKey(item, index)}>{renderItem({ item, index })}</li>
                        ) : (
                            <SpineRow
                                key={itemKey(item, index)}
                                item={item}
                                config={resolveSpineConfig(item, eventConfig)}
                                showRelative={showRelative}
                                showAbsolute={showAbsolute}
                                formatRelative={formatRelative}
                                formatAbsolute={formatAbsolute}
                            />
                        ),
                    )}
                </ul>
                {footer}
            </div>
        );
    }

    return (
        <div className={className}>
            {filters}
            {resultCount}
            <div className="relative mt-4">
                <div className="absolute bottom-2 left-[13px] top-2 w-px bg-slate-200" />
                <ul className={`space-y-6 ${listClassName}`}>
                    {sorted.map((item, index) => (
                        <SpineRow
                            key={itemKey(item, index)}
                            item={item}
                            config={resolveSpineConfig(item, eventConfig)}
                            showRelative={showRelative}
                            showAbsolute={showAbsolute}
                            formatRelative={formatRelative}
                            formatAbsolute={formatAbsolute}
                            renderItem={renderItem ? (ctx) => renderItem({ ...ctx, index }) : null}
                        />
                    ))}
                </ul>
            </div>
            {footer}
        </div>
    );
}
