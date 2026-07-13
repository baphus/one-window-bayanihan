import { usePage } from '@inertiajs/react';
import DashboardBanner from '@/Components/DashboardBanner';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatCaseAge, formatDisplayDate, getCaseAgeInDays } from '@/lib/utils';
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
    TrendBar,
    ViewAllLink,
    safeArray,
} from '@/Components/Dashboard/primitives';

function buildWorkQueue(dashboard, stats, referrals, cases) {
    const supplied = safeArray(dashboard.workQueue);

    if (supplied.length > 0) {
        return supplied;
    }

    return [
        { key: 'openCases', label: 'Open cases', count: cases.filter((item) => item.status === 'OPEN').length || stats.openCases || stats.totalOpenCases || 0, note: 'Cases still active.', tone: 'blue', href: '/cases' },
        { key: 'pendingReferrals', label: 'Pending referrals', count: referrals.filter((item) => item.status === 'PENDING').length || stats.pendingReferrals || 0, note: 'Awaiting agency action.', tone: 'amber', href: '/referrals' },
        { key: 'processingReferrals', label: 'Processing', count: referrals.filter((item) => item.status === 'PROCESSING').length || stats.processingReferrals || 0, note: 'Currently being worked.', tone: 'cyan', href: '/referrals' },
        { key: 'overdueReferrals', label: 'Overdue', count: stats.overdueReferrals || 0, note: 'Needs follow-up.', tone: 'rose', href: '/overdue-referrals' },
    ];
}

