import { Link, usePage } from '@inertiajs/react';
import DashboardBanner from '@/Components/DashboardBanner';
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

function SectionShell({ eyebrow, title, action, children, className = '' }) {
    return (
        <section className={`rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}>
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

    const queueCards = (operationalQueues.length > 0 ? operationalQueues : [
        { key: 'openCases', label: 'Open cases', count: stats.openCases ?? 0, note: 'Active case files that still need movement.', icon: 'folder_open' },
        { key: 'pendingReferrals', label: 'Pending referrals', count: stats.pendingReferrals ?? 0, note: 'Waiting for first agency action.', icon: 'schedule' },
        { key: 'processingReferrals', label: 'Processing', count: stats.processingReferrals ?? 0, note: 'Accepted and underway.', icon: 'sync' },
        { key: 'forComplianceReferrals', label: 'For compliance', count: stats.forComplianceReferrals ?? 0, note: 'Missing documents or follow-up.', icon: 'fact_check' },
        { key: 'overdueReferrals', label: 'Overdue referrals', count: stats.overdueReferrals ?? 0, note: 'Active referrals older than five days.', icon: 'warning' },
    ]).map((item) => ({ ...item, route: queueRoutes[item.key] || 'referrals.index' }));

    const adminActions = [
        { label: 'Users', description: 'Roles, verification, access', route: 'admin.users.index', icon: 'group' },
        { label: 'Agencies', description: 'Partner profiles and activation', route: 'admin.agencies.index', icon: 'business' },
        { label: 'Services', description: 'Catalog and requirements', route: 'admin.services.index', icon: 'inventory_2' },
        { label: 'Reports', description: 'Export trends and scorecards', route: 'reports.index', icon: 'bar_chart' },
        { label: 'Audit logs', description: 'Review system changes', route: 'audit-logs.index', icon: 'history' },
        { label: 'Sessions', description: 'Monitor signed-in users', route: 'admin.system.active-sessions', icon: 'devices' },
    ];

    const recentCaseRows = recentCases.slice(0, 6);
    const recentActivityRows = recentLogs.slice(0, 6);
    const topAgencyRows = topAgencies.slice(0, 5);
    const roleRows = usersByRole.slice(0, 4);
    const categoryRows = casesByCategory.slice(0, 5);

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <DashboardBanner />

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

            <section className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SoftMetric label="Total cases" value={stats.totalCases ?? 0} icon="folder" helper="All non-draft case files in the system." />
                <SoftMetric label="Total referrals" value={stats.totalReferrals ?? 0} icon="send" helper="Referrals sent to partner agencies." tone="amber" />
                <SoftMetric label="Active agencies" value={stats.activeAgencies ?? stats.totalAgencies ?? 0} icon="account_balance" helper={`${formatCount(stats.inactiveAgencies ?? 0)} inactive agency records.`} tone="emerald" />
                <SoftMetric label="Overdue referrals" value={stats.overdueReferrals ?? 0} icon="warning" helper="Active referrals older than five days." tone="rose" />
            </section>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(340px,0.8fr)]">
                <div className="space-y-6">
                    <SectionShell
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
                        eyebrow="Cases"
                        title="Recent case movement"
                        action={<TextLink href={route('cases.index')}>View all cases</TextLink>}
                        className="overflow-hidden"
                    >
                        <div className="divide-y divide-slate-100">
                            {recentCaseRows.length === 0 ? (
                                <p className="px-5 py-6 text-sm text-slate-500 sm:px-6">No recent cases yet.</p>
                            ) : (
                                recentCaseRows.map((item) => (
                                    <Link
                                        key={item.id}
                                        href={route('cases.show', item.id)}
                                        className="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                                                    {item.case_number}
                                                </span>
                                                {item.client_type ? (
                                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                                        {item.client_type === 'Overseas Filipino Worker' ? 'OFW' : 'NOK'}
                                                    </span>
                                                ) : null}
                                            </div>
                                            <p className="mt-2 truncate text-sm font-bold text-slate-900">{item.client_name}</p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {item.category ? `${item.category} · ` : ''}{item.case_owner ? `${item.case_owner} · ` : ''}Created {formatDisplayDate(item.created_at)}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-3">
                                            <StatusBadge status={item.status} />
                                            <span className="hidden text-xs text-slate-500 sm:inline">Updated {formatDisplayDate(item.updated_at)}</span>
                                            <MaterialSymbol name="chevron_right" className="text-[18px] text-slate-400" />
                                        </div>
                                    </Link>
                                ))
                            )}
                        </div>
                    </SectionShell>

                    <SectionShell
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
                    <SectionShell eyebrow="Admin tools" title="Manage the system">
                        <div className="grid gap-2 p-5 sm:p-6">
                            {adminActions.map((item) => (
                                <Link
                                    key={item.route}
                                    href={route(item.route)}
                                    className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 transition-colors hover:border-primary/20 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                >
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <MaterialSymbol name={item.icon} className="text-[20px]" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-bold text-slate-900">{item.label}</span>
                                        <span className="block text-xs leading-5 text-slate-500">{item.description}</span>
                                    </span>
                                    <MaterialSymbol name="chevron_right" className="text-[18px] text-slate-400" />
                                </Link>
                            ))}
                        </div>
                    </SectionShell>

                    <SectionShell eyebrow="Agencies" title="Partner load">
                        <div className="grid grid-cols-2 gap-3 px-5 pt-5 sm:px-6">
                            <SoftMetric label="Active" value={stats.activeAgencies ?? 0} icon="check_circle" tone="emerald" />
                            <SoftMetric label="Inactive" value={stats.inactiveAgencies ?? 0} icon="pause_circle" tone="slate" />
                        </div>
                        <div className="space-y-3 p-5 sm:p-6">
                            {topAgencyRows.length === 0 ? (
                                <p className="text-sm text-slate-500">No agency load data yet.</p>
                            ) : (
                                topAgencyRows.map((agency) => {
                                    const maxLoad = Math.max(...topAgencyRows.map((row) => Number(row.activeReferrals ?? row.totalReferrals ?? 0)), 1);
                                    const width = Math.max(((agency.activeReferrals ?? agency.totalReferrals ?? 0) / maxLoad) * 100, 8);

                                    return (
                                        <div key={agency.id}>
                                            <div className="flex items-center justify-between gap-3 text-sm">
                                                <span className="truncate font-semibold text-slate-900">{agency.name}</span>
                                                <span className="shrink-0 text-xs text-slate-500">{formatCount(agency.activeReferrals ?? 0)} active</span>
                                            </div>
                                            <div className="mt-2 h-2 rounded-full bg-slate-100">
                                                <div className="h-2 rounded-full bg-primary" style={{ width: `${width}%` }} />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </SectionShell>

                    <SectionShell eyebrow="Users" title="Access overview">
                        <div className="grid grid-cols-2 gap-3 px-5 pt-5 sm:px-6">
                            <SoftMetric label="Users" value={stats.totalUsers ?? 0} icon="groups" />
                            <SoftMetric label="Verified" value={stats.verifiedUsers ?? 0} icon="verified_user" tone="cyan" />
                        </div>
                        <div className="space-y-2 p-5 sm:p-6">
                            {roleRows.length === 0 ? (
                                <p className="text-sm text-slate-500">No role data yet.</p>
                            ) : (
                                roleRows.map((role) => (
                                    <div key={role.role} className="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                                        <div>
                                            <p className="text-sm font-bold text-slate-900">{role.label}</p>
                                            <p className="text-xs text-slate-500">{role.role}</p>
                                        </div>
                                        <span className="rounded-full bg-primary/10 px-3 py-1 text-sm font-bold text-primary">{formatCount(role.count)}</span>
                                    </div>
                                ))
                            )}
                        </div>
                    </SectionShell>

                    <SectionShell eyebrow="Categories" title="Case mix">
                        <div className="space-y-3 p-5 sm:p-6">
                            {categoryRows.length === 0 ? (
                                <p className="text-sm text-slate-500">No category data yet.</p>
                            ) : (
                                categoryRows.map((category) => {
                                    const count = Number(category.count ?? 0);
                                    const maxCount = Math.max(...categoryRows.map((item) => Number(item.count ?? 0)), 1);
                                    const width = Math.max((count / maxCount) * 100, 10);

                                    return (
                                        <div key={category.name}>
                                            <div className="flex items-center justify-between gap-3 text-sm">
                                                <span className="truncate font-semibold text-slate-900">{category.name}</span>
                                                <span className="shrink-0 text-xs text-slate-500">{formatCount(category.count)}</span>
                                            </div>
                                            <div className="mt-2 h-2 rounded-full bg-slate-100">
                                                <div className="h-2 rounded-full" style={{ width: `${width}%`, backgroundColor: category.color || '#005288' }} />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </SectionShell>
                </aside>
            </div>
        </div>
    );
}
