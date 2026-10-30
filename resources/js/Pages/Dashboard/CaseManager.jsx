import { useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Line } from 'react-chartjs-2';
import { CategoryScale, Chart as ChartJS, Filler, Legend, LinearScale, LineElement, PointElement, Tooltip } from 'chart.js';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import StatusBadge from '@/Components/ui/StatusBadge';
import { ActivityFeed, EmptyState, QuickActions, SectionCard, ViewAllLink, formatCount, safeArray } from '@/Components/Dashboard/primitives';
import { DashboardRow, DashboardRowList, RowAgeFlag } from '@/Components/Dashboard/DashboardRow';
import KpiCard from '@/Components/ui/KpiCard';
import { formatDisplayDate, getCaseAgeInDays } from '@/lib/utils';
import { humanizeStatus } from '@/lib/statusLabels';
import safeRoute from '@/utils/safeRoute';

ChartJS.register(CategoryScale, Filler, Legend, LinearScale, LineElement, PointElement, Tooltip);

/**
 * Case manager overview.
 *
 * Four numbers, one chart, and the two queues a case manager actually works
 * from (Needs You, Pending Reviews). Distribution charts deliberately live on
 * the Reports page so this screen stays a glance, not a dashboard wall.
 */

const PERIODS = [
    { days: 7, label: 'Last 7 days' },
    { days: 30, label: 'Last 30 days' },
    { days: 90, label: 'Last 90 days' },
];

const OVERDUE_DAYS = 5;

/** "Wednesday, 28 October 2026" for the header. */
function formatToday() {
    return new Date().toLocaleDateString('en-PH', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/** 2026-10-28 -> "28 Oct" for the chart axis. */
function formatBucket(value) {
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-PH', { day: '2-digit', month: 'short' });
}

/* ---------- shared helpers (ported unchanged from the previous page) ---------- */

function pick(...values) {
    for (const value of values) {
        if (value !== undefined && value !== null && value !== '') {
            return value;
        }
    }
    return undefined;
}

function toAgeDays(value, fallback = 0) {
    if (value === undefined || value === null) {
        return fallback;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed >= 0 ? Math.floor(parsed) : fallback;
}

function createdAtOf(item) {
    return pick(item?.createdAt, item?.created_at, item?.occurredAt, item?.occurred_at, item?.timestamp, item?.time);
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


const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#0f172a',
            padding: 10,
            cornerRadius: 6,
            titleFont: { size: 11 },
            bodyFont: { size: 11 },
            boxWidth: 8,
            boxHeight: 8,
        },
    },
    elements: {
        line: { tension: 0.35, borderWidth: 2 },
        point: { radius: 0, hoverRadius: 4, hitRadius: 12 },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8', padding: 6 },
            grid: { color: '#f1f5f9', drawTicks: false },
            border: { display: false },
        },
        x: {
            ticks: {
                font: { size: 10 },
                color: '#94a3b8',
                maxTicksLimit: 8,
                maxRotation: 0,
                autoSkip: true,
                padding: 4,
            },
            grid: { display: false },
            border: { display: false },
        },
    },
};

/* ---------- Needs You ---------- */

const URGENCY = { REJECTED: 5, FOR_COMPLIANCE: 4, PENDING: 3, OPEN: 2, PROCESSING: 1 };

function buildNeedsYou(dashboard) {
    const referrals = safeArray(dashboard?.priorityReferrals).map((item) => {
        const age = toAgeDays(pick(item.age_days, item.ageDays, getCaseAgeInDays(createdAtOf(item) ?? '')), 0);
        const status = pick(item.status, 'PENDING');
        const overdueBoost = age >= OVERDUE_DAYS ? 0.5 : 0;
        return {
            key: `ref-${item.id}`,
            href: pick(item.href, safeRoute('referrals.show', item.id, `/referrals/${item.id}`)),
            trackingId: pick(item.tracking_number, item.trackerNumber, item.tracker_number, item.case_number, item.caseNumber),
            title: pick(item.client_name, item.clientName, 'Unnamed'),
            note: pick(item.agency_name, item.agencyName, item.service),
            status,
            age,
            score: (URGENCY[status] ?? 0) + overdueBoost + Math.min(age / 30, 1),
        };
    });

    const cases = safeArray(dashboard?.priorityCases).map((item) => {
        const age = toAgeDays(pick(item.ageDays, item.age_days, getCaseAgeInDays(createdAtOf(item) ?? '')), 0);
        const status = pick(item.status, 'OPEN');
        const referralNote = pick(item.latestReferralStatus, item.latest_referral_status);
        return {
            key: `case-${item.id}`,
            href: pick(item.href, safeRoute('cases.show', item.id, `/cases/${item.id}`)),
            trackingId: pick(item.trackerNumber, item.tracker_number, item.caseNo, item.case_number),
            title: pick(item.clientName, item.client_name, 'Unnamed'),
            note: pick(item.reason, referralNote ? humanizeStatus(referralNote) : undefined),
            status,
            age,
            score: (URGENCY[status] ?? 2) + (age >= OVERDUE_DAYS ? 0.5 : 0) + Math.min(age / 30, 1) - 0.1,
        };
    });

    return [...referrals, ...cases]
        .sort((a, b) => b.score - a.score || b.age - a.age)
        .slice(0, 8);
}

