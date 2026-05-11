import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Doughnut, Line, Bar } from 'react-chartjs-2';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, BarElement, LineElement,
    PointElement, ArcElement, Title, Tooltip, Legend, Filler,
} from 'chart.js';
import KpiCard from '@/Components/ui/KpiCard';

ChartJS.register(
    CategoryScale, LinearScale, BarElement, LineElement,
    PointElement, ArcElement, Title, Tooltip, Legend, Filler,
);

const statusStyles = {
    COMPLETED: 'bg-green-100 text-green-800',
    PROCESSING: 'bg-blue-100 text-blue-800',
    REJECTED: 'bg-red-100 text-red-800',
    PENDING: 'bg-yellow-100 text-yellow-800',
};

const pieOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } } }, cutout: '55%' };

function AgencyDashboard({ stats, recentReferrals }) {
    return (
        <>
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900">Agency Dashboard</h1>
                <p className="text-sm text-slate-500 mt-1">Overview of your agency's referrals and performance.</p>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <KpiCard title="Total Referrals" value={stats.totalReferrals} accent="border-l-blue-900" icon="send" />
                <KpiCard title="Pending" value={stats.pendingReferrals} accent="border-l-yellow-500" icon="hourglass" />
                <KpiCard title="Processing" value={stats.processingReferrals} accent="border-l-blue-500" icon="sync" />
                <KpiCard title="Completed" value={stats.completedReferrals} accent="border-l-green-500" icon="check_circle" />
            </div>

            <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                    <h3 className="text-base font-semibold text-slate-900">Recent Referrals</h3>
                    <Link href={route('referrals.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Client</th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Service</th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {recentReferrals?.length === 0 ? (
                                <tr><td colSpan={4} className="px-6 py-4 text-center text-sm text-slate-500">No referrals yet.</td></tr>
                            ) : (
                                recentReferrals?.map((ref) => (
                                    <tr key={ref.id} className="hover:bg-slate-50">
                                        <td className="px-6 py-4 text-sm font-medium text-slate-900">{ref.case_file?.case_number ?? 'N/A'}</td>
                                        <td className="px-6 py-4 text-sm text-slate-500">
                                            {ref.case_file?.client ? `${ref.case_file.client.first_name} ${ref.case_file.client.last_name}` : 'N/A'}
                                        </td>
                                        <td className="px-6 py-4 text-sm text-slate-500">{ref.required_services}</td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[ref.status] || 'bg-slate-100 text-slate-800'}`}>{ref.status}</span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function AdminDashboard({ stats, recentCases, recentLogs }) {
    return (
        <>
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900">Admin Dashboard</h1>
                <p className="text-sm text-slate-500 mt-1">System-wide overview and monitoring.</p>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <KpiCard title="Total Cases" value={stats.totalCases} accent="border-l-indigo-500" icon="folder" />
                <KpiCard title="Total Referrals" value={stats.totalReferrals} accent="border-l-orange-500" icon="send" />
                <KpiCard title="Users" value={stats.totalUsers} accent="border-l-blue-500" icon="people" />
                <KpiCard title="Agencies" value={stats.totalAgencies} accent="border-l-green-500" icon="account_balance" />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                    <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                        <h3 className="text-base font-semibold text-slate-900">Recent Cases</h3>
                        <Link href={route('cases.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Created</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {recentCases?.length === 0 ? (
                                    <tr><td colSpan={3} className="px-6 py-4 text-center text-sm text-slate-500">No cases yet.</td></tr>
                                ) : (
                                    recentCases?.map((c) => (
                                        <tr key={c.id} className="hover:bg-slate-50">
                                            <td className="px-6 py-4 text-sm font-medium text-slate-900">{c.case_number}</td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${c.status === 'OPEN' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'}`}>{c.status}</span>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-slate-500">{new Date(c.created_at).toLocaleDateString()}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                    <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                        <h3 className="text-base font-semibold text-slate-900">Recent Activity</h3>
                        <Link href={route('audit-logs.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
                    </div>
                    <div className="divide-y divide-slate-200">
                        {recentLogs?.length === 0 ? (
                            <p className="px-6 py-4 text-sm text-slate-500">No recent activity.</p>
                        ) : (
                            recentLogs?.map((log) => (
                                <div key={log.id} className="px-6 py-3">
                                    <p className="text-sm text-slate-900">
                                        <span className="font-medium">{log.user?.name ?? 'System'}</span> {log.action} {log.module}
                                    </p>
                                    <p className="text-xs text-slate-500 mt-0.5">{new Date(log.timestamp).toLocaleString()}</p>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function CaseManagerDashboard({ stats, recentCases, recentReferrals, caseTrends, referralStatusDistribution, caseTypeDistribution }) {
    const referralStatusPie = useMemo(() => ({
        labels: referralStatusDistribution?.labels ?? [],
        datasets: [{ data: referralStatusDistribution?.data ?? [], backgroundColor: referralStatusDistribution?.colors ?? [], borderWidth: 0 }],
    }), [referralStatusDistribution]);

    const caseTrendsChart = useMemo(() => ({
        labels: caseTrends?.labels ?? [],
        datasets: [{
            label: 'Cases Created',
            data: caseTrends?.data ?? [],
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            fill: true,
            tension: 0.3,
        }],
    }), [caseTrends]);

    const caseTypePie = useMemo(() => ({
        labels: caseTypeDistribution?.labels ?? [],
        datasets: [{ data: caseTypeDistribution?.data ?? [], backgroundColor: caseTypeDistribution?.colors ?? [], borderWidth: 0 }],
    }), [caseTypeDistribution]);

    const trendsOptions = {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
    };

    return (
        <>
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900">Case Manager Dashboard</h1>
                <p className="text-sm text-slate-500 mt-1">Overview of cases, referrals, and agencies.</p>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <KpiCard title="Total Cases" value={stats.totalCases} accent="border-l-indigo-500" icon="folder" />
                <KpiCard title="Open Cases" value={stats.openCases} accent="border-l-green-500" icon="unfold_more" />
                <KpiCard title="Pending Referrals" value={stats.pendingReferrals} accent="border-l-yellow-500" icon="hourglass" />
                <KpiCard title="Active Agencies" value={stats.activeAgencies} accent="border-l-purple-500" icon="account_balance" />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <h3 className="text-sm font-semibold text-slate-900 mb-4">Referral Status</h3>
                    <div className="h-56">
                        <Doughnut data={referralStatusPie} options={pieOptions} />
                    </div>
                </div>
                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <h3 className="text-sm font-semibold text-slate-900 mb-4">Case Trends (12 months)</h3>
                    <div className="h-56">
                        <Line data={caseTrendsChart} options={trendsOptions} />
                    </div>
                </div>
                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <h3 className="text-sm font-semibold text-slate-900 mb-4">Case Types</h3>
                    <div className="h-56">
                        <Doughnut data={caseTypePie} options={pieOptions} />
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                    <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                        <h3 className="text-base font-semibold text-slate-900">Recent Cases</h3>
                        <Link href={route('cases.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Client</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Created</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {recentCases?.length === 0 ? (
                                    <tr><td colSpan={4} className="px-6 py-4 text-center text-sm text-slate-500">No cases yet.</td></tr>
                                ) : (
                                    recentCases?.map((c) => (
                                        <tr key={c.id} className="hover:bg-slate-50">
                                            <td className="px-6 py-4 text-sm font-medium text-slate-900">{c.case_number}</td>
                                            <td className="px-6 py-4 text-sm text-slate-500">{c.client ? `${c.client.first_name} ${c.client.last_name}` : 'N/A'}</td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${c.status === 'OPEN' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'}`}>{c.status}</span>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-slate-500">{new Date(c.created_at).toLocaleDateString()}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                    <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                        <h3 className="text-base font-semibold text-slate-900">Recent Referrals</h3>
                        <Link href={route('referrals.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Agency</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {recentReferrals?.length === 0 ? (
                                    <tr><td colSpan={3} className="px-6 py-4 text-center text-sm text-slate-500">No referrals yet.</td></tr>
                                ) : (
                                    recentReferrals?.map((ref) => (
                                        <tr key={ref.id} className="hover:bg-slate-50">
                                            <td className="px-6 py-4 text-sm font-medium text-slate-900">{ref.case_file?.case_number ?? 'N/A'}</td>
                                            <td className="px-6 py-4 text-sm text-slate-500">{ref.agency?.name ?? 'N/A'}</td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[ref.status] || 'bg-slate-100 text-slate-800'}`}>{ref.status}</span>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

export default function Dashboard(props) {
    const { role, recentCases, recentReferrals, recentLogs, caseTrends, referralStatusDistribution, caseTypeDistribution, ...stats } = props;

    if (role === 'AGENCY') {
        return (
            <AppLayout title="Dashboard">
                <Head title="Dashboard" />
                <AgencyDashboard stats={stats} recentReferrals={recentReferrals} />
            </AppLayout>
        );
    }

    if (role === 'ADMIN') {
        return (
            <AppLayout title="Dashboard">
                <Head title="Dashboard" />
                <AdminDashboard stats={stats} recentCases={recentCases} recentLogs={recentLogs} />
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />
            <CaseManagerDashboard
                stats={stats}
                recentCases={recentCases}
                recentReferrals={recentReferrals}
                caseTrends={caseTrends}
                referralStatusDistribution={referralStatusDistribution}
                caseTypeDistribution={caseTypeDistribution}
            />
        </AppLayout>
    );
}
