import { useMemo } from 'react';
import { formatRelativeTime, formatDateGroup } from '@/lib/relativeTime';
import { formatDisplayDateTime } from '@/lib/utils';

/**
 * Map action + module to a human-readable activity label.
 */
function getActivityType(action, module) {
    const act = (action || '').toUpperCase();
    const mod = (module || '').toUpperCase();

    if (act === 'LOGIN') return 'User Login';
    if (act === 'LOGOUT') return 'User Logout';
    if (act === 'PUBLISH') return 'Published';
    if (act === 'ARCHIVE') return 'Archived';
    if (act === 'UNARCHIVE') return 'Unarchived';

    if (['CASE', 'CASES', 'CASE_FILES'].includes(mod)) {
        if (act === 'CREATE') return 'Case Opened';
        if (act === 'UPDATE') return 'Case Updated';
        if (act === 'DELETE') return 'Case Deleted';
    }

    if (['REFERRAL', 'REFERRALS'].includes(mod)) {
        if (act === 'CREATE') return 'Referral Created';
        if (act === 'UPDATE') return 'Referral Updated';
        if (act === 'DELETE') return 'Referral Deleted';
    }

    if (['CLIENTS', 'CLIENT'].includes(mod)) {
        if (act === 'CREATE') return 'Client Registered';
        if (act === 'UPDATE') return 'Client Updated';
        if (act === 'DELETE') return 'Client Deleted';
    }

    if (['MILESTONE', 'MILESTONES'].includes(mod)) {
        if (act === 'CREATE') return 'Milestone Achieved';
        if (act === 'UPDATE') return 'Milestone Updated';
    }

    if (['USERS', 'USER'].includes(mod)) {
        if (act === 'CREATE') return 'User Registered';
    }

    return act.charAt(0) + act.slice(1).toLowerCase() || 'Activity';
}

/**
 * Icon per module type — small inline SVGs for the timeline nodes.
 */
function ActivityIcon({ module }) {
    const mod = (module || '').toUpperCase();

    if (['CASE', 'CASES', 'CASE_FILES'].includes(mod)) {
        return (
            <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
            </svg>
        );
    }

    if (['REFERRAL', 'REFERRALS'].includes(mod)) {
        return (
            <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M7 17l9.2-9.2M17 17V7H7" />
            </svg>
        );
    }

    if (['CLIENTS', 'CLIENT'].includes(mod)) {
        return (
            <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
            </svg>
        );
    }

    if (['MILESTONE', 'MILESTONES'].includes(mod)) {
        return (
            <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="9 11 12 14 22 4" />
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            </svg>
        );
    }

    // Default: clock icon
    return (
        <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
    );
}

/**
 * Color mapping for the timeline node dot based on action type.
 */
function getNodeColor(action) {
    const act = (action || '').toUpperCase();
    if (act === 'CREATE') return 'bg-emerald-500';
    if (act === 'UPDATE') return 'bg-blue-500';
    if (act === 'DELETE') return 'bg-red-500';
    if (act === 'PUBLISH') return 'bg-indigo-500';
    if (act === 'ARCHIVE' || act === 'UNARCHIVE') return 'bg-amber-500';
    return 'bg-slate-400';
}

function getNodeRingColor(action) {
    const act = (action || '').toUpperCase();
    if (act === 'CREATE') return 'ring-emerald-100';
    if (act === 'UPDATE') return 'ring-blue-100';
    if (act === 'DELETE') return 'ring-red-100';
    if (act === 'PUBLISH') return 'ring-indigo-100';
    if (act === 'ARCHIVE' || act === 'UNARCHIVE') return 'ring-amber-100';
    return 'ring-slate-100';
}

/**
 * AuditLogTimeline — vertical timeline with date-grouped entries.
 *
 * @param {Object}  props
 * @param {Array}   props.logs  - Audit log entries (server-limited, sliced to 50 client-side)
 * @param {Object|null} props.client - Full client object; used for case_file.case_number in metadata
 */