export default function CaseManagerDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'there';

    const stats = dashboard.stats ?? dashboard;
    const cases = safeArray(dashboard.allCases ?? dashboard.recentCases);
    const referrals = safeArray(dashboard.allReferrals ?? dashboard.recentReferrals);
    const activity = safeArray(dashboard.recentActivity ?? dashboard.recentLogs).slice(0, 6);
    const agencyBreakdown = safeArray(dashboard.agencyBreakdown).slice(0, 6);
    const categories = safeArray(dashboard.casesByCategory).slice(0, 6);
    const trends = dashboard.caseTrends ?? {
        labels: safeArray(dashboard.casesOverTime).map((item) => item.label),
        data: safeArray(dashboard.casesOverTime).map((item) => item.count),
    };

    const latestReferralByCaseId = referrals.reduce((acc, referral) => {
        const caseId = referral.caseId ?? referral.case_id;
        if (!caseId) return acc;

        const current = acc[caseId];
        const currentDate = new Date(current?.updatedAt ?? current?.updated_at ?? 0).getTime();
        const nextDate = new Date(referral.updatedAt ?? referral.updated_at ?? 0).getTime();

        if (!current || nextDate >= currentDate) {
            acc[caseId] = referral;
        }

        return acc;
    }, {});

    const recentCases = cases
        .slice()
        .sort((a, b) => new Date(b.updatedAt ?? b.updated_at ?? 0).getTime() - new Date(a.updatedAt ?? a.updated_at ?? 0).getTime())
        .slice(0, 6);

    const workQueue = buildWorkQueue(dashboard, stats, referrals, cases);

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <DashboardBanner />
            <GettingStartedChecklist />

            <PageHeader
                eyebrow="Case manager"
                title={`Welcome back, ${firstName}`}
                subtitle="Your active cases, referrals, and follow-ups in one place."
            >
                <QuickActions
                    actions={[
                        { href: '/cases/create', label: 'New case', icon: 'add', primary: true },
                        { href: '/cases', label: 'Cases', icon: 'folder', count: stats.totalCases },
                        { href: '/referrals', label: 'Referrals', icon: 'send', count: stats.totalReferrals },
                    ]}
                />
            </PageHeader>

            <StatRow
                dataTour="dashboard-stats"
                stats={[
                    { title: 'Active cases', value: stats.openCases ?? stats.totalOpenCases ?? cases.filter((item) => item.status === 'OPEN').length, icon: 'folder', description: 'Cases currently being handled.' },
                    { title: 'Clients served', value: stats.uniqueClientCount ?? stats.totalClients ?? 0, icon: 'groups', iconBg: 'bg-violet-50', iconColor: 'text-violet-600', description: `${stats.ofwCount ?? 0} OFW · ${stats.nokCount ?? 0} NOK` },
                    { title: 'Pending referrals', value: stats.pendingReferrals ?? referrals.filter((item) => item.status === 'PENDING').length, icon: 'schedule', iconBg: 'bg-amber-50', iconColor: 'text-amber-700', description: 'Referrals waiting for first action.' },
                    { title: 'Completed referrals', value: stats.completedReferrals ?? referrals.filter((item) => item.status === 'COMPLETED').length, icon: 'task_alt', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-700', description: 'Referrals completed by agencies.' },
                ]}
            />

            <TriageStrip items={workQueue} dataTour="dashboard-work-queue" />

            <div className="grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-8">
                    <SectionCard
                        title="Recent case activity"
                        dataTour="dashboard-recent-cases"
                        action={<ViewAllLink href="/cases">View all cases</ViewAllLink>}
                        bodyClassName=""
                    >
                        <EntityList empty={<EmptyState message="No recent cases yet." href="/cases/create" actionLabel="Create a case" />}>
                            {recentCases.map((item) => {
                                const caseId = item.id;
                                const latestReferral = latestReferralByCaseId[caseId];
                                const createdAt = item.createdAt ?? item.created_at;
                                const ageDays = getCaseAgeInDays(createdAt);
                                const ageColor = ageDays <= 3 ? 'text-emerald-600' : ageDays <= 10 ? 'text-amber-600' : 'text-rose-600';

                                return (
                                    <EntityRow
                                        key={caseId}
                                        href={`/cases/${caseId}`}
                                        pill={item.trackerNumber ?? item.tracker_number ?? item.caseNo ?? item.case_number}
                                        title={item.clientName ?? item.client_name ?? 'Unnamed client'}
                                        note={[item.category, latestReferral?.agencyName ?? latestReferral?.agency_name, createdAt ? `Filed ${formatDisplayDate(createdAt)}` : null].filter(Boolean).join(' · ')}
                                        age={<span className={ageColor}>{formatCaseAge(createdAt)}</span>}
                                        right={<StatusBadge status={item.status} />}
                                    />
                                );
                            })}
                        </EntityList>
                    </SectionCard>

                    <SectionCard title="Recent activity" dataTour="dashboard-recent-activity">
                        <ActivityFeed items={activity} />
                    </SectionCard>
                </div>

                <aside className="space-y-6 xl:col-span-4">
                    <SectionCard title="Referral status" dataTour="dashboard-chart">
                        {safeArray(dashboard.referralStatusDistribution).length > 0 ? (
                            <StatusDonut items={dashboard.referralStatusDistribution} />
                        ) : (
                            <p className="text-sm text-slate-500">Status distribution appears once referrals are in play.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Cases per month">
                        {safeArray(trends.labels).length > 0 ? (
                            <TrendBar labels={trends.labels} data={trends.data} />
                        ) : (
                            <p className="text-sm text-slate-500">The trend appears as case activity accumulates.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Agency load">
                        {agencyBreakdown.length > 0 ? (
                            <BarList
                                items={agencyBreakdown.map((item) => ({
                                    key: item.agencyId ?? item.agency_id ?? item.agencyName,
                                    label: item.agencyName ?? item.agency_name,
                                    count: item.count ?? item.activeCount ?? 0,
                                    detail: item.overdueCount !== undefined ? `${item.overdueCount} overdue` : undefined,
                                    tone: (item.overdueCount ?? 0) > 0 ? 'rose' : 'blue',
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">Agency load appears once referrals are assigned.</p>
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
                </aside>
            </div>
        </div>
    );
}
