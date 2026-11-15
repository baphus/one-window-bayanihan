import { useState } from 'react';
import { Link } from '@inertiajs/react';
import KpiCard from '@/Components/ui/KpiCard';
import safeRoute from '@/utils/safeRoute';

const numberFormatter = new Intl.NumberFormat('en-US');

export function formatCount(value) {
    const parsed = Number(value ?? 0);
    return numberFormatter.format(Number.isFinite(parsed) ? parsed : 0);
}

export function safeArray(value) {
    return Array.isArray(value) ? value.filter((item) => item !== null && item !== undefined) : [];
}

const TONES = {
    blue: { dot: 'bg-primary', hex: '#005288' },
    amber: { dot: 'bg-amber-500', hex: '#d97706' },
    orange: { dot: 'bg-orange-500', hex: '#ea580c' },
    cyan: { dot: 'bg-cyan-600', hex: '#0891b2' },
    emerald: { dot: 'bg-emerald-600', hex: '#059669' },
    rose: { dot: 'bg-rose-600', hex: '#e11d48' },
    slate: { dot: 'bg-slate-400', hex: '#94a3b8' },
};

export function toneDot(tone) {
    return TONES[tone]?.dot ?? TONES.slate.dot;
}

export function toneHex(tone) {
    return TONES[tone]?.hex ?? TONES.slate.hex;
}

export function MaterialSymbol({ name, className = '' }) {
    return (
        <span aria-hidden="true" className={`material-symbols-outlined leading-none ${className}`}>
            {name}
        </span>
    );
}

export function PageHeader({ eyebrow, title, subtitle, children }) {
    return (
        <header data-tour="dashboard-header" className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
                {eyebrow ? <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{eyebrow}</p> : null}
                <h1 className={`font-headline text-2xl font-extrabold tracking-tight text-slate-900 ${eyebrow ? 'mt-1' : ''}`}>{title}</h1>
                {subtitle ? <p className="mt-1 text-sm text-slate-500">{subtitle}</p> : null}
            </div>
            {children ? <div className="shrink-0">{children}</div> : null}
        </header>
    );
}

export function QuickActions({ actions }) {
    return (
        <div data-tour="dashboard-quick-actions" className="flex flex-wrap items-center gap-2">
            {safeArray(actions).map((action) => (
                <Link
                    key={action.route ?? action.href}
                    href={action.route ? safeRoute(action.route, action.params, action.href) : action.href}
                    className={
                        action.primary
                            ? 'inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-primary-container focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2'
                            : 'inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2'
                    }
                >
                    {action.icon ? <MaterialSymbol name={action.icon} className="text-[16px]" /> : null}
                    {action.label}
                    {Number(action.count) > 0 ? (
                        <span className={`rounded px-1.5 py-0.5 text-[10px] font-black ${action.primary ? 'bg-white/20 text-white' : 'bg-primary/10 text-primary'}`}>
                            {formatCount(action.count)}
                        </span>
                    ) : null}
                </Link>
            ))}
        </div>
    );
}

export function StatRow({ stats, dataTour }) {
    return (
        <section data-tour={dataTour} className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {safeArray(stats).map((stat) => (
                <KpiCard
                    key={stat.title}
                    title={stat.title}
                    value={formatCount(stat.value) + (stat.suffix ?? '')}
                    icon={stat.icon}
                    iconBg={stat.iconBg ?? 'bg-blue-50'}
                    iconColor={stat.iconColor ?? 'text-primary'}
                    description={stat.description}
                    trend={stat.trend}
                />
            ))}
        </section>
    );
}

