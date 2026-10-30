import Select from '@/Components/Select';
import { useEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Bar, Doughnut, Line } from 'react-chartjs-2';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import GettingStartedChecklist from '@/Components/GettingStartedChecklist';
import {
    EmptyState,
    FilterChip,
    MaterialSymbol,
    QuickActions,
    SectionCard,
    ViewAllLink,
    formatCount,
    safeArray,
} from '@/Components/Dashboard/primitives';
import { DashboardRow, DashboardRowList } from '@/Components/Dashboard/DashboardRow';
import KpiCard from '@/Components/ui/KpiCard';
import { formatDisplayDateTime } from '@/lib/utils';
import safeRoute from '@/utils/safeRoute';

ChartJS.register(ArcElement, BarElement, CategoryScale, Filler, Legend, LinearScale, LineElement, PointElement, Tooltip);

/**
 * System admin overview.
 *
 * System health, user and agency management, access control, and audit
 * monitoring — not casework. The admin answers "is the system healthy, who
 * has access, and what changed" — not "which cases need action today".
 */

const PERIODS = [
    { days: 7, label: 'Last 7 days' },
    { days: 30, label: 'Last 30 days' },
    { days: 90, label: 'Last 90 days' },
];

const ROLE_LABELS = {
    ADMIN: 'System Admins',
    CASE_MANAGER: 'Case Managers',
    AGENCY: 'Agency Focals',
    OFW: 'OFW Tracking Users',
};

const ROLE_HUES = ['#005288', '#0891b2', '#059669', '#d97706', '#7c3aed', '#94a3b8'];
const SECURITY_HUES = ['#e11d48', '#ea580c', '#d97706', '#005288', '#7c3aed', '#94a3b8'];

const ADMIN_TOOLS = [
    { label: 'Users', description: 'Roles, invites, verification', href: '/admin/users', route: 'admin.users.index', icon: 'group' },
    { label: 'Agencies', description: 'Partner profiles and activation', href: '/admin/agencies', route: 'admin.agencies.index', icon: 'business' },
    { label: 'Services', description: 'Catalog and requirements', href: '/admin/services', route: 'admin.services.index', icon: 'inventory_2' },
    { label: 'System settings', description: 'Thresholds and chatbot', href: '/admin/system-settings', route: 'admin.system-settings.index', icon: 'settings' },
    { label: 'Security', description: 'Password and lockout policy', href: '/admin/system/security', route: 'admin.system.security', icon: 'shield' },
    { label: 'Maintenance', description: 'Maintenance mode control', href: '/admin/system/maintenance', route: 'admin.system.maintenance', icon: 'construction' },
    { label: 'Audit logs', description: 'Full unscoped change trail', href: '/audit-logs', route: 'audit-logs.index', icon: 'history' },
    { label: 'Case statuses', description: 'Status taxonomy', href: '/admin/case-statuses', route: 'admin.case-statuses.index', icon: 'flag' },
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

function formatToday() {
    return new Date().toLocaleDateString('en-PH', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatBucket(value) {
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-PH', { day: '2-digit', month: 'short' });
}


/* ---------- Chart section (matches case manager / agency conventions) ---------- */

function ChartSection({ title, description, legend, children, height = 'h-64' }) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div className="min-w-0">
                    <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">{title}</h2>
                    {description ? <p className="mt-0.5 text-[12px] text-slate-500">{description}</p> : null}
                </div>
                {legend ?? null}
            </header>
            <div className={`px-5 py-5 ${height}`}>{children}</div>
        </section>
    );
}

function LegendDot({ color, children }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: color }} />
            {children}
        </span>
    );
}

function EmptyChart() {
    return (
        <div className="flex h-full flex-col items-center justify-center text-center">
            <span className="material-symbols-outlined text-3xl text-slate-300" aria-hidden="true">insights</span>
            <p className="mt-2 text-[13px] font-semibold text-slate-700">Nothing to chart yet</p>
            <p className="mt-0.5 text-[12px] text-slate-500">Data appears as system activity accumulates.</p>
        </div>
    );
}

const TOOLTIP = {
    backgroundColor: '#0f172a',
    padding: 10,
    cornerRadius: 6,
    titleFont: { size: 11 },
    bodyFont: { size: 11 },
    boxWidth: 8,
    boxHeight: 8,
};

const BASE_SCALES = {
    y: {
        beginAtZero: true,
        ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8', padding: 6 },
        grid: { color: '#f1f5f9', drawTicks: false },
        border: { display: false },
    },
    x: {
        ticks: { font: { size: 10 }, color: '#94a3b8', maxTicksLimit: 8, maxRotation: 0, autoSkip: true },
        grid: { display: false },
        border: { display: false },
    },
};

/* ---------- Page ---------- */

