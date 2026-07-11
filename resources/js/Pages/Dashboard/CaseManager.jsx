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
    TrendBar,
    TriageStrip,
    ViewAllLink,
    safeArray,
} from '@/Components/Dashboard/primitives';

function formatAge(days) {
    const parsed = Number(days ?? 0);
    return parsed > 0 ? `${parsed}d` : 'New';
}

export default function CaseManagerDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';

    const priorityCases = safeArray(dashboard.priorityCases).slice(0, 6);
    const priorityReferrals = safeArray(dashboard.priorityReferrals).slice(0, 6);
    const scorecard = safeArray(dashboard.agencyResponseScorecard).slice(0, 5);
    const categories = safeArray(dashboard.casesByCategory).slice(0, 5);
    const caseTrends = dashboard.caseTrends ?? { labels: [], data: [] };

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <PageHeader
                eyebrow="Case manager"
                title={`Welcome back, ${firstName}`}
                subtitle="Start with the queues that need movement, then review pipeline health."
            >
                <QuickActions
                    actions={[
                        { href: '/cases/create', label: 'New case', icon: 'add', primary: true },
                        { href: '/cases/drafts', label: 'Drafts', icon: 'edit_note', count: dashboard.myDraftCount },
                        { href: '/referrals', label: 'Referrals', icon: 'send' },
                        { href: '/reports', label: 'Reports', icon: 'bar_chart' },
                    ]}
                />
            </PageHeader>

            <StatRow
                dataTour="dashboard-stats"
                stats={[
                    { title: 'Open cases', value: dashboard.openCases, icon: 'folder_open' },
                    { title: 'Clients served', value: dashboard.uniqueClientCount, icon: 'groups', iconBg: 'bg-cyan-50', iconColor: 'text-cyan-700' },
                    { title: 'Pending referrals', value: dashboard.pendingReferrals, icon: 'schedule', iconBg: 'bg-amber-50', iconColor: 'text-amber-700' },
                    { title: 'Avg. days to close', value: dashboard.averageCaseDaysToClose, suffix: 'd', icon: 'timer', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-700' },
                ]}
            />

            <TriageStrip items={dashboard.workQueue} dataTour="dashboard-work-queue" />

            <div className="grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-8">
                    <SectionCard
                        title="Priority cases"
                        dataTour="dashboard-recent"
                        action={<ViewAllLink href="/cases" />}
                        bodyClassName=""
                    >
                        <EntityList empty={<EmptyState message="No cases need attention right now." href="/cases" actionLabel="Open cases" />}>
                            {priorityCases.map((item) => (
                                <EntityRow
                                    key={item.id}
                                    href={item.href ?? `/cases/${item.id}`}
                                    pill={item.trackerNumber ?? item.caseNo}
                                    title={item.clientName}
                                    note={item.reason}
                                    age={formatAge(item.ageDays)}
                                    right={
                                        <>
                                            <StatusBadge status={item.status} />
                                            {item.latestReferralStatus ? <StatusBadge status={item.latestReferralStatus} /> : null}
                                        </>
                                    }
                                />
                            ))}
                        </EntityList>
                    </SectionCard>

                    <SectionCard
                        title="Priority referrals"
                        action={<ViewAllLink href="/referrals" />}
                        bodyClassName=""
                    >
                        <EntityList empty={<EmptyState message="No referrals are blocked or waiting." href="/referrals" actionLabel="Open referrals" />}>
                            {priorityReferrals.map((item) => (
                                <EntityRow
                                    key={item.id}
                                    href={item.href ?? `/referrals/${item.id}`}
                                    pill={item.caseNo}
                                    title={item.clientName}
                                    note={[item.service, item.agencyName].filter(Boolean).join(' · ')}
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
                    <SectionCard title="Referral status" dataTour="dashboard-chart">
                        {safeArray(dashboard.referralStatusDistribution).length > 0 ? (
                            <StatusDonut items={dashboard.referralStatusDistribution} />
                        ) : (
                            <p className="text-sm text-slate-500">Status distribution appears once referrals are in play.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Cases per month">
                        {safeArray(caseTrends.labels).length > 0 ? (
                            <TrendBar labels={caseTrends.labels} data={caseTrends.data} />
                        ) : (
                            <p className="text-sm text-slate-500">The trend appears as case activity accumulates.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Agency responsiveness">
                        {scorecard.length > 0 ? (
                            <BarList
                                items={scorecard.map((item) => ({
                                    key: item.agencyId ?? item.agencyName,
                                    label: item.agencyName,
                                    count: item.activeCount,
                                    detail: `${item.overdueCount ?? 0} overdue · ${item.completionRate ?? 0}% completion`,
                                    tone: (item.overdueCount ?? 0) > 0 ? 'rose' : 'blue',
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">A scorecard appears once referrals span multiple agencies.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Case mix">
                        {categories.length > 0 ? (
                            <BarList
                                items={categories.map((item) => ({
                                    key: item.name,
                                    label: item.name,
                                    count: item.count,
                                    hex: item.color,
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">Category demand appears once cases are filed.</p>
                        )}
                    </SectionCard>
                </div>
            </div>
        </div>
    );
}
