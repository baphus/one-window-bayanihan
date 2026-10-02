import { useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Bar, Line, Pie } from 'react-chartjs-2';
import { ArcElement, Chart as ChartJS, Filler, Legend, LineElement, PointElement, Tooltip } from 'chart.js';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatDisplayDate, getCaseAgeInDays } from '@/lib/utils';
import { humanizeStatus } from '@/lib/statusLabels';
import { formatRelativeTime } from '@/lib/relativeTime';
import { getSeverityConfig, normalizeNotification, timeAgo } from '@/lib/notifications';
import {
    ActivityFeed,
    EmptyState,
    EntityList,
    EntityRow,
    MaterialSymbol,
    PageHeader,
    QuickActions,
    SectionCard,
    ViewAllLink,
    formatCount,
    safeArray,
    toneHex,
} from '@/Components/Dashboard/primitives';

ChartJS.register(ArcElement, Filler, Legend, LineElement, PointElement, Tooltip);

const OVERDUE_DAYS = 5;

const CASE_STATUSES = ['DRAFT', 'OPEN', 'CLOSED'];
const REFERRAL_STATUSES = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];

const CASE_EVENT_TYPES = new Set([
    'case_opened',
    'referral_sent',
    'referral_status_changed',
    'milestone_added',
    'case_closed',
    'case_reopened',
]);

function safeRoute(name, params, fallback) {
    try {
        if (typeof route === 'function') {
            return route(name, params);
        }
    } catch {
        // Ziggy not ready (tests / early boot) — fall through to plain path.
    }
    return fallback;
}

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

function relativeOf(item) {
    const stamp = createdAtOf(item);
    if (!stamp) {
        return pick(item?.time, '—');
    }
    try {
        return formatRelativeTime(String(stamp));
    } catch {
        return pick(item?.time, '—');
    }
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

function normalizeTrend(dashboard) {
    // First source with labels wins: cases-over-time, then case trends,
    // then referral trends — so the chart renders whenever any source
    // has labels. Shapes differ (datasets[0].data vs flat data), hence
    // both are read. Short data arrays are zero-padded to the labels.
    const sources = [dashboard?.casesOverTime, dashboard?.caseTrends, dashboard?.referralTrends];
    for (const source of sources) {
        const labels = safeArray(source?.labels);
        if (labels.length === 0) {
            continue;
        }
        const dataset = safeArray(source?.datasets)[0] ?? {};
        const rawData = Array.isArray(source?.data) ? source.data : dataset.data;
        const data = safeArray(rawData).map((value) => Number(value ?? 0));
        while (data.length < labels.length) {
            data.push(0);
        }
        return { labels, data: data.slice(0, labels.length) };
    }
    return { labels: [], data: [] };
}

function applyThirtyDayWindow(labels, data) {
    const cutoff = Date.now() - 30 * 24 * 60 * 60 * 1000;
    const kept = labels
        .map((label, index) => ({ label, value: data[index] ?? 0 }))
        .filter(({ label }) => {
            const parsed = new Date(label).getTime();
            if (Number.isNaN(parsed)) {
                return true;
            }
            return parsed >= cutoff;
        });
    // Monthly labels rarely fall inside 30 days — keep the latest periods
    // so the filter never blanks the chart.
    if (kept.length === 0) {
        return { labels: labels.slice(-4), data: data.slice(-4) };
    }
    return { labels: kept.map((item) => item.label), data: kept.map((item) => item.value) };
}

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
        x: { ticks: { font: { size: 10 } }, grid: { display: false } },
    },
};

/**
 * Single source for the exact status counts shown in the Numbers block.
 * The pies below reuse it so every total on screen always matches.
 */
