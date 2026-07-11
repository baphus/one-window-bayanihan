import { Link, usePage } from '@inertiajs/react';
import DashboardBanner from '@/Components/DashboardBanner';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatDisplayDate, formatDisplayDateTime } from '@/lib/utils';
import { actionConfig } from '@/Components/Dashboard/activityConfig';
import { Eye } from 'lucide-react';

const numberFormatter = new Intl.NumberFormat('en-US');

function MaterialSymbol({ name, className = '' }) {
    return (
        <span aria-hidden="true" className={`material-symbols-outlined leading-none ${className}`}>
            {name}
        </span>
    );
}

function formatCount(value) {
    const parsed = Number(value ?? 0);
    return numberFormatter.format(Number.isFinite(parsed) ? parsed : 0);
}

function SectionShell({ eyebrow, title, action, children, className = '', dataTour }) {
    return (
        <section data-tour={dataTour} className={`rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>
            <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <p className="text-[11px] font-bold uppercase tracking-widest text-slate-500">{eyebrow}</p>
                    <h2 className="mt-1 font-headline text-lg font-extrabold text-primary">{title}</h2>
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

function SoftMetric({ label, value, icon, helper, tone = 'primary' }) {
    const tones = {
        primary: 'bg-primary/10 text-primary ring-primary/10',
        amber: 'bg-amber-50 text-amber-700 ring-amber-100',
        cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-100',
        orange: 'bg-orange-50 text-orange-700 ring-orange-100',
        rose: 'bg-rose-50 text-rose-700 ring-rose-100',
        emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        slate: 'bg-slate-100 text-slate-600 ring-slate-200',
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{label}</p>
                    <p className="mt-2 text-2xl font-black text-slate-900">{formatCount(value)}</p>
                </div>
                <span className={`flex h-11 w-11 items-center justify-center rounded-full ring-1 ${tones[tone] ?? tones.primary}`}>
                    <MaterialSymbol name={icon} className="text-[22px]" />
                </span>
            </div>
            {helper ? <p className="mt-3 text-[10px] text-slate-400">{helper}</p> : null}
        </div>
    );
}

function TextLink({ href, children }) {
    return (
        <Link
            href={href}
            className="inline-flex items-center gap-1.5 text-sm font-bold text-primary transition-colors hover:text-primary/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
        >
            {children}
            <MaterialSymbol name="arrow_forward" className="text-[16px]" />
        </Link>
    );
}

export default function AdminDashboard({ dashboard }) {
    const { auth } = usePage().props;
    const source = dashboard?.stats ? dashboard : dashboard?.dashboard ?? dashboard ?? {};
    const stats = source.stats ?? source;

    const recentCases = Array.isArray(source.recentCases) ? source.recentCases : [];
    const recentLogs = Array.isArray(source.recentLogs) ? source.recentLogs : [];
    const operationalQueues = Array.isArray(source.operationalQueues) ? source.operationalQueues : [];
    const topAgencies = Array.isArray(source.topAgencies) ? source.topAgencies : [];
    const usersByRole = Array.isArray(source.usersByRole) ? source.usersByRole : [];
    const casesByCategory = Array.isArray(source.casesByCategory) ? source.casesByCategory : [];

    const userName = auth?.user?.name?.split(' ')[0] || 'Administrator';
    const today = new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    const queueRoutes = {
        openCases: 'cases.index',
        pendingReferrals: 'referrals.index',
        processingReferrals: 'referrals.index',
        forComplianceReferrals: 'referrals.index',
        overdueReferrals: 'overdue-referrals.index',
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
            <DashboardBanner />
            <GettingStartedChecklist />

            <header data-tour="dashboard-header" className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between mb-8">
                <div className="max-w-3xl">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                        <MaterialSymbol name="admin_panel_settings" className="text-sm" />
                        Admin overview
                    </span>
                    <h1 className="mt-3 font-headline text-3xl font-extrabold tracking-tight text-slate-900 md:text-4xl">
                        <span className="text-primary">Bayanihan One Window</span> Dashboard
                    </h1>
                    <p className="mt-1 text-sm text-slate-400">
                        Welcome back, <span className="font-semibold text-slate-600">{userName}</span>. Monitor queues, track referrals, and manage the system from one place.
                    </p>
                </div>

                <div className="shrink-0">
                    <time className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{today}</time>
                </div>
            </header>

            <section data-tour="dashboard-stats" className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SoftMetric label="Total cases" value={stats.totalCases ?? 0} icon="folder" helper="All non-draft case files in the system." />
                <SoftMetric label="Total referrals" value={stats.totalReferrals ?? 0} icon="send" helper="Referrals sent to partner agencies." tone="amber" />
                <SoftMetric label="Active agencies" value={stats.activeAgencies ?? stats.totalAgencies ?? 0} icon="account_balance" helper={`${formatCount(stats.inactiveAgencies ?? 0)} inactive agency records.`} tone="emerald" />
                <SoftMetric label="Overdue referrals" value={stats.overdueReferrals ?? 0} icon="warning" helper="Active referrals older than five days." tone="rose" />
            </section>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(340px,0.8fr)]">
                <div className="space-y-6">
                    <SectionShell
                        dataTour="dashboard-work-queues"
                        eyebrow="Work queues"
                        title="Where admins should look first"
                        action={<TextLink href={route('overdue-referrals.index')}>Open overdue referrals</TextLink>}
                    >
                        <div className="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-5 sm:p-6">
                            {queueCards.map((item) => (
                                <Link
                                    key={item.key}
                                    href={route(item.route)}
                                    className="group rounded-xl border border-slate-200 bg-slate-50 p-4 transition-all hover:-translate-y-0.5 hover:border-primary/20 hover:bg-white hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-reduce:transform-none"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-white text-primary ring-1 ring-slate-200">
                                            <MaterialSymbol name={item.icon} className="text-[20px]" />
                                        </span>
                                        <span className="text-2xl font-extrabold text-slate-900">{formatCount(item.count)}</span>
                                    </div>
                                    <h3 className="mt-4 text-sm font-bold text-slate-900">{item.label}</h3>
                                    <p className="mt-1 text-xs leading-5 text-slate-500">{item.note}</p>
                                </Link>
                            ))}
                        </div>
                    </SectionShell>

                    <SectionShell
                        dataTour="dashboard-recent-cases"
                        eyebrow="Cases"
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

                    <SectionShell
                        dataTour="dashboard-recent-activity"
                        eyebrow="Activity"
                        title="Recent administrative changes"
                        action={<TextLink href={route('audit-logs.index')}>View audit logs</TextLink>}
                    >
                        <div className="divide-y divide-slate-100">
                            {recentActivityRows.length === 0 ? (
                                <p className="px-5 py-6 text-sm text-slate-500 sm:px-6">No recent activity.</p>
                            ) : (
                                recentActivityRows.map((log) => {
                                    const cfg = actionConfig[log.action || log.actionType] || { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', label: '' };

                                    return (
                                        <div key={log.id} className="flex items-start gap-3 px-5 py-4 sm:px-6">
                                            <span className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-1 ring-slate-200 ${cfg.bg}`}>
                                                <cfg.icon className={`h-4 w-4 ${cfg.text}`} />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium leading-6 text-slate-900">
                                                    {log.actor ? <span className="font-normal text-slate-500">{log.actor} </span> : null}
                                                    {log.message ?? log.description}
                                                </p>
                                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                                    <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider ${cfg.text} ${cfg.bg}`}>
                                                        {cfg.label || log.action || log.actionType || 'Activity'}
                                                    </span>
                                                    <span className="text-xs text-slate-500">{formatDisplayDateTime(log.timestamp)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </SectionShell>
                </div>

                <aside className="space-y-6">
                    <SectionShell dataTour="dashboard-admin-tools" eyebrow="Admin tools" title="Manage the system">
                        <div className="grid gap-2 p-5 sm:p-6">
                            {adminActions.map((item) => (
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
