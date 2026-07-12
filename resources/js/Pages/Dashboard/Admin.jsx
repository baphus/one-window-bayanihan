import { Link, usePage } from '@inertiajs/react';
import DashboardBanner from '@/Components/DashboardBanner';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
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
    openCases: '/cases?status=OPEN',
    pendingReferrals: '/referrals?status=PENDING',
    processingReferrals: '/referrals?status=PROCESSING',
    forComplianceReferrals: '/referrals?status=FOR_COMPLIANCE',
    overdueReferrals: '/overdue-referrals',
};

const ADMIN_TOOLS = [
    { label: 'Users', description: 'Roles, verification, access', href: '/admin/users', icon: 'group' },
    { label: 'Agencies', description: 'Partner profiles and activation', href: '/admin/agencies', icon: 'business' },
    { label: 'Services', description: 'Catalog and requirements', href: '/admin/services', icon: 'inventory_2' },
    { label: 'Audit logs', description: 'Review system changes', href: '/audit-logs', icon: 'history' },
    { label: 'Sessions', description: 'Monitor signed-in users', href: '/admin/system/active-sessions', icon: 'devices' },
];

function buildQueueItems(dashboard, stats) {
    const supplied = safeArray(dashboard.operationalQueues);

    if (supplied.length > 0) {
        return supplied.map((item) => ({
            ...item,
            href: item.href ?? QUEUE_ROUTES[item.key] ?? '/referrals',
        }));
    }

    return [
        { key: 'openCases', label: 'Open cases', count: stats.openCases ?? stats.totalOpenCases ?? 0, note: 'Cases still being handled.', tone: 'blue', href: '/cases?status=OPEN' },
        { key: 'pendingReferrals', label: 'Pending referrals', count: stats.pendingReferrals ?? 0, note: 'Waiting for agency action.', tone: 'amber', href: '/referrals?status=PENDING' },
        { key: 'processingReferrals', label: 'Processing', count: stats.processingReferrals ?? 0, note: 'Currently being worked by agencies.', tone: 'cyan', href: '/referrals?status=PROCESSING' },
        { key: 'forComplianceReferrals', label: 'For compliance', count: stats.forComplianceReferrals ?? 0, note: 'Need missing requirements.', tone: 'orange', href: '/referrals?status=FOR_COMPLIANCE' },
        { key: 'overdueReferrals', label: 'Overdue', count: stats.overdueReferrals ?? 0, note: 'Past the expected response window.', tone: 'rose', href: '/overdue-referrals' },
    ];
}

export default function AdminDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'Administrator';
    const stats = dashboard.stats ?? dashboard;

    const queueItems = buildQueueItems(dashboard, stats);
    const recentCases = safeArray(dashboard.recentCases).slice(0, 6);
    const recentLogs = safeArray(dashboard.recentLogs).slice(0, 6).map((log) => ({
        ...log,
        actionType: log.action ?? log.actionType,
        title: log.message ?? log.description ?? 'System activity',
        desc: log.detail ?? '',
        time: log.timestamp ? formatDisplayDateTime(log.timestamp) : log.time,
    }));
    const topAgencies = safeArray(dashboard.topAgencies).slice(0, 6);
    const usersByRole = safeArray(dashboard.usersByRole);
    const categories = safeArray(dashboard.casesByCategory).slice(0, 6);

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <DashboardBanner />
            <GettingStartedChecklist />

            <PageHeader
                eyebrow="Admin overview"
                title={`Welcome back, ${firstName}`}
                subtitle="Monitor queues, track referrals, and manage the system from one place."
            >
                <QuickActions
                    actions={[
                        { href: '/cases', label: 'Cases', icon: 'folder', count: stats.totalCases, primary: true },
                        { href: '/referrals', label: 'Referrals', icon: 'send', count: stats.totalReferrals },
                        { href: '/overdue-referrals', label: 'Overdue', icon: 'warning', count: stats.overdueReferrals },
                    ]}
                />
            </PageHeader>

            <StatRow
                dataTour="dashboard-stats"
                stats={[
                    { title: 'Total cases', value: stats.totalCases ?? 0, icon: 'folder', description: 'All non-draft case files in the system.' },
                    { title: 'Total referrals', value: stats.totalReferrals ?? 0, icon: 'send', iconBg: 'bg-amber-50', iconColor: 'text-amber-700', description: 'Referrals sent to partner agencies.' },
                    { title: 'Active agencies', value: stats.activeAgencies ?? stats.totalAgencies ?? 0, icon: 'account_balance', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-700', description: `${formatCount(stats.inactiveAgencies ?? 0)} inactive agency records.` },
                    { title: 'Overdue referrals', value: stats.overdueReferrals ?? 0, icon: 'warning', iconBg: 'bg-rose-50', iconColor: 'text-rose-700', description: 'Active referrals older than five days.' },
                ]}
            />

            <TriageStrip items={queueItems} dataTour="dashboard-work-queues" />

            <div className="grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-8">
                    <SectionCard
                        title="Recent case activity"
                        dataTour="dashboard-recent-cases"
                        action={<ViewAllLink href="/cases">View all cases</ViewAllLink>}
                        bodyClassName=""
                    >
                        <EntityList empty={<EmptyState message="No recent cases yet." href="/cases" actionLabel="Open cases" />}>
                            {recentCases.map((item) => (
                                <EntityRow
                                    key={item.id}
                                    href={`/cases/${item.id}`}
                                    pill={item.case_number ?? item.caseNo}
                                    title={item.client_name ?? item.clientName ?? 'Unnamed client'}
                                    note={[item.category, item.case_owner ?? item.caseOwner, item.last_activity ?? (item.updated_at ? `Updated ${formatDisplayDate(item.updated_at)}` : null)].filter(Boolean).join(' · ')}
                                    right={<StatusBadge status={item.status} />}
                                />
                            ))}
                        </EntityList>
                    </SectionCard>

                    <SectionCard
                        title="Recent administrative changes"
                        dataTour="dashboard-recent-activity"
                        action={<ViewAllLink href="/audit-logs">View audit logs</ViewAllLink>}
                    >
                        <ActivityFeed items={recentLogs} />
                    </SectionCard>
                </div>

                <aside className="space-y-6 xl:col-span-4">
                    <SectionCard title="Admin tools" dataTour="dashboard-admin-tools" bodyClassName="p-3">
                        <div className="grid gap-1">
                            {ADMIN_TOOLS.map((tool) => (
                                <Link
                                    key={tool.href}
                                    href={tool.href}
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
                                    key: agency.id ?? agency.name,
                                    label: agency.name ?? agency.agencyName,
                                    count: agency.activeReferrals ?? agency.count ?? 0,
                                    detail: `${formatCount(agency.totalReferrals ?? 0)} total referrals`,
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
                                    <div key={role.role ?? role.label} className="flex items-center justify-between gap-3 text-sm">
                                        <span className="font-semibold text-slate-800">{role.label ?? role.role}</span>
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
                </aside>
            </div>
        </div>
    );
}
