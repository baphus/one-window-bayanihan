import { usePage } from '@inertiajs/react';
import StatusBadge from '@/Components/ui/StatusBadge';
import {
    ActivityFeed,
    BarList,
    EmptyState,
    EntityList,
    EntityRow,
    PageHeader,
    QuickActions,
    SectionCard,
    StatRow,
    StatusDonut,
    TriageStrip,
    ViewAllLink,
    formatCount,
    safeArray,
} from '@/Components/Dashboard/primitives';

function formatAge(days) {
    const parsed = Number(days ?? 0);
    return parsed > 0 ? `${parsed}d` : 'New';
}

function PulseStat({ label, value }) {
    return (
        <div className="rounded-lg bg-slate-50 px-3 py-2.5">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{label}</p>
            <p className="mt-0.5 text-lg font-black text-slate-900">{value}</p>
        </div>
    );
}

export default function AgencyDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';

    const priorityReferrals = safeArray(dashboard.priorityReferrals).slice(0, 8);
    const serviceDemand = safeArray(dashboard.serviceDemand).slice(0, 6);
    const agingBands = safeArray(dashboard.referralAgingBands);
    const pulse = dashboard.feedbackPulse ?? {};
    const hasPulse = Boolean(pulse.hasData);

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <PageHeader
                eyebrow="Agency focal"
                title={`Welcome back, ${firstName}`}
                subtitle="Referrals assigned to your agency, ordered by what needs action first."
            >
                <QuickActions
                    actions={[
                        { href: '/referrals', label: 'Open referrals', icon: 'send', primary: true },
                        { href: '/overdue-referrals', label: 'Overdue', icon: 'warning' },
                        { href: '/feedbacks', label: 'Feedback', icon: 'reviews' },
                        { href: '/reports', label: 'Reports', icon: 'bar_chart' },
                    ]}
                />
            </PageHeader>

            <StatRow
                dataTour="dashboard-agency-metrics"
                stats={[
                    { title: 'Pending', value: dashboard.pendingReferrals, icon: 'schedule', iconBg: 'bg-amber-50', iconColor: 'text-amber-700' },
                    { title: 'Processing', value: dashboard.processingReferrals, icon: 'sync', iconBg: 'bg-cyan-50', iconColor: 'text-cyan-700' },
                    { title: 'For compliance', value: dashboard.forComplianceReferrals, icon: 'fact_check', iconBg: 'bg-orange-50', iconColor: 'text-orange-700' },
                    { title: 'Completed', value: dashboard.completedReferrals, icon: 'task_alt', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-700' },
                ]}
            />

            <TriageStrip items={dashboard.workQueue} dataTour="dashboard-work-queue" />

            <div className="grid gap-6 xl:grid-cols-12">
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
                                    pill={item.caseNo}
                                    title={item.clientName}
                                    note={item.service}
                                    age={formatAge(item.ageDays)}
                                    right={<StatusBadge status={item.status} />}
                                />
                            ))}
                        </EntityList>
                    </SectionCard>

                    <SectionCard title="Recent activity" action={<ViewAllLink href="/audit-logs" />}>
                        <ActivityFeed items={dashboard.recentActivity} limit={6} />
                    </SectionCard>
                </div>

                <div className="space-y-6 xl:col-span-4">
                    <SectionCard title="Referral status">
                        {safeArray(dashboard.referralStatusDistribution).length > 0 ? (
                            <StatusDonut items={dashboard.referralStatusDistribution} />
                        ) : (
                            <p className="text-sm text-slate-500">Status distribution appears once referrals arrive.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Referral aging">
                        {agingBands.length > 0 ? (
                            <BarList
                                items={agingBands.map((item) => ({
                                    key: item.key,
                                    label: item.label,
                                    count: item.count,
                                    percent: item.percent,
                                    tone: item.tone,
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">Aging bands appear once active referrals are present.</p>
                        )}
                    </SectionCard>

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

                    <SectionCard title="Client feedback" action={<ViewAllLink href="/feedbacks" />}>
                        {hasPulse ? (
                            <div className="space-y-3">
                                <div className="grid grid-cols-3 gap-2">
                                    <PulseStat label="Response" value={`${formatCount(pulse.responseRate)}%`} />
                                    <PulseStat label="Rating" value={pulse.avgRating ?? '—'} />
                                    <PulseStat label="SERVQUAL" value={pulse.avgServqual ?? '—'} />
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
