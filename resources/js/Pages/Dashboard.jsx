import { Head, Deferred, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { Doughnut, Bar } from 'react-chartjs-2';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, BarElement,
    ArcElement, Title, Tooltip, Legend,
} from 'chart.js';
import { FolderCheck, Users, ArrowRightLeft, Plus, Send, Eye, ChevronRight, AlertTriangle, Clock, CheckCircle2, Loader2, XCircle, TrendingUp, TrendingDown, UserPlus, Pencil, Trash2, LogIn, LogOut, NotepadText } from 'lucide-react';
import KpiCard from '@/Components/ui/KpiCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import RecentTable from '@/Components/ui/RecentTable';
import { formatDisplayDate, formatDisplayDateTime, formatStatusLabel } from '@/lib/utils';
import DashboardBanner from '@/Components/DashboardBanner';
import { DashboardSkeleton } from '@/Components/Dashboard/primitives';
import AdminDashboard from './Dashboard/Admin';
import AgencyDashboard from './Dashboard/Agency';
import CaseManagerDashboard from './Dashboard/CaseManager';
import TourPrototype from './__TourPrototype';

function DashboardContent({ role }) {
    const { dashboard } = usePage().props;

    if (role === 'ADMIN') {
        return <AdminDashboard dashboard={dashboard ?? {}} />;
    }
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import AdminDispatchDashboard from './Dashboard/Admin';

ChartJS.register(
    CategoryScale, LinearScale, BarElement,
    ArcElement, Title, Tooltip, Legend,
);

const pieOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } } }, cutout: '55%' };

const toneStyles = {
    blue: { pill: 'bg-blue-50 text-blue-900 ring-blue-100', bar: 'bg-blue-900', dot: 'bg-blue-900', panel: 'border-blue-200/80' },
    amber: { pill: 'bg-amber-50 text-amber-800 ring-amber-100', bar: 'bg-amber-500', dot: 'bg-amber-500', panel: 'border-amber-200/80' },
    orange: { pill: 'bg-orange-50 text-orange-800 ring-orange-100', bar: 'bg-orange-500', dot: 'bg-orange-500', panel: 'border-orange-200/80' },
    cyan: { pill: 'bg-cyan-50 text-cyan-800 ring-cyan-100', bar: 'bg-cyan-600', dot: 'bg-cyan-600', panel: 'border-cyan-200/80' },
    emerald: { pill: 'bg-emerald-50 text-emerald-800 ring-emerald-100', bar: 'bg-emerald-600', dot: 'bg-emerald-600', panel: 'border-emerald-200/80' },
    rose: { pill: 'bg-rose-50 text-rose-800 ring-rose-100', bar: 'bg-rose-600', dot: 'bg-rose-600', panel: 'border-rose-200/80' },
    slate: { pill: 'bg-slate-100 text-slate-700 ring-slate-200', bar: 'bg-slate-500', dot: 'bg-slate-500', panel: 'border-slate-200/80' },
};

const queueIconMap = {
    newReferrals: Send,
    pendingReferrals: Clock,
    forComplianceReferrals: FolderCheck,
    processingReferrals: Loader2,
    overdueReferrals: AlertTriangle,
    returnedReferrals: XCircle,
    agingOpenCases: Clock,
    draftCases: NotepadText,
    casesWithoutReferrals: ArrowRightLeft,
};

function safeArray(value) {
    return Array.isArray(value) ? value.filter((item) => item !== null && item !== undefined) : [];
}