export function SectionCard({ title, action, collapsible = false, defaultOpen = false, children, dataTour, className = '', bodyClassName = 'p-5' }) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <section data-tour={dataTour} className={`rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>
            {collapsible ? (
                <button
                    type="button"
                    onClick={() => setOpen((o) => !o)}
                    className="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-left transition-colors hover:bg-slate-50/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                >
                    <h2 className="text-sm font-semibold tracking-tight text-slate-900">{title}</h2>
                    <MaterialSymbol
                        name="expand_more"
                        className={`text-[18px] text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`}
                    />
                </button>
            ) : (
                <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                    <h2 className="text-sm font-semibold tracking-tight text-slate-900">{title}</h2>
                    {action ?? null}
                </div>
            )}
            {collapsible && !open ? null : <div className={bodyClassName}>{children}</div>}
        </section>
    );
}

export function ViewAllLink({ href, children = 'View all' }) {
    return (
        <Link
            href={href}
            className="inline-flex items-center gap-1 text-xs font-bold text-primary transition-colors hover:text-primary/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
        >
            {children}
            <MaterialSymbol name="arrow_forward" className="text-[14px]" />
        </Link>
    );
}

export function EmptyState({ message, href, actionLabel, art = false }) {
    return (
        <div className="flex flex-col items-center gap-3 px-5 py-12 text-center">
            {art ? (
                <div className="relative flex h-24 w-32 items-center justify-center rounded-2xl bg-slate-50">
                    <span className="absolute h-12 w-20 rounded-lg border-2 border-slate-200 bg-white shadow-sm" />
                    <span className="absolute h-2 w-12 -translate-y-4 rounded-full bg-slate-200" />
                    <span className="absolute h-2 w-8 translate-y-1 rounded-full bg-slate-100" />
                    <span className="absolute -right-1 top-2 h-5 w-5 rounded-full border-2 border-primary/30 bg-primary/10" />
                </div>
            ) : null}
            <p className="text-sm text-slate-500">{message}</p>
            {href ? (
                <Link href={href} className="font-bold text-primary hover:text-primary/80">
                    {actionLabel}
                </Link>
            ) : null}
        </div>
    );
}

/**
 * The triage strip: one card, one cell per work queue, so the whole day's
 * workload reads in a single row. Counts of zero render muted.
 */
const STRIP_COLS = {
    1: 'lg:grid-cols-1',
    2: 'lg:grid-cols-2',
    3: 'lg:grid-cols-3',
    4: 'lg:grid-cols-4',
    5: 'lg:grid-cols-5',
    6: 'lg:grid-cols-6',
};

export function TriageStrip({ items, dataTour }) {
    const queues = safeArray(items);

    if (queues.length === 0) return null;

    return (
        <section data-tour={dataTour} className="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className={`grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-3 ${STRIP_COLS[Math.min(queues.length, 6)]}`}>
                {queues.map((item, index) => {
                    const count = Number(item.count ?? 0);
                    const urgent = count > 0 && (item.tone === 'rose' || item.tone === 'orange');

                    return (
                        <Link
                            key={item.key ?? index}
                            href={item.href ?? '#'}
                            className={`group flex flex-col gap-1 px-4 py-3.5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${
                                urgent ? 'bg-rose-50/40' : 'bg-white'
                            }`}
                        >
                            <span className="flex items-center gap-1.5">
                                <span className={`h-1.5 w-1.5 rounded-full ${toneDot(item.tone)}`} />
                                <span className="truncate text-[10px] font-bold uppercase tracking-widest text-slate-400">{item.label}</span>
                            </span>
                            <span className={`text-xl font-black ${count === 0 ? 'text-slate-300' : urgent ? 'text-rose-600' : 'text-slate-900'}`}>
                                {formatCount(count)}
                            </span>
                            <span className={`hidden truncate text-[11px] md:block ${urgent ? 'font-medium text-rose-500' : 'text-slate-500'}`}>
                                {item.note}
                            </span>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

export function BarList({ items, maxItems = 6 }) {
    const rows = safeArray(items).slice(0, maxItems);
    const maxValue = Math.max(...rows.map((item) => Number(item.count ?? 0)), 1);

    if (rows.length === 0) return null;

    return (
        <div className="space-y-3">
            {rows.map((item, index) => {
                const count = Number(item.count ?? 0);
                const width = Math.max(item.percent ?? Math.round((count / maxValue) * 100), 3);

                return (
                    <div key={item.key ?? item.label ?? index}>
                        <div className="flex items-center justify-between gap-3 text-sm">
                            <span className="truncate font-semibold text-slate-800">{item.label}</span>
                            <span className="shrink-0 text-xs font-bold text-slate-500">{formatCount(count)}</span>
                        </div>
                        {item.detail ? <p className="mt-0.5 text-[11px] text-slate-400">{item.detail}</p> : null}
                        <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div
                                className="h-full rounded-full"
                                style={{ width: `${width}%`, backgroundColor: item.hex ?? toneHex(item.tone ?? 'blue') }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

/**
 * Compact filter chip for client-side list filtering.
 */
export function FilterChip({ label, count, active, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 ${
                active
                    ? 'border-primary bg-primary text-white'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'
            }`}
        >
            {label}
            {count != null && count > 0 ? (
                <span
                    className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none ${
                        active ? 'bg-white/20' : 'bg-slate-100 text-slate-500'
                    }`}
                >
                    {count}
                </span>
            ) : null}
        </button>
    );
}

/**
 * Agency response scorecard — compact table-like list showing active, overdue,
 * overdue rate, and average completion time per agency. Sorted worst-first
 * by the backend.
 */
export function AgencyScorecard({ agencies, empty }) {
    const rows = safeArray(agencies);
    if (rows.length === 0) return empty ?? null;

    return (
        <div className="divide-y divide-slate-100">
            {rows.map((agency) => {
                const active = Number(agency.activeReferrals ?? 0);
                const overdue = Number(agency.overdueReferrals ?? 0);
                const rate = Number(agency.overdueRate ?? 0);
                const avgDays = agency.avgDaysToComplete != null ? Number(agency.avgDaysToComplete) : null;

                const rateTone = rate >= 20 ? 'rose' : rate >= 10 ? 'amber' : 'slate';
                const rateClasses = {
                    rose: 'bg-rose-50 text-rose-700',
                    amber: 'bg-amber-50 text-amber-700',
                    slate: 'bg-slate-100 text-slate-600',
                };

                return (
                    <Link
                        key={agency.id ?? agency.name}
                        href={agency.id ? safeRoute('admin.agencies.show', agency.id, `/admin/agencies/${agency.id}`) : safeRoute('admin.agencies.index', undefined, '/admin/agencies')}
                        className="block px-5 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="min-w-0 truncate text-sm font-bold text-slate-900">{agency.name}</span>
                            <div className="flex shrink-0 items-center gap-1.5">
                                <span className="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">
                                    {formatCount(active)} active
                                </span>
                                <span
                                    className={`inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold ${
                                        overdue > 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500'
                                    }`}
                                >
                                    {formatCount(overdue)} overdue
                                </span>
                            </div>
                        </div>
                        <div className="mt-1 flex items-center justify-between gap-2">
                            <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold ${rateClasses[rateTone]}`}>
                                {rate.toFixed(0)}% overdue
                            </span>
                            <span className="text-[11px] text-slate-400">
                                {avgDays != null ? `Avg ${avgDays.toFixed(1)}d` : '—'}
                            </span>
                        </div>
                    </Link>
                );
            })}
        </div>
    );
}

