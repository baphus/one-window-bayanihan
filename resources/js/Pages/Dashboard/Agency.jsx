import { Link, usePage } from '@inertiajs/react';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import {
    ActivityFeed,
    BarList,
    EmptyState,
    EntityList,
    EntityRow,
    PageHeader,
    QuickActions,
    SectionCard,
    ViewAllLink,
    formatCount,
    safeArray,
    toneDot,
} from '@/Components/Dashboard/primitives';

const OVERDUE_DAYS = 5;

function toAgeDays(value, fallback = 0) {
    if (value === undefined || value === null) {
        return fallback;
    }
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

function PulseStat({ label, value }) {
    return (
        <div className="rounded-lg bg-slate-50 px-3 py-2.5">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{label}</p>
            <p className="mt-0.5 text-lg font-black text-slate-900">{value}</p>
        </div>
    );
}

const QUEUE_ORDER = ['newReferrals', 'pendingReferrals', 'processingReferrals', 'forComplianceReferrals', 'overdueReferrals', 'returnedReferrals'];

export default function AgencyDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';

    const priorityReferrals = safeArray(dashboard.priorityReferrals).slice(0, 5);
    const serviceDemand = safeArray(dashboard.serviceDemand).slice(0, 6);
    const pulse = dashboard.feedbackPulse ?? {};
    const hasPulse = Boolean(pulse.hasData);

    const queueCells = QUEUE_ORDER.map((key) => safeArray(dashboard.workQueue).find((item) => item.key === key))
        .filter(Boolean)
        .map((item) => (item.key === 'returnedReferrals' ? { ...item, tone: 'slate' } : item));

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <GettingStartedChecklist />

            <PageHeader
                eyebrow="Agency focal"
                title={`Welcome back, ${firstName}`}
                subtitle="Referrals assigned to your agency, ordered by what needs action first."
            >
                <QuickActions
                    actions={[
                        { href: '/referrals', label: 'Open referrals', icon: 'send', primary: true },
                        { href: '/overdue-referrals', label: 'Overdue', icon: 'warning' },
                        { href: '/surveys', label: 'Surveys', icon: 'reviews' },
                        { href: '/reports', label: 'Reports', icon: 'bar_chart' },
                    ]}
                />
            </PageHeader>

            <SectionCard title="My referrals" dataTour="dashboard-work-queue" bodyClassName="">
                <div data-tour="dashboard-stats" className="grid grid-cols-2 gap-px overflow-hidden rounded-b-xl bg-slate-100 sm:grid-cols-3 lg:grid-cols-6">
                    {queueCells.map((item, index) => {
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
            </SectionCard>

            <div className="mt-6 grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-8">
                    <SectionCard
                        title="Priority referrals"
                        dataTour="dashboard-agency-referrals"
                        action={<ViewAllLink href="/referrals" />}
                        bodyClassName=""
                    >
                        <EntityList empty={<EmptyState message="No referrals need action right now." href="/referrals" actionLabel="Open referrals" />}>
                            {priorityReferrals.map((item) => (
                                <EntityRow
                                    key={item.id}
                                    href={item.href ?? `/referrals/${item.id}`}
                                    pill={item.case_number}
                                    title={item.client_name}
                                    note={item.service}
                                    age={<AgeFlag days={item.age_days} />}
                                />
                            ))}
                        </EntityList>
                    </SectionCard>

                    <SectionCard title="Recent activity">
                        <ActivityFeed items={dashboard.recentActivity} limit={6} />
                    </SectionCard>
                </div>

                <div className="space-y-6 xl:col-span-4">
                    <SectionCard title="Service demand">
                        {serviceDemand.length > 0 ? (
                            <BarList
                                items={serviceDemand.map((item) => ({
                                    key: item.serviceId ?? item.serviceName,
                                    label: item.serviceName,
                                    count: item.totalCount,
                                    detail: `${item.activeCount} active · ${item.completionRate}% completion`,
                                    tone: 'blue',
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">Demand appears once referrals request your services.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Client feedback" action={<ViewAllLink href="/surveys" />}>
                        {hasPulse ? (
                            <div className="space-y-3">
                                <div className="grid grid-cols-2 gap-2">
                                    <PulseStat label="Response" value={`${formatCount(pulse.responseRate)}%`} />
                                    <PulseStat label="Rating" value={pulse.avgRating ?? '—'} />
                                </div>
                                <p className="text-xs text-slate-500">
                                    {formatCount(pulse.totalSubmitted)} of {formatCount(pulse.totalSent)} invitations answered.
                                </p>
                            </div>
                        ) : (
                            <p className="text-sm text-slate-500">Feedback signals appear once clients respond to invitations.</p>
                        )}
                    </SectionCard>
                </div>
            </div>
        </div>
    );
}
