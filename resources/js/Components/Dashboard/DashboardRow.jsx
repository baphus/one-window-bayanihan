import { Link } from '@inertiajs/react';
import { MaterialSymbol } from '@/Components/Dashboard/primitives';
import { safeArray } from '@/Components/Dashboard/primitives';

/**
 * DashboardRow — the shared quick-panel row for every dashboard.
 *
 * Replaces the DashboardTable pattern: instead of a <table> with column
 * headers, each item is a clickable divide-y row with a two-line scannable
 * layout. The pill anchors the row, the title answers "what", the note
 * answers "context", and the right side carries the status badge / age flag.
 *
 * Reads at the same density across case manager, agency focal, and admin.
 */

/**
 * @param {object} props
 * @param {string} props.href         Where the row navigates.
 * @param {string} [props.pill]       Small uppercase label (tracking number, action).
 * @param {string} [props.pillTone]   Tailwind text/bg classes for the pill.
 * @param {import('react').ReactNode} props.title  The main label.
 * @param {import('react').ReactNode} [props.note] Secondary context line.
 * @param {import('react').ReactNode} [props.right] Right-side content (badge, age).
 * @param {boolean} [props.alignStart] Top-align the row instead of center.
 */
export function DashboardRow({ href, pill, pillTone = 'bg-primary/10 text-primary', title, note, right, alignStart = false }) {
    return (
        <Link
            href={href}
            className={`flex ${alignStart ? 'items-start' : 'items-center'} gap-3 px-5 py-3.5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary`}
        >
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    {pill ? (
                        <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${pillTone}`}>
                            {pill}
                        </span>
                    ) : null}
                    <span className="truncate text-[13px] font-bold text-slate-900">{title}</span>
                </div>
                {note ? <p className="mt-0.5 truncate text-[12px] text-slate-500">{note}</p> : null}
            </div>
            <div className={`flex shrink-0 items-center gap-2 ${alignStart ? 'pt-0.5' : ''}`}>
                {right}
                <MaterialSymbol name="chevron_right" className="text-[16px] text-slate-300" />
            </div>
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
                    ? 'inline-flex items-center rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-black text-white'
                    : 'text-[10px] font-bold uppercase tracking-widest text-slate-400'
            }
        >
            {parsed}d
        </span>
    );
}
