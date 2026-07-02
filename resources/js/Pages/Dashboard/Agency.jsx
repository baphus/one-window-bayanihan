import { router } from '@inertiajs/react';
import DashboardBanner from '@/Components/DashboardBanner';
import KpiCard from '@/Components/ui/KpiCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import RecentTable from '@/Components/ui/RecentTable';
import ActivityItem from '@/Components/Dashboard/ActivityItem';
import { Send, FolderCheck, Eye, TrendingUp, ChevronRight } from 'lucide-react';

export default function AgencyDashboard({ dashboard }) {
    const { stats, recentReferrals = [], recentActivity = [], dashboardNotifications = [] } = dashboard || {};

    return (
        <div className="max-w-7xl mx-auto pb-6">
            <DashboardBanner />
            <header data-tour="dashboard-header" className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
                <div>
                    <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Agency Dashboard</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Overview of your agency's referrals and performance.</p>
                </div>
                <div className="flex items-center gap-3">
                </div>
            </header>

            <section data-tour="dashboard-stats" className="mb-8">
                <div data-tour="dashboard-agency-metrics" className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard title="Total Referrals" value={stats.totalReferrals} accent="border-l-blue-900" icon="send" />
                    <KpiCard title="Pending" value={stats.pendingReferrals} accent="border-l-yellow-500" icon="hourglass" />
                    <KpiCard title="Processing" value={stats.processingReferrals} accent="border-l-blue-500" icon="sync" />
                    <KpiCard title="Completed" value={stats.completedReferrals} accent="border-l-green-500" icon="check_circle" />
                </div>
            </section>

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                <div data-tour="dashboard-agency-referrals" className="lg:col-span-8">
                    <RecentTable
                        title="Recent Referrals"
                        data={recentReferrals ?? []}
                        columns={[
                            { key: 'case_number', title: 'Case #', render: (row) => row.case_file?.case_number ?? 'N/A' },
                            { key: 'client', title: 'Client', render: (row) => row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A' },
                            { key: 'service', title: 'Service', render: (row) => row.required_services },
                            { key: 'status', title: 'Status', render: (row) => (
                                <StatusBadge status={row.status} />
                            )},
                        ]}
                        keyExtractor={(row) => row.id}
                        onViewAll={() => router.visit(route('referrals.index'))}
                    />
                </div>

                <div className="lg:col-span-4 space-y-3">
                    <div data-tour="dashboard-quick-actions" className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Quick Actions</h3>
                        <div className="space-y-2">
                            <button
                                onClick={() => router.visit(route('referrals.index'))}
                                className="w-full py-2.5 px-3.5 bg-blue-900 text-white rounded-lg flex items-center justify-between hover:bg-blue-800 active:scale-[0.98] transition-all shadow-sm"
                            >
                                <span className="flex items-center gap-2 text-[12px] font-bold">
                                    <Send className="w-3.5 h-3.5" /> View Referrals
                                </span>
                                <ChevronRight className="w-3.5 h-3.5 opacity-60" />
                            </button>
                            <button
                                onClick={() => router.visit(route('agency.services.index'))}
                                className="w-full py-2.5 px-3.5 bg-white text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between hover:bg-slate-50 transition-all"
                            >
                                <span className="flex items-center gap-2 text-[12px] font-bold">
                                    <FolderCheck className="w-3.5 h-3.5 text-slate-400" /> Manage Services
                                </span>
                                <ChevronRight className="w-3.5 h-3.5 text-slate-300" />
                            </button>
                            <button
                                onClick={() => router.visit(route('audit-logs.index'))}
                                className="w-full py-2.5 px-3.5 bg-white text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between hover:bg-slate-50 transition-all"
                            >
                                <span className="flex items-center gap-2 text-[12px] font-bold">
                                    <Eye className="w-3.5 h-3.5 text-slate-400" /> Activity Logs
                                </span>
                                <ChevronRight className="w-3.5 h-3.5 text-slate-300" />
                            </button>
                            <button
                                onClick={() => router.visit(route('reports.index'))}
                                className="w-full py-2.5 px-3.5 bg-white text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between hover:bg-slate-50 transition-all"
                            >
                                <span className="flex items-center gap-2 text-[12px] font-bold">
                                    <TrendingUp className="w-3.5 h-3.5 text-slate-400" /> Reports
                                </span>
                                <ChevronRight className="w-3.5 h-3.5 text-slate-300" />
                            </button>
                        </div>
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="px-4 py-3 border-b border-slate-100">
                            <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Recent Activity</h3>
                        </div>
                        <div className="p-4">
                            <div className="relative pl-4 border-l-2 border-slate-100 space-y-4">
                                {(recentActivity ?? []).length === 0 ? (
                                    <p className="text-xs text-slate-400 py-2">No recent activity.</p>
                                ) : (
                                    (recentActivity ?? []).slice(0, 5).map((activity) => (
                                        <ActivityItem
                                            key={activity.id}
                                            title={activity.title}
                                            desc={activity.desc}
                                            time={activity.time?.toUpperCase() ?? ''}
                                            logoSrc={activity.logoSrc ?? '/logo.png'}
                                            actionType={activity.actionType}
                                            actor={activity.actor}
                                            message={activity.message}
                                            detail={activity.detail}
                                        />
                                    ))
                                )}
                            </div>
                            <button
                                onClick={() => router.visit(route('audit-logs.index'))}
                                className="w-full mt-3 text-[11px] font-bold font-label text-blue-900 hover:text-blue-700 transition-colors text-center"
                            >
                                VIEW ALL ACTIVITY
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