function NeedsYouBlock({ dashboard }) {
    const rows = useMemo(() => buildNeedsYou(dashboard), [dashboard]);

    return (
        <SectionCard title="Needs You" dataTour="dashboard-needs-you" bodyClassName="">
            <DashboardRowList empty={<EmptyState message="Nothing needs attention." href={safeRoute('overdue-referrals.index', undefined, '/overdue-referrals')} actionLabel="Check overdue" />}>
                {rows.map((row) => (
                    <DashboardRow
                        key={row.key}
                        href={row.href}
                        pill={row.trackingId}
                        title={row.title}
                        note={row.note}
                        right={
                            <>
                                <StatusBadge status={row.status} label={humanizeStatus(row.status)} />
                                <RowAgeFlag days={row.age} overdueDays={OVERDUE_DAYS} />
                            </>
                        }
                    />
                ))}
            </DashboardRowList>
        </SectionCard>
    );
}

/* ---------- Pending Reviews ---------- */

function PendingReviews({ dashboard }) {
    const items = useMemo(
        () =>
            safeArray(dashboard?.intakeReview)
                .slice()
                .sort((a, b) => new Date(createdAtOf(a) ?? 0).getTime() - new Date(createdAtOf(b) ?? 0).getTime())
                .slice(0, 5),
        [dashboard],
    );
    const count = dashboard?.pendingReviewCount ?? items.length;
    const listHref = safeRoute('cases.review-pending', undefined, '/cases/review-pending');

    return (
        <SectionCard
            title={count > 0 ? `Pending Reviews (${formatCount(count)})` : 'Pending Reviews'}
            dataTour="dashboard-pending-reviews"
            action={<ViewAllLink href={listHref}>View all</ViewAllLink>}
            bodyClassName=""
        >
            <DashboardRowList empty={<EmptyState message="No filings waiting for review." />}>
                {items.map((item) => {
                    const filed = createdAtOf(item);
                    const href = item.href && typeof item.href === 'string' && item.href.length > 0 ? item.href : listHref;
                    const trackingId = pick(item.trackerNumber, item.tracker_number, item.caseNo, item.case_number, '—');
                    const clientName = pick(item.clientName, item.client_name, 'Unnamed client');
                    const filedLabel = filed ? formatDisplayDate(String(filed)) : '';

                    return (
                        <DashboardRow
                            key={item.id}
                            href={href}
                            pill={trackingId}
                            title={clientName}
                            note={`Request for Assistance${filedLabel ? ` · filed ${filedLabel}` : ''}`}
                            right={<StatusBadge status={pick(item.status, 'PENDING_REVIEW')} label={humanizeStatus(pick(item.status, 'PENDING_REVIEW'))} />}
                        />
                    );
                })}
            </DashboardRowList>
        </SectionCard>
    );
}

/* ---------- Case activity ---------- */

function normalizeCaseEvents(dashboard) {
    // Case-only slice of the AuditLog feed (no login / system noise).
    const noisyModules = new Set(['auth', 'user', 'users', 'session', 'sessions', 'system', 'settings']);
    const noisyActions = new Set(['login', 'logout', 'permission', 'password']);
    return safeArray(dashboard?.recentActivity)
        .filter((item) => !noisyModules.has(String(item.module ?? '').toLowerCase()))
        .filter((item) => !noisyActions.has(String(item.actionType ?? item.action ?? '').toLowerCase()))
        .slice(0, 8);
}

function CaseActivityBlock({ dashboard }) {
    const items = useMemo(() => normalizeCaseEvents(dashboard), [dashboard]);

    return (
        <SectionCard title="Case Activity Log" dataTour="dashboard-activity-log">
            <ActivityFeed items={items} limit={8} />
        </SectionCard>
    );
}

/* ---------- Page ---------- */

