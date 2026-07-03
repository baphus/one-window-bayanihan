import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import StatusBadge from '@/Components/ui/StatusBadge';
import { Bar } from 'react-chartjs-2';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, BarElement,
    Title, Tooltip, Legend,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

export default function AnonymizedAnalytics({ analytics }) {
    // Safely default empty data if not perfectly formed
    const data = analytics || {};
    const totalCases = data.cases_by_status?.reduce((acc, curr) => acc + (curr.total || 0), 0) || 0;

    const barOptions = {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: '#1e293b', titleFont: { size: 12 }, bodyFont: { size: 12 } },
        },
        scales: {
            x: { grid: { color: '#e2e8f0' }, ticks: { font: { size: 11 } } },
            y: { grid: { display: false }, ticks: { font: { size: 11 } } },
        },
    };

    const categoryChartData = useMemo(() => ({
        labels: data.cases_by_category?.map(c => c.name) || [],
        datasets: [{
            label: 'Cases',
            data: data.cases_by_category?.map(c => c.count) || [],
            backgroundColor: data.cases_by_category?.map(c => c.color) || [],
            borderColor: data.cases_by_category?.map(c => c.color) || [],
            borderWidth: 1,
            borderRadius: 4,
        }],
    }), [data.cases_by_category]);

    return (
        <AppLayout>
            <Head title="Anonymized Analytics" />

            <div className="mx-auto max-w-7xl space-y-5 pb-4 px-4 sm:px-6 lg:px-8 pt-6">
                {/* Header Section */}
                <div>
                    <h1 className="text-xl font-extrabold text-slate-800 tracking-tight">Anonymized Analytics</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        All data is anonymized and aggregated. No personally identifiable information is displayed.
                    </p>
                    {data.generated_at && (
                        <p className="mt-1 text-xs text-slate-400">
                            Generated at: {new Date(data.generated_at).toLocaleString()}
                        </p>
                    )}
                </div>

                {/* Summary Header Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div className="border border-slate-300 bg-white p-4">
                        <p className="text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Total Cases</p>
                        <p className="mt-1 text-2xl font-bold text-slate-800">{totalCases}</p>
                    </div>
                    <div className="border border-slate-300 bg-white p-4">
                        <p className="text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Total Referrals</p>
                        <p className="mt-1 text-2xl font-bold text-slate-800">{data.referral_stats?.total || 0}</p>
                    </div>
                    <div className="border border-slate-300 bg-white p-4">
                        <p className="text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Total Clients</p>
                        <p className="mt-1 text-2xl font-bold text-slate-800">{data.total_clients || 0}</p>
                    </div>
                </div>

                {/* Tables Section */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {/* Cases by Status Table */}
                    <div className="border border-slate-300 bg-white p-4">
                        <h2 className="mb-3 text-sm font-bold text-slate-800">Cases by Status</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="border-b border-slate-200">
                                        <th className="py-2 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Status</th>
                                        <th className="py-2 text-right text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {data.cases_by_status?.length > 0 ? (
                                        data.cases_by_status.map((item, idx) => (
                                            <tr key={idx}>
                                                <td className="py-2 text-[12px] text-slate-700">
                                                    <StatusBadge status={item.status} />
                                                </td>
                                                <td className="py-2 text-right text-[12px] font-semibold text-slate-700">
                                                    {item.total}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="2" className="py-4 text-center text-[11px] text-slate-400">No data available</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Cases by Service Table */}
                    <div className="border border-slate-300 bg-white p-4">
                        <h2 className="mb-3 text-sm font-bold text-slate-800">Cases by Service</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="border-b border-slate-200">
                                        <th className="py-2 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Service</th>
                                        <th className="py-2 text-right text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {data.cases_by_service?.length > 0 ? (
                                        data.cases_by_service.map((item, idx) => (
                                            <tr key={idx}>
                                                <td className="py-2 text-[12px] text-slate-700">{item.service}</td>
                                                <td className="py-2 text-right text-[12px] font-semibold text-slate-700">
                                                    {item.total}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="2" className="py-4 text-center text-[11px] text-slate-400">No data available</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {/* Over Time and Resolution Time Section */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {/* Cases Over Time Table */}
                    <div className="border border-slate-300 bg-white p-4">
                        <h2 className="mb-3 text-sm font-bold text-slate-800">Cases Over Time</h2>
                        <div className="overflow-y-auto max-h-64 pr-2">
                            <table className="w-full border-collapse text-left">
                                <thead>
                                    <tr className="border-b border-slate-200">
                                        <th className="py-2 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Month</th>
                                        <th className="py-2 text-right text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Total Cases</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {data.cases_over_time?.length > 0 ? (
                                        data.cases_over_time.map((item, idx) => (
                                            <tr key={idx}>
                                                <td className="py-2 text-[12px] text-slate-700">{item.month}</td>
                                                <td className="py-2 text-right text-[12px] font-semibold text-slate-700">
                                                    {item.total}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="2" className="py-4 text-center text-[11px] text-slate-400">No data available</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Resolution & Referral Stats */}
                    <div className="flex flex-col space-y-5">
                        <div className="border border-slate-300 bg-white p-4 flex-1">
                            <h2 className="mb-3 text-sm font-bold text-slate-800">Average Resolution Time</h2>
                            <div className="mt-4">
                                <p className="text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Average Days to Resolve</p>
                                <div className="mt-1 flex items-baseline gap-2">
                                    <p className="text-3xl font-extrabold text-slate-800">
                                        {data.average_resolution_time?.average_days != null 
                                            ? Number(data.average_resolution_time.average_days).toFixed(1) 
                                            : 'N/A'}
                                    </p>
                                    <p className="text-sm font-medium text-slate-500">days</p>
                                </div>
                                <p className="mt-2 text-[11px] text-slate-400">
                                    Based on {data.average_resolution_time?.resolved_cases || 0} resolved cases.
                                </p>
                            </div>
                        </div>

                        <div className="border border-slate-300 bg-white p-4 flex-1">
                            <h2 className="mb-3 text-sm font-bold text-slate-800">Referral Stats</h2>
                            <div className="grid grid-cols-2 gap-4 mt-2">
                                <div>
                                    <p className="text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Total Referrals</p>
                                    <p className="mt-1 text-xl font-bold text-slate-800">{data.referral_stats?.total || 0}</p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">Avg Per Case</p>
                                    <p className="mt-1 text-xl font-bold text-slate-800">
                                        {data.referral_stats?.avg_per_case != null 
                                            ? Number(data.referral_stats.avg_per_case).toFixed(1) 
                                            : '0'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Cases by Category Chart */}
                <div className="border border-slate-300 bg-white p-4">
                    <h2 className="mb-3 text-sm font-bold text-slate-800">Cases by Category</h2>
                    {data.cases_by_category?.length > 0 ? (
                        <div className="h-56">
                            <Bar data={categoryChartData} options={barOptions} />
                        </div>
                    ) : (
                        <p className="py-8 text-center text-[13px] text-slate-400">No case category data available.</p>
                    )}
                </div>

            </div>
        </AppLayout>
    );
}