export default function AuditLogTimeline({ logs = [], client = null }) {
    const grouped = useMemo(() => {
        const entries = logs.slice(0, 50).map((log) => {
            const timestamp = log.formattedTimestamp || log.timestamp;
            return {
                id: log.id,
                action: log.formattedAction || log.action || '',
                module: log.formattedModule || log.module || '',
                type: getActivityType(
                    log.formattedAction || log.action,
                    log.formattedModule || log.module,
                ),
                details: log.formattedDescription || log.message || log.description || '',
                actorName: log.actor || log.user?.name || '',
                timestamp,
                caseNo: client?.case_file?.case_number || null,
            };
        });

        // Group by date bucket (Today, Yesterday, This Week, etc.)
        const groups = [];
        let currentGroup = null;

        for (const entry of entries) {
            const groupLabel = formatDateGroup(entry.timestamp);
            if (!currentGroup || currentGroup.label !== groupLabel) {
                currentGroup = { label: groupLabel, entries: [] };
                groups.push(currentGroup);
            }
            currentGroup.entries.push(entry);
        }

        return groups;
    }, [logs, client]);

    if (grouped.length === 0) {
        return <EmptyState />;
    }

    return (
        <div className="space-y-5">
            {grouped.map((group) => (
                <div key={group.label}>
                    {/* Date group header */}
                    <div className="flex items-center gap-2 mb-3">
                        <span className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-400">
                            {group.label}
                        </span>
                        <div className="flex-1 h-px bg-slate-200" />
                    </div>

                    {/* Timeline entries */}
                    <div className="relative ml-3">
                        {/* Vertical line */}
                        <div className="absolute left-[7px] top-2 bottom-2 w-px bg-slate-200" />

                        <div className="space-y-0">
                            {group.entries.map((entry, index) => (
                                <div key={entry.id || `${group.label}-${index}`} className="relative flex gap-3 pb-4 last:pb-0">
                                    {/* Timeline node */}
                                    <div className="relative z-10 flex-shrink-0 mt-0.5">
                                        <div className={`h-[15px] w-[15px] rounded-full ${getNodeColor(entry.action)} ring-[3px] ${getNodeRingColor(entry.action)} flex items-center justify-center`}>
                                            <div className="h-[5px] w-[5px] rounded-full bg-white" />
                                        </div>
                                    </div>

                                    {/* Content */}
                                    <div className="flex-1 min-w-0 -mt-0.5">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex items-center gap-1.5 min-w-0">
                                                <span className="text-slate-400">
                                                    <ActivityIcon module={entry.module} />
                                                </span>
                                                <span className="text-[12px] font-semibold text-slate-800 truncate">
                                                    {entry.type}
                                                </span>
                                            </div>
                                            <span className="text-[10px] text-slate-400 whitespace-nowrap flex-shrink-0">
                                                {formatRelativeTime(entry.timestamp)}
                                            </span>
                                        </div>

                                        {entry.details && (
                                            <p className="mt-0.5 text-[11px] text-slate-600 leading-relaxed line-clamp-2">
                                                {entry.details}
                                            </p>
                                        )}

                                        <div className="mt-1 flex items-center gap-1.5 text-[10px] text-slate-400">
                                            {entry.actorName && (
                                                <span className="font-medium text-slate-500">{entry.actorName}</span>
                                            )}
                                            {entry.actorName && entry.caseNo && <span>&middot;</span>}
                                            {entry.caseNo && <span>Case {entry.caseNo}</span>}
                                            {(entry.actorName || entry.caseNo) && <span>&middot;</span>}
                                            <span>{formatDisplayDateTime(entry.timestamp)}</span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="rounded-[3px] border border-dashed border-slate-300 bg-slate-50/50 py-8 px-6 text-center">
            <svg
                className="mx-auto mb-2 h-8 w-8 text-slate-300"
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
            <p className="text-[12px] font-medium text-slate-500">
                No activity recorded yet.
            </p>
        </div>
    );
}