function getStatusCounts(dashboard) {
    const distribution = safeArray(dashboard?.referralStatusDistribution);
    const countFor = (status) => distribution.find((item) => item.status === status)?.count;

    return {
        caseCounts: {
            DRAFT: dashboard?.myDraftCount ?? dashboard?.draftCases ?? 0,
            OPEN: dashboard?.openCases ?? dashboard?.totalOpenCases ?? 0,
            CLOSED: dashboard?.closedCases ?? 0,
        },
        referralCounts: {
            PENDING: dashboard?.pendingReferrals ?? countFor('PENDING') ?? 0,
            PROCESSING: dashboard?.processingReferrals ?? countFor('PROCESSING') ?? 0,
            FOR_COMPLIANCE: dashboard?.forComplianceReferrals ?? countFor('FOR_COMPLIANCE') ?? 0,
            COMPLETED: dashboard?.completedReferrals ?? countFor('COMPLETED') ?? 0,
            REJECTED: dashboard?.rejectedReferrals ?? countFor('REJECTED') ?? 0,
        },
    };
}

function NumbersBlock({ dashboard }) {
    const [chartType, setChartType] = useState('line');
    const [last30Only, setLast30Only] = useState(false);

    const { caseCounts, referralCounts } = useMemo(() => getStatusCounts(dashboard), [dashboard]);

    const trend = useMemo(() => normalizeTrend(dashboard), [dashboard]);
    const visible = useMemo(
        () => (last30Only ? applyThirtyDayWindow(trend.labels, trend.data) : trend),
        [trend, last30Only],
    );
    const chartData = {
        labels: visible.labels,
        datasets: [
            {
                data: visible.data,
                borderColor: '#005288',
                backgroundColor: chartType === 'line' ? 'rgba(0, 82, 136, 0.12)' : '#005288',
                borderRadius: 3,
                maxBarThickness: 22,
                fill: chartType === 'line',
                tension: 0.3,
                pointRadius: 2,
            },
        ],
    };

    return (
        <SectionCard
            title="Numbers"
            dataTour="dashboard-stats"
            action={
                <div className="flex items-center gap-1.5">
                    <button
                        type="button"
                        onClick={() => setLast30Only((value) => !value)}
                        aria-pressed={last30Only}
                        className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 ${
                            last30Only
                                ? 'border-primary bg-primary text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                        }`}
                    >
                        <MaterialSymbol name="calendar_month" className="text-[14px]" />
                        30 days
                    </button>
                    <div className="inline-flex overflow-hidden rounded-full border border-slate-200" role="group" aria-label="Chart type">
                        {[
                            { key: 'line', label: 'Line', icon: 'show_chart' },
                            { key: 'bar', label: 'Bar', icon: 'bar_chart' },
                        ].map((option) => (
                            <button
                                key={option.key}
                                type="button"
                                onClick={() => setChartType(option.key)}
                                aria-pressed={chartType === option.key}
                                className={`inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${
                                    chartType === option.key ? 'bg-primary text-white' : 'bg-white text-slate-600 hover:bg-slate-50'
                                }`}
                            >
                                <MaterialSymbol name={option.icon} className="text-[14px]" />
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>
            }
        >
            <p className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Cases</p>
            <div className="mt-2 grid grid-cols-3 gap-2">
                {CASE_STATUSES.map((status) => (
                    <div key={status} className="rounded-lg bg-slate-50 px-3 py-2.5">
                        <p className="text-lg font-black text-slate-900">{formatCount(caseCounts[status])}</p>
                        <div className="mt-1">
                            <StatusBadge status={status} label={humanizeStatus(status)} />
                        </div>
                    </div>
                ))}
            </div>

            <p className="mt-4 text-[11px] font-bold uppercase tracking-widest text-slate-400">Referrals</p>
            <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
                {REFERRAL_STATUSES.map((status) => (
                    <div key={status} className="rounded-lg bg-slate-50 px-3 py-2.5">
                        <p className="text-lg font-black text-slate-900">{formatCount(referralCounts[status])}</p>
                        <div className="mt-1">
                            <StatusBadge status={status} label={humanizeStatus(status)} />
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-4 border-t border-slate-100 pt-3">
                {visible.labels.length > 0 ? (
                    <div className="h-36">
                        {chartType === 'line' ? (
                            <Line data={chartData} options={chartOptions} />
                        ) : (
                            <Bar data={chartData} options={chartOptions} />
                        )}
                    </div>
                ) : (
                    <p className="text-sm text-slate-500">The trend appears as case activity accumulates.</p>
                )}
            </div>
        </SectionCard>
    );
}

// Slice hues mirror StatusBadge: exact DB status text, CaseStatus colors.
const STATUS_TONE = {
    DRAFT: 'amber',
    OPEN: 'blue',
    CLOSED: 'slate',
    PENDING: 'amber',
    PROCESSING: 'blue',
    FOR_COMPLIANCE: 'orange',
    COMPLETED: 'emerald',
    REJECTED: 'rose',
};

const AGENCY_TONES = ['blue', 'cyan', 'emerald', 'amber', 'orange', 'rose'];

const pieOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
};

function PieCard({ title, dataTour, slices, emptyMessage }) {
    const visible = slices.filter((slice) => Number(slice.count) > 0);

    return (
        <SectionCard title={title} dataTour={dataTour}>
            {visible.length === 0 ? (
                <p className="text-sm text-slate-500">{emptyMessage}</p>
            ) : (
                <div>
                    <div className="h-44">
                        <Pie
                            data={{
                                labels: visible.map((slice) => slice.label),
                                datasets: [
                                    {
                                        data: visible.map((slice) => Number(slice.count)),
                                        backgroundColor: visible.map((slice) => slice.hex),
                                        borderColor: '#ffffff',
                                        borderWidth: 1,
                                    },
                                ],
                            }}
                            options={pieOptions}
                        />
                    </div>
                    <ul className="mt-3 space-y-1.5">
                        {visible.map((slice) => (
                            <li key={slice.key} className="flex items-center justify-between gap-2 text-xs">
                                <span className="flex min-w-0 items-center gap-1.5">
                                    <span className="h-2 w-2 shrink-0 rounded-circle" style={{ backgroundColor: slice.hex }} />
                                    <span className="truncate font-semibold text-slate-700">{slice.label}</span>
                                </span>
                                <span className="shrink-0 font-bold text-slate-900">{formatCount(slice.count)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </SectionCard>
    );
}

function PiesRow({ dashboard }) {
    const { caseCounts, referralCounts } = useMemo(() => getStatusCounts(dashboard), [dashboard]);
    const agencies = useMemo(() => normalizeAgencies(dashboard), [dashboard]);

    return (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            <PieCard
                title="Cases by status"
                dataTour="dashboard-pie-cases"
                slices={CASE_STATUSES.map((status) => ({
                    key: status,
                    label: humanizeStatus(status),
                    count: caseCounts[status],
                    hex: toneHex(STATUS_TONE[status]),
                }))}
                emptyMessage="No case data to show."
            />
            <PieCard
                title="Referrals by agency"
                dataTour="dashboard-pie-agencies"
                slices={agencies.map((agency, index) => ({
                    key: agency.id ?? agency.name ?? index,
                    label: agency.name,
                    count: agency.active,
                    hex: toneHex(AGENCY_TONES[index % AGENCY_TONES.length]),
                }))}
                emptyMessage="No agency data to show."
            />
            <PieCard
                title="Referrals by status"
                dataTour="dashboard-pie-status"
                slices={REFERRAL_STATUSES.map((status) => ({
                    key: status,
                    label: humanizeStatus(status),
                    count: referralCounts[status],
                    hex: toneHex(STATUS_TONE[status]),
                }))}
                emptyMessage="No referral data to show."
            />
        </div>
    );
}

function NotificationsBlock() {
    const queryClient = useQueryClient();

    const { data: notifData, isLoading } = useQuery({
        queryKey: ['notifications'],
        queryFn: async () => {
            const res = await fetch(route('notifications.index', { per_page: 20 }), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`Failed: ${res.status}`);
            return res.json();
        },
        refetchInterval: 60000,
        staleTime: 30000,
    });

    const { data: unreadData } = useQuery({
        queryKey: ['notifications', 'unread-count'],
        queryFn: async () => {
            const res = await fetch(route('notifications.unread-count'), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(`Failed: ${res.status}`);
            return res.json();
        },
        refetchInterval: 60000,
        staleTime: 30000,
    });

    const markReadMutation = useMutation({
        mutationFn: (rawId) =>
            fetch(route('notifications.mark-as-read', rawId), {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            }).then((res) => {
                if (!res.ok) throw new Error(`Failed to mark as read: ${res.status}`);
                return res.json();
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications'] });
        },
    });

    const items = safeArray(notifData?.data).map((row) => normalizeNotification(row)).slice(0, 5);
    const unreadCount = Number(unreadData?.count ?? items.filter((item) => !item.is_read).length);

    return (
        <SectionCard
            title="Notifications"
            dataTour="dashboard-work-queue"
            action={
                <div className="flex items-center gap-2">
                    {unreadCount > 0 ? (
                        <span className="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700">
                            {formatCount(unreadCount)} unread
                        </span>
                    ) : null}
                    <ViewAllLink href="/notifications/page">View all</ViewAllLink>
                </div>
            }
            bodyClassName=""
        >
            {isLoading && items.length === 0 ? (
                <p className="px-5 py-6 text-sm text-slate-500">Loading notifications...</p>
            ) : items.length === 0 ? (
                <EmptyState message="No notifications." />
            ) : (
                <div className="divide-y divide-slate-100">
                    {items.map((item) => {
                        const config = getSeverityConfig(item.severity);
                        const isUnread = !item.is_read;
                        return (
                            <div
                                key={item.id}
                                onClick={() => {
                                    if (item.action_url) {
                                        router.visit(item.action_url);
                                    }
                                }}
                                className={`flex items-center gap-3 px-5 py-3 transition-colors ${
                                    isUnread ? 'bg-blue-50/40' : ''
                                } ${item.action_url ? 'cursor-pointer hover:bg-slate-100' : ''}`}
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={`h-1.5 w-1.5 shrink-0 rounded-circle ${config.dot}`} />
                                        <span className="text-[10px] font-extrabold uppercase tracking-widest text-slate-500">
                                            {config.label}
                                        </span>
                                        <span className="truncate text-sm font-bold text-slate-900">
                                            {item.title || 'Notification'}
                                        </span>
                                    </div>
                                    <p className="mt-0.5 text-[11px] text-slate-400">{timeAgo(item.created_at)}</p>
                                </div>
                                {isUnread ? (
                                    <button
                                        type="button"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            markReadMutation.mutate(item._rawId);
                                        }}
                                        disabled={markReadMutation.isPending}
                                        title="Mark as read"
                                        className="inline-flex shrink-0 items-center rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold text-slate-500 transition-colors hover:border-primary hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                    >
                                        Read
                                    </button>
                                ) : null}
                                <MaterialSymbol name="chevron_right" className="shrink-0 text-[16px] text-slate-300" />
                            </div>
                        );
                    })}
                </div>
            )}
        </SectionCard>
    );
}

const URGENCY = { REJECTED: 5, FOR_COMPLIANCE: 4, PENDING: 3, OPEN: 2, PROCESSING: 1 };

function buildNeedsYou(dashboard) {
    const referrals = safeArray(dashboard?.priorityReferrals ?? dashboard?.needsYou).map((item) => {
        const age = toAgeDays(pick(item.age_days, item.ageDays, getCaseAgeInDays(createdAtOf(item) ?? '')), 0);
        const status = pick(item.status, 'PENDING');
        const overdueBoost = age >= OVERDUE_DAYS ? 0.5 : 0;
        return {
            key: `ref-${item.id}`,
            href: pick(item.href, safeRoute('referrals.show', item.id, `/referrals/${item.id}`)),
            pill: pick(item.case_number, item.caseNumber),
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
            pill: pick(item.trackerNumber, item.tracker_number, item.caseNo, item.case_number),
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
            <EntityList empty={<EmptyState message="Nothing is late." href="/overdue-referrals" actionLabel="Check overdue" />}>
                {rows.map((row) => (
                    <EntityRow
                        key={row.key}
                        href={row.href}
                        pill={row.pill}
                        title={row.title}
                        note={row.note}
                        age={<AgeFlag days={row.age} />}
                        right={<StatusBadge status={row.status} label={humanizeStatus(row.status)} />}
                    />
                ))}
            </EntityList>
        </SectionCard>
    );
}

function IntakeQueueBlock({ dashboard }) {
    const intakes = useMemo(
        () =>
            safeArray(dashboard?.intakeReview)
                .slice()
                .sort(
                    (a, b) =>
                        new Date(createdAtOf(a) ?? 0).getTime() - new Date(createdAtOf(b) ?? 0).getTime(),
                )
                .slice(0, 5),
        [dashboard],
    );

    return (
        <SectionCard
            title="Intake Queue"
            dataTour="dashboard-intake-queue"
            action={<ViewAllLink href={safeRoute('cases.intake-queue', undefined, '/cases/intake-queue')}>View all</ViewAllLink>}
            bodyClassName=""
        >
            <EntityList empty={<EmptyState message="No pending intakes." />}>
                {intakes.map((item) => {
                    const id = item.id;
                    const filed = createdAtOf(item);
                    const ageDays = filed ? getCaseAgeInDays(String(filed)) : null;
                    const isPortal = pick(item.source, item.channel) === 'self_filed';
                    return (
                        <div
                            key={id}
                            className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary">
                                        {isPortal ? 'Portal' : 'Draft'}
                                    </span>
                                    <span className="truncate text-sm font-bold text-slate-900">
                                        {pick(item.clientName, item.client_name, 'Unnamed client')}
                                    </span>
                                </div>
                                <p className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
                                    {filed ? <span>Filed {formatDisplayDate(String(filed))}</span> : null}
                                    {ageDays !== null ? <AgeFlag days={ageDays} /> : null}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <StatusBadge status={pick(item.status, 'DRAFT')} label={humanizeStatus(pick(item.status, 'DRAFT'))} />
                                <Link
                                    href={safeRoute('cases.review-intake', id, `/cases/${id}/review-intake`)}
                                    className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-primary px-3 py-1.5 text-xs font-bold text-primary transition-colors hover:bg-primary hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                >
                                    Review
                                    <MaterialSymbol name="arrow_forward" className="text-[14px]" />
                                </Link>
                            </div>
                        </div>
                    );
                })}
            </EntityList>
        </SectionCard>
    );
}

function normalizeReferralNews(dashboard) {
    const supplied = safeArray(dashboard?.referralNews);
    if (supplied.length > 0) {
        return supplied
            .map((item) => ({
                id: pick(item.id, `${item.from}-${item.to}-${item.occurredAt}`),
                from: pick(item.from, item.fromStatus, item.oldStatus, item.meta?.from),
                to: pick(item.to, item.toStatus, item.newStatus, item.meta?.to),
                kind: pick(item.kind, item.type, item.from || item.to ? 'status' : 'milestone'),
                caseNumber: pick(item.caseNumber, item.case_number, item.meta?.case_number),
                clientName: pick(item.clientName, item.client_name, item.meta?.client_name),
                milestone: pick(item.milestone, item.milestoneTitle, item.title),
                href: pick(item.href, item.referral_id ? `/referrals/${item.referral_id}` : item.case_id ? `/cases/${item.case_id}` : '/referrals'),
                at: createdAtOf(item) ?? pick(item.time),
                raw: item,
            }))
            .sort((a, b) => new Date(b.at ?? 0).getTime() - new Date(a.at ?? 0).getTime());
    }

    // CaseEvent-shaped fallback (backend 1.x has not wired referralNews yet).
    const events = safeArray(dashboard?.caseEvents);
    const news = events
        .filter((event) => ['referral_status_changed', 'milestone_added'].includes(event.type ?? event.kind))
        .map((event) => ({
            id: event.id,
            from: pick(event.meta?.from, event.from),
            to: pick(event.meta?.to, event.to),
            kind: event.type ?? event.kind,
            caseNumber: pick(event.case_number, event.meta?.case_number),
            clientName: pick(event.client_name, event.meta?.client_name),
            milestone: pick(event.title, event.description),
            href: event.referral_id
                ? safeRoute('referrals.show', event.referral_id, `/referrals/${event.referral_id}`)
                : safeRoute('cases.show', event.case_id, event.case_id ? `/cases/${event.case_id}` : '/referrals'),
            at: pick(event.occurred_at, event.occurredAt, event.created_at, event.timestamp),
            raw: event,
        }))
        .sort((a, b) => new Date(b.at ?? 0).getTime() - new Date(a.at ?? 0).getTime());

    return news;
}

function ReferralNewsBlock({ dashboard }) {
    const news = useMemo(() => normalizeReferralNews(dashboard), [dashboard]);

    return (
        <SectionCard
            title="Referral News"
            dataTour="dashboard-referral-news"
            action={<ViewAllLink href={safeRoute('referrals.index', undefined, '/referrals')}>View all</ViewAllLink>}
            bodyClassName=""
        >
            {news.length === 0 ? (
                <EmptyState message="No referral updates yet." />
            ) : (
                <div className="divide-y divide-slate-100">
                    {news.map((item) => (
                        <Link
                            key={item.id}
                            href={item.href}
                            className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                        >
                            <div className="min-w-0 flex-1">
                                {item.kind === 'milestone_added' ? (
                                    <p className="truncate text-sm font-bold text-slate-900">
                                        Milestone added{item.caseNumber ? ` · ${item.caseNumber}` : ''}
                                    </p>
                                ) : (
                                    <p className="flex flex-wrap items-center gap-1.5">
                                        <StatusBadge status={item.from ?? 'PENDING'} label={humanizeStatus(item.from ?? 'PENDING')} />
                                        <MaterialSymbol name="arrow_forward" className="text-[14px] text-slate-400" />
                                        <StatusBadge status={item.to ?? 'PROCESSING'} label={humanizeStatus(item.to ?? 'PROCESSING')} />
                                    </p>
                                )}
                                <p className="mt-0.5 truncate text-xs text-slate-500">
                                    {[item.clientName, item.caseNumber, item.kind === 'milestone_added' ? item.milestone : null]
                                        .filter(Boolean)
                                        .join(' · ') || 'Referral update'}
                                </p>
                            </div>
                            <span className="shrink-0 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                {relativeOf({ createdAt: item.at })}
                            </span>
                            <MaterialSymbol name="chevron_right" className="shrink-0 text-[16px] text-slate-300" />
                        </Link>
                    ))}
                </div>
            )}
        </SectionCard>
    );
}

function normalizeAgencies(dashboard) {
    const breakdown = safeArray(dashboard?.agencyBreakdown);
    if (breakdown.length > 0) {
        return breakdown.slice(0, 5).map((item) => ({
            id: pick(item.agencyId, item.agency_id, item.id),
            name: pick(item.agencyName, item.agency_name, item.name, 'Unknown'),
            active: Number(pick(item.activeCount, item.count, 0)),
            overdue: Number(pick(item.overdueCount, 0)),
            detail: undefined,
        }));
    }
    return safeArray(dashboard?.agencyResponseScorecard).slice(0, 5).map((agency) => ({
        id: pick(agency.id, agency.agencyId),
        name: pick(agency.name, agency.agencyName, 'Unknown'),
        active: Number(pick(agency.activeReferrals, agency.activeCount, 0)),
        overdue: Number(pick(agency.overdueReferrals, agency.overdueCount, 0)),
        detail: agency.completionRate !== undefined ? `${agency.completionRate}% done` : undefined,
    }));
}

function AgenciesBlock({ dashboard }) {
    const agencies = useMemo(() => normalizeAgencies(dashboard), [dashboard]);

    return (
        <SectionCard
            title="Agencies Handling"
            dataTour="dashboard-agencies"
            action={<ViewAllLink href={safeRoute('reports.index', undefined, '/reports')}>View all</ViewAllLink>}
            bodyClassName=""
        >
            <EntityList empty={<EmptyState message="No agencies handling referrals." />}>
                {agencies.map((agency, index) => (
                    <EntityRow
                        key={agency.id ?? agency.name ?? index}
                        href={agency.id ? `/agencies/${agency.id}` : safeRoute('reports.index', undefined, '/reports')}
                        title={agency.name}
                        note={[`${formatCount(agency.active)} active`, agency.detail].filter(Boolean).join(' · ')}
                        age={agency.overdue > 0 ? <AgeFlag days={OVERDUE_DAYS} /> : undefined}
                        right={
                            <span
                                className={`inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold ${
                                    agency.overdue > 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500'
                                }`}
                            >
                                {formatCount(agency.overdue)} overdue
                            </span>
                        }
                    />
                ))}
            </EntityList>
        </SectionCard>
    );
}

function normalizeCaseEvents(dashboard) {
    const events = safeArray(dashboard?.caseEvents ?? dashboard?.caseActivityLog);
    if (events.length > 0) {
        return events
            .filter((event) => CASE_EVENT_TYPES.has(event.type ?? event.kind))
            .slice(0, 8)
            .map((event) => ({
                id: event.id,
                title: pick(event.title, 'Case update'),
                desc: pick(event.description, event.detail),
                time: relativeOf(event),
                logoSrc: '/logo.png',
                message: pick(event.title, 'Case update'),
                detail: pick(event.description, event.detail),
                timestamp: createdAtOf(event),
            }));
    }

    // Fallback: case-only slice of the AuditLog feed (no login / system noise).
    const noisyModules = new Set(['auth', 'user', 'users', 'session', 'sessions', 'system', 'settings']);
    const noisyActions = new Set(['login', 'logout', 'permission', 'password']);
    return safeArray(dashboard?.recentActivity ?? dashboard?.recentLogs)
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

export default function CaseManagerDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';
    const stats = dashboard.stats ?? dashboard;

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <GettingStartedChecklist />

            <PageHeader
                eyebrow="Case manager"
                title={`Welcome back, ${firstName}`}
                subtitle="What needs you today."
            >
                <QuickActions
                    actions={[
                        { href: safeRoute('cases.create', undefined, '/cases/create'), label: 'New case', icon: 'add', primary: true },
                        { href: safeRoute('cases.index', undefined, '/cases'), label: 'Cases', icon: 'folder', count: stats.totalCases },
                        { href: safeRoute('referrals.index', undefined, '/referrals'), label: 'Referrals', icon: 'send', count: stats.totalReferrals },
                    ]}
                />
            </PageHeader>

            <div className="mb-6">
                <NumbersBlock dashboard={dashboard} />
            </div>

            <div className="mb-6">
                <PiesRow dashboard={dashboard} />
            </div>

            <div className="grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-7">
                    <NotificationsBlock />
                    <IntakeQueueBlock dashboard={dashboard} />
                    <ReferralNewsBlock dashboard={dashboard} />
                </div>

                <div className="space-y-6 xl:col-span-5">
                    <NeedsYouBlock dashboard={dashboard} />
                    <AgenciesBlock dashboard={dashboard} />
                    <CaseActivityBlock dashboard={dashboard} />
                </div>
            </div>
        </div>
    );
}
