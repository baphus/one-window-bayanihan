import { useState, useEffect, useCallback } from 'react';
import { formatDisplayDateTime } from '@/lib/utils';

/**
 * AuditLogModal — full-screen modal displaying paginated audit log entries
 * for a given entity (case or referral).
 *
 * @param {boolean}  show       - Whether the modal is visible
 * @param {function} onClose    - Callback to close the modal
 * @param {'case'|'referral'} entityType - The entity type to fetch logs for
 * @param {string}   entityId   - UUID of the entity
 * @param {string}   [title]    - Modal title (defaults to 'Activity Log')
 */
export default function AuditLogModal({ show, onClose, entityType, entityId, title = 'Activity Log' }) {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(false);
    const [cursor, setCursor] = useState(null);
    const [hasMore, setHasMore] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);

    const buildUrl = useCallback((nextCursor = null) => {
        const base = entityType === 'case'
            ? `/api/cases/${entityId}/audit-logs`
            : `/api/referrals/${entityId}/audit-logs`;
        return nextCursor ? `${base}?cursor=${encodeURIComponent(nextCursor)}` : base;
    }, [entityType, entityId]);

    const fetchLogs = useCallback(async (nextCursor = null) => {
        const isInitial = !nextCursor;
        if (isInitial) {
            setLoading(true);
        } else {
            setLoadingMore(true);
        }

        try {
            const response = await fetch(buildUrl(nextCursor), {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch audit logs: ${response.status}`);
            }

            const data = await response.json();
            const entries = data.data || data.logs || data || [];
            const newCursor = data.next_cursor || data.meta?.next_cursor || null;

            if (isInitial) {
                setLogs(Array.isArray(entries) ? entries : []);
            } else {
                setLogs((prev) => [...prev, ...(Array.isArray(entries) ? entries : [])]);
            }

            setCursor(newCursor);
            setHasMore(!!newCursor);
        } catch (err) {
            console.error('AuditLogModal fetch error:', err);
            if (isInitial) {
                setLogs([]);
            }
            setHasMore(false);
        } finally {
            setLoading(false);
            setLoadingMore(false);
        }
    }, [buildUrl]);

    // Fetch on open
    useEffect(() => {
        if (show && entityId) {
            setLogs([]);
            setCursor(null);
            setHasMore(false);
            fetchLogs();
        }
    }, [show, entityId, fetchLogs]);

    // Escape key handler
    useEffect(() => {
        if (!show) return;
        function handleKeyDown(e) {
            if (e.key === 'Escape') {
                onClose();
            }
        }
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [show, onClose]);

    if (!show) return null;

    function handleBackdropClick(e) {
        if (e.target === e.currentTarget) {
            onClose();
        }
    }

    function handleLoadMore() {
        if (cursor && !loadingMore) {
            fetchLogs(cursor);
        }
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4"
            onClick={handleBackdropClick}
        >
            <div className="w-full max-w-2xl max-h-[85vh] flex flex-col rounded-lg border border-slate-200 bg-white shadow-lg owb-modal-animate">
                {/* Header */}
                <div className="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3.5">
                    <div className="flex items-center gap-2.5">
                        <h2 className="text-[14px] font-bold text-slate-900">{title}</h2>
                        <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600 border border-slate-200">
                            {entityType}
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors"
                    >
                        <span className="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto px-5 py-4 owb-scroll-wide">
                    {loading ? (
                        <LoadingState />
                    ) : logs.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <div className="space-y-3">
                            {logs.map((log, idx) => (
                                <LogEntry key={log.id || `${log.timestamp}-${idx}`} log={log} />
                            ))}

                            {hasMore && (
                                <div className="pt-2 text-center">
                                    <button
                                        type="button"
                                        onClick={handleLoadMore}
                                        disabled={loadingMore}
                                        className="px-4 py-1.5 text-[11px] font-bold text-blue-900 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 transition-colors disabled:opacity-50"
                                    >
                                        {loadingMore ? 'Loading...' : 'Load More'}
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * Individual audit log entry card.
 */
function LogEntry({ log }) {
    const activityType = getActivityType(
        log.formattedAction || log.action,
        log.formattedModule || log.module,
    );
    const description = log.formattedDescription || log.message || log.description || '';
    const changes = log.changes || [];
    const timestamp = log.formattedTimestamp || log.timestamp;
    const actor = log.actor || log.user?.name || 'System';
    const module = log.formattedModule || log.module || '';

    return (
        <div className="rounded-[3px] border border-slate-200 bg-slate-50 p-3">
            {/* Activity type badge */}
            <p className="text-[11px] font-extrabold uppercase tracking-[0.1em] text-blue-900">
                {activityType}
            </p>

            {/* Description */}
            {description && (
                <p className="mt-1 text-[12px] text-slate-700">{description}</p>
            )}

            {/* Changes table */}
            <ChangesTable changes={changes} />

            {/* Metadata line */}
            <p className="mt-1 text-[10px] text-slate-500">
                {module && <>{module} &bull; </>}
                {timestamp && <>{formatDisplayDateTime(timestamp)} &bull; </>}
                {actor}
            </p>
        </div>
    );
}

/**
 * Changes table showing field-level differences (old → new).
 */
function ChangesTable({ changes }) {
    if (!changes || changes.length === 0) return null;

    const visible = changes.slice(0, 5);
    const remaining = changes.length - 5;

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

/**
 * Map action + module to a human-readable activity label.
 */
function getActivityType(action, module) {
    const act = (action || '').toUpperCase();
    const mod = (module || '').toUpperCase();

    if (act === 'LOGIN') return 'USER LOGIN';
    if (act === 'LOGOUT') return 'USER LOGOUT';
    if (act === 'PUBLISH') return 'PUBLISHED';
    if (act === 'ARCHIVE') return 'ARCHIVED';
    if (act === 'UNARCHIVE') return 'UNARCHIVED';

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

    if (['MILESTONE', 'MILESTONES'].includes(mod)) {
        if (act === 'CREATE') return 'MILESTONE ACHIEVED';
        if (act === 'UPDATE') return 'MILESTONE UPDATED';
    }

    return act || 'ACTIVITY';
}

function LoadingState() {
    return (
        <div className="flex flex-col items-center justify-center py-12">
            <div className="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-blue-900" />
            <p className="mt-3 text-[12px] text-slate-500">Loading audit log…</p>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-10 text-center">
            <span className="material-symbols-outlined mx-auto mb-3 block text-[40px] text-slate-300">history</span>
            <p className="text-sm font-medium text-slate-500">No activity recorded yet.</p>
        </div>
    );
}
