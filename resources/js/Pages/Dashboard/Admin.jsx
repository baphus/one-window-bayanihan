import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import { formatDisplayDateTime } from '@/lib/utils';
import safeRoute from '@/utils/safeRoute';
import {
    ActivityFeed,
    BarList,
    CollapsibleSectionCard,
    EmptyState,
    FilterChip,
    MaterialSymbol,
    PageHeader,
    QuickActions,
    SectionCard,
    StatRow,
    ViewAllLink,
    formatCount,
    safeArray,
} from '@/Components/Dashboard/primitives';

const ADMIN_TOOLS = [
    { label: 'Users', description: 'Roles, invites, verification, access', href: '/admin/users', route: 'admin.users.index', icon: 'group' },
    { label: 'Agencies', description: 'Partner profiles and activation', href: '/admin/agencies', route: 'admin.agencies.index', icon: 'business' },
    { label: 'Services', description: 'Catalog and requirements', href: '/admin/services', route: 'admin.services.index', icon: 'inventory_2' },
    { label: 'Categories', description: 'Case category taxonomy', href: '/admin/case-categories', route: 'admin.case-categories.index', icon: 'category' },
    { label: 'Statuses', description: 'Case status taxonomy', href: '/admin/case-statuses', route: 'admin.case-statuses.index', icon: 'flag' },
    { label: 'Issues', description: 'Case issue taxonomy', href: '/admin/case-issues', route: 'admin.case-issues.index', icon: 'report_problem' },
    { label: 'System settings', description: 'Thresholds and chatbot index', href: '/admin/system-settings', route: 'admin.system-settings.index', icon: 'settings' },
    { label: 'Active sessions', description: 'Signed-in users, terminate access', href: '/admin/system/active-sessions', route: 'admin.system.active-sessions', icon: 'devices' },
    { label: 'Security', description: 'Password and lockout policy', href: '/admin/system/security', route: 'admin.system.security', icon: 'shield' },
    { label: 'System logs', description: 'Application and error logs', href: '/admin/system/logs', route: 'admin.system.logs', icon: 'terminal' },
    { label: 'Maintenance', description: 'Maintenance mode control', href: '/admin/system/maintenance', route: 'admin.system.maintenance', icon: 'construction' },
    { label: 'Email logs', description: 'Deliverability and resend', href: '/admin/system/email-logs', route: 'admin.system.email-logs.index', icon: 'mail' },
    { label: 'Data export', description: 'Export system data', href: '/admin/data-export', route: 'admin.data-export.index', icon: 'download' },
    { label: 'Audit logs', description: 'Full unscoped change trail', href: '/audit-logs', route: 'audit-logs.index', icon: 'history' },
];

const PLATFORM_HEALTH_LINKS = [
    { label: 'System logs', description: 'Check for errors before they spread.', href: '/admin/system/logs', route: 'admin.system.logs', icon: 'terminal' },
    { label: 'Email logs', description: 'Deliverability, failures, and resend.', href: '/admin/system/email-logs', route: 'admin.system.email-logs.index', icon: 'mail' },
    { label: 'Maintenance mode', description: 'Take the system offline for work.', href: '/admin/system/maintenance', route: 'admin.system.maintenance', icon: 'construction' },
    { label: 'Data export', description: 'Pull records for reporting.', href: '/admin/data-export', route: 'admin.data-export.index', icon: 'download' },
    { label: 'System settings', description: 'Overdue threshold and chatbot index.', href: '/admin/system-settings', route: 'admin.system-settings.index', icon: 'settings' },
];

const MODULE_GROUPS = {
    Users: ['user', 'auth', 'session', 'mfa', 'security'],
    Agencies: ['agency', 'service', 'service_requirement'],
    Cases: ['case', 'case_category', 'case_issue', 'case_status', 'case_document'],
    Referrals: ['referral', 'referral_comment', 'referral_attachment', 'referral_client_request', 'referral_client_request_item', 'referral_client_message', 'referral_client_access_link', 'referral_service_requirement'],
};

function categorizeModule(mod) {
    const lower = String(mod ?? '').toLowerCase();
    for (const [group, modules] of Object.entries(MODULE_GROUPS)) {
        if (modules.includes(lower)) return group;
    }
    return 'Other';
}

