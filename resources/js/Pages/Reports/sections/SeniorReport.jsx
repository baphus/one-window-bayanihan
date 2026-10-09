import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Doughnut } from 'react-chartjs-2';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import { humanizeStatus } from '@/lib/statusLabels';

const FALLBACK_HUES = ['#0b5a8c', '#0891b2', '#059669', '#d97706', '#ea580c', '#e11d48', '#7c3aed', '#0b7a75'];
const EXTENDED_HUES = [...FALLBACK_HUES, '#9333ea', '#db2777', '#65a30d', '#0284c7', '#b45309', '#0d9488', '#e879f9', '#84cc16'];
export const OTHER_GRAY = '#94a3b8';

/**
 * Fill gaps and fix adjacent duplicates in slice hues so a many-slice pie
 * stays readable. Slices that already carry their own hue keep it unless it
 * matches the previous slice; missing or clashing hues take the next unused
 * palette hue. Only adjacency is de-duplicated, never the item list.
 */
export function ensureDistinctHues(slices, palette = EXTENDED_HUES) {
    const hues = Array.isArray(palette) && palette.length > 0 ? palette : FALLBACK_HUES;
    let cursor = 0;
    let prev = null;
    return slices.map((slice) => {
        let hex = slice.hex;
        if (!hex || hex === prev) {
            let guard = 0;
            while (hues[cursor % hues.length] === prev && guard <= hues.length) {
                cursor += 1;
                guard += 1;
            }
            hex = hues[cursor % hues.length];
            cursor += 1;
        } else if (hex === hues[cursor % hues.length]) {
            cursor += 1;
        }
        prev = hex;
        return hex === slice.hex ? slice : { ...slice, hex };
    });
}

/**
 * Observe a page prop that may arrive late (Inertia deferred) or never
 * (aggregate not wired yet). Returns [value, settled] — settled flips true
 * as soon as data arrives, or after the timeout so missing aggregates
 * degrade to a plain "not available" line instead of spinning forever.
 */
export function useSettledProp(key, timeoutMs = 8000) {
    const { props } = usePage();
    const value = props[key];
    const [timedOut, setTimedOut] = useState(false);

    useEffect(() => {
        if (value !== undefined) {
            setTimedOut(false);
            return undefined;
        }
        const timer = setTimeout(() => setTimedOut(true), timeoutMs);
        return () => clearTimeout(timer);
    }, [value, timeoutMs]);

    return [value, value !== undefined || timedOut];
}

/**
 * Normalize a { labels, data, colors } distribution into slices with
 * counts and percents. Labels listed in `exclude` are dropped.
 * Pass humanizeLabels: true for backend enum labels (statuses, reasons,
 * actor types, sources, client types, sex) so charts, legends, and lists
 * show senior-friendly text. Keys always stay raw for stable identity.
 */
export function toSlices(distribution, { exclude = [], humanizeLabels = false } = {}) {
    const labels = Array.isArray(distribution?.labels) ? distribution.labels : [];
    const data = Array.isArray(distribution?.data) ? distribution.data : [];
    const colors = Array.isArray(distribution?.colors) ? distribution.colors : [];
    const total = data.reduce((sum, value) => sum + Number(value ?? 0), 0);

    return labels
        .map((label, index) => {
            const count = Number(data[index] ?? 0);
            return {
                key: String(label),
                label: humanizeLabels ? humanizeStatus(label) : String(label),
                count,
                hex: colors[index] || FALLBACK_HUES[index % FALLBACK_HUES.length],
                percent: total > 0 ? Math.round((count / total) * 100) : 0,
            };
        })
        .filter((slice) => !exclude.includes(slice.key));
}

export function totalOf(slices) {
    return slices.reduce((sum, slice) => sum + Number(slice.count ?? 0), 0);
}

export function topSlice(slices) {
    if (slices.length === 0) return null;
    return slices.slice().sort((a, b) => b.count - a.count)[0];
}

/**
 * Keep the top N slices, grouping the rest into one gray "Other" row.
 * Pass totalTypes to label it "Other (N types)"; otherwise plain "Other".
 */
