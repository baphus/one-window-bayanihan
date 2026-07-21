import { useMemo } from 'react';
import { formatDisplayDateTime } from '@/lib/utils';
import { ChangesTable, getActivityType, getEntityLabel } from '@/lib/audit';

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
            const moduleLabel = log.formatted_module || log.module;
            const timestamp = log.timestamp;

            return {
                id: log.id,
                type: getActivityType(log.action, log.module),
                entityType: getEntityLabel(moduleLabel),
                details: log.message || log.description || '',
                changes: Array.isArray(log.changes) ? log.changes : [],
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
                    <ChangesTable changes={entry.changes} variant="compact" maxRows={3} />

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
