import { Link, usePage } from '@inertiajs/react';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatDisplayDate, formatDisplayDateTime } from '@/lib/utils';
import {
    ActivityFeed,
    BarList,
    EmptyState,
    EntityList,
    EntityRow,
    MaterialSymbol,
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

const QUEUE_ROUTES = {
    openCases: '/cases',
    pendingReferrals: '/referrals',
    processingReferrals: '/referrals',
    forComplianceReferrals: '/referrals',
    overdueReferrals: '/overdue-referrals',
};

const ADMIN_TOOLS = [
    { label: 'Users', description: 'Roles, verification, access', route: 'admin.users.index', icon: 'group' },
    { label: 'Agencies', description: 'Partner profiles and activation', route: 'admin.agencies.index', icon: 'business' },
    { label: 'Services', description: 'Catalog and requirements', route: 'admin.services.index', icon: 'inventory_2' },
    { label: 'Audit logs', description: 'Review system changes', route: 'audit-logs.index', icon: 'history' },
    { label: 'Sessions', description: 'Monitor signed-in users', route: 'admin.system.active-sessions', icon: 'devices' },
];

export default function AdminDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'Administrator';

    const stats = dashboard.stats ?? dashboard;
    const queues = safeArray(dashboard.operationalQueues).map((item) => ({
        ...item,
        href: QUEUE_ROUTES[item.key] ?? '/referrals',
    }));
    const recentCases = safeArray(dashboard.recentCases).slice(0, 6);
    const recentLogs = safeArray(dashboard.recentLogs).slice(0, 6).map((log) => ({
        ...log,
        actionType: log.action ?? log.actionType,
        title: log.message ?? log.description,
        desc: log.detail ?? '',
        time: log.timestamp ? formatDisplayDateTime(log.timestamp) : '',
    }));
    const topAgencies = safeArray(dashboard.topAgencies).slice(0, 5);
    const usersByRole = safeArray(dashboard.usersByRole);
    const categories = safeArray(dashboard.casesByCategory).slice(0, 5);

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <PageHeader
                eyebrow="Administrator"
                title={`Welcome back, ${firstName}`}
                subtitle="System-wide queues, referral movement, and access at a glance."
            >
                <QuickActions
                    actions={[
                        { href: route('admin.users.index'), label: 'Users', icon: 'group', primary: true },
                        { href: route('admin.agencies.index'), label: 'Agencies', icon: 'business' },
                        { href: route('admin.services.index'), label: 'Services', icon: 'inventory_2' },
                        { href: route('reports.index'), label: 'Reports', icon: 'bar_chart' },
                    ]}
                />
            </PageHeader>

            <StatRow
                dataTour="dashboard-stats"
                stats={[
                    { title: 'Total cases', value: stats.totalCases, icon: 'folder' },
                    { title: 'Total referrals', value: stats.totalReferrals, icon: 'send', iconBg: 'bg-amber-50', iconColor: 'text-amber-700' },
                    { title: 'Active agencies', value: stats.activeAgencies, icon: 'account_balance', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-700', description: `${formatCount(stats.inactiveAgencies)} inactive` },
                    { title: 'Overdue referrals', value: stats.overdueReferrals, icon: 'warning', iconBg: 'bg-rose-50', iconColor: 'text-rose-600', description: 'Active for more than 5 days' },
                ]}
            />

            <TriageStrip items={queues} dataTour="dashboard-work-queue" />

            <div className="grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-8">
                    <SectionCard
                        title="Recent case movement"
                        dataTour="admin-recent-cases"
                        action={<ViewAllLink href={route('cases.index')}>View all cases</ViewAllLink>}
                        bodyClassName=""
                    >
                        <EntityList empty={<EmptyState message="No recent cases yet." href={route('cases.index')} actionLabel="Open cases" />}>
                            {recentCases.map((item) => (
                                <EntityRow
                                    key={item.id}
                                    href={route('cases.show', item.id)}
                                    pill={item.case_number}
                                    title={item.client_name}
                                    note={[item.category, item.case_owner, `Created ${formatDisplayDate(item.created_at)}`].filter(Boolean).join(' · ')}
                                    right={<StatusBadge status={item.status} />}
                                />
                            ))}
                        </EntityList>
                    </SectionCard>

                    <SectionCard
                        title="Recent administrative activity"
                        dataTour="admin-recent-activity"
                        action={<ViewAllLink href={route('audit-logs.index')}>View audit logs</ViewAllLink>}
                    >
                        <ActivityFeed items={recentLogs} limit={6} />
                    </SectionCard>
                </div>

                <div className="space-y-6 xl:col-span-4">
                    <SectionCard title="Manage the system" dataTour="dashboard-admin-system" bodyClassName="p-3">
                        <div className="space-y-1">
                            {ADMIN_TOOLS.map((tool) => (
                                <Link
                                    key={tool.route}
                                    href={route(tool.route)}
                                    className="flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                >
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <MaterialSymbol name={tool.icon} className="text-[18px]" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-bold text-slate-900">{tool.label}</span>
                                        <span className="block truncate text-xs text-slate-500">{tool.description}</span>
                                    </span>
                                    <MaterialSymbol name="chevron_right" className="text-[16px] text-slate-300" />
                                </Link>
                            ))}
                        </div>
                    </SectionCard>

                    <SectionCard title="Referral status">
                        {safeArray(dashboard.referralStatusDistribution).length > 0 ? (
                            <StatusDonut items={dashboard.referralStatusDistribution} />
                        ) : (
                            <p className="text-sm text-slate-500">Status distribution appears once referrals exist.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Agency load">
                        {topAgencies.length > 0 ? (
                            <BarList
                                items={topAgencies.map((agency) => ({
                                    key: agency.id,
                                    label: agency.name,
                                    count: agency.activeReferrals,
                                    detail: `${formatCount(agency.totalReferrals)} total referrals`,
                                    tone: 'blue',
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">Agency load appears once referrals are assigned.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Users by role">
                        {usersByRole.length > 0 ? (
                            <div className="space-y-2">
                                {usersByRole.map((role) => (
                                    <div key={role.role} className="flex items-center justify-between gap-3 text-sm">
                                        <span className="font-semibold text-slate-800">{role.label}</span>
                                        <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-bold text-primary">{formatCount(role.count)}</span>
                                    </div>
                                ))}
                                <p className="pt-1 text-xs text-slate-400">
                                    {formatCount(stats.verifiedUsers)} of {formatCount(stats.totalUsers)} users verified.
                                </p>
                            </div>
                        ) : (
                            <p className="text-sm text-slate-500">No role data yet.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Case mix">
                        {categories.length > 0 ? (
                            <BarList
                                items={categories.map((category) => ({
                                    key: category.name,
                                    label: category.name,
                                    count: category.count,
                                    hex: category.color,
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">Category mix appears once cases are filed.</p>
                        )}
                    </SectionCard>
                </div>
            </div>
        </div>
    );
}