export function topNWithOther(slices, n, totalTypes = null) {
    const ranked = slices.slice().sort((a, b) => b.count - a.count);
    if (ranked.length <= n) return ranked;
    const head = ranked.slice(0, n);
    const rest = ranked.slice(n);
    const restCount = totalOf(rest);
    const total = totalOf(ranked);
    const otherCount = totalTypes == null ? null : Math.max(0, totalTypes - n);
    return [
        ...head.map((slice) => ({
            ...slice,
            percent: total > 0 ? Math.round((slice.count / total) * 100) : 0,
        })),
        {
            key: '__other',
            label: otherCount == null ? 'Other' : `Other (${otherCount} types)`,
            count: restCount,
            hex: OTHER_GRAY,
            percent: total > 0 ? Math.round((restCount / total) * 100) : 0,
        },
    ];
}

/**
 * Split ranked slices into a named top-N plus an honest tail aggregate for
 * ranked infographic lists. Remainder buckets (key '__other') never count as
 * named — they collapse into the tail so a long tail can never win a headline.
 * Pass totalTypes (when the backend ships an authoritative distinct count
 * beyond the shipped labels) so the tail type-count stays exact; otherwise it
 * counts the shipped tail. Returns { top, tailCount, tailCountries }.
 */
export function topNamedWithTail(slices, n, totalTypes = null) {
    const ranked = slices.slice().sort((a, b) => b.count - a.count);
    const named = ranked.filter((slice) => slice.key !== '__other');
    const top = named.slice(0, n);
    const tail = named.slice(n);
    const tailCountries = totalTypes == null ? tail.length : Math.max(0, totalTypes - top.length);
    return { top, tailCount: totalOf(tail), tailCountries };
}

export function ReportCard({ title, takeaway, children, dataTour }) {
    return (
        <section
            data-tour={dataTour}
            className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
            <h2 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-primary">
                {title}
            </h2>
            {takeaway ? (
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{takeaway}</p>
            ) : null}
            <div className="mt-4">{children}</div>
        </section>
    );
}

export function EmptyLine({ message }) {
    return (
        <p className="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{message}</p>
    );
}

export function LoadingBlock() {
    return <ChartSkeleton />;
}

