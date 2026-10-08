import { useMemo } from 'react';
import { formatDisplayDateTime, getCaseAgeInDays } from '@/lib/utils';
import { ChangesList, getActivityType, getEntityLabel } from '@/lib/audit';
import UnifiedTimeline from '@/Components/Timeline';

/**
 * AuditLogTimeline — flat card-based activity feed for client detail pages.
 *
 * Thin wrapper over UnifiedTimeline: keeps the 50-entry slice and the audit
 * card styling, while ordering (newest first) and row rendering run through
 * the shared component.
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
                timestamp,
                type: getActivityType(log.action, log.module),
                entityType: getEntityLabel(moduleLabel),
                details: log.message || '',
                changes: Array.isArray(log.changes) ? log.changes : [],
                actorName: log.actor || '',
                caseNo: client?.caseFile?.case_number || null,
                daysSince: Math.max(1, getCaseAgeInDays(timestamp)),
            };
        });
    }, [logs, client]);

    return (
        <UnifiedTimeline
            variant="plain"
            items={entries}
            emptyTitle="No activity recorded yet."
            renderItem={({ item }) => {
                const metaSegments = [
                    item.caseNo && `Case ${item.caseNo}`,
                    item.entityType,
                    formatDisplayDateTime(item.timestamp),
                    item.actorName || 'System',
                    `${item.daysSince} day${item.daysSince > 1 ? 's' : ''}`,
                ].filter(Boolean);
                return (
                    <div className="rounded-[3px] border border-slate-200 bg-slate-50 p-3">
                        <p className="text-[11px] font-extrabold uppercase tracking-[0.1em] text-blue-900">
                            {item.type}
                        </p>
                        {item.details && (
                            <p className="mt-1 text-[12px] text-slate-700">{item.details}</p>
                        )}
                        <ChangesList changes={item.changes} variant="compact" maxRows={3} />
                        {metaSegments.length > 0 && (
                            <p className="mt-1 text-[10px] text-slate-500">
                                {metaSegments.join(' • ')}
                            </p>
                        )}
                    </div>
                );
            }}
        />
    );
}
