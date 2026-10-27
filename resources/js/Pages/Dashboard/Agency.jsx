import { useEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Bar, Doughnut, Line } from 'react-chartjs-2';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import StatusBadge from '@/Components/ui/StatusBadge';
import { ActivityFeed, DashboardTable, EmptyState, QuickActions, SectionCard, ViewAllLink, formatCount, safeArray } from '@/Components/Dashboard/primitives';
import { humanizeStatus } from '@/lib/statusLabels';
import safeRoute from '@/utils/safeRoute';

ChartJS.register(ArcElement, BarElement, CategoryScale, Filler, Legend, LineElement, LinearScale, PointElement, Tooltip);

/**
 * Agency focal overview.
 *
 * Deliberately narrower than the case manager dashboard: everything here is
 * scoped to the signed-in user's own agency. A focal person answers "what has
 * been referred to us, what is waiting on us, and what have we finished" —
 * not "what is happening across the service".
 */

const PERIODS = [
    { days: 7, label: 'Last 7 days' },
    { days: 30, label: 'Last 30 days' },
    { days: 90, label: 'Last 90 days' },
];

const OVERDUE_DAYS = 5;

const STATUS_TONE = {
    PENDING: { hex: '#d97706', label: 'Pending review' },
    PROCESSING: { hex: '#005288', label: 'In progress' },
    FOR_COMPLIANCE: { hex: '#ea580c', label: 'Awaiting information' },
    COMPLETED: { hex: '#059669', label: 'Resolved' },
    REJECTED: { hex: '#e11d48', label: 'Unable to assist' },
};

const CATEGORY_HUES = ['#005288', '#0891b2', '#059669', '#d97706', '#ea580c', '#7c3aed', '#0b7a75', '#94a3b8'];

function formatToday() {
    return new Date().toLocaleDateString('en-PH', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatBucket(value) {
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-PH', { day: '2-digit', month: 'short' });
}

function toAgeDays(value, fallback = 0) {
    if (value === undefined || value === null) return fallback;
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed >= 0 ? Math.floor(parsed) : fallback;
}

function AgeFlag({ days }) {
    const parsed = toAgeDays(days, 0);
    const overdue = parsed >= OVERDUE_DAYS;
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

/* ---------- KPI ---------- */

function KpiCard({ label, value, hint, tone = 'default' }) {
    const valueTone = {
        default: 'text-slate-900',
        attention: 'text-amber-700',
        positive: 'text-emerald-700',
    }[tone] ?? 'text-slate-900';

    return (
        <div className="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">{label}</p>
            <p className={`mt-1.5 font-headline text-[28px] font-extrabold leading-none tracking-tight ${valueTone}`}>
                {value ?? 0}
            </p>
            {hint ? <p className="mt-1.5 text-[11px] text-slate-500">{hint}</p> : null}
        </div>
    );
}

/* ---------- Charts ---------- */

const BASE_SCALES = {
    y: {
        beginAtZero: true,
        ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8', padding: 6 },
        grid: { color: '#f1f5f9', drawTicks: false },
        border: { display: false },
    },
    x: {
        ticks: { font: { size: 10 }, color: '#94a3b8', maxTicksLimit: 8, maxRotation: 0, autoSkip: true },
        grid: { display: false },
        border: { display: false },
    },
};

const TOOLTIP = {
    backgroundColor: '#0f172a',
    padding: 10,
    cornerRadius: 6,
    titleFont: { size: 11 },
    bodyFont: { size: 11 },
    boxWidth: 8,
    boxHeight: 8,
};

function ChartCard({ title, description, legend, children, height = 'h-64' }) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div className="min-w-0">
                    <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">{title}</h2>
                    {description ? <p className="mt-0.5 text-[12px] text-slate-500">{description}</p> : null}
                </div>
                {legend ?? null}
            </header>
            <div className={`px-5 py-5 ${height}`}>{children}</div>
        </section>
    );
}

function LegendDot({ color, children }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: color }} />
            {children}
        </span>
    );
}

