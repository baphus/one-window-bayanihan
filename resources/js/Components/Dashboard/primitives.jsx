import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Doughnut, Bar } from 'react-chartjs-2';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    Tooltip,
    Legend,
} from 'chart.js';
import KpiCard from '@/Components/ui/KpiCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import ActivityItem from '@/Components/Dashboard/ActivityItem';
import { formatStatusLabel } from '@/lib/utils';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend);

const numberFormatter = new Intl.NumberFormat('en-US');

export function formatCount(value) {
    const parsed = Number(value ?? 0);
    return numberFormatter.format(Number.isFinite(parsed) ? parsed : 0);
}

export function safeArray(value) {
    return Array.isArray(value) ? value.filter((item) => item !== null && item !== undefined) : [];
}

const TONE_DOTS = {
    blue: 'bg-primary',
    amber: 'bg-amber-500',
    orange: 'bg-orange-500',
    cyan: 'bg-cyan-600',
    emerald: 'bg-emerald-600',
    rose: 'bg-rose-600',
    slate: 'bg-slate-400',
};

const TONE_HEX = {
    blue: '#005288',
    amber: '#d97706',
    orange: '#ea580c',
    cyan: '#0891b2',
    emerald: '#059669',
    rose: '#e11d48',
    slate: '#94a3b8',
};

export function toneDot(tone) {
    return TONE_DOTS[tone] ?? TONE_DOTS.slate;
}

