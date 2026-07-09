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

function toneClasses(tone) {
    switch (tone) {
        case 'amber':
            return 'bg-amber-50 text-amber-700 ring-amber-100';
        case 'cyan':
            return 'bg-cyan-50 text-cyan-700 ring-cyan-100';
        case 'orange':
            return 'bg-orange-50 text-orange-700 ring-orange-100';
        case 'rose':
            return 'bg-rose-50 text-rose-700 ring-rose-100';
        case 'emerald':
            return 'bg-emerald-50 text-emerald-700 ring-emerald-100';
        case 'slate':
            return 'bg-slate-100 text-slate-700 ring-slate-200';
        case 'blue':
        default:
            return 'bg-blue-50 text-blue-700 ring-blue-100';
    }
}

function accentClasses(tone) {
    switch (tone) {
        case 'amber':
            return 'bg-amber-400';
        case 'cyan':
            return 'bg-cyan-400';
        case 'orange':
            return 'bg-orange-400';
        case 'rose':
            return 'bg-rose-400';
        case 'emerald':
            return 'bg-emerald-400';
        case 'slate':
            return 'bg-slate-400';
        case 'blue':
        default:
            return 'bg-blue-400';
    }
}

function formatCount(value) {
    const parsed = Number(value ?? 0);
    return numberFormatter.format(Number.isFinite(parsed) ? parsed : 0);
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
        {
            key: 'openCases',
            label: 'Open cases',
            count: stats.openCases ?? 0,
            note: 'Active case files on deck.',
            tone: 'blue',
            icon: 'folder_open',
        },
        {
            key: 'pendingReferrals',
            label: 'Pending referrals',
            count: stats.pendingReferrals ?? 0,
            note: 'Waiting for agency action.',
            tone: 'amber',
            icon: 'schedule',
        },
        {
            key: 'processingReferrals',
            label: 'Processing',
            count: stats.processingReferrals ?? 0,
            note: 'Already in motion.',
            tone: 'cyan',
            icon: 'sync',
        },
        {
            key: 'forComplianceReferrals',
            label: 'For compliance',
            count: stats.forComplianceReferrals ?? 0,
            note: 'Needs missing documents.',
            tone: 'orange',
            icon: 'fact_check',
        },
        {
            key: 'overdueReferrals',
            label: 'Overdue referrals',
            count: stats.overdueReferrals ?? 0,
            note: 'Older than five days.',
            tone: 'rose',
            icon: 'warning',
        },
    ]).map((item) => ({
        ...item,
        route: queueRoutes[item.key] || 'referrals.index',
    }));

    const adminActions = [
        { label: 'Manage users', description: 'Roles, verification, access', route: 'admin.users.index', icon: 'group' },
        { label: 'Agency registry', description: 'Profiles, activity, activation', route: 'admin.agencies.index', icon: 'business' },
        { label: 'Service catalog', description: 'Offerings and service setup', route: 'admin.services.index', icon: 'inventory_2' },
        { label: 'Audit logs', description: 'Trace every change', route: 'audit-logs.index', icon: 'history' },
        { label: 'System logs', description: 'Server and app traces', route: 'admin.system.logs', icon: 'terminal' },
        { label: 'Active sessions', description: 'Review live logins', route: 'admin.system.active-sessions', icon: 'devices' },
        { label: 'Maintenance mode', description: 'Control downtime windows', route: 'admin.system.maintenance', icon: 'handyman' },
        { label: 'Reports', description: 'Exports and dashboards', route: 'reports.index', icon: 'bar_chart' },
    ];

    const heroStats = [
        { label: 'Total cases', value: stats.totalCases ?? 0, tone: 'blue' },
        { label: 'Total referrals', value: stats.totalReferrals ?? 0, tone: 'amber' },
        { label: 'Active agencies', value: stats.activeAgencies ?? 0, tone: 'emerald' },
        { label: 'Overdue referrals', value: stats.overdueReferrals ?? 0, tone: 'rose' },
    ];

    const recentCaseRows = recentCases.slice(0, 6);
    const recentActivityRows = recentLogs.slice(0, 8);
    const topAgencyRows = topAgencies.slice(0, 5);
    const roleRows = usersByRole.slice(0, 4);
    const categoryRows = casesByCategory.slice(0, 5);

    return (
        <div className="mx-auto max-w-7xl space-y-6 pb-8">
            <DashboardBanner />

            <header data-tour="dashboard-header" className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="space-y-3">
                    <div className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] font-bold uppercase tracking-[0.35em] text-slate-500 shadow-sm">
                        <span className="h-2 w-2 rounded-full bg-cyan-500 motion-safe:animate-pulse" />
                        Operations Dispatch
                    </div>

                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight text-slate-950 md:text-4xl">
                            Region VII Command Desk
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            Watch the inter-agency case network, spot pressure fast, and jump straight into the right admin surface.
                        </p>
                    </div>
                </div>

                <div className="rounded-3xl border border-slate-200 bg-slate-950 px-5 py-4 text-white shadow-[0_18px_50px_-30px_rgba(15,23,42,0.75)]">
                    <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">Today</p>
                    <time className="mt-2 block text-lg font-semibold tracking-tight">{today}</time>
                    <p className="mt-1 text-sm text-slate-300">
                        Admin: <span className="font-semibold text-white">{userName}</span>
                    </p>
                </div>
            </header>

            <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_20px_70px_-40px_rgba(15,23,42,0.45)]">
                <div className="border-b border-slate-200/80 bg-slate-50/80 px-5 py-4 sm:px-6">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">Dispatch rail</p>
                            <h2 className="mt-1 text-lg font-semibold text-slate-950">Network pressure by queue</h2>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 text-[11px] font-semibold text-slate-500">
                            <span className="rounded-full bg-slate-100 px-2.5 py-1">Cases</span>
                            <span className="rounded-full bg-slate-100 px-2.5 py-1">Referrals</span>
                            <span className="rounded-full bg-slate-100 px-2.5 py-1">Agencies</span>
                            <span className="rounded-full bg-slate-100 px-2.5 py-1">Compliance</span>
                        </div>
                    </div>
                </div>

                <div className="grid gap-0 lg:grid-cols-[1.25fr_0.75fr]">
                    <div className="border-b border-slate-200/80 p-5 sm:p-6 lg:border-b-0 lg:border-r lg:border-slate-200/80">
                        <div className="rounded-[26px] bg-slate-950 p-5 text-white shadow-[0_30px_80px_-50px_rgba(15,23,42,0.9)] sm:p-6">
                            <div className="relative">
                                <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                                    <div>
                                        <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">Network pulse</p>
                                        <h3 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">
                                            {formatCount(stats.totalCases ?? 0)} cases under stewardship
                                        </h3>
                                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                                            Pressure rises where referrals stall, compliance slips, or agencies fall behind.
                                        </p>
                                    </div>

                                    <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300 backdrop-blur">
                                        <div className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">
                                            <MaterialSymbol name="route" className="text-[16px]" />
                                            Route strip
                                        </div>
                                        <p className="mt-2 text-base font-semibold text-white">Cases · Referrals · Agencies</p>
                                        <p className="mt-1 text-xs leading-5 text-slate-300">
                                            One rail, five stations, all the pressure points in view.
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-5 h-1 rounded-full bg-[linear-gradient(90deg,#0ea5e9_0%,#1e3a8a_28%,#f59e0b_54%,#f97316_75%,#ef4444_100%)]" />

                                <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    {heroStats.map((item) => (
                                        <div key={item.label} className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)] backdrop-blur">
                                            <div className={`h-1.5 w-12 rounded-full ${accentClasses(item.tone)}`} />
                                            <p className="mt-3 text-[10px] font-bold uppercase tracking-[0.3em] text-white/60">{item.label}</p>
                                            <p className="mt-2 text-2xl font-semibold text-white">{formatCount(item.value)}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            {queueCards.map((item) => (
                                <Link
                                    key={item.key}
                                    href={route(item.route)}
                                    className="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 motion-reduce:transform-none motion-reduce:transition-none"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ring-1 ${toneClasses(item.tone)}`}>
                                            <MaterialSymbol name={item.icon} className="text-[22px]" />
                                        </span>
                                        <div className="text-right">
                                            <p className="text-3xl font-semibold tracking-tight text-slate-950">{formatCount(item.count)}</p>
                                            <p className="mt-0.5 text-[10px] font-bold uppercase tracking-[0.3em] text-slate-400">Queue</p>
                                        </div>
                                    </div>
                                    <div className="mt-4">
                                        <h3 className="text-sm font-semibold text-slate-950">{item.label}</h3>
                                        <p className="mt-1 text-xs leading-5 text-slate-500">{item.note}</p>
                                    </div>
                                    <div className="mt-4 flex items-center justify-between text-[11px] font-bold uppercase tracking-[0.25em] text-slate-400">
                                        <span>Jump to queue</span>
                                        <MaterialSymbol name="arrow_forward" className="text-[16px] transition-transform group-hover:translate-x-0.5" />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>

                    <aside className="space-y-4 bg-slate-50/70 p-5 sm:p-6">
                        <div className="rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">Command surfaces</p>
                                    <h3 className="mt-1 text-base font-semibold text-slate-950">Direct admin actions</h3>
                                </div>
                                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.3em] text-slate-500">
                                    {formatCount(stats.activeUsers ?? 0)} active users
                                </span>
                            </div>

                            <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                {adminActions.map((item) => (
                                    <Link
                                        key={item.route}
                                        href={route(item.route)}
                                        className="group flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 transition hover:border-slate-300 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 motion-reduce:transition-none"
                                    >
                                        <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-white shadow-sm">
                                            <MaterialSymbol name={item.icon} className="text-[18px]" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-semibold text-slate-950">{item.label}</span>
                                            <span className="mt-0.5 block text-xs leading-5 text-slate-500">{item.description}</span>
                                        </span>
                                        <MaterialSymbol name="arrow_forward" className="mt-1 text-[16px] text-slate-300 transition-transform group-hover:translate-x-0.5" />
                                    </Link>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">System posture</p>
                            <div className="mt-3 grid grid-cols-2 gap-3">
                                <div className="rounded-2xl bg-emerald-50 px-3 py-3 ring-1 ring-emerald-100">
                                    <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-emerald-600">Verified users</p>
                                    <p className="mt-1 text-2xl font-semibold text-emerald-950">{formatCount(stats.verifiedUsers ?? 0)}</p>
                                </div>
                                <div className="rounded-2xl bg-slate-100 px-3 py-3 ring-1 ring-slate-200">
                                    <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-slate-500">Inactive users</p>
                                    <p className="mt-1 text-2xl font-semibold text-slate-950">{formatCount(stats.inactiveUsers ?? 0)}</p>
                                </div>
                            </div>
                            <p className="mt-3 text-sm leading-6 text-slate-500">
                                Keep the network tight: verify access, trim stale sessions, and steer maintenance before traffic backs up.
                            </p>
                        </div>
                    </aside>
                </div>
            </section>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(360px,0.9fr)]">
                <div className="space-y-6">
                    <section className="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">Attention queue</p>
                                <h3 className="mt-1 text-base font-semibold text-slate-950">The network needs these checked first</h3>
                            </div>
                            <Link href={route('overdue-referrals.index')} className="text-sm font-medium text-indigo-600 transition hover:text-indigo-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                                Open overdue referrals
                            </Link>
                        </div>

                        <div className="grid gap-3 p-5 sm:grid-cols-2 sm:p-6">
                            {queueCards.map((item) => (
                                <Link
                                    key={`attention-${item.key}`}
                                    href={route(item.route)}
                                    className="group rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 motion-reduce:transition-none"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex items-start gap-3">
                                            <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl ring-1 ${toneClasses(item.tone)}`}>
                                                <MaterialSymbol name={item.icon} className="text-[20px]" />
                                            </span>
                                            <div>
                                                <h4 className="text-sm font-semibold text-slate-950">{item.label}</h4>
                                                <p className="mt-1 text-xs leading-5 text-slate-500">{item.note}</p>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-3xl font-semibold tracking-tight text-slate-950">{formatCount(item.count)}</p>
                                            <p className="mt-0.5 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400">Queue</p>
                                        </div>
                                    </div>
                                    <div className="mt-4 flex items-center justify-between text-[11px] font-bold uppercase tracking-[0.25em] text-slate-400">
                                        <span>Review now</span>
                                        <MaterialSymbol name="arrow_forward" className="text-[16px] transition-transform group-hover:translate-x-0.5" />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm" data-tour="admin-recent-cases">
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">Recent cases</p>
                                <h3 className="mt-1 text-base font-semibold text-slate-950">New or updated cases</h3>
                            </div>
                            <Link href={route('cases.index')} className="text-sm font-medium text-indigo-600 transition hover:text-indigo-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                                View all cases
                            </Link>
                        </div>

                        <div className="divide-y divide-slate-200">
                            {recentCaseRows.length === 0 ? (
                                <p className="px-5 py-6 text-sm text-slate-500 sm:px-6">No recent cases yet.</p>
                            ) : (
                                recentCaseRows.map((item) => (
                                    <Link
                                        key={item.id}
                                        href={route('cases.show', item.id)}
                                        className="group flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 sm:px-6"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="rounded-full bg-slate-950 px-2.5 py-1 text-[10px] font-bold tracking-[0.25em] text-white">
                                                    {item.case_number}
                                                </span>
                                                {item.tracker_number ? (
                                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-500">
                                                        {item.tracker_number}
                                                    </span>
                                                ) : null}
                                                {item.client_type ? (
                                                    <span className="rounded-full bg-cyan-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.25em] text-cyan-700">
                                                        {item.client_type === 'Overseas Filipino Worker' ? 'OFW' : 'NOK'}
                                                    </span>
                                                ) : null}
                                            </div>
                                            <p className="mt-2 truncate text-sm font-semibold text-slate-950">{item.client_name}</p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {item.category ? `${item.category} · ` : ''}
                                                {item.case_owner ? `${item.case_owner} · ` : ''}
                                                Created {formatDisplayDate(item.created_at)}
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-3">
                                            <StatusBadge status={item.status} />
                                            <div className="hidden text-right sm:block">
                                                <p className="text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400">Updated</p>
                                                <p className="text-sm text-slate-600">{formatDisplayDate(item.updated_at)}</p>
                                            </div>
                                            <MaterialSymbol name="chevron_right" className="text-[18px] text-slate-300 transition-transform group-hover:translate-x-0.5" />
                                        </div>
                                    </Link>
                                ))
                            )}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm" data-tour="admin-recent-activity">
                        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">Recent activity</p>
                                <h3 className="mt-1 text-base font-semibold text-slate-950">Audit trail snapshot</h3>
                            </div>
                            <Link href={route('audit-logs.index')} className="text-sm font-medium text-indigo-600 transition hover:text-indigo-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                                View audit logs
                            </Link>
                        </div>

                        <div className="divide-y divide-slate-200">
                            {recentActivityRows.length === 0 ? (
                                <p className="px-5 py-6 text-sm text-slate-500 sm:px-6">No recent activity.</p>
                            ) : (
                                recentActivityRows.map((log) => {
                                    const cfg = actionConfig[log.action || log.actionType] || { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: '' };

                                    return (
                                        <div key={log.id} className="flex items-start gap-3 px-5 py-4 sm:px-6">
                                            <span className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-1 ring-slate-200 ${cfg.bg}`}>
                                                <cfg.icon className={`h-4 w-4 ${cfg.text}`} />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium leading-6 text-slate-950">
                                                    {log.actor ? <span className="font-normal text-slate-500">{log.actor} </span> : null}
                                                    <span className="truncate">{log.message ?? log.description}</span>
                                                </p>
                                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                                    <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.25em] ${cfg.text} ${cfg.bg}`}>
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
                    </section>
                </div>

                <aside className="space-y-6">
                    <section className="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4 sm:px-6">
                            <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">Agency health</p>
                            <h3 className="mt-1 text-base font-semibold text-slate-950">Load across partner agencies</h3>
                        </div>

                        <div className="grid grid-cols-2 gap-3 px-5 pt-5 sm:px-6">
                            <div className="rounded-2xl bg-emerald-50 px-3 py-3 ring-1 ring-emerald-100">
                                <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-emerald-700">Active</p>
                                <p className="mt-1 text-2xl font-semibold text-emerald-950">{formatCount(stats.activeAgencies ?? 0)}</p>
                            </div>
                            <div className="rounded-2xl bg-slate-100 px-3 py-3 ring-1 ring-slate-200">
                                <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-slate-500">Inactive</p>
                                <p className="mt-1 text-2xl font-semibold text-slate-950">{formatCount(stats.inactiveAgencies ?? 0)}</p>
                            </div>
                        </div>

                        <div className="space-y-3 p-5 sm:p-6">
                            {topAgencyRows.length === 0 ? (
                                <p className="text-sm text-slate-500">No agency load data yet.</p>
                            ) : (
                                topAgencyRows.map((agency) => {
                                    const maxLoad = Math.max(...topAgencyRows.map((row) => Number(row.activeReferrals ?? row.totalReferrals ?? 0)), 1);
                                    const width = Math.max(((agency.activeReferrals ?? agency.totalReferrals ?? 0) / maxLoad) * 100, 8);

                                    return (
                                        <div key={agency.id} className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold text-slate-950">{agency.name}</p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {formatCount(agency.activeReferrals ?? 0)} active of {formatCount(agency.totalReferrals ?? 0)} referrals
                                                    </p>
                                                </div>
                                                <span className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-[0.25em] ${agency.isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                                                    {agency.isActive ? 'Active' : 'Inactive'}
                                                </span>
                                            </div>
                                            <div className="mt-3 h-2 rounded-full bg-slate-200">
                                                <div
                                                    className={`h-2 rounded-full ${agency.isActive ? 'bg-emerald-500' : 'bg-slate-400'}`}
                                                    style={{ width: `${width}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4 sm:px-6">
                            <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">User roster</p>
                            <h3 className="mt-1 text-base font-semibold text-slate-950">Role mix and access health</h3>
                        </div>

                        <div className="grid grid-cols-2 gap-3 px-5 pt-5 sm:px-6">
                            <div className="rounded-2xl bg-blue-50 px-3 py-3 ring-1 ring-blue-100">
                                <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-blue-700">Users</p>
                                <p className="mt-1 text-2xl font-semibold text-blue-950">{formatCount(stats.totalUsers ?? 0)}</p>
                            </div>
                            <div className="rounded-2xl bg-cyan-50 px-3 py-3 ring-1 ring-cyan-100">
                                <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-cyan-700">Verified</p>
                                <p className="mt-1 text-2xl font-semibold text-cyan-950">{formatCount(stats.verifiedUsers ?? 0)}</p>
                            </div>
                        </div>

                        <div className="space-y-3 p-5 sm:p-6">
                            {roleRows.length === 0 ? (
                                <p className="text-sm text-slate-500">No role data yet.</p>
                            ) : (
                                roleRows.map((role) => (
                                    <div key={role.role} className="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-950">{role.label}</p>
                                            <p className="text-xs text-slate-500">{role.role}</p>
                                        </div>
                                        <span className="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-950">{formatCount(role.count)}</span>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4 sm:px-6">
                            <p className="text-[10px] font-bold uppercase tracking-[0.35em] text-slate-400">Case category load</p>
                            <h3 className="mt-1 text-base font-semibold text-slate-950">Which issues dominate the board</h3>
                        </div>

                        <div className="space-y-3 p-5 sm:p-6">
                            {categoryRows.length === 0 ? (
                                <p className="text-sm text-slate-500">No category data yet.</p>
                            ) : (
                                categoryRows.map((category) => {
                                    const count = Number(category.count ?? 0);
                                    const maxCount = Math.max(...categoryRows.map((item) => Number(item.count ?? 0)), 1);
                                    const width = Math.max((count / maxCount) * 100, 10);

                                    return (
                                        <div key={category.name} className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="truncate text-sm font-semibold text-slate-950">{category.name}</p>
                                                <span className="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-500 ring-1 ring-slate-200">
                                                    {formatCount(category.count)}
                                                </span>
                                            </div>
                                            <div className="mt-3 h-2 rounded-full bg-slate-200">
                                                <div
                                                    className="h-2 rounded-full"
                                                    style={{ width: `${width}%`, backgroundColor: category.color || '#0f172a' }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    );
}