function EmptyChart() {
    return (
        <div className="flex h-full flex-col items-center justify-center text-center">
            <span className="material-symbols-outlined text-3xl text-slate-300" aria-hidden="true">insights</span>
            <p className="mt-2 text-[13px] font-semibold text-slate-700">Nothing to chart yet</p>
            <p className="mt-0.5 text-[12px] text-slate-500">Data appears once referrals reach your agency.</p>
        </div>
    );
}

/* ---------- Referral table ---------- */

function ReferralQueue({ title, description, referrals, emptyMessage, href, dataTour }) {
    const columns = [
        { key: 'tracking', label: 'Tracking ID' },
        { key: 'category', label: 'Category' },
        { key: 'referred', label: 'Referred' },
        { key: 'status', label: 'Status' },
        { key: 'age', label: 'Age', className: 'text-right', cellClassName: 'text-right' },
        { key: 'action', label: '', className: 'text-right', cellClassName: 'text-right' },
    ];

    const rows = referrals.map((item) => {
        const referralHref = item.href ?? safeRoute('referrals.show', item.id, `/referrals/${item.id}`);
        const referred = item.referred_at
            ? new Date(item.referred_at).toLocaleDateString('en-PH', { day: '2-digit', month: 'short', year: 'numeric' })
            : '—';

        return {
            key: item.id,
            tracking: (
                <Link href={referralHref} className="font-bold text-primary hover:text-primary-container">
                    {item.tracking_number ?? item.case_number}
                </Link>
            ),
            category: <span className="block max-w-[12rem] truncate">{item.service ?? '—'}</span>,
            referred,
            status: <StatusBadge status={item.status} label={humanizeStatus(item.status)} />,
            age: <AgeFlag days={item.age_days} />,
            action: (
                <Link href={referralHref} className="font-bold text-primary hover:text-primary-container">
                    Open Case
                </Link>
            ),
        };
    });

    return (
        <SectionCard title={title} dataTour={dataTour} action={<ViewAllLink href={href} />} bodyClassName="">
            {description ? <p className="px-5 pt-3 text-[12px] text-slate-500">{description}</p> : null}
            <DashboardTable
                columns={columns}
                rows={rows}
                empty={<EmptyState message={emptyMessage} href={safeRoute('referrals.index', undefined, '/referrals')} actionLabel="Open referrals" />}
            />
        </SectionCard>
    );
}

/* ---------- Page ---------- */

