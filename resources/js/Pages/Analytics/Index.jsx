import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Bar, Doughnut, Line } from 'react-chartjs-2';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

export default function AnalyticsIndex({ overview, caseTrends, referralStatusDistribution, agencyWorkload, caseTypeDistribution }) {
    const caseTrendsChart = {
        labels: caseTrends.labels,
        datasets: [
            {
                label: 'Cases Created',
                data: caseTrends.data,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.3,
            },
        ],
    };

    const statusChart = {
        labels: referralStatusDistribution.labels,
        datasets: [
            {
                data: referralStatusDistribution.data,
                backgroundColor: referralStatusDistribution.colors,
                borderWidth: 0,
            },
        ],
    };

    const workloadChart = {
        labels: agencyWorkload.labels,
        datasets: [
            {
                label: 'Referrals',
                data: agencyWorkload.data,
                backgroundColor: '#818cf8',
                borderRadius: 4,
            },
        ],
    };

    const caseTypeChart = {
        labels: caseTypeDistribution.labels,
        datasets: [
            {
                data: caseTypeDistribution.data,
                backgroundColor: ['#6366f1', '#a5b4fc'],
                borderWidth: 0,
            },
        ],
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Analytics & Reporting
                </h2>
            }
        >
            <Head title="Analytics" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <p className="text-sm font-medium text-gray-500">Total Cases</p>
                            <p className="mt-2 text-3xl font-bold text-gray-900">{overview.totalCases}</p>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <p className="text-sm font-medium text-gray-500">Open Cases</p>
                            <p className="mt-2 text-3xl font-bold text-green-600">{overview.openCases}</p>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <p className="text-sm font-medium text-gray-500">Total Referrals</p>
                            <p className="mt-2 text-3xl font-bold text-gray-900">{overview.totalReferrals}</p>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <p className="text-sm font-medium text-gray-500">Active Agencies</p>
                            <p className="mt-2 text-3xl font-bold text-gray-900">{overview.activeAgencies}</p>
                        </div>
                    </div>

                    <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <h3 className="mb-4 text-base font-medium text-gray-900">Case Trends</h3>
                            <Line
                                data={caseTrendsChart}
                                options={{
                                    responsive: true,
                                    plugins: { legend: { display: false } },
                                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                                }}
                            />
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <h3 className="mb-4 text-base font-medium text-gray-900">Referral Status</h3>
                            <div className="mx-auto max-w-xs">
                                <Doughnut
                                    data={statusChart}
                                    options={{
                                        responsive: true,
                                        plugins: {
                                            legend: { position: 'bottom' },
                                        },
                                    }}
                                />
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <h3 className="mb-4 text-base font-medium text-gray-900">Agency Workload</h3>
                            <Bar
                                data={workloadChart}
                                options={{
                                    responsive: true,
                                    indexAxis: 'y',
                                    plugins: { legend: { display: false } },
                                    scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
                                }}
                            />
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <h3 className="mb-4 text-base font-medium text-gray-900">Case Types</h3>
                            <div className="mx-auto max-w-xs">
                                <Doughnut
                                    data={caseTypeChart}
                                    options={{
                                        responsive: true,
                                        plugins: {
                                            legend: { position: 'bottom' },
                                        },
                                    }}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