function safeCount(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatCompactCount(value) {
    return new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(safeCount(value));
}

function getToneStyle(tone = 'blue') {
    return toneStyles[tone] ?? toneStyles.blue;
}

function getToneHex(tone = 'blue') {
    return {
        blue: '#1e3a8a',
        amber: '#d97706',
        orange: '#ea580c',
        cyan: '#0891b2',
        emerald: '#059669',
        rose: '#e11d48',
        slate: '#64748b',
    }[tone] ?? '#1e3a8a';
}

function getQueueIcon(key) {
    return queueIconMap[key] ?? Send;
}

function getQueueHref(item) {
    return item?.href || '/referrals';
}

function InsightCard({ title, eyebrow, action, children, className = '', bodyClassName = '', dataTour }) {
    return (
        <section data-tour={dataTour} className={`overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>
            {(title || eyebrow || action) && (
                <div className="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3.5">
                    <div className="min-w-0">
                        {eyebrow && <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-400">{eyebrow}</p>}
                        {title && <h3 className="mt-1 text-[13px] font-bold font-headline text-slate-700">{title}</h3>}
                    </div>
                    {action ? <div className="shrink-0">{action}</div> : null}
                </div>
            )}
            <div className={`p-4 ${bodyClassName}`}>{children}</div>
        </section>
    );
}

function EmptyChartState({ title, description, href, actionLabel = 'Open workflow', tone = 'slate' }) {
    const style = getToneStyle(tone);

    return (
        <div className="flex min-h-[150px] flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white px-4 py-6 text-center">
            <span className={`flex h-10 w-10 items-center justify-center rounded-full ${style.pill} ring-1`}>
                <Eye className="h-5 w-5" />
            </span>
            <p className="mt-3 text-sm font-semibold text-slate-800">{title}</p>
            <p className="mt-1 max-w-md text-xs leading-relaxed text-slate-500">{description}</p>
            {href ? (
                <Link href={href} className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-blue-900 px-3 py-2 text-[11px] font-bold uppercase tracking-widest text-white transition hover:bg-blue-800">
                    {actionLabel}
                    <ChevronRight className="h-3.5 w-3.5" />
                </Link>
            ) : null}
        </div>
    );
}

function WorkQueueRibbon({ title, subtitle, items = [], emptyTitle, emptyDescription, emptyHref }) {
    const queueItems = safeArray(items);

    return (
        <section data-tour="dashboard-work-queue" className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-400">{title}</p>
                    <h2 className="mt-1 text-[13px] font-bold font-headline text-slate-700">{subtitle}</h2>
                </div>
                <p className="max-w-2xl text-xs leading-relaxed text-slate-500">{queueItems.length > 0 ? 'Actionable items are ordered by urgency so the day starts with the right work.' : 'A calm board means there are no immediate dashboard actions.'}</p>
            </div>

            {queueItems.length > 0 ? (
                <div className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-5">
                    {queueItems.map((item) => {
                        const style = getToneStyle(item.tone);
                        const Icon = getQueueIcon(item.key);

                        return (
                            <Link
                                key={item.key}
                                href={getQueueHref(item)}
                                className="group flex min-w-0 flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-900/20"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className={`flex h-9 w-9 items-center justify-center rounded-lg ${style.pill} ring-1`}>
                                        <Icon className={`h-4 w-4 ${item.key === 'processingReferrals' ? 'animate-spin' : ''}`} />
                                    </div>
                                    <span className="text-2xl font-black tracking-tight text-slate-900">{safeCount(item.count)}</span>
                                </div>
                                <p className="mt-3 text-[10px] font-bold uppercase tracking-[0.28em] text-slate-400">{item.label}</p>
                                <p className="mt-1 text-xs leading-relaxed text-slate-600">{item.note}</p>
                                <div className="mt-4 flex items-center justify-between text-[11px] font-bold uppercase tracking-widest text-slate-500 transition group-hover:text-blue-900">
                                    <span>Open queue</span>
                                    <ChevronRight className="h-3.5 w-3.5" />
                                </div>
                            </Link>
                        );
                    })}
                </div>
            ) : (
                <div className="px-4 py-4">
                    <EmptyChartState title={emptyTitle} description={emptyDescription} href={emptyHref} actionLabel="Open dashboard" tone="slate" />
                </div>
            )}
        </section>
    );
}

function HorizontalBarSummary({ title, eyebrow, items = [], dataTour, emptyTitle, emptyDescription, emptyHref, actionLabel = 'Open workflow' }) {
    const rows = safeArray(items).slice(0, 6);
    const maxValue = Math.max(...rows.map((item) => safeCount(item.count)), 1);

    return (
        <InsightCard title={title} eyebrow={eyebrow} dataTour={dataTour}>
            {rows.length > 0 ? (
                <div className="space-y-3">
                    {rows.map((item) => {
                        const style = getToneStyle(item.tone);
                        const value = safeCount(item.count);
                        const percent = item.percent ?? (maxValue > 0 ? Math.round((value / maxValue) * 100) : 0);
                        const row = (
                            <div className="rounded-xl border border-slate-200 bg-white p-3 transition group-hover:border-blue-200 group-hover:shadow-sm">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-slate-800">{item.label}</p>
                                        {item.detail ? <p className="mt-0.5 text-[11px] leading-relaxed text-slate-500">{item.detail}</p> : null}
                                    </div>
                                    <div className="shrink-0 text-right">
                                        <p className="text-sm font-black text-slate-950">{formatCompactCount(value)}</p>
                                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{percent}%</p>
                                    </div>
                                </div>
                                <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div className={`h-full rounded-full ${style.bar}`} style={{ width: `${Math.max(4, percent)}%`, backgroundColor: item.hex ?? undefined }} />
                                </div>
                                {item.meta ? <p className="mt-2 text-[11px] text-slate-500">{item.meta}</p> : null}
                            </div>
                        );

                        if (item.href) {
                            return (
                                <Link key={item.key ?? item.label} href={item.href} className="group block focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-900/20 focus-visible:ring-offset-2">
                                    {row}
                                </Link>
                            );
                        }

                        return <div key={item.key ?? item.label}>{row}</div>;
                    })}
                </div>
            ) : (
                <EmptyChartState title={emptyTitle} description={emptyDescription} href={emptyHref} actionLabel={actionLabel} />
            )}
        </InsightCard>
    );
}

function PriorityListRow({ item, index }) {
    const style = getToneStyle(item.tone);

    return (
        <Link
            href={item.href}
            className="group block rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-900/20"
        >
            <div className="flex items-start gap-3">
                <div className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${style.pill} ring-1`}>
                    <span className="text-[11px] font-black">{index + 1}</span>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-bold text-slate-900 transition group-hover:text-blue-900">{item.title}</p>
                            {item.subtitle ? <p className="mt-0.5 text-xs text-slate-500">{item.subtitle}</p> : null}
                        </div>
                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                            {safeArray(item.badges).map((badge) => (
                                <StatusBadge key={badge} status={badge} />
                            ))}
                            {item.age ? <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest ring-1 ${style.pill}`}>{item.age}</span> : null}
                        </div>
                    </div>
                    {item.note ? <p className="mt-2 text-xs leading-relaxed text-slate-500">{item.note}</p> : null}
                    <div className="mt-2 flex items-center justify-between gap-3 text-[11px] font-bold uppercase tracking-widest text-slate-400">
                        <span>{item.meta}</span>
                        <span className="flex items-center gap-1 text-blue-900 transition group-hover:gap-1.5">
                            Open <ChevronRight className="h-3.5 w-3.5" />
                        </span>
                    </div>
                </div>
            </div>
        </Link>
    );
}

function DashboardChartCard({ title, eyebrow, items = [], dataTour, emptyTitle, emptyDescription, emptyHref, actionLabel = 'Open workflow' }) {
    const rows = safeArray(items);
    const chartData = useMemo(() => ({
        labels: rows.map((item) => item.label),
        datasets: [
            {
                data: rows.map((item) => safeCount(item.count)),
                backgroundColor: rows.map((item) => item.hex ?? getToneHex(item.tone)),
                borderWidth: 0,
            },
        ],
    }), [rows]);

    return (
        <InsightCard title={title} eyebrow={eyebrow} dataTour={dataTour}>
            {rows.length > 0 ? (
                <div className="grid gap-4 lg:grid-cols-[112px_minmax(0,1fr)] lg:items-center">
                    <div className="mx-auto h-28 w-28 sm:h-32 sm:w-32">
                        <Doughnut data={chartData} options={doughnutOptions} />
                    </div>
                    <div className="space-y-2">
                        {rows.map((item) => {
                            const style = getToneStyle(item.tone);
                            return (
                                <div key={item.key ?? item.label} className="rounded-xl border border-slate-200 bg-white p-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-slate-800">{item.label}</p>
                                            {item.detail ? <p className="mt-0.5 text-[11px] text-slate-500">{item.detail}</p> : null}
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="text-sm font-black text-slate-950">{formatCompactCount(item.count)}</p>
                                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{item.percent ?? 0}%</p>
                                        </div>
                                    </div>
                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div className={`h-full rounded-full ${style.bar}`} style={{ width: `${Math.max(4, item.percent ?? 0)}%` }} />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ) : (
                <EmptyChartState title={emptyTitle} description={emptyDescription} href={emptyHref} actionLabel={actionLabel} />
            )}
        </InsightCard>
    );
}

function FeedbackPulseCard({ feedbackPulse, href = '/feedbacks' }) {
    const pulse = feedbackPulse ?? {};
    const hasData = Boolean(pulse.hasData) && (safeCount(pulse.totalSent) > 0 || safeCount(pulse.totalSubmitted) > 0 || pulse.avgRating !== null || pulse.avgServqual !== null);

    return (
        <InsightCard
            title="Feedback pulse"
            eyebrow="Client sentiment"
            dataTour="dashboard-feedback-pulse"
            action={
                <Link href={href} className="text-[11px] font-bold uppercase tracking-widest text-blue-900 transition hover:text-blue-700">
                    Feedbacks
                </Link>
            }
        >
            {hasData ? (
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div className="rounded-xl border border-slate-200 bg-white p-3">
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Response rate</p>
                            <p className="mt-1 text-xl font-black text-slate-950">{safeCount(pulse.responseRate)}%</p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-3">
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Average rating</p>
                            <p className="mt-1 text-xl font-black text-slate-950">{pulse.avgRating ?? '—'}</p>
                        </div>
                        <div className="col-span-2 rounded-xl border border-slate-200 bg-white p-3 sm:col-span-1">
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">SERVQUAL</p>
                            <p className="mt-1 text-xl font-black text-slate-950">{pulse.avgServqual ?? '—'}</p>
                        </div>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-3">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Submitted / sent</p>
                        <p className="mt-1 text-sm font-semibold text-slate-800">{safeCount(pulse.totalSubmitted)} of {safeCount(pulse.totalSent)} invitations replied to.</p>
                    </div>
                </div>
            ) : (
                <EmptyChartState
                    title="Feedback is still quiet"
                    description="No meaningful feedback signal is available yet. Open the feedback workflow when you're ready to review submissions."
                    href={href}
                    actionLabel="Open feedbacks"
                />
            )}
        </InsightCard>
    );
}

function AgencyDashboard({
    stats,
    recentReferrals = [],
    recentActivity = [],
    dashboardNotifications = [],
    workQueue,
    referralStatusDistribution,
    referralAgingBands,
    priorityReferrals,
    serviceDemand,
    feedbackPulse,
    casesByCategory = [],
}) {
    const referrals = safeArray(recentReferrals);
    const agencyQueue = safeArray(workQueue).length > 0 ? workQueue : [
        {
            key: 'newReferrals',
            label: 'New referrals',
            count: referrals.length > 0 ? referrals.filter((referral) => getCaseAgeInDays(referral.created_at ?? referral.createdAt) <= 2).length : safeCount(stats.pendingReferrals),
            note: 'Recently received and ready to triage.',
            tone: 'blue',
            href: '/referrals',
        },
        {
            key: 'pendingReferrals',
            label: 'Pending',
            count: safeCount(stats.pendingReferrals),
            note: 'Waiting for first action.',
            tone: 'amber',
            href: '/referrals',
        },
        {
            key: 'forComplianceReferrals',
            label: 'For compliance',
            count: safeCount(stats.forComplianceReferrals),
            note: 'Missing requirements to complete.',
            tone: 'orange',
            href: '/referrals',
        },
        {
            key: 'processingReferrals',
            label: 'Processing',
            count: safeCount(stats.processingReferrals),
            note: 'Already in motion.',
            tone: 'cyan',
            href: '/referrals',
        },
        {
            key: 'overdueReferrals',
            label: 'Overdue',
            count: safeCount(stats.overdueReferrals),
            note: 'Older than the dashboard window.',
            tone: 'rose',
            href: '/referrals',
        },
        {
            key: 'returnedReferrals',
            label: 'Returned',
            count: safeCount(stats.rejectedReferrals),
            note: 'Needs clarification or reassignment.',
            tone: 'rose',
            href: '/referrals',
        },
    ];

    const statusDistribution = safeArray(referralStatusDistribution).length > 0 ? referralStatusDistribution : referrals.reduce((acc, referral) => {
        const status = referral.status ?? 'OTHER';
        const existing = acc.find((item) => item.status === status);
        if (existing) {
            existing.count += 1;
            return acc;
        }
        acc.push({ status, label: formatStatusLabel(status), count: 1, percent: 0, tone: 'slate' });
        return acc;
    }, []).map((item, index, array) => ({ ...item, percent: array.length > 0 ? Math.round((item.count / array.reduce((sum, row) => sum + row.count, 0)) * 100) : 0, key: item.status ?? index }));

    const agingBands = safeArray(referralAgingBands).length > 0 ? referralAgingBands : (referrals.length > 0 ? [
        { key: '0-2', label: '0-2 days', count: referrals.filter((item) => getCaseAgeInDays(item.createdAt ?? item.created_at) <= 2).length, percent: 0, tone: 'emerald' },
        { key: '3-5', label: '3-5 days', count: referrals.filter((item) => {
            const age = getCaseAgeInDays(item.createdAt ?? item.created_at);
            return age >= 3 && age <= 5;
        }).length, percent: 0, tone: 'amber' },
        { key: '6-10', label: '6-10 days', count: referrals.filter((item) => {
            const age = getCaseAgeInDays(item.createdAt ?? item.created_at);
            return age >= 6 && age <= 10;
        }).length, percent: 0, tone: 'orange' },
        { key: '11+', label: '11+ days', count: referrals.filter((item) => getCaseAgeInDays(item.createdAt ?? item.created_at) >= 11).length, percent: 0, tone: 'rose' },
    ].map((item, _, array) => {
        const total = array.reduce((sum, row) => sum + row.count, 0);
        return { ...item, percent: total > 0 ? Math.round((item.count / total) * 100) : 0 };
    }) : []);
    const serviceDemandRows = safeArray(serviceDemand).map((item) => ({
        key: item.serviceId ?? item.serviceName,
        label: item.serviceName,
        count: item.totalCount,
        detail: `${item.activeCount} active · ${item.completedCount} completed`,
        meta: `Completion ${item.completionRate}%`,
        href: item.href ?? '/referrals',
        tone: item.activeCount > 3 ? 'blue' : 'slate',
    }));
    const categoryDemandRows = safeArray(casesByCategory).map((item) => ({
        key: item.name,
        label: item.name,
        count: item.count,
        detail: 'Category demand across active referrals.',
        href: '/referrals',
        tone: 'cyan',
        hex: item.color,
    }));
    const priorityRows = safeArray(priorityReferrals).length > 0 ? priorityReferrals : safeArray(recentReferrals).map((referral) => ({
        id: referral.id,
        caseNo: referral.case_file?.case_number ?? referral.caseNo ?? 'N/A',
        clientName: referral.case_file?.client ? `${referral.case_file.client.first_name ?? ''} ${referral.case_file.client.last_name ?? ''}`.trim() : (referral.clientName ?? 'N/A'),
        service: referral.required_services ?? referral.requiredServices ?? 'Service not specified',
        agencyName: referral.agency?.name ?? null,
        status: referral.status ?? 'PENDING',
        ageDays: getCaseAgeInDays(referral.created_at ?? referral.createdAt),
        href: `/referrals/${referral.id}`,
    }));

    return (
        <div className="max-w-7xl mx-auto pb-6">
            <DashboardBanner />
            <GettingStartedChecklist />
            <header data-tour="dashboard-header" className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-slate-400">Agency focal</p>
                    <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 md:text-3xl">Today’s work queue</h1>
                    <p className="mt-1 text-sm text-slate-500">Start with the referrals that need movement, then review health, demand, and client signal.</p>
                </div>
                <div className="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-slate-400">
                    <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{safeCount(stats.totalReferrals)} referrals</span>
                    <span className="rounded-full bg-blue-50 px-3 py-1 text-blue-900">{safeCount(stats.completedReferrals)} completed</span>
                </div>
            </header>

            <div data-tour="dashboard-stats">
                <WorkQueueRibbon
                    title="Today’s work queue"
                    subtitle="Dispatch board"
                    items={agencyQueue}
                    emptyTitle="No immediate work queue items"
                    emptyDescription="The agency board is calm for the moment. Use the referral workflow when new items arrive."
                    emptyHref="/referrals"
                />
            </div>

            <div className="mt-6 grid gap-6">
                <div className="order-2 grid gap-6 xl:order-1 xl:grid-cols-12">
                    <div className="xl:col-span-7">
                        <DashboardChartCard
                            title="Referral status"
                            eyebrow="Referral health"
                            items={statusDistribution}
                            dataTour="dashboard-agency-metrics"
                            emptyTitle="No referral status data"
                            emptyDescription="Referral distribution will appear once the agency has active referrals."
                            emptyHref="/referrals"
                            actionLabel="Open referrals"
                        />
                    </div>
                    <div className="xl:col-span-5">
                        <HorizontalBarSummary
                            title="Referral aging"
                            eyebrow="Referral health"
                            items={agingBands}
                            dataTour="dashboard-agency-aging"
                            emptyTitle="No aging bands yet"
                            emptyDescription="Aging bands are shown once active referrals are present."
                            emptyHref="/referrals"
                            actionLabel="Open referrals"
                        />
                    </div>
                </div>

                <div className="order-3 grid gap-6 xl:order-2 xl:grid-cols-12">
                    <div className="xl:col-span-7">
                        <HorizontalBarSummary
                            title="Service demand"
                            eyebrow="Service load"
                            items={serviceDemandRows}
                            dataTour="dashboard-service-demand"
                            emptyTitle="No service demand yet"
                            emptyDescription="Requested services will surface here once referrals are assigned to the agency."
                            emptyHref="/services"
                            actionLabel="Manage services"
                        />
                    </div>
                    <div className="xl:col-span-5">
                        <HorizontalBarSummary
                            title="Category demand"
                            eyebrow="Demand mix"
                            items={categoryDemandRows}
                            dataTour="dashboard-category-demand"
                            emptyTitle="No category demand yet"
                            emptyDescription="Category demand will appear as active referrals accumulate."
                            emptyHref="/referrals"
                            actionLabel="Open referrals"
                        />
                    </div>
                </div>

                <div className="order-4">
                    <FeedbackPulseCard feedbackPulse={feedbackPulse} />
                </div>

                <div className="order-1 grid gap-6 xl:order-5 xl:grid-cols-12">
                    <div className="xl:col-span-8">
                        <InsightCard
                            title="Priority referrals"
                            eyebrow="Action list"
                            dataTour="dashboard-agency-referrals"
                            action={
                                <Link href="/referrals" className="text-[11px] font-bold uppercase tracking-widest text-blue-900 transition hover:text-blue-700">
                                    View all
                                </Link>
                            }
                        >
                            <div className="space-y-3">
                                {priorityRows.length > 0 ? priorityRows.map((item, index) => (
                                    <PriorityListRow
                                        key={item.id ?? item.caseNo ?? index}
                                        item={{
                                            href: item.href,
                                            title: item.caseNo,
                                            subtitle: item.clientName,
                                            note: `${item.service}${item.agencyName ? ` · ${item.agencyName}` : ''}`,
                                            meta: `${item.ageDays} day${item.ageDays === 1 ? '' : 's'} old`,
                                            badges: [item.status],
                                            age: item.ageDays > 0 ? `${item.ageDays}d` : 'New',
                                            tone: item.status === 'REJECTED' ? 'rose' : item.status === 'FOR_COMPLIANCE' ? 'orange' : item.status === 'PROCESSING' ? 'cyan' : 'amber',
                                        }}
                                        index={index}
                                    />
                                )) : (
                                    <EmptyChartState title="No priority referrals" description="Once referrals need follow-up, they will appear here first." href="/referrals" actionLabel="Open referrals" />
                                )}
                            </div>
                        </InsightCard>
                    </div>
                    <div className="xl:col-span-4">
                        <InsightCard
                            title="Quick actions"
                            eyebrow="Navigate"
                            dataTour="dashboard-quick-actions"
                        >
                            <div className="space-y-2">
                                {[
                                    { href: '/referrals', label: 'Open referrals', icon: Send, tone: 'blue' },
                                    { href: '/services', label: 'Manage services', icon: FolderCheck, tone: 'slate' },
                                    { href: '/feedbacks', label: 'Review feedback', icon: Eye, tone: 'slate' },
                                    { href: '/audit-logs', label: 'Activity logs', icon: TrendingUp, tone: 'slate' },
                                ].map((action) => {
                                    const Icon = action.icon;
                                    return (
                                        <Link
                                            key={action.href}
                                            href={action.href}
                                            className={`flex items-center justify-between rounded-xl border px-3.5 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-900/20 ${action.tone === 'blue' ? 'border-blue-900 bg-blue-900 text-white hover:bg-blue-800' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'}`}
                                        >
                                            <span className="flex items-center gap-2 text-[12px] font-bold">
                                                <Icon className={`h-3.5 w-3.5 ${action.tone === 'blue' ? 'text-white' : 'text-slate-400'}`} />
                                                {action.label}
                                            </span>
                                            <ChevronRight className={`h-3.5 w-3.5 ${action.tone === 'blue' ? 'text-white/70' : 'text-slate-300'}`} />
                                        </Link>
                                    );
                                })}
                            </div>
                        </InsightCard>
                    </div>
                </div>

                <div className="order-5">
                    <InsightCard
                        title="Recent activity"
                        eyebrow="Audit trail"
                        dataTour="dashboard-recent-activity"
                        action={
                            <Link href="/audit-logs" className="text-[11px] font-bold uppercase tracking-widest text-blue-900 transition hover:text-blue-700">
                                View all
                            </Link>
                        }
                    >
                        <div className="relative space-y-4 border-l-2 border-slate-100 pl-4">
                            {safeArray(recentActivity).slice(0, 5).length === 0 ? (
                                <p className="py-2 text-xs text-slate-400">No recent activity.</p>
                            ) : (
                                safeArray(recentActivity).slice(0, 5).map((activity) => (
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
                                ))
                            )}
                        </div>
                    </InsightCard>
                </div>
            </div>
        </div>
    );
}