export default function AgencyDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const agency = auth?.user?.agency ?? null;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';

    const [days, setDays] = useState(30);
    const [period, setPeriod] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        fetch(safeRoute('dashboard.agency-period', { days }, `/dashboard/agency-period?days=${days}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (!cancelled && data) setPeriod(data);
            })
            .catch(() => {})
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [days]);

    const kpis = period?.kpis ?? { assigned: 0, awaiting: 0, action: 0, resolved: 0 };
    const volume = period?.volume ?? { labels: [], received: [], resolved: [] };
    const statusMix = safeArray(period?.status_mix);
    const categoryMix = safeArray(period?.category_mix);

    // Work queues come from the deferred dashboard payload, already scoped to
    // this agency server-side.
    const pending = safeArray(dashboard.pendingReferrals);
    const processing = safeArray(dashboard.processingReferrals);
    const overdue = safeArray(dashboard.overdueReferrals);
    const recentActivity = safeArray(dashboard.recentActivity);

    const periodLabel = period?.period?.label ?? PERIODS.find((p) => p.days === days)?.label;
    const hasVolume = (volume.labels ?? []).length > 0;

    const volumeChart = useMemo(() => ({
        labels: (volume.labels ?? []).map(formatBucket),
        datasets: [
            {
                label: 'Referrals received',
                data: volume.received ?? [],
                borderColor: '#005288',
                backgroundColor: 'rgba(0, 82, 136, 0.10)',
                pointBackgroundColor: '#005288',
                fill: true,
            },
            {
                label: 'Referrals resolved',
                data: volume.resolved ?? [],
                borderColor: '#059669',
                backgroundColor: 'rgba(5, 150, 105, 0.10)',
                pointBackgroundColor: '#059669',
                fill: true,
            },
        ],
    }), [volume]);

    const statusChart = useMemo(() => {
        const slices = statusMix
            .map((row) => ({
                label: STATUS_TONE[row.status]?.label ?? humanizeStatus(row.status),
                total: row.total,
                hex: STATUS_TONE[row.status]?.hex ?? '#94a3b8',
            }))
            .filter((slice) => slice.total > 0);

        return { slices };
    }, [statusMix]);

    const categoryChart = useMemo(() => ({
        labels: categoryMix.map((row) => row.label),
        values: categoryMix.map((row) => row.total),
        hues: categoryMix.map((_, index) => CATEGORY_HUES[index % CATEGORY_HUES.length]),
    }), [categoryMix]);

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <GettingStartedChecklist />

            {/* Greeting + agency identity + period */}
            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        {agency?.name ? `${agency.name} · Agency focal` : 'Agency focal'}
                    </p>
                    <h1 className="mt-1 font-headline text-[28px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">
                        Good day, {firstName}
                    </h1>
                    <p className="mt-1 text-[14px] text-slate-500">{formatToday()}</p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <label htmlFor="agency-dashboard-period" className="sr-only">Reporting period</label>
                    <div className="relative">
                        <span
                            aria-hidden="true"
                            className="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[17px] leading-none text-slate-400"
                        >
                            calendar_month
                        </span>
                        <select
                            id="agency-dashboard-period"
                            value={days}
                            onChange={(e) => setDays(Number(e.target.value))}
                            className="appearance-none rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-9 text-[13px] font-semibold text-slate-700 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            {PERIODS.map((option) => (
                                <option key={option.days} value={option.days}>{option.label}</option>
                            ))}
                        </select>
                        <span
                            aria-hidden="true"
                            className="material-symbols-outlined pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[18px] leading-none text-slate-400"
                        >
                            expand_more
                        </span>
                    </div>

                    <QuickActions
                        actions={[
                            { href: safeRoute('referrals.index', undefined, '/referrals'), label: 'Referrals', icon: 'send', primary: true },
                            { href: safeRoute('overdue-referrals.index', undefined, '/overdue-referrals'), label: 'Overdue', icon: 'warning' },
                        ]}
                    />
                </div>
            </div>

            {/* Four KPIs */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Assigned referrals" value={kpis.assigned} hint="Referrals this agency received" />
                <KpiCard label="Awaiting our review" value={kpis.awaiting} hint="Pending or in progress" />
                <KpiCard
                    label="Action required"
                    value={kpis.action}
                    hint="Awaiting information or unable to assist"
                    tone="attention"
                />
                <KpiCard
                    label="Resolved referrals"
                    value={kpis.resolved}
                    hint={periodLabel ?? 'In the selected period'}
                    tone="positive"
                />
            </div>

            <p className="mt-3 text-[11px] leading-relaxed text-slate-400">
                &ldquo;Resolved&rdquo; means your agency completed its part of a referral. It does not mean the
                overall case is closed — the DMW case manager still coordinates the case.
            </p>

            {/* Chart 1 — received vs resolved */}
            <div className="mt-6">
                <ChartCard
                    title="Referrals received vs resolved"
                    description="Referrals arriving at your agency against those your agency finished."
                    legend={
                        <div className="flex items-center gap-4 pt-1">
                            <LegendDot color="#005288">Referrals received</LegendDot>
                            <LegendDot color="#059669">Referrals resolved</LegendDot>
                        </div>
                    }
                >
                    {loading && !hasVolume ? (
                        <div className="flex h-full items-center justify-center">
                            <span aria-hidden="true" className="material-symbols-outlined animate-spin text-[24px] text-slate-300">
                                progress_activity
                            </span>
                        </div>
                    ) : hasVolume ? (
                        <Line
                            data={volumeChart}
                            options={{
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                plugins: { legend: { display: false }, tooltip: TOOLTIP },
                                elements: { line: { tension: 0.35, borderWidth: 2 }, point: { radius: 0, hoverRadius: 4, hitRadius: 12 } },
                                scales: BASE_SCALES,
                            }}
                        />
                    ) : (
                        <EmptyChart />
                    )}
                </ChartCard>
            </div>

            {/* Charts 2 + 3 */}
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <ChartCard title="Referrals by status" description="Where your current workload sits.">
                    {statusChart.slices.length === 0 ? (
                        <EmptyChart />
                    ) : (
                        <div className="flex h-full flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="h-40 w-full shrink-0 sm:h-44 sm:w-44">
                                <Doughnut
                                    data={{
                                        labels: statusChart.slices.map((s) => s.label),
                                        datasets: [
                                            {
                                                data: statusChart.slices.map((s) => s.total),
                                                backgroundColor: statusChart.slices.map((s) => s.hex),
                                                borderColor: '#ffffff',
                                                borderWidth: 2,
                                            },
                                        ],
                                    }}
                                    options={{
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        cutout: '62%',
                                        plugins: { legend: { display: false }, tooltip: TOOLTIP },
                                    }}
                                />
                            </div>
                            <ul className="min-w-0 flex-1 space-y-1.5">
                                {statusChart.slices.map((slice) => (
                                    <li key={slice.label} className="flex items-center justify-between gap-2 text-[12px]">
                                        <span className="flex min-w-0 items-center gap-1.5">
                                            <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: slice.hex }} />
                                            <span className="truncate font-semibold text-slate-700">{slice.label}</span>
                                        </span>
                                        <span className="shrink-0 font-bold text-slate-900">{formatCount(slice.total)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </ChartCard>

                <ChartCard title="Referrals by assistance type" description="The case categories your agency handles most.">
                    {categoryChart.labels.length === 0 ? (
                        <EmptyChart />
                    ) : (
                        <Bar
                            data={{
                                labels: categoryChart.labels,
                                datasets: [
                                    {
                                        data: categoryChart.values,
                                        backgroundColor: categoryChart.hues,
                                        borderRadius: 4,
                                        maxBarThickness: 22,
                                    },
                                ],
                            }}
                            options={{
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false }, tooltip: TOOLTIP },
                                scales: {
                                    x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' }, grid: { color: '#f1f5f9', drawTicks: false }, border: { display: false } },
                                    y: { ticks: { font: { size: 10 }, color: '#475569' }, grid: { display: false }, border: { display: false } },
                                },
                            }}
                        />
                    )}
                </ChartCard>
            </div>

            {/* Work queues */}
            <div className="mt-6 grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-7">
                    <ReferralQueue
                        title="My pending referrals"
                        description="Referred to your agency and not yet reviewed."
                        referrals={pending}
                        emptyMessage="No referrals waiting for review."
                        href={safeRoute('referrals.index', undefined, '/referrals')}
                        dataTour="dashboard-agency-pending"
                    />
                    <ReferralQueue
                        title="Action required"
                        description="Needs documents or clarification before your agency can proceed."
                        referrals={processing.filter((item) => item.status === 'FOR_COMPLIANCE')}
                        emptyMessage="Nothing is waiting on information."
                        href={safeRoute('referrals.index', undefined, '/referrals')}
                        dataTour="dashboard-agency-action"
                    />
                </div>

                <div className="space-y-6 xl:col-span-5">
                    <ReferralQueue
                        title="Overdue referrals"
                        description={`Open longer than ${OVERDUE_DAYS} days.`}
                        referrals={overdue}
                        emptyMessage="No overdue referrals."
                        href={safeRoute('overdue-referrals.index', undefined, '/overdue-referrals')}
                        dataTour="dashboard-agency-overdue"
                    />
                    <SectionCard title="Recent activity" dataTour="dashboard-agency-activity">
                        <ActivityFeed items={recentActivity} limit={6} />
                    </SectionCard>
                </div>
            </div>
        </div>
    );
}
