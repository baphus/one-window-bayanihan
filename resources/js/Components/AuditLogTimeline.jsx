import { useMemo } from 'react';
import { formatDisplayDateTime } from '@/lib/utils';
import { getActivityType, getEntityLabel } from '@/lib/audit';
import AuditLogCard from '@/Components/AuditLogCard';

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
                details: log.message || '',
                changes: Array.isArray(log.changes) ? log.changes : [],
                actorName: log.actor || '',
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
                <AuditLogCard
                    key={entry.id || `${entry.timestamp}-${idx}`}
                    type={entry.type}
                    details={entry.details}
                    changes={entry.changes}
                    maxRows={3}
                    meta={[
                        entry.caseNo && `Case ${entry.caseNo}`,
                        entry.entityType,
                        formatDisplayDateTime(entry.timestamp),
                        entry.actorName || 'System',
                        `${entry.daysSince} day${entry.daysSince > 1 ? 's' : ''}`,
                    ]}
                />
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
