import { Link, router, usePage } from '@inertiajs/react';
import DashboardBanner from '@/Components/DashboardBanner';
import KpiCard from '@/Components/ui/KpiCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import RecentTable from '@/Components/ui/RecentTable';
import { formatDisplayDate, formatDisplayDateTime } from '@/lib/utils';
import { actionConfig } from '@/Components/Dashboard/activityConfig';
import { Eye } from 'lucide-react';

export default function AdminDashboard({ dashboard }) {
    const { auth } = usePage().props;
    const { stats, recentCases = [], recentLogs = [] } = dashboard || {};
    const userName = auth?.user?.name?.split(' ')[0] || 'Administrator';
    const today = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    return (
        <>
            <DashboardBanner />
            <header data-tour="dashboard-header" className="flex flex-col md:flex-row md:items-end justify-between gap-3 mb-10">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Admin Dashboard</h1>
                    <p className="text-sm text-slate-500 mt-1">Welcome back, {userName}.</p>
                </div>
                <time className="text-sm text-slate-400 whitespace-nowrap">{today}</time>
            </header>


            {/* KPI Cards */}
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-10">
                <KpiCard title="Total Cases" value={stats.totalCases} icon="folder" iconBg="bg-blue-50" iconColor="text-blue-900" />
                <KpiCard title="Total Referrals" value={stats.totalReferrals} icon="send" iconBg="bg-amber-50" iconColor="text-amber-600" />
                <KpiCard title="Users" value={stats.totalUsers} icon="people" iconBg="bg-violet-50" iconColor="text-violet-600" />
                <KpiCard title="Agencies" value={stats.totalAgencies} icon="account_balance" iconBg="bg-emerald-50" iconColor="text-emerald-600" />
            </div>

            {/* Recent Cases & Recent Activity */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div data-tour="admin-recent-cases">
                <RecentTable
                    title="Recent Cases"
                    data={recentCases ?? []}
                    columns={[
                        { key: 'case_number', title: 'Case #', render: (row) => row.case_number },
                        { key: 'status', title: 'Status', render: (row) => (
                            <StatusBadge status={row.status} />
                        )},
                        { key: 'created', title: 'Created', render: (row) => formatDisplayDate(row.created_at) },
                    ]}
                    keyExtractor={(row) => row.id}
                    onViewAll={() => router.visit(route('cases.index'))}
                />
                </div>

                <div data-tour="admin-recent-activity" className="rounded-md bg-white border border-slate-200">
                    <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-slate-900">Recent Activity</h3>
                        <Link href={route('audit-logs.index')} className="text-sm font-medium text-indigo-600 hover:text-indigo-800">View All</Link>
                    </div>
                    <div className="divide-y divide-slate-200">
                        {recentLogs?.length === 0 ? (
                            <p className="px-6 py-4 text-sm text-slate-500">No recent activity.</p>
                        ) : (
                            recentLogs?.map((log) => {
                                const cfg = actionConfig[log.action] || { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: '' }
                                return (
                                <div key={log.id} className="px-6 py-3 flex items-start gap-3">
                                    <span className={`shrink-0 flex items-center justify-center w-7 h-7 rounded-full ring-1 ring-slate-200 ${cfg.bg} mt-0.5`}>
                                        <cfg.icon className={`w-3.5 h-3.5 ${cfg.text}`} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm text-slate-900 font-medium leading-snug">
                                            {log.actor ? <span className="text-slate-500 font-normal">{log.actor} </span> : null}{log.message ?? log.description}
                                        </p>
                                        <div className="flex items-center gap-2 mt-1">
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-sm font-bold ${cfg.text} ${cfg.bg}`}>
                                                {cfg.label || log.action}
                                            </span>
                                            <p className="text-sm text-slate-500">{formatDisplayDateTime(log.timestamp)}</p>
                                        </div>
                                    </div>
                                </div>
                                )
                            })
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
