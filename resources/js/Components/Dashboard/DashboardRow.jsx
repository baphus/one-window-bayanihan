import { Link } from '@inertiajs/react';
import { safeArray } from '@/Components/Dashboard/primitives';

/**
 * DashboardRow — the shared queue row for every dashboard.
 *
 * One grammar across roles: identifier, what it is, one context line, then the
 * status/age on the right. The whole row is the link, so no chevron.
 */

/**
 * @param {object} props
 * @param {string} props.href         Where the row navigates.
 * @param {string} [props.pill]       Identifier (case number, reference).
 * @param {import('react').ReactNode} props.title  The main label.
 * @param {import('react').ReactNode} [props.note] Secondary context line.
 * @param {import('react').ReactNode} [props.right] Right-side content (badge, age).
 * @param {boolean} [props.alignStart] Top-align the row instead of center.
 */
export function DashboardRow({ href, pill, title, note, right, alignStart = false }) {
    return (
        <Link
            href={href}
            className={`flex ${alignStart ? 'items-start' : 'items-center'} gap-3 px-4 py-2.5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary`}
        >
            <div className="min-w-0 flex-1">
                <div className="flex items-baseline gap-2">
                    {pill ? (
                        <span className="shrink-0 font-mono text-[11px] font-semibold tabular-nums text-slate-500">
                            {pill}
                        </span>
                    ) : null}
                    <span className="truncate text-[13px] font-semibold text-slate-900">{title}</span>
                </div>
                {note ? <p className="mt-0.5 truncate text-[11.5px] text-slate-500">{note}</p> : null}
            </div>
            {right ? (
                <div className={`flex shrink-0 items-center gap-2 ${alignStart ? 'pt-0.5' : ''}`}>{right}</div>
            ) : null}
        </Link>
    );
}

/**
 * A divide-y list of DashboardRows. Falls back to the `empty` node when the
 * list has no items.
 */
export function DashboardRowList({ children, empty }) {
    const rows = safeArray(children);

    if (rows.length === 0) return empty ?? null;

    return <div className="divide-y divide-slate-100">{rows}</div>;
}

/**
 * A compact age flag. Overdue items render in rose; everything else is muted.
 */
export function RowAgeFlag({ days, overdueDays = 7 }) {
    const parsed = Number(days ?? 0);
    const overdue = parsed >= overdueDays;

    return (
        <span
            className={
                overdue
                    ? 'inline-flex items-center rounded bg-rose-600 px-1.5 py-px text-[10px] font-bold tabular-nums text-white'
                    : 'text-[11px] font-semibold tabular-nums text-slate-400'
            }
        >
            {parsed}d
        </span>
    );
}