export default function CaseManagerDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';

    const [days, setDays] = useState(30);
    const [period, setPeriod] = useState(null);
    const [loading, setLoading] = useState(true);

    // The page itself keeps rendering from the deferred dashboard payload while
    // this first request is in flight, so the skeleton is only the KPI/chart
    // area, never the whole page.
    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        fetch(safeRoute('dashboard.case-manager-period', { days }, `/dashboard/case-manager-period?days=${days}`), {
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

    const kpis = period?.kpis ?? { active: 0, awaiting_agency: 0, needs_action: 0, closed: 0 };
    const volume = period?.volume ?? { labels: [], received: [], closed: [] };

    const chartData = useMemo(() => ({
        labels: (volume.labels ?? []).map(formatBucket),
        datasets: [
            {
                label: 'Cases received',
                data: volume.received ?? [],
                borderColor: '#005288',
                backgroundColor: 'rgba(0, 82, 136, 0.10)',
                pointBackgroundColor: '#005288',
                fill: true,
            },
            {
                label: 'Cases closed',
                data: volume.closed ?? [],
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.10)',
                pointBackgroundColor: '#10b981',
                fill: true,
            },
        ],
    }), [volume]);

    const hasVolume = (volume.labels ?? []).length > 0;
    const periodLabel = period?.period?.label ?? PERIODS.find((p) => p.days === days)?.label;

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <GettingStartedChecklist />

            {/* Greeting + today's date + period filter */}
            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Case manager</p>
                    <h1 className="mt-1 font-headline text-[28px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">
                        Good day, {firstName}
                    </h1>
                    <p className="mt-1 text-[14px] text-slate-500">{formatToday()}</p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <label htmlFor="dashboard-period" className="sr-only">Reporting period</label>
                    <div className="relative">
                        <span
                            aria-hidden="true"
                            className="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[17px] leading-none text-slate-400"
                        >
                            calendar_month
                        </span>
                        <select
                            id="dashboard-period"
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
                            { href: safeRoute('cases.create', undefined, '/cases/create'), label: 'New case', icon: 'add', primary: true },
                            { href: safeRoute('cases.index', undefined, '/cases'), label: 'Cases', icon: 'folder' },
                        ]}
                    />
                </div>
            </div>

            {/* Four KPIs */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Total active cases" value={kpis.active} hint="Open cases right now" icon="folder_open" />
                <KpiCard
                    label="Awaiting agency response"
                    value={kpis.awaiting_agency}
                    hint="Referrals awaiting a partner office"
                    icon="hourglass_top"
                />
                <KpiCard
                    label="Cases needing action"
                    value={kpis.needs_action}
                    hint="Documents requested or referral rejected"
                    tone="attention"
                    icon="priority_high"
                />
                <KpiCard
                    label="Cases closed this period"
                    value={kpis.closed}
                    hint={periodLabel ?? 'In the selected period'}
                    tone="positive"
                    icon="task_alt"
                />
            </div>

            {/* Case volume */}
            <section className="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
                <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">
                            Case volume over time
                        </h2>
                        <p className="mt-0.5 text-[12px] text-slate-500">Filings against closures, day by day.</p>
                    </div>

                    <div className="flex items-center gap-4 pt-1">
                        <span className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                            <span className="h-2 w-2 rounded-full bg-[#005288]" />
                            Cases received
                        </span>
                        <span className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                            <span className="h-2 w-2 rounded-full bg-[#10b981]" />
                            Cases closed
                        </span>
                    </div>
                </header>

                <div className="px-5 py-5">
                    {loading && !hasVolume ? (
                        <div className="flex h-64 items-center justify-center">
                            <span aria-hidden="true" className="material-symbols-outlined animate-spin text-[24px] text-slate-300">
                                progress_activity
                            </span>
                        </div>
                    ) : hasVolume ? (
                        <div className="h-64">
                            <Line data={chartData} options={chartOptions} />
                        </div>
                    ) : (
                        <div className="flex h-64 flex-col items-center justify-center text-center">
                            <span className="material-symbols-outlined text-3xl text-slate-300" aria-hidden="true">show_chart</span>
                            <p className="mt-2 text-[13px] font-semibold text-slate-700">No case activity in this period</p>
                            <p className="mt-0.5 text-[12px] text-slate-500">Try a longer period to see the trend.</p>
                        </div>
                    )}
                </div>
            </section>

            {/* Work queues + activity */}
            <div className="mt-6 grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-7">
                    <PendingReviews dashboard={dashboard} />
                    <NeedsYouBlock dashboard={dashboard} />
                </div>
                <div className="space-y-6 xl:col-span-5">
                    <CaseActivityBlock dashboard={dashboard} />
                </div>
            </div>
        </div>
    );
}
