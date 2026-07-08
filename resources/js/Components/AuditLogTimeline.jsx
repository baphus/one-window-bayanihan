import { useMemo } from 'react';
import { formatDisplayDateTime } from '@/lib/utils';

/**
 * Map action + module to a human-readable uppercase activity label.
 */
function getActivityType(action, module) {
    const act = (action || '').toUpperCase();
    const mod = (module || '').toUpperCase();

    // Action-only mappings
    if (act === 'LOGIN') return 'USER LOGIN';
    if (act === 'LOGOUT') return 'USER LOGOUT';
    if (act === 'PUBLISH') return 'PUBLISHED';
    if (act === 'ARCHIVE') return 'ARCHIVED';
    if (act === 'UNARCHIVE') return 'UNARCHIVED';

    // Module + action combinations
    if (['CASE', 'CASES', 'CASE_FILES'].includes(mod)) {
        if (act === 'CREATE') return 'CASE OPENED';
        if (act === 'UPDATE') return 'CASE UPDATED';
        if (act === 'DELETE') return 'CASE DELETED';
    }

    if (['REFERRAL', 'REFERRALS'].includes(mod)) {
        if (act === 'CREATE') return 'REFERRAL CREATED';
        if (act === 'UPDATE') return 'REFERRAL UPDATED';
        if (act === 'DELETE') return 'REFERRAL DELETED';
    }

    if (['CLIENTS', 'CLIENT'].includes(mod)) {
        if (act === 'CREATE') return 'CLIENT REGISTERED';
        if (act === 'UPDATE') return 'CLIENT UPDATED';
        if (act === 'DELETE') return 'CLIENT DELETED';
    }

    if (['MILESTONE', 'MILESTONES'].includes(mod)) {
        if (act === 'CREATE') return 'MILESTONE ACHIEVED';
        if (act === 'UPDATE') return 'MILESTONE UPDATED';
    }

    if (['USERS', 'USER'].includes(mod)) {
        if (act === 'CREATE') return 'USER REGISTERED';
    }

    // Fallback
    return (act || 'ACTIVITY');
}

/**
 * Map module to a concise entity type label.
 */
function getEntityLabel(module) {
    const mod = (module || '').toUpperCase();

    if (['CASE', 'CASES', 'CASE_FILES'].includes(mod)) return 'Case';
    if (['REFERRAL', 'REFERRALS'].includes(mod)) return 'Referral';
    if (['CLIENTS', 'CLIENT'].includes(mod)) return 'Client';
    if (['MILESTONE', 'MILESTONES'].includes(mod)) return 'Milestone';
    if (['USERS', 'USER'].includes(mod)) return 'User';
    if (['AGENCIES', 'AGENCY'].includes(mod)) return 'Agency';

    return module || 'Record';
}

/**
 * Calculate days elapsed since a given timestamp.
 */
function daysAgo(timestamp) {
    const now = new Date();
    const then = new Date(timestamp);
    const diffMs = now.getTime() - then.getTime();
    return Math.max(1, Math.round(diffMs / (1000 * 60 * 60 * 24)));
}

/**
 * AuditLogTimeline — flat card-based activity feed for client detail pages.
 *
 * @param {Object}  props
 * @param {Array}   props.logs  - Audit log entries (server-limited, sliced to 50 client-side)
 * @param {Object|null} props.client - Full client object; used for case_file.case_number in metadata
 */
export default function AuditLogTimeline({ logs = [], client = null }) {
    const entries = useMemo(() => {
        return logs.slice(0, 50).map((log) => {
            const timestamp = log.formattedTimestamp || log.timestamp;

            return {
                id: log.id,
                type: getActivityType(
                    log.formattedAction || log.action,
                    log.formattedModule || log.module,
                ),
                entityType: getEntityLabel(log.formattedModule || log.module),
                details: log.formattedDescription || log.message || log.description || '',
                changes: log.changes || [],
                actorName: log.actor || log.user?.name || '',
                timestamp,
                caseNo: client?.caseFile?.case_number || null,
                daysSince: daysAgo(timestamp),
            };
        });
    }, [logs, client]);

    if (entries.length === 0) {
        return <EmptyState />;
    }

    return (
        <div className="space-y-3">
            {entries.map((entry, idx) => (
                <div key={entry.id || `${entry.timestamp}-${idx}`} className="rounded-[3px] border border-slate-200 bg-slate-50 p-3">
                    {/* Activity type — uppercase blue badge */}
                    <p className="text-[11px] font-extrabold uppercase tracking-[0.1em] text-blue-900">
                        {entry.type}
                    </p>

                    {/* Details narrative */}
                    {entry.details && (
                        <p className="mt-1 text-[12px] text-slate-700">{entry.details}</p>
                    )}

                    {/* Changes table */}
                    <ChangesTable changes={entry.changes} />

                    {/* Metadata line */}
                    <p className="mt-1 text-[10px] text-slate-500">
                        {entry.caseNo && <>Case {entry.caseNo} &bull; </>}
                        {entry.entityType} &bull;{' '}
                        {formatDisplayDateTime(entry.timestamp)} &bull;{' '}
                        {entry.actorName || 'System'} &bull;{' '}
                        {entry.daysSince} day{entry.daysSince > 1 ? 's' : ''}
                    </p>
                </div>
            ))}
        </div>
    );
}

/**
 * ChangesTable — compact inline table showing field-level changes.
 * Shows up to 3 rows inline, then "+N more" if there are additional changes.
 */
function ChangesTable({ changes }) {
    if (!changes || changes.length === 0) return null;

    const visible = changes.slice(0, 3);
    const remaining = changes.length - 3;

    return (
        <div className="mt-2 overflow-hidden rounded-[2px] border border-slate-200 bg-white/60">
            <table className="w-full border-collapse text-[11px] leading-tight">
                <tbody>
                    {visible.map((change, idx) => (
                        <tr key={idx} className={idx > 0 ? 'border-t border-slate-100' : ''}>
                            <td className="min-w-[80px] max-w-[100px] truncate px-2 py-[3px] font-medium text-slate-500">
                                {change.fieldLabel || change.field}
                            </td>
                            <td className="px-1 py-[3px] text-slate-400 line-through">
                                {change.old ?? '—'}
                            </td>
                            <td className="px-1 py-[3px] text-slate-700">
                                {change.new ?? '—'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {remaining > 0 && (
                <p className="border-t border-slate-100 px-2 py-[3px] text-[10px] text-slate-400">
                    +{remaining} more
                </p>
            )}
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