export default function AdminDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'Administrator';
    const stats = dashboard.stats ?? dashboard;

    const recentLogs = safeArray(dashboard.recentLogs).slice(0, 8).map((log) => ({
        ...log,
        actionType: log.action ?? log.actionType,
        title: log.message ?? 'System activity',
        desc: log.detail ?? '',
        time: log.timestamp ? formatDisplayDateTime(log.timestamp) : log.time,
    }));
    const usersByRole = safeArray(dashboard.usersByRole);

    const [auditFilter, setAuditFilter] = useState('all');
    const auditModuleCounts = recentLogs.reduce((acc, log) => {
        const group = categorizeModule(log.module);
        acc[group] = (acc[group] ?? 0) + 1;
        return acc;
    }, {});
    const auditModuleGroups = Object.keys(auditModuleCounts).sort();
    const filteredLogs = auditFilter === 'all'
        ? recentLogs
        : recentLogs.filter((log) => categorizeModule(log.module) === auditFilter);

    const activeReferrals = (stats.pendingReferrals ?? 0) + (stats.processingReferrals ?? 0) + (stats.forComplianceReferrals ?? 0);

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <GettingStartedChecklist />

            <PageHeader
                eyebrow="System administration"
                title={`Welcome back, ${firstName}`}
                subtitle="Manage people, agencies, settings, and review what changed across the system."
            >
                <QuickActions
                    actions={[
                        { href: '/admin/users', route: 'admin.users.index', label: 'Users', icon: 'group', count: stats.totalUsers, primary: true },
                        { href: '/admin/agencies', route: 'admin.agencies.index', label: 'Agencies', icon: 'business', count: stats.totalAgencies },
                        { href: '/audit-logs', route: 'audit-logs.index', label: 'Audit logs', icon: 'history' },
                    ]}
                />
            </PageHeader>

            {/* ── Admin KPIs: identity, directory, and system-wide load ── */}
            <StatRow
                dataTour="dashboard-stats"
                stats={[
                    {
                        title: 'People',
                        value: stats.totalUsers ?? 0,
                        icon: 'group',
                        description: `${formatCount(stats.activeUsers ?? 0)} active · ${formatCount(stats.inactiveUsers ?? 0)} inactive.`,
                    },
                    {
                        title: 'Agencies',
                        value: stats.activeAgencies ?? stats.totalAgencies ?? 0,
                        icon: 'business',
                        iconBg: 'bg-emerald-50',
                        iconColor: 'text-emerald-700',
                        description: `${formatCount(stats.inactiveAgencies ?? 0)} inactive of ${formatCount(stats.totalAgencies ?? 0)} total.`,
                    },
                    {
                        title: 'Open cases',
                        value: stats.openCases ?? 0,
                        icon: 'folder',
                        iconBg: 'bg-amber-50',
                        iconColor: 'text-amber-700',
                        description: 'System-wide, across all case managers.',
                    },
                    {
                        title: 'Overdue referrals',
                        value: stats.overdueReferrals ?? 0,
                        icon: 'warning',
                        iconBg: 'bg-rose-50',
                        iconColor: 'text-rose-700',
                        description: 'Past the expected response window.',
                        trend: (stats.overdueReferrals ?? 0) > 0 ? 'Needs action' : undefined,
                    },
                ]}
            />

            <div className="grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-8">
                    {/* ── Security & access ── */}
                    <SectionCard
                        title="Security & access"
                        dataTour="dashboard-security-access"
                        action={<ViewAllLink href={safeRoute('admin.users.index', undefined, '/admin/users')}>Manage users</ViewAllLink>}
                    >
                        {usersByRole.length > 0 ? (
                            <BarList
                                items={usersByRole.map((role) => ({
                                    key: role.role ?? role.label,
                                    label: role.label ?? role.role,
                                    count: role.count ?? 0,
                                    tone: 'emerald',
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">No role data yet.</p>
                        )}
                        <p className="mt-3 text-xs text-slate-500">
                            {formatCount(stats.verifiedUsers)} of {formatCount(stats.totalUsers)} verified.
                            {' '}MFA status and signed-in sessions live on the pages below.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Link
                                href={safeRoute('admin.system.active-sessions', undefined, '/admin/system/active-sessions')}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-50"
                            >
                                <MaterialSymbol name="devices" className="text-[16px]" />
                                Active sessions
                            </Link>
                            <Link
                                href={safeRoute('admin.system.security', undefined, '/admin/system/security')}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-50"
                            >
                                <MaterialSymbol name="shield" className="text-[16px]" />
                                Security policy
                            </Link>
                        </div>
                    </SectionCard>

                    {/* ── Recent administrative changes ── */}
                    <SectionCard
                        title="Recent administrative changes"
                        dataTour="dashboard-recent-activity"
                        action={<ViewAllLink href={safeRoute('audit-logs.index', undefined, '/audit-logs')}>View audit logs</ViewAllLink>}
                        bodyClassName="p-0"
                    >
                        <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 pb-3 pt-4">
                            <FilterChip
                                label="All"
                                count={recentLogs.length}
                                active={auditFilter === 'all'}
                                onClick={() => setAuditFilter('all')}
                            />
                            {auditModuleGroups.map((group) => (
                                <FilterChip
                                    key={group}
                                    label={group}
                                    count={auditModuleCounts[group] ?? 0}
                                    active={auditFilter === group}
                                    onClick={() => setAuditFilter(group)}
                                />
                            ))}
                        </div>
                        <ActivityFeed
                            items={filteredLogs}
                            limit={8}
                            empty={<EmptyState message="No matching activity." />}
                        />
                    </SectionCard>

                    {/* ── Demoted: system-wide case snapshot (glance only, not casework) ── */}
                    <CollapsibleSectionCard
                        title="System-wide case snapshot"
                        dataTour="dashboard-system-snapshot"
                        defaultOpen={false}
                    >
                        <dl className="divide-y divide-slate-100">
                            <div className="flex items-center justify-between gap-3 py-2.5">
                                <dt className="text-sm font-semibold text-slate-700">Open cases</dt>
                                <dd>
                                    <Link href={safeRoute('cases.index', { status: 'OPEN' }, '/cases?status=OPEN')} className="text-sm font-bold text-primary hover:text-primary/80">
                                        {formatCount(stats.openCases)}
                                    </Link>
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3 py-2.5">
                                <dt className="text-sm font-semibold text-slate-700">Active referrals</dt>
                                <dd>
                                    <Link href={safeRoute('referrals.index', undefined, '/referrals')} className="text-sm font-bold text-primary hover:text-primary/80">
                                        {formatCount(activeReferrals)}
                                    </Link>
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3 py-2.5">
                                <dt className="text-sm font-semibold text-slate-700">Overdue referrals</dt>
                                <dd>
                                    <Link href={safeRoute('overdue-referrals.index', undefined, '/overdue-referrals')} className="text-sm font-bold text-rose-600 hover:text-rose-500">
                                        {formatCount(stats.overdueReferrals)}
                                    </Link>
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3 py-2.5">
                                <dt className="text-sm font-semibold text-slate-700">Closed cases</dt>
                                <dd className="text-sm font-bold text-slate-900">{formatCount(stats.closedCases)}</dd>
                            </div>
                        </dl>
                        <p className="mt-3 text-[11px] text-slate-400">Casework belongs to case managers and agencies — this is oversight only.</p>
                    </CollapsibleSectionCard>
                </div>

                <aside className="space-y-6 xl:col-span-4">
                    {/* ── Platform health ── */}
                    <SectionCard title="Platform health" dataTour="dashboard-platform-health" bodyClassName="p-3">
                        <div className="grid gap-1">
                            {PLATFORM_HEALTH_LINKS.map((item) => (
                                <Link
                                    key={item.route ?? item.href}
                                    href={item.route ? safeRoute(item.route, undefined, item.href) : item.href}
                                    className="flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                >
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <MaterialSymbol name={item.icon} className="text-[18px]" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-bold text-slate-900">{item.label}</span>
                                        <span className="block truncate text-xs text-slate-500">{item.description}</span>
                                    </span>
                                    <MaterialSymbol name="chevron_right" className="text-[16px] text-slate-300" />
                                </Link>
                            ))}
                        </div>
                    </SectionCard>

                    {/* ── Admin tools ── */}
                    <SectionCard title="Admin tools" dataTour="dashboard-admin-tools" bodyClassName="p-3">
                        <div className="grid gap-1">
                            {ADMIN_TOOLS.map((tool) => (
                                <Link
                                    key={tool.route ?? tool.href}
                                    href={tool.route ? safeRoute(tool.route, undefined, tool.href) : tool.href}
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
                </aside>
            </div>
        </div>
    );
}
