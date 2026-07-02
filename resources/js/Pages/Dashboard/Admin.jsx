import { Link, router } from '@inertiajs/react';
import DashboardBanner from '@/Components/DashboardBanner';
import KpiCard from '@/Components/ui/KpiCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import RecentTable from '@/Components/ui/RecentTable';
import { formatDisplayDate, formatDisplayDateTime } from '@/lib/utils';
import { actionConfig } from '@/Components/Dashboard/activityConfig';
import { Eye } from 'lucide-react';

export default function AdminDashboard({ dashboard }) {
    const { stats, recentCases = [], recentLogs = [], systemHealth = null } = dashboard || {};

    return (
        <>
            <DashboardBanner />
            <header data-tour="dashboard-header" className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Admin Dashboard</h1>
                    <p className="text-sm text-slate-500 mt-1">System-wide overview and monitoring.</p>
                </div>
            </header>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <KpiCard title="Total Cases" value={stats.totalCases} accent="border-l-indigo-500" icon="folder" />
                <KpiCard title="Total Referrals" value={stats.totalReferrals} accent="border-l-orange-500" icon="send" />
                <KpiCard title="Users" value={stats.totalUsers} accent="border-l-blue-500" icon="people" />
                <KpiCard title="Agencies" value={stats.totalAgencies} accent="border-l-green-500" icon="account_balance" />
            </div>

            {/* System Health Overview */}
            {systemHealth && (
                <div data-tour="dashboard-admin-system" className="mb-6">
                    <div className="flex items-center justify-between mb-3">
                        <h3 className="text-base font-semibold text-slate-900">System Health</h3>
                        <Link href="/admin/system/health" className="text-sm text-indigo-600 hover:text-indigo-900">View Details</Link>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className={`rounded-xl border p-4 shadow-sm ${
                            systemHealth.overallStatus === 'healthy' ? 'border-green-200 bg-green-50' :
                            systemHealth.overallStatus === 'warning' ? 'border-yellow-200 bg-yellow-50' :
                            systemHealth.overallStatus === 'critical' ? 'border-red-200 bg-red-50' :
                            'border-slate-200 bg-white'
                        }`}>
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Overall Status</p>
                            <p className={`text-lg font-bold mt-1 ${
                                systemHealth.overallStatus === 'healthy' ? 'text-green-700' :
                                systemHealth.overallStatus === 'warning' ? 'text-yellow-700' :
                                systemHealth.overallStatus === 'critical' ? 'text-red-700' :
                                'text-slate-700'
                            }`}>{systemHealth.overallStatus || 'Unknown'}</p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Unread Alerts</p>
                            <p className={`text-lg font-bold mt-1 ${systemHealth.alertCount > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                {systemHealth.alertCount ?? 0}
                            </p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Last Check</p>
                            <p className="text-lg font-bold mt-1 text-slate-700">{systemHealth.lastCheckAt || 'Never'}</p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Active Checks</p>
                            <p className="text-lg font-bold mt-1 text-slate-700">{systemHealth.checks?.length ?? 0}</p>
                        </div>
                    </div>
                </div>
            )}

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

                <div data-tour="admin-recent-activity" className="rounded-lg bg-white shadow-sm border border-slate-200">
                    <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                        <h3 className="text-base font-semibold text-slate-900">Recent Activity</h3>
                        <Link href={route('audit-logs.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
                    </div>
                    <div className="divide-y divide-slate-200">
                        {recentLogs?.length === 0 ? (
                            <p className="px-6 py-4 text-sm text-slate-500">No recent activity.</p>
                        ) : (
                            recentLogs?.map((log) => {
                                const cfg = actionConfig[log.action] || { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: '' }
                                return (
                                <div key={log.id} className="px-6 py-3 flex items-start gap-3">
                                    <span className={`shrink-0 flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white ${cfg.bg} shadow-sm mt-0.5`}>
                                        <cfg.icon className={`w-3.5 h-3.5 ${cfg.text}`} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm text-slate-900 font-medium leading-snug">
                                            {log.actor ? <span className="text-slate-500 font-normal">{log.actor} </span> : null}{log.message ?? log.description}
                                        </p>
                                        <div className="flex items-center gap-2 mt-1">
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ${cfg.text} ${cfg.bg}`}>
                                                {cfg.label || log.action}
                                            </span>
                                            <p className="text-xs text-slate-500">{formatDisplayDateTime(log.timestamp)}</p>
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