export function toneHex(tone) {
    return TONE_HEX[tone] ?? TONE_HEX.slate;
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
                <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{eyebrow}</p>
                <h1 className="mt-1 font-headline text-2xl font-extrabold tracking-tight text-slate-900">{title}</h1>
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
                    key={action.href}
                    href={action.href}
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

export function SectionCard({ title, action, children, dataTour, className = '', bodyClassName = 'p-5' }) {
    return (
        <section data-tour={dataTour} className={`rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>
            <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                <h2 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-primary">{title}</h2>
                {action ?? null}
            </div>
            <div className={bodyClassName}>{children}</div>
        </section>
    );
}

/**
 * Collapsible variant of SectionCard. Header is a toggle button;
 * chevron rotates to indicate state. Used for demoted/secondary widgets.
 */
export function CollapsibleSectionCard({ title, defaultOpen = false, children, dataTour, className = '', bodyClassName = 'p-5' }) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <section data-tour={dataTour} className={`rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-left transition-colors hover:bg-slate-50/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
            >
                <h2 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-primary">{title}</h2>
                <MaterialSymbol
                    name="expand_more"
                    className={`text-[18px] text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`}
                />
            </button>
            {open ? <div className={bodyClassName}>{children}</div> : null}
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

export function EmptyState({ message, href, actionLabel }) {
    return (
        <div className="px-5 py-6 text-sm text-slate-500">
            {message}
            {href ? (
                <Link href={href} className="ml-2 font-bold text-primary hover:text-primary/80">
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
                                <span className={`h-1.5 w-1.5 rounded-circle ${toneDot(item.tone)}`} />
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

export function EntityRow({ href, pill, title, note, badges = [], age, right }) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
        >
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    {pill ? (
                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">{pill}</span>
                    ) : null}
                    <span className="truncate text-sm font-bold text-slate-900">{title}</span>
                </div>
                {note ? <p className="mt-0.5 truncate text-xs text-slate-500">{note}</p> : null}
            </div>
            <div className="flex shrink-0 items-center gap-2">
                {right}
                {age ? <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{age}</span> : null}
                <MaterialSymbol name="chevron_right" className="text-[16px] text-slate-300" />
            </div>
        </Link>
    );
}

export function EntityList({ children, empty }) {
    const rows = safeArray(children);

    if (rows.length === 0) return empty ?? null;

    return <div className="divide-y divide-slate-100">{rows}</div>;
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

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    cutout: '62%',
};

export function StatusDonut({ items }) {
    const rows = safeArray(items);

    if (rows.length === 0) return null;

    const chartData = {
        labels: rows.map((item) => item.label),
        datasets: [
            {
                data: rows.map((item) => Number(item.count ?? 0)),
                backgroundColor: rows.map((item) => item.hex ?? toneHex(item.tone)),
                borderWidth: 0,
            },
        ],
    };

    return (
        <div className="flex items-center gap-5">
            <div className="h-28 w-28 shrink-0">
                <Doughnut data={chartData} options={doughnutOptions} />
            </div>
            <ul className="min-w-0 flex-1 space-y-1.5">
                {rows.map((item) => (
                    <li key={item.status ?? item.label} className="flex items-center justify-between gap-2 text-xs">
                        <span className="flex min-w-0 items-center gap-1.5">
                            <span className="h-2 w-2 shrink-0 rounded-circle" style={{ backgroundColor: item.hex ?? toneHex(item.tone) }} />
                            <span className="truncate font-semibold text-slate-700">{item.label}</span>
                        </span>
                        <span className="shrink-0 font-bold text-slate-900">
                            {formatCount(item.count)}
                            <span className="ml-1 font-semibold text-slate-400">{item.percent ?? 0}%</span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
        x: { ticks: { font: { size: 10 } }, grid: { display: false } },
    },
};

export function TrendBar({ labels = [], data = [] }) {
    if (!labels.length) return null;

    return (
        <div className="h-36">
            <Bar
                data={{
                    labels,
                    datasets: [{ data, backgroundColor: '#005288', borderRadius: 3, maxBarThickness: 22 }],
                }}
                options={barOptions}
            />
        </div>
    );
}

export function ActivityFeed({ items, limit = 6, empty }) {
    const rows = safeArray(items).slice(0, limit);

    if (rows.length === 0) {
        return empty ?? <p className="px-5 py-6 text-sm text-slate-500">No recent activity.</p>;
    }

    return (
        <div className="space-y-4 px-5 py-4">
            {rows.map((activity) => (
                <ActivityItem
                    key={activity.id}
                    title={activity.title}
                    desc={activity.desc}
                    time={activity.time?.toUpperCase() ?? ''}
                    logoSrc={activity.logoSrc ?? '/logo.png'}
                    actionType={activity.actionType}
                    actor={activity.actor}
                    message={activity.message}
                    detail={activity.detail}
                />
            ))}
        </div>
    );
}

/**
 * Attention-aware case activity row. Two-line scannable layout:
 *   Line 1: pill(case_number) + client_name + category
 *   Line 2: owner · referrals · worst status
 *   Line 3: updated time
 *   Right:  StatusBadge + SLA pill
 */
export function CaseActivityRow({
    href,
    caseNumber,
    clientName,
    category,
    caseOwner,
    referralCount = 0,
    worstReferralStatus,
    isOverdue,
    maxReferralAgeDays,
    updatedAt,
    status,
}) {
    const slaLabel = isOverdue
        ? `OVERDUE${maxReferralAgeDays ? ` ${maxReferralAgeDays}d` : ''}`
        : referralCount > 0 && worstReferralStatus
            ? `${referralCount} referral${referralCount === 1 ? '' : 's'} · ${formatStatusLabel(worstReferralStatus).toLowerCase()}`
            : referralCount > 0
                ? `${referralCount} referral${referralCount === 1 ? '' : 's'}`
                : null;

    const slaTone =
        isOverdue || worstReferralStatus === 'REJECTED'
            ? 'rose'
            : worstReferralStatus === 'FOR_COMPLIANCE' || worstReferralStatus === 'PENDING'
                ? 'amber'
                : 'slate';

    const slaClasses = {
        rose: 'border-rose-200 bg-rose-50 text-rose-700',
        amber: 'border-amber-200 bg-amber-50 text-amber-700',
        slate: 'border-slate-200 bg-slate-100 text-slate-600',
    };

    const secondaryParts = [];
    if (caseOwner) secondaryParts.push(caseOwner);
    if (referralCount > 0) secondaryParts.push(`${referralCount} referral${referralCount === 1 ? '' : 's'}`);
    if (worstReferralStatus && worstReferralStatus !== 'PENDING') {
        secondaryParts.push(formatStatusLabel(worstReferralStatus).toLowerCase());
    }

    return (
        <Link
            href={href}
            className="flex items-start gap-3 px-5 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
        >
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    {caseNumber ? (
                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">
                            {caseNumber}
                        </span>
                    ) : null}
                    <span className="truncate text-sm font-bold text-slate-900">
                        {clientName || 'Unnamed client'}
                    </span>
                    {category ? (
                        <span className="text-[11px] font-medium text-slate-400">{category}</span>
                    ) : null}
                </div>
                {secondaryParts.length > 0 ? (
                    <p className="mt-0.5 truncate text-xs text-slate-500">
                        {secondaryParts.join(' · ')}
                    </p>
                ) : null}
                {updatedAt ? (
                    <p className="mt-0.5 text-[11px] text-slate-400">{updatedAt}</p>
                ) : null}
            </div>
            <div className="flex shrink-0 items-center gap-2 pt-0.5">
                <StatusBadge status={status} />
                {slaLabel ? (
                    <span
                        className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${slaClasses[slaTone]}`}
                    >
                        {slaLabel}
                    </span>
                ) : null}
                <MaterialSymbol name="chevron_right" className="text-[16px] text-slate-300" />
            </div>
        </Link>
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
 * Priority referral row — compact variant for the priority referrals card.
 */
export function PriorityReferralRow({ href, caseNumber, clientName, agencyName, status, ageDays }) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 px-5 py-2.5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
        >
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    {caseNumber ? (
                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">
                            {caseNumber}
                        </span>
                    ) : null}
                    <span className="truncate text-sm font-bold text-slate-900">{clientName || 'Unnamed'}</span>
                </div>
                <p className="mt-0.5 truncate text-xs text-slate-500">
                    {[agencyName, ageDays != null ? `${ageDays}d old` : null].filter(Boolean).join(' · ')}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <StatusBadge status={status} />
                <MaterialSymbol name="chevron_right" className="text-[16px] text-slate-300" />
            </div>
        </Link>
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
                        href={agency.id ? `/agencies/${agency.id}` : '/admin/agencies'}
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