export function BarRows({ items }) {
    const peak = Math.max(1, ...items.map((item) => Number(item.count ?? 0)));
    return (
        <div className="space-y-4">
            {items.map((item) => (
                <div key={item.key}>
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="text-sm font-bold text-slate-800 dark:text-slate-200">
                            {item.label}
                        </span>
                        <span className="shrink-0 text-sm font-black text-slate-900 dark:text-slate-100">
                            {item.count}
                        </span>
                    </div>
                    <div className="mt-1.5 h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div
                            className="h-full rounded-full"
                            style={{
                                width: `${Math.max(2, Math.round((Number(item.count ?? 0) / peak) * 100))}%`,
                                backgroundColor: item.hex ?? '#0b5a8c',
                            }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

/**
 * Stepped funnel for the referral pipeline. Stages cascade left-aligned
 * from the first stage downward (never centered, so small stages read as
 * intentional steps rather than stray floating lines); REJECTED leaves
 * the pipeline and renders as its own annotated exit row. Every row
 * carries its label and count inline. Slices must use raw backend keys
 * (humanized display labels ride along).
 */
const PIPELINE_STAGES = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED'];

export function FunnelSteps({ slices }) {
    const byKey = {};
    slices.forEach((slice) => {
        byKey[slice.key] = slice;
    });
    const stages = PIPELINE_STAGES.map((key) => byKey[key]).filter((stage) => stage !== undefined);
    const exited = byKey.REJECTED;
    const head = Math.max(1, ...stages.map((stage) => Number(stage.count ?? 0)));

    if (stages.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3">
            {stages.map((stage) => (
                <div key={stage.key}>
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="text-sm font-bold text-slate-800 dark:text-slate-200">
                            {stage.label}
                        </span>
                        <span className="shrink-0 text-sm font-black text-slate-900 dark:text-slate-100">
                            {stage.count}
                        </span>
                    </div>
                    <div className="mt-1">
                        <div
                            className="h-5 rounded-md"
                            style={{
                                width: `${Math.max(8, Math.round((Number(stage.count ?? 0) / head) * 100))}%`,
                                backgroundColor: stage.hex ?? '#0b5a8c',
                            }}
                        />
                    </div>
                </div>
            ))}
            {exited && Number(exited.count) > 0 ? (
                <div className="flex items-baseline justify-between gap-3 border-t border-dashed border-slate-300 pt-2 dark:border-slate-600">
                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        Left the pipeline: {exited.label}
                    </span>
                    <span className="shrink-0 text-xs font-black text-slate-700 dark:text-slate-300">
                        {exited.count}
                    </span>
                </div>
            ) : null}
        </div>
    );
}

/**
 * Stacked horizontal bars for per-group active/finished-style splits.
 * Segment widths scale against the largest group total so groups stay
 * comparable; each row names every segment count inline, plus the total.
 */
export function StackedBars({ rows }) {
    const peak = Math.max(1, ...rows.map((row) => Number(row.total ?? 0)));

    return (
        <div className="space-y-4">
            {rows.map((row) => (
                <div key={row.key}>
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="text-sm font-bold text-slate-800 dark:text-slate-200">
                            {row.label}
                        </span>
                        <span className="shrink-0 text-sm font-black text-slate-900 dark:text-slate-100">
                            {row.total}
                        </span>
                    </div>
                    <div className="mt-1.5 flex h-4 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        {row.segments.map((segment) => (
                            <div
                                key={segment.key}
                                title={`${segment.label}: ${segment.count}`}
                                style={{
                                    width: `${Math.max(segment.count > 0 ? 2 : 0, (Number(segment.count ?? 0) / peak) * 100)}%`,
                                    backgroundColor: segment.hex ?? '#0b5a8c',
                                }}
                            />
                        ))}
                    </div>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {row.segments.map((segment) => `${segment.label}: ${segment.count}`).join(' · ')}
                    </p>
                </div>
            ))}
        </div>
    );
}

/**
 * Donut strictly for 2–5-slice part-to-whole data, always paired with an
 * adjacent label — count (percent) list. Anything else renders the list
 * alone so no slice is ever hidden and no bare legend appears. Pass showAll
 * for complete many-slice pies: the chart draws every slice and the adjacent
 * list scrolls instead of truncating.
 */
export function DonutWithList({ slices, large = false, showAll = false }) {
    const chartable =
        slices.length >= 2 && (showAll || slices.length <= 5) && slices.some((slice) => slice.count > 0);

    return (
        <div className="flex flex-col items-start gap-5 sm:flex-row sm:items-center sm:gap-8">
            {chartable ? (
                <div className={`${large ? 'h-56 w-56' : 'h-44 w-44'} shrink-0`}>
                    <Doughnut
                        data={{
                            labels: slices.map((slice) => slice.label),
                            datasets: [
                                {
                                    data: slices.map((slice) => slice.count),
                                    backgroundColor: slices.map((slice) => slice.hex),
                                    borderColor: '#ffffff',
                                    borderWidth: 2,
                                },
                            ],
                        }}
                        options={{
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            cutout: '62%',
                        }}
                    />
                </div>
            ) : null}
            <ul className={`min-w-0 flex-1 space-y-2${showAll ? ' max-h-64 overflow-y-auto pr-1' : ''}`}>
                {slices.map((slice) => (
                    <li key={slice.key} className="flex items-center justify-between gap-3 text-sm">
                        <span className="flex min-w-0 items-center gap-2">
                            <span
                                className="h-3 w-3 shrink-0 rounded-full"
                                style={{ backgroundColor: slice.hex }}
                            />
                            <span className="truncate font-semibold text-slate-700 dark:text-slate-300">
                                {slice.label}
                            </span>
                        </span>
                        <span className="shrink-0 font-bold text-slate-900 dark:text-slate-100">
                            {slice.count} ({slice.percent}%)
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
