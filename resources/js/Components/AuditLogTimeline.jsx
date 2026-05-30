import { useMemo } from 'react';
import { formatTimeAgo } from '@/lib/relativeTime';

/**
 * Action-based color and icon configuration.
 * Mirrors AuditTimeline's ACTION_STYLES for visual consistency.
 */
const ACTION_STYLES = {
    CREATE: {
        dot: 'bg-emerald-500',
        badge: 'bg-emerald-100 text-emerald-700',
        label: 'Created',
        svg: (
            <svg className="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                <line x1="8" y1="3" x2="8" y2="13" />
                <line x1="3" y1="8" x2="13" y2="8" />
            </svg>
        ),
    },
    UPDATE: {
        dot: 'bg-blue-500',
        badge: 'bg-blue-100 text-blue-700',
        label: 'Updated',
        svg: (
            <svg className="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                <path d="M11.5 1.5l3 3L5 14H2v-3L11.5 1.5z" />
            </svg>
        ),
    },
    DELETE: {
        dot: 'bg-red-500',
        badge: 'bg-red-100 text-red-700',
        label: 'Deleted',
        svg: (
            <svg className="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
                <path d="M2 4h12" />
                <path d="M5.333 4V2.667a1.333 1.333 0 011.334-1.334h2.666a1.333 1.333 0 011.334 1.334V4" />
                <path d="M3.333 4l.667 9.333a1.333 1.333 0 001.333 1.334h5.334a1.333 1.333 0 001.333-1.334L12.667 4" />
            </svg>
        ),
    },
    VIEW: {
        dot: 'bg-purple-500',
        badge: 'bg-purple-100 text-purple-700',
        label: 'Viewed',
        svg: (
            <svg className="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
                <circle cx="8" cy="8" r="2.5" />
                <path d="M1.333 8s2.667-4.667 6.667-4.667S14.667 8 14.667 8s-2.667 4.667-6.667 4.667S1.333 8 1.333 8z" />
            </svg>
        ),
    },
    PUBLISH: {
        dot: 'bg-indigo-500',
        badge: 'bg-indigo-100 text-indigo-700',
        label: 'Published',
        svg: (
            <svg className="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                <path d="M4 14V8" />
                <path d="M8 14V3" />
                <path d="M12 14V6" />
            </svg>
        ),
    },
    ARCHIVE: {
        dot: 'bg-amber-500',
        badge: 'bg-amber-100 text-amber-700',
        label: 'Archived',
        svg: (
            <svg className="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                <rect x="1.5" y="1.5" width="13" height="3.5" rx="1" />
                <path d="M2.5 5v7.5a1 1 0 001 1h9a1 1 0 001-1V5" />
                <path d="M6.5 8.5h3" />
            </svg>
        ),
    },
    UNARCHIVE: {
        dot: 'bg-teal-500',
        badge: 'bg-teal-100 text-teal-700',
        label: 'Unarchived',
        svg: (
            <svg className="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                <rect x="1.5" y="1.5" width="13" height="3.5" rx="1" />
                <path d="M2.5 5v7.5a1 1 0 001 1h9a1 1 0 001-1V5" />
                <path d="M5.5 7.5l2.5-2.5 2.5 2.5" />
                <path d="M8 5v6.5" />
            </svg>
        ),
    },
    LOGIN: {
        dot: 'bg-slate-500',
        badge: 'bg-slate-100 text-slate-700',
        label: 'Logged in',
        svg: (
            <svg className="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                <path d="M5.333 11.333H3.333a1.333 1.333 0 01-1.333-1.333V3.333A1.333 1.333 0 013.333 2h2" />
                <path d="M10.667 11.333h2a1.333 1.333 0 001.333-1.333V3.333A1.333 1.333 0 0012.667 2h-2" />
                <path d="M10.667 8l2.666-2.667L10.667 2.667" />
                <path d="M13.333 8H6" />
            </svg>
        ),
    },
};

const ACTION_DEFAULT = {
    dot: 'bg-slate-400',
    badge: 'bg-slate-100 text-slate-600',
    label: 'Activity',
    svg: (
        <svg className="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
            <circle cx="8" cy="8" r="6.5" />
            <line x1="8" y1="5" x2="8" y2="8.5" />
            <circle cx="8" cy="11" r="0.5" fill="currentColor" />
        </svg>
    ),
};

/**
 * Module badge color map — each module gets a distinct, muted color.
 */
const MODULE_STYLES = {
    CASE: 'bg-blue-50 text-blue-600 ring-blue-600/10',
    REFERRAL: 'bg-purple-50 text-purple-600 ring-purple-600/10',
    clients: 'bg-emerald-50 text-emerald-600 ring-emerald-600/10',
    MILESTONE: 'bg-amber-50 text-amber-600 ring-amber-600/10',
    auth: 'bg-slate-100 text-slate-500 ring-slate-500/10',
};

const MODULE_DEFAULT = 'bg-slate-50 text-slate-500 ring-slate-500/10';

/**
 * AuditLogTimeline — vertical timeline for client detail pages.
 *
 * @param {Object} props
 * @param {Array}  props.logs - Audit log entries (max 20, server-limited)
 */
export default function AuditLogTimeline({ logs = [] }) {
    const entries = useMemo(() => logs.slice(0, 20), [logs]);

    if (entries.length === 0) {
        return <EmptyState />;
    }

    return (
        <div className="relative">
            {/* Vertical connecting line */}
            <div
                className="absolute left-[15px] top-3 bottom-3 w-px bg-slate-200"
                aria-hidden="true"
            />

            <div className="space-y-0">
                {entries.map((log) => (
                    <AuditLogEntry key={log.id} log={log} />
                ))}
            </div>
        </div>
    );
}

function AuditLogEntry({ log }) {
    const action = ACTION_STYLES[log.action] || ACTION_DEFAULT;
    const moduleClass = MODULE_STYLES[log.module] || MODULE_DEFAULT;
    const timestamp = log.formattedTimestamp || formatTimeAgo(log.timestamp);
    const description = log.formattedDescription || log.description;

    return (
        <div className="group relative flex gap-3 py-3 px-2 -mx-2 rounded-lg transition-colors duration-150 hover:bg-slate-50/60">
            {/* Timeline node */}
            <div className="relative z-10 mt-0.5 flex-shrink-0">
                <div
                    className={`flex h-[30px] w-[30px] items-center justify-center rounded-full text-white ring-4 ring-white ${action.dot} transition-transform duration-150 group-hover:scale-110`}
                >
                    {action.svg}
                </div>
            </div>

            {/* Content */}
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    {/* Action label */}
                    <span className="text-sm font-semibold text-slate-900">
                        {action.label}
                    </span>

                    {/* Module badge */}
                    <span
                        className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset ${moduleClass}`}
                    >
                        {log.module}
                    </span>

                    {/* Timestamp — pushed to the right on wider screens */}
                    <span className="ml-auto text-xs text-slate-400 tabular-nums whitespace-nowrap">
                        {timestamp}
                    </span>
                </div>

                {/* Description */}
                {description && (
                    <p className="mt-1 text-sm leading-relaxed text-slate-600">
                        {description}
                    </p>
                )}

                {/* User attribution */}
                {log.user && (
                    <p className="mt-1 text-xs text-slate-400">
                        by {log.user.name}
                    </p>
                )}
            </div>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-10 text-center">
            <svg
                className="mx-auto mb-3 h-10 w-10 text-slate-300"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
            <p className="text-sm font-medium text-slate-500">
                No activity recorded yet.
            </p>
        </div>
    );
}