export default function AdminDashboard({ dashboard = {} }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0] ?? 'Administrator';

    const [days, setDays] = useState(30);
    const [period, setPeriod] = useState(null);
    const [loading, setLoading] = useState(true);

    const recentLogs = safeArray(dashboard.recentLogs).slice(0, 8).map((log) => ({
        ...log,
        actionType: log.action ?? log.actionType,
        title: log.message ?? 'System activity',
        desc: log.detail ?? '',
        time: log.timestamp ? formatDisplayDateTime(log.timestamp) : log.time,
    }));

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

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        fetch(safeRoute('dashboard.admin-period', { days }, `/dashboard/admin-period?days=${days}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (!cancelled && data) setPeriod(data);
            })
            .catch(() => {})
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [days]);

    const kpis = period?.kpis ?? { total_users: 0, total_agencies: 0, pending_invites: 0, security_events: 0 };
    const activity = period?.activity ?? { labels: [], events: [] };
    const usersByRole = safeArray(period?.users_by_role);
    const securityByType = safeArray(period?.security_by_type);

    const periodLabel = period?.period?.label ?? PERIODS.find((p) => p.days === days)?.label;
    const hasActivity = (activity.labels ?? []).length > 0;

    const activityChart = useMemo(() => ({
        labels: (activity.labels ?? []).map(formatBucket),
        datasets: [
            {
                label: 'System events',
                data: activity.events ?? [],
                borderColor: '#005288',
                backgroundColor: 'rgba(0, 82, 136, 0.10)',
                pointBackgroundColor: '#005288',
                fill: true,
            },
        ],
    }), [activity]);

    const roleChart = useMemo(() => ({
        labels: usersByRole.map((row) => ROLE_LABELS[row.role] ?? row.role),
        values: usersByRole.map((row) => row.total),
        hues: usersByRole.map((_, index) => ROLE_HUES[index % ROLE_HUES.length]),
    }), [usersByRole]);

    const securityChart = useMemo(() => ({
        labels: securityByType.map((row) => row.action),
        values: securityByType.map((row) => row.total),
        hues: securityByType.map((_, index) => SECURITY_HUES[index % SECURITY_HUES.length]),
    }), [securityByType]);

    return (
        <div className="mx-auto max-w-7xl pb-8">
            <GettingStartedChecklist />

            {/* Greeting + today's date + period */}
            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">System administration</p>
                    <h1 className="mt-1 font-headline text-[28px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">
                        Good day, {firstName}
                    </h1>
                    <p className="mt-1 text-[14px] text-slate-500">{formatToday()}</p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <label htmlFor="admin-dashboard-period" className="sr-only">Reporting period</label>
                    <div className="relative">
                        <span
                            aria-hidden="true"
                            className="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[17px] leading-none text-slate-400"
                        >
                            calendar_month
                        </span>
                        <Select
                            id="admin-dashboard-period"
                            value={days}
                            onChange={(e) => setDays(Number(e.target.value))}
                            className="appearance-none rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-9 text-[13px] font-semibold text-slate-700 shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            {PERIODS.map((option) => (
                                <option key={option.days} value={option.days}>{option.label}</option>
                            ))}
                        </Select>
                    </div>

                    <QuickActions
                        actions={[
                            { href: safeRoute('admin.users.index', undefined, '/admin/users'), label: 'Users', icon: 'group', primary: true },
                            { href: safeRoute('admin.agencies.index', undefined, '/admin/agencies'), label: 'Agencies', icon: 'business' },
                            { href: safeRoute('audit-logs.index', undefined, '/audit-logs'), label: 'Audit', icon: 'history' },
                        ]}
                    />
                </div>
            </div>

            {/* Four KPIs */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Total users" value={kpis.total_users} hint="Across all roles" icon="group" />
                <KpiCard label="Registered agencies" value={kpis.total_agencies} hint="Participating agencies and LGUs" icon="business" />
                <KpiCard
                    label="Pending invitations"
                    value={kpis.pending_invites}
                    hint="Sent but not yet accepted"
                    tone="attention"
                    icon="mail"
                />
                <KpiCard
                    label="Security alerts"
                    value={kpis.security_events}
                    hint={`Events in ${periodLabel?.toLowerCase() ?? 'the period'}`}
                    tone="alert"
                    icon="warning"
                />
            </div>

            {/* Chart 1 — system activity */}
            <div className="mt-6">
                <ChartSection
                    title="System activity over time"
                    description="Logins, user-management actions, and other system events by day."
                    legend={
                        <div className="flex items-center gap-4 pt-1">
                            <LegendDot color="#005288">System events</LegendDot>
                        </div>
                    }
                >
                    {loading && !hasActivity ? (
                        <div className="flex h-full items-center justify-center">
                            <span aria-hidden="true" className="material-symbols-outlined animate-spin text-[24px] text-slate-300">
                                progress_activity
                            </span>
                        </div>
                    ) : hasActivity ? (
                        <Line
                            data={activityChart}
                            options={{
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                plugins: { legend: { display: false }, tooltip: TOOLTIP },
                                elements: { line: { tension: 0.35, borderWidth: 2 }, point: { radius: 0, hoverRadius: 4, hitRadius: 12 } },
                                scales: BASE_SCALES,
                            }}
                        />
                    ) : (
                        <EmptyChart />
                    )}
                </ChartSection>
            </div>

            {/* Charts 2 + 3 */}
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <ChartSection title="Users by role" description="Distribution of access across the system.">
                    {roleChart.labels.length === 0 ? (
                        <EmptyChart />
                    ) : (
                        <div className="flex h-full flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="h-40 w-full shrink-0 sm:h-44 sm:w-44">
                                <Doughnut
                                    data={{
                                        labels: roleChart.labels,
                                        datasets: [
                                            {
                                                data: roleChart.values,
                                                backgroundColor: roleChart.hues,
                                                borderColor: '#ffffff',
                                                borderWidth: 2,
                                            },
                                        ],
                                    }}
                                    options={{
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        cutout: '62%',
                                        plugins: { legend: { display: false }, tooltip: TOOLTIP },
                                    }}
                                />
                            </div>
                            <ul className="min-w-0 flex-1 space-y-1.5">
                                {roleChart.labels.map((label, index) => (
                                    <li key={label} className="flex items-center justify-between gap-2 text-[12px]">
                                        <span className="flex min-w-0 items-center gap-1.5">
                                            <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: roleChart.hues[index] }} />
                                            <span className="truncate font-semibold text-slate-700">{label}</span>
                                        </span>
                                        <span className="shrink-0 font-bold text-slate-900">{formatCount(roleChart.values[index])}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </ChartSection>

                <ChartSection title="Security events by type" description="Failed logins, denied access, and credential changes in the period.">
                    {securityChart.labels.length === 0 ? (
                        <EmptyChart />
                    ) : (
                        <Bar
                            data={{
                                labels: securityChart.labels,
                                datasets: [
                                    {
                                        data: securityChart.values,
                                        backgroundColor: securityChart.hues,
                                        borderRadius: 4,
                                        maxBarThickness: 22,
                                    },
                                ],
                            }}
                            options={{
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false }, tooltip: TOOLTIP },
                                scales: {
                                    x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' }, grid: { color: '#f1f5f9', drawTicks: false }, border: { display: false } },
                                    y: { ticks: { font: { size: 10 }, color: '#475569' }, grid: { display: false }, border: { display: false } },
                                },
                            }}
                        />
                    )}
                </ChartSection>
            </div>

            {/* Below the charts: audit logs + admin tools */}
            <div className="mt-6 grid gap-6 xl:grid-cols-12">
                <div className="space-y-6 xl:col-span-8">
                    {/* Recent audit logs */}
                    <SectionCard
                        title="Recent audit logs"
                        description="Who performed an action, what changed, and when."
                        dataTour="dashboard-recent-activity"
                        action={<ViewAllLink href={safeRoute('audit-logs.index', undefined, '/audit-logs')}>View all</ViewAllLink>}
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
                        <DashboardRowList empty={<EmptyState message="No matching activity." />}>
                            {filteredLogs.map((log) => {
                                const isSecurity = categorizeModule(log.module) === 'Users';
                                const pillTone = isSecurity
                                    ? 'bg-rose-50 text-rose-700'
                                    : 'bg-primary/10 text-primary';

                                return (
                                    <DashboardRow
                                        key={log.id}
                                        href={safeRoute('audit-logs.index', undefined, '/audit-logs')}
                                        pill={log.actionType ?? log.action ?? 'EVENT'}
                                        pillTone={pillTone}
                                        title={log.title}
                                        note={`${categorizeModule(log.module)}${log.actor ? ` · ${log.actor}` : ''}`}
                                        right={<span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{log.time}</span>}
                                    />
                                );
                            })}
                        </DashboardRowList>
                    </SectionCard>
                </div>

                <aside className="space-y-6 xl:col-span-4">
                    {/* Admin tools / system configuration */}
                    <SectionCard title="System configuration" dataTour="dashboard-admin-tools" bodyClassName="p-3">
                        <div className="grid gap-1">
                            {ADMIN_TOOLS.map((tool) => (
                                <Link
                                    key={tool.route ?? tool.href}
                                    href={tool.route ? safeRoute(tool.route, undefined, tool.href) : tool.href}
                                    className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                                >
                                    <span className="material-symbols-outlined text-[20px] text-slate-400" aria-hidden="true">
                                        {tool.icon}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-[13px] font-bold text-slate-900">{tool.label}</p>
                                        <p className="truncate text-[11px] text-slate-500">{tool.description}</p>
                                    </div>
                                    <span className="material-symbols-outlined ml-auto shrink-0 text-[16px] text-slate-300" aria-hidden="true">
                                        chevron_right
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </SectionCard>
                </aside>
            </div>

            <p className="mt-6 text-[11px] leading-relaxed text-slate-400">
                Casework belongs to case managers and agency focals — this dashboard is system oversight only.
            </p>
        </div>
    );
}