function AdminDashboard({ stats, recentCases, recentLogs }) {
    return (
        <>
            <DashboardBanner />
            <header data-tour="dashboard-header" className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Admin Dashboard</h1>
                    <p className="text-sm text-slate-500 mt-1">System-wide overview and monitoring.</p>
                </div>
            </header>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <KpiCard title="Total Cases" value={stats.totalCases} icon="folder" iconBg="bg-indigo-50" iconColor="text-indigo-600" />
                <KpiCard title="Total Referrals" value={stats.totalReferrals} icon="send" iconBg="bg-orange-50" iconColor="text-orange-600" />
                <KpiCard title="Users" value={stats.totalUsers} icon="people" iconBg="bg-blue-50" iconColor="text-blue-600" />
                <KpiCard title="Agencies" value={stats.totalAgencies} icon="account_balance" iconBg="bg-green-50" iconColor="text-green-600" />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div data-tour="admin-recent-cases">
                <RecentTable
                    title="Recent Cases"
                    data={recentCases ?? []}
                    columns={[
                        { key: 'case_number', title: 'Case #', render: (row) => row.case_number },
                        { key: 'status', title: 'Status', render: (row) => (
                            <StatusBadge status={row.status} />
                        )},
                        { key: 'created', title: 'Created', render: (row) => formatDisplayDate(row.created_at) },
                    ]}
                    keyExtractor={(row) => row.id}
                    onViewAll={() => router.visit(route('cases.index'))}
                />
                </div>

                <div data-tour="admin-recent-activity" className="rounded-lg bg-white shadow-sm border border-slate-200">
                    <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                        <h3 className="text-base font-semibold text-slate-900">Recent Activity</h3>
                        <Link href={route('audit-logs.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
                    </div>
                    <div className="divide-y divide-slate-200">
                        {recentLogs?.length === 0 ? (
                            <p className="px-6 py-4 text-sm text-slate-500">No recent activity.</p>
                        ) : (
                            recentLogs?.map((log) => {
                                const cfg = actionConfig[log.action] || { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: '' }
                                return (
                                <div key={log.id} className="px-6 py-3 flex items-start gap-3">
                                    <span className={`shrink-0 flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white ${cfg.bg} shadow-sm mt-0.5`}>
                                        <cfg.icon className={`w-3.5 h-3.5 ${cfg.text}`} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm text-slate-900 font-medium leading-snug">
                                            {log.actor ? <span className="text-slate-500 font-normal">{log.actor} </span> : null}{log.message ?? log.description}
                                        </p>
                                        <div className="flex items-center gap-2 mt-1">
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ${cfg.text} ${cfg.bg}`}>
                                                {cfg.label || log.action}
                                            </span>
                                            <p className="text-xs text-slate-500">{formatDisplayDateTime(log.timestamp)}</p>
                                        </div>
                                    </div>
                                </div>
                                )
                            })
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function formatCaseAge(timestamp) {
  const parsed = new Date(timestamp)
  if (Number.isNaN(parsed.getTime())) return 'N/A'
  const ageInMs = Math.max(0, Date.now() - parsed.getTime())
  const oneDayInMs = 24 * 60 * 60 * 1000
  const ageInDays = Math.floor(ageInMs / oneDayInMs)
  if (ageInDays > 0) return `${ageInDays} day${ageInDays === 1 ? '' : 's'}`
  const ageInHours = Math.floor(ageInMs / (60 * 60 * 1000))
  if (ageInHours > 0) return `${ageInHours} hr${ageInHours === 1 ? '' : 's'}`
  const ageInMinutes = Math.floor(ageInMs / (60 * 1000))
  return `${Math.max(1, ageInMinutes)} min`
}

const actionConfig = {
  CREATE: { icon: UserPlus, bg: 'bg-emerald-50', text: 'text-emerald-600', ring: 'ring-emerald-200', label: 'Created' },
  UPDATE: { icon: Pencil, bg: 'bg-blue-50', text: 'text-blue-600', ring: 'ring-blue-200', label: 'Updated' },
  DELETE: { icon: Trash2, bg: 'bg-rose-50', text: 'text-rose-600', ring: 'ring-rose-200', label: 'Removed' },
  VIEW: { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: 'Viewed' },
  LOGIN: { icon: LogIn, bg: 'bg-indigo-50', text: 'text-indigo-600', ring: 'ring-indigo-200', label: 'Signed In' },
  LOGOUT: { icon: LogOut, bg: 'bg-orange-50', text: 'text-orange-600', ring: 'ring-orange-200', label: 'Signed Out' },
}

function ActivityItem({ title, desc, time, logoSrc, actionType, actor, message, detail }) {
  const cfg = actionConfig[actionType] || { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: '' }
  const ActionIcon = cfg.icon
  const displayTitle = message ?? title
  const displayDesc = title === desc ? '' : (detail || desc)

  return (
    <div className="relative flex items-start gap-3">
      {actionType ? (
        <span className={`shrink-0 flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white ${cfg.bg} shadow-sm`}>
          <ActionIcon className={`w-3.5 h-3.5 ${cfg.text}`} />
        </span>
      ) : (
        <span className="shrink-0 absolute -left-[25px] top-0 h-4 w-4 overflow-hidden rounded-full border border-white bg-white shadow-sm">
          <img src={logoSrc} alt="Activity source" className="h-full w-full object-contain p-[1px]" />
        </span>
      )}
      <div className="space-y-0.5 min-w-0">
        <p className="text-xs font-bold text-slate-900 font-body leading-snug">{displayTitle}</p>
        {displayDesc && (
          <p className="text-[11px] text-slate-500 font-body leading-relaxed line-clamp-2">{displayDesc}</p>
        )}
        <p className="text-[9px] font-bold uppercase tracking-widest text-blue-800">{time}</p>
      </div>
    </div>
  )
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
  },
  cutout: '50%',
}

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
    x: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
}

function getCaseAgeInDays(timestamp) {
  const parsed = new Date(timestamp)
  if (Number.isNaN(parsed.getTime())) return 0
  return Math.floor(Math.max(0, Date.now() - parsed.getTime()) / (24 * 60 * 60 * 1000))
}

function CaseManagerDashboard({
    stats,
    allCases = [],
    allReferrals = [],
    casesByProvince = [],
    agencyBreakdown = [],
    casesByCategory = [],
    casesOverTime = [],
    recentActivity = [],
    dashboardNotifications = [],
    workQueue,
    referralStatusDistribution,
    referralAgingBands,
    priorityReferrals,
    priorityCases,
    agencyResponseScorecard,
}) {
    const cases = safeArray(allCases);
    const referrals = safeArray(allReferrals);
    const todayLabel = new Intl.DateTimeFormat('en-US', { weekday: 'long', month: 'long', day: '2-digit', year: 'numeric' }).format(new Date());

    const sortedCases = useMemo(
        () => [...cases].sort((a, b) => new Date(b.updatedAt ?? b.updated_at ?? 0).getTime() - new Date(a.updatedAt ?? a.updated_at ?? 0).getTime()),
        [cases],
    );

    const latestReferralByCaseId = useMemo(() => {
        const acc = {};
        referrals.forEach((referral) => {
            const caseId = referral.caseId ?? referral.case_id;
            if (!caseId) return;
            const existing = acc[caseId];
            if (!existing || new Date(referral.updatedAt ?? referral.updated_at ?? 0).getTime() > new Date(existing.updatedAt ?? existing.updated_at ?? 0).getTime()) {
                acc[caseId] = referral;
            }
        });
        return acc;
    }, [referrals]);

    const caseCount = safeCount(stats.totalCases) || cases.length;
    const openCount = safeCount(stats.openCases) || cases.filter((item) => item.status === 'OPEN').length;
    const closedCount = safeCount(stats.closedCases) || cases.filter((item) => item.status === 'CLOSED').length;
    const pendingCount = safeCount(stats.pendingReferrals) || referrals.filter((item) => item.status === 'PENDING').length;
    const processingCount = safeCount(stats.processingReferrals) || referrals.filter((item) => item.status === 'PROCESSING').length;
    const rejectedCount = safeCount(stats.rejectedReferrals) || referrals.filter((item) => item.status === 'REJECTED').length;
    const completedReferralsCount = safeCount(stats.completedReferrals) || referrals.filter((item) => item.status === 'COMPLETED').length;
    const averageReferralCompletionRate = referrals.length > 0 ? Math.round((completedReferralsCount / referrals.length) * 100) : 0;

    const workQueueItems = safeArray(workQueue).length > 0 ? workQueue : [
        { key: 'agingOpenCases', label: 'Aging open cases', count: cases.filter((item) => item.status === 'OPEN' && getCaseAgeInDays(item.createdAt ?? item.created_at) >= 7).length, note: 'Open seven days or more.', tone: 'amber', href: '/cases' },
        { key: 'pendingReferrals', label: 'Pending referrals', count: pendingCount, note: 'Waiting for agency action.', tone: 'amber', href: '/referrals' },
        { key: 'rejectedReferrals', label: 'Returned referrals', count: rejectedCount, note: 'Needs reassignment or follow-up.', tone: 'rose', href: '/referrals' },
        { key: 'draftCases', label: 'Draft cases', count: safeCount(stats.myDraftCount), note: 'Your unfinished case drafts.', tone: 'slate', href: '/cases/drafts' },
        { key: 'casesWithoutReferrals', label: 'Cases without referrals', count: cases.filter((item) => item.status === 'OPEN' && !referrals.some((referral) => (referral.caseId ?? referral.case_id) === item.id)).length, note: 'Open cases that may need routing.', tone: 'blue', href: '/cases' },
    ];

    const caseStatusItems = [
        { key: 'open', label: 'Open', count: openCount, percent: caseCount > 0 ? Math.round((openCount / caseCount) * 100) : 0, tone: 'blue' },
        { key: 'closed', label: 'Closed', count: closedCount, percent: caseCount > 0 ? Math.round((closedCount / caseCount) * 100) : 0, tone: 'slate' },
    ];

    const referralStatusItems = safeArray(referralStatusDistribution).length > 0 ? referralStatusDistribution : [
        { key: 'pending', label: 'Pending', count: pendingCount, percent: referrals.length > 0 ? Math.round((pendingCount / referrals.length) * 100) : 0, tone: 'amber' },
        { key: 'processing', label: 'Processing', count: processingCount, percent: referrals.length > 0 ? Math.round((processingCount / referrals.length) * 100) : 0, tone: 'cyan' },
        { key: 'completed', label: 'Completed', count: completedReferralsCount, percent: referrals.length > 0 ? Math.round((completedReferralsCount / referrals.length) * 100) : 0, tone: 'emerald' },
        { key: 'rejected', label: 'Rejected', count: rejectedCount, percent: referrals.length > 0 ? Math.round((rejectedCount / referrals.length) * 100) : 0, tone: 'rose' },
    ].filter((item) => item.count > 0);

    const agingBands = safeArray(referralAgingBands).length > 0 ? referralAgingBands : referrals.length > 0 ? ['0-2', '3-5', '6-10', '11+'].map((band) => ({ key: band, label: `${band} days`, count: 0, percent: 0, tone: band === '0-2' ? 'emerald' : band === '3-5' ? 'amber' : band === '6-10' ? 'orange' : 'rose' })) : [];

    const priorityCaseRows = safeArray(priorityCases).length > 0 ? priorityCases : sortedCases.slice(0, 8).map((item) => ({
        id: item.id,
        caseNo: item.caseNo ?? item.case_number ?? 'N/A',
        trackerNumber: item.trackerNumber ?? item.tracker_number ?? null,
        clientName: item.clientName ?? item.client_name ?? 'N/A',
        status: item.status ?? 'OPEN',
        latestReferralStatus: latestReferralByCaseId[item.id]?.status ?? null,
        ageDays: getCaseAgeInDays(item.createdAt ?? item.created_at),
        reason: 'Recent case',
        href: `/cases/${item.id}`,
    })).filter((item) => item.status === 'OPEN' || item.latestReferralStatus || item.ageDays >= 7);

    const priorityReferralRows = safeArray(priorityReferrals).length > 0 ? priorityReferrals : referrals.filter((referral) => ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'REJECTED'].includes(referral.status)).slice(0, 8).map((referral) => ({
        id: referral.id,
        caseNo: referral.caseNo ?? referral.case_number ?? 'N/A',
        clientName: referral.clientName ?? referral.client_name ?? 'N/A',
        service: referral.required_services ?? referral.requiredServices ?? 'Service not specified',
        agencyName: referral.agencyName ?? referral.agency?.name ?? null,
        status: referral.status ?? 'PENDING',
        ageDays: getCaseAgeInDays(referral.createdAt ?? referral.created_at),
        href: `/referrals/${referral.id}`,
    }));

    const responseRows = safeArray(agencyResponseScorecard).length > 0 ? agencyResponseScorecard : safeArray(agencyBreakdown).map((item) => ({
        agencyId: item.agencyId ?? item.id,
        agencyName: item.agencyName ?? item.name,
        activeCount: item.activeReferrals ?? item.active_referrals_count ?? item.count ?? 0,
        overdueCount: 0,
        completedCount: item.completedCount ?? 0,
        averageCompletionDays: null,
        completionRate: 0,
        href: '/referrals',
    }));

    const caseTrendRows = safeArray(casesOverTime).map((item) => ({
        label: item.label,
        count: item.count,
        hex: '#1e3a8a',
    }));

    const demandCategoryRows = safeArray(casesByCategory).map((item) => ({
        key: item.name,
        label: item.name,
        count: item.count,
        detail: 'Active case demand by category.',
        hex: item.color,
        tone: 'blue',
    }));

    const geographyRows = safeArray(casesByProvince).map((item) => ({
        key: item.province,
        label: item.province,
        count: item.count,
        detail: 'Case load by province.',
        tone: 'slate',
    }));

    const activeCaseRows = sortedCases.slice(0, 6).map((item) => {
        const latestRef = latestReferralByCaseId[item.id];
        const ageDays = getCaseAgeInDays(item.createdAt ?? item.created_at);
        return {
            href: `/cases/${item.id}`,
            title: item.caseNo ?? item.case_number ?? item.trackerNumber ?? item.tracker_number ?? 'Case',
            subtitle: item.clientName ?? item.client_name ?? 'N/A',
            note: latestRef?.status ? `Latest referral ${formatStatusLabel(latestRef.status)}` : 'No referral state yet',
            meta: `${ageDays} day${ageDays === 1 ? '' : 's'} old`,
            badges: [item.status, latestRef?.status].filter(Boolean),
            age: ageDays > 0 ? `${ageDays}d` : 'New',
            tone: ageDays >= 10 ? 'rose' : ageDays >= 5 ? 'amber' : 'blue',
        };
    });

    const draftRows = safeArray(stats.myRecentDrafts).map((item) => ({
        href: `/cases/${item.id}`,
        title: item.case_number ?? item.caseNo ?? 'Draft case',
        subtitle: item.client_name ?? item.clientName ?? 'N/A',
        note: item.created_at ? `Created ${formatDisplayDate(item.created_at)}` : 'Draft in progress',
        meta: 'Draft',
        badges: ['DRAFT'],
        age: 'Draft',
        tone: 'slate',
    }));

    return (
        <div className="max-w-7xl mx-auto pb-6">
            <DashboardBanner />
            <GettingStartedChecklist />
            <header data-tour="dashboard-header" className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-slate-400">Case manager</p>
                    <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 md:text-3xl">Today’s work queue</h1>
                    <p className="mt-1 text-sm text-slate-500">Keep the pipeline moving, then scan bottlenecks, demand, and open cases.</p>
                </div>
                <div className="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-slate-400">
                    <span className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{caseCount} cases</span>
                    <span className="rounded-full bg-blue-50 px-3 py-1 text-blue-900">{safeCount(stats.totalReferrals) || referrals.length} referrals</span>
                </div>
            </header>

            <div data-tour="dashboard-stats">
                <WorkQueueRibbon
                    title="Today’s work queue"
                    subtitle="Dispatch board"
                    items={workQueueItems}
                    emptyTitle="No immediate work queue items"
                    emptyDescription="There are no urgent case-management actions right now. Open the cases workflow when you need to continue work."
                    emptyHref="/cases"
                />
            </div>

            <div className="mt-6 grid gap-6">
                <div className="order-2 grid gap-6 xl:order-1 xl:grid-cols-12">
                    <div className="xl:col-span-6">
                        <DashboardChartCard
                            title="Case status"
                            eyebrow="Pipeline health"
                            items={caseStatusItems}
                            dataTour="dashboard-chart"
                            emptyTitle="No case status data"
                            emptyDescription="Status distribution appears once case files are available."
                            emptyHref="/cases"
                            actionLabel="Open cases"
                        />
                    </div>
                    <div className="xl:col-span-6">
                        <DashboardChartCard
                            title="Referral status"
                            eyebrow="Pipeline health"
                            items={referralStatusItems}
                            dataTour="dashboard-referral-health"
                            emptyTitle="No referral status data"
                            emptyDescription="Referral distribution appears once referrals are in play."
                            emptyHref="/referrals"
                            actionLabel="Open referrals"
                        />
                    </div>
                    <div className="xl:col-span-12">
                        <InsightCard
                            title="Case trend"
                            eyebrow="Pipeline health"
                            dataTour="dashboard-case-trend"
                        >
                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_180px] lg:items-center">
                                <div className="h-44">
                                    {caseTrendRows.length > 0 ? (
                                        <Bar
                                            data={{
                                                labels: caseTrendRows.map((item) => item.label),
                                                datasets: [{ label: 'Cases', data: caseTrendRows.map((item) => item.count), backgroundColor: '#1e3a8a', borderRadius: 3 }],
                                            }}
                                            options={barOptions}
                                        />
                                    ) : (
                                        <EmptyChartState title="No case trend data" description="Creation trend will appear as case activity accumulates." href="/cases" actionLabel="Open cases" />
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="rounded-xl border border-slate-200 bg-white p-3">
                                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Average close time</p>
                                        <p className="mt-1 text-2xl font-black text-slate-950">{Number(stats.averageCaseDaysToClose ?? 0).toFixed(1)}<span className="text-sm font-bold text-slate-400">d</span></p>
                                    </div>
                                    <div className="rounded-xl border border-slate-200 bg-white p-3">
                                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Completion rate</p>
                                        <p className="mt-1 text-2xl font-black text-slate-950">{averageReferralCompletionRate}%</p>
                                    </div>
                                    <div className="col-span-2 rounded-xl border border-slate-200 bg-white p-3">
                                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Throughput</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-700">{completedReferralsCount} completed referrals are contributing to the case pipeline.</p>
                                    </div>
                                </div>
                            </div>
                        </InsightCard>
                    </div>
                </div>

                <div className="order-3 grid gap-6 xl:order-2 xl:grid-cols-12">
                    <div className="xl:col-span-7">
                        <HorizontalBarSummary
                            title="Agency response scorecard"
                            eyebrow="Coordination"
                            items={responseRows.map((item) => ({
                                key: item.agencyId ?? item.agencyName,
                                label: item.agencyName,
                                count: item.activeCount,
                                detail: `Overdue ${item.overdueCount ?? 0} · Completed ${item.completedCount ?? 0}`,
                                meta: `Completion ${item.completionRate ?? 0}%`,
                                href: item.href ?? '/referrals',
                                tone: item.overdueCount > 0 ? 'rose' : 'blue',
                            }))}
                            dataTour="dashboard-agency-response-scorecard"
                            emptyTitle="No agency response data"
                            emptyDescription="A scorecard will appear once referral activity spans multiple agencies."
                            emptyHref="/referrals"
                            actionLabel="Open referrals"
                        />
                    </div>
                    <div className="xl:col-span-5">
                        <InsightCard title="Completion context" eyebrow="Coordination">
                            <div className="space-y-3">
                                <div className="rounded-xl border border-slate-200 bg-white p-3">
                                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending referrals</p>
                                    <p className="mt-1 text-xl font-black text-slate-950">{pendingCount}</p>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-white p-3">
                                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Processing referrals</p>
                                    <p className="mt-1 text-xl font-black text-slate-950">{processingCount}</p>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-white p-3">
                                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Returned referrals</p>
                                    <p className="mt-1 text-xl font-black text-slate-950">{rejectedCount}</p>
                                </div>
                            </div>
                        </InsightCard>
                    </div>
                </div>

                <div className="order-4 grid gap-6 xl:order-3 xl:grid-cols-12">
                    <div className="xl:col-span-6">
                        <HorizontalBarSummary
                            title="Category demand"
                            eyebrow="Demand context"
                            items={demandCategoryRows}
                            dataTour="dashboard-demand-context"
                            emptyTitle="No category demand"
                            emptyDescription="Category demand will show once case files have active demand data."
                            emptyHref="/cases"
                            actionLabel="Open cases"
                        />
                    </div>
                    <div className="xl:col-span-6">
                        <HorizontalBarSummary
                            title="Geography load"
                            eyebrow="Demand context"
                            items={geographyRows}
                            dataTour="dashboard-geography-context"
                            emptyTitle="No geography data"
                            emptyDescription="Province summaries appear once the case population is broad enough."
                            emptyHref="/cases"
                            actionLabel="Open cases"
                        />
                    </div>
                </div>

                <div className="order-1 grid gap-6 xl:order-4 xl:grid-cols-12">
                    <div className="xl:col-span-6">
                        <InsightCard
                            title="Priority cases"
                            eyebrow="Attention list"
                            dataTour="dashboard-attention-list"
                            action={
                                <Link href="/cases" className="text-[11px] font-bold uppercase tracking-widest text-blue-900 transition hover:text-blue-700">
                                    View all
                                </Link>
                            }
                        >
                            <div className="space-y-3">
                                {priorityCaseRows.length > 0 ? priorityCaseRows.map((item, index) => (
                                    <PriorityListRow
                                        key={item.id ?? item.caseNo ?? index}
                                        item={{
                                            href: item.href,
                                            title: item.trackerNumber ?? item.caseNo,
                                            subtitle: item.clientName,
                                            note: item.reason ?? 'Needs follow-up',
                                            meta: `${item.ageDays ?? 0} day${(item.ageDays ?? 0) === 1 ? '' : 's'} old`,
                                            badges: [item.status, item.latestReferralStatus].filter(Boolean),
                                            age: item.ageDays > 0 ? `${item.ageDays}d` : 'New',
                                            tone: item.reason === 'Returned referral' ? 'rose' : item.reason === 'No referral yet' ? 'amber' : 'blue',
                                        }}
                                        index={index}
                                    />
                                )) : (
                                    <EmptyChartState title="No priority cases" description="Aging or blocked cases will be surfaced here first." href="/cases" actionLabel="Open cases" />
                                )}
                            </div>
                        </InsightCard>
                    </div>
                    <div className="xl:col-span-6">
                        <InsightCard
                            title="Priority referrals"
                            eyebrow="Attention list"
                            dataTour="dashboard-priority-referrals"
                            action={
                                <Link href="/referrals" className="text-[11px] font-bold uppercase tracking-widest text-blue-900 transition hover:text-blue-700">
                                    View all
                                </Link>
                            }
                        >
                            <div className="space-y-3">
                                {priorityReferralRows.length > 0 ? priorityReferralRows.map((item, index) => (
                                    <PriorityListRow
                                        key={item.id ?? item.caseNo ?? index}
                                        item={{
                                            href: item.href,
                                            title: item.caseNo,
                                            subtitle: item.clientName,
                                            note: `${item.service}${item.agencyName ? ` · ${item.agencyName}` : ''}`,
                                            meta: `${item.ageDays ?? 0} day${(item.ageDays ?? 0) === 1 ? '' : 's'} old`,
                                            badges: [item.status].filter(Boolean),
                                            age: item.ageDays > 0 ? `${item.ageDays}d` : 'New',
                                            tone: item.status === 'REJECTED' ? 'rose' : item.status === 'FOR_COMPLIANCE' ? 'orange' : item.status === 'PROCESSING' ? 'cyan' : 'amber',
                                        }}
                                        index={index}
                                    />
                                )) : (
                                    <EmptyChartState title="No priority referrals" description="Referral bottlenecks will be listed here when they appear." href="/referrals" actionLabel="Open referrals" />
                                )}
                            </div>
                        </InsightCard>
                    </div>
                </div>

                <div className="order-5 grid gap-6 xl:order-5 xl:grid-cols-12">
                    <div className="xl:col-span-3">
                        <InsightCard title="Drafts" eyebrow="Work in progress" dataTour="dashboard-drafts" action={<Link href="/cases/drafts" className="text-[11px] font-bold uppercase tracking-widest text-blue-900 transition hover:text-blue-700">View all</Link>}>
                            <div className="space-y-3">
                                {draftRows.length > 0 ? draftRows.map((item, index) => (
                                    <PriorityListRow key={`${item.title}-${index}`} item={item} index={index} />
                                )) : <EmptyChartState title="No drafts" description="Your saved drafts will show up here." href="/cases/drafts" actionLabel="Open drafts" />}
                            </div>
                        </InsightCard>
                    </div>
                    <div className="xl:col-span-5">
                        <InsightCard title="Active cases" eyebrow="Live queue" dataTour="dashboard-recent" action={<Link href="/cases" className="text-[11px] font-bold uppercase tracking-widest text-blue-900 transition hover:text-blue-700">View all</Link>}>
                            <div className="space-y-3">
                                {activeCaseRows.length > 0 ? activeCaseRows.map((item, index) => (
                                    <PriorityListRow key={item.href} item={item} index={index} />
                                )) : <EmptyChartState title="No active cases" description="Open cases will appear here once casework begins." href="/cases" actionLabel="Open cases" />}
                            </div>
                        </InsightCard>
                    </div>
                    <div className="xl:col-span-4">
                        <InsightCard title="Quick actions" eyebrow="Navigate" dataTour="dashboard-quick-actions">
                            <div className="space-y-2">
                                {[
                                    { href: '/cases/create', label: 'New case', icon: Plus, tone: 'blue' },
                                    { href: '/cases/drafts', label: 'Open drafts', icon: NotepadText, tone: 'slate' },
                                    { href: '/referrals', label: 'Open referrals', icon: Send, tone: 'slate' },
                                    { href: '/audit-logs', label: 'Activity logs', icon: TrendingUp, tone: 'slate' },
                                ].map((action) => {
                                    const Icon = action.icon;

                                    return (
                                        <Link
                                            key={action.href}
                                            href={action.href}
                                            className={`flex items-center justify-between rounded-xl border px-3.5 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-900/20 ${action.tone === 'blue' ? 'border-blue-900 bg-blue-900 text-white hover:bg-blue-800' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'}`}
                                        >
                                            <span className="flex items-center gap-2 text-[12px] font-bold">
                                                <Icon className={`h-3.5 w-3.5 ${action.tone === 'blue' ? 'text-white' : 'text-slate-400'}`} />
                                                {action.label}
                                            </span>
                                            <ChevronRight className={`h-3.5 w-3.5 ${action.tone === 'blue' ? 'text-white/70' : 'text-slate-300'}`} />
                                        </Link>
                                    );
                                })}
                            </div>
                        </InsightCard>
                    </div>
                </div>

                <div className="order-6">
                    <InsightCard title="Recent activity" eyebrow="Audit trail" dataTour="dashboard-recent-activity" action={<Link href="/audit-logs" className="text-[11px] font-bold uppercase tracking-widest text-blue-900 transition hover:text-blue-700">View all</Link>}>
                        <div className="relative space-y-4 border-l-2 border-slate-100 pl-4">
                            {safeArray(recentActivity).slice(0, 5).length === 0 ? (
                                <p className="py-2 text-xs text-slate-400">No recent activity.</p>
                            ) : (
                                safeArray(recentActivity).slice(0, 5).map((activity) => (
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
                                ))
                            )}
                        </div>
                    </InsightCard>
                </div>
            </div>
        </div>
    );
}

export default function Dashboard(props) {
    const deferredDashboard = props.dashboard ?? {};
    const dashboardProps = {
        ...props,
        ...deferredDashboard,
        role: props.role ?? deferredDashboard.role,
    };

    const {
        role, recentCases, recentReferrals, recentLogs,
        allCases, allReferrals, casesByProvince, agencyBreakdown,
        casesByCategory, casesOverTime, recentActivity, dashboardNotifications,
        ...stats
    } = dashboardProps;

    if (role === 'AGENCY') {
        return <AgencyDashboard dashboard={dashboard ?? {}} />;
    }

    return <CaseManagerDashboard dashboard={dashboard ?? {}} />;
}

export default function Dashboard({ role }) {
    const showTourPrototype = typeof window !== 'undefined'
        && new URLSearchParams(window.location.search).get('__TOUR_PROTO__') === '1';

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl">
                <DashboardBanner />
            </div>
            {showTourPrototype && <TourPrototype />}
            <Deferred data="dashboard" fallback={<DashboardSkeleton />}>
                <DashboardContent role={role} />
            </Deferred>
        </AppLayout>
    );
}