export function DashboardSkeleton() {
    return (
        <div className="mx-auto max-w-7xl animate-pulse pb-8" aria-busy="true" aria-label="Loading dashboard">
            <div className="mb-6 flex items-end justify-between">
                <div>
                    <div className="h-3 w-24 rounded bg-slate-200" />
                    <div className="mt-2 h-7 w-64 rounded bg-slate-200" />
                </div>
                <div className="hidden h-9 w-72 rounded-lg bg-slate-200 lg:block" />
            </div>
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {[0, 1, 2, 3].map((i) => (
                    <div key={i} className="h-24 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="h-3 w-20 rounded bg-slate-200" />
                        <div className="mt-3 h-7 w-16 rounded bg-slate-200" />
                    </div>
                ))}
            </div>
            <div className="mb-6 h-20 rounded-xl border border-slate-200 bg-white shadow-sm" />
            <div className="grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-8">
                    <div className="h-72 rounded-xl border border-slate-200 bg-white shadow-sm" />
                    <div className="h-72 rounded-xl border border-slate-200 bg-white shadow-sm" />
                </div>
                <div className="space-y-6 xl:col-span-4">
                    <div className="h-52 rounded-xl border border-slate-200 bg-white shadow-sm" />
                    <div className="h-52 rounded-xl border border-slate-200 bg-white shadow-sm" />
                </div>
            </div>
        </div>
    );
}
