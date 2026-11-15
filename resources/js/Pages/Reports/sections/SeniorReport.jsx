import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Bar, Doughnut } from 'react-chartjs-2';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js';

import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import { humanizeStatus } from '@/lib/statusLabels';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

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
            className="flex min-w-0 flex-col rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)] dark:border-slate-700 dark:bg-slate-900 print:break-inside-avoid"
        >
            <header className="mb-4 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <h2 className="text-[15px] font-bold text-black dark:text-white">{title}</h2>
            </header>
            <div className="flex-1 px-5 pb-4">
                {takeaway ? <p className="mb-3 text-xs text-slate-600 dark:text-slate-300">{takeaway}</p> : null}
                {children}
            </div>
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

const chartOptions = (horizontal, stacked = false) => ({
    indexAxis: horizontal ? 'y' : 'x',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: stacked }, tooltip: { enabled: true } },
    scales: {
        x: { stacked, beginAtZero: true, ticks: horizontal ? { precision: 0 } : { maxRotation: 45, minRotation: 0 }, grid: { display: horizontal } },
        y: { stacked, beginAtZero: true, ticks: horizontal ? { callback(value) { const label = this.getLabelForValue(value); return label.length > 30 ? label.slice(0, 27) + '…' : label; } } : { precision: 0 }, grid: { display: !horizontal } },
    },
});

export function BarRows({ items, vertical = false }) {
    const data = {
        labels: items.map((item) => item.label),
        datasets: [{ label: 'Count', data: items.map((item) => item.count), backgroundColor: items.map((item) => item.hex ?? '#0b5a8c'), borderRadius: 0 }],
    };
    return (
        <div>
            <div style={{ height: vertical ? 280 : Math.max(180, items.length * 42 + 40) }}>
                <Bar data={data} options={chartOptions(!vertical)} aria-label="Bar chart" role="img" />
            </div>
            <ul className="sr-only">
                {items.map((item) => <li key={item.key} className="flex justify-between gap-3"><span>{item.label}</span><strong>{item.count}</strong></li>)}
            </ul>
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
    if (stages.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3">
            <BarRows items={stages} />
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

export function StackedBars({ rows }) {
    const segments = rows[0]?.segments ?? [];
    const data = {
        labels: rows.map((row) => row.label),
        datasets: segments.map((segment) => ({
            label: segment.label,
            data: rows.map((row) => row.segments.find((item) => item.key === segment.key)?.count ?? 0),
            backgroundColor: segment.hex ?? '#0b5a8c',
            borderRadius: 0,
        })),
    };
    return (
        <div>
            <div style={{ height: Math.max(220, rows.length * 48 + 70) }}>
                <Bar data={data} options={chartOptions(true, true)} aria-label="Stacked bar chart" role="img" />
            </div>
            <div className="sr-only">
                {rows.map((row) => (
                    <div key={row.key}>
                        <div className="flex justify-between gap-3"><strong>{row.label}</strong><strong>{row.total}</strong></div>
                        <p>{row.segments.map((segment) => `${segment.label}: ${segment.count}`).join(' · ')}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

/**
 * Small part-to-whole distributions use a labelled donut; larger sets use
 * horizontal bars so every category stays visible without crowded slices.
 */
export function DonutWithList({ slices, large = false }) {
    if (slices.length > 5) return <BarRows items={slices} />;
    const chartable = slices.length >= 2 && slices.some((slice) => slice.count > 0);

    return (
        <div className="flex flex-wrap items-center gap-4">
            {chartable ? (
                <div className={`${large ? 'h-40 w-40' : 'h-32 w-32'} shrink-0`}>
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
            <ul className="min-w-[160px] flex-1 space-y-2">
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
                        <span className={chartable ? 'sr-only' : 'shrink-0 font-bold text-slate-900 dark:text-slate-100'}>
                            {slice.count} ({slice.percent}%)
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
