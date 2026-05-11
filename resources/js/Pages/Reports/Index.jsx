import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
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
import KpiCard from '@/Components/ui/KpiCard';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';

ChartJS.register(
    CategoryScale, LinearScale, BarElement, LineElement,
    PointElement, ArcElement, Title, Tooltip, Legend, Filler,
);

const statColorMap = {
    OPEN: 'bg-green-100 text-green-800',
    CLOSED: 'bg-indigo-100 text-indigo-800',
    PENDING: 'bg-yellow-100 text-yellow-800',
    PROCESSING: 'bg-blue-100 text-blue-800',
    FOR_COMPLIANCE: 'bg-orange-100 text-orange-800',
    COMPLETED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
};

const defaultChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } } },
};

const pieOptions = { ...defaultChartOptions, cutout: '55%' };

export default function ReportsIndex({
    kpis, caseStatusDistribution, casesOverTime,
    genderDistribution, clientTypeDistribution, ageGroupDistribution,
    previousOccupations, lastEmploymentCountries,
    topOccupation, topCountry,
    referralStatusDistribution, referralAgencyDistribution,
    mostActiveAgency, avgReferralCompletion, mostRequestedService,
    managedCases, managedReferrals, managedClients,
}) {
    const [casesOverTimeView, setCasesOverTimeView] = useState('line');
    const [activeTab, setActiveTab] = useState('cases');

    const casesOverTimeChartData = useMemo(() => ({
        labels: casesOverTime.labels,
        datasets: casesOverTime.datasets.map(ds => ({
            ...ds,
            fill: casesOverTimeView === 'line',
            tension: 0.3,
        })),
    }), [casesOverTime, casesOverTimeView]);

    const caseStatusPie = useMemo(() => ({
        labels: caseStatusDistribution.labels,
        datasets: [{ data: caseStatusDistribution.data, backgroundColor: caseStatusDistribution.colors, borderWidth: 0 }],
    }), [caseStatusDistribution]);

    const genderPie = useMemo(() => ({
        labels: genderDistribution.labels,
        datasets: [{ data: genderDistribution.data, backgroundColor: genderDistribution.colors, borderWidth: 0 }],
    }), [genderDistribution]);

    const clientTypePie = useMemo(() => ({
        labels: clientTypeDistribution.labels,
        datasets: [{ data: clientTypeDistribution.data, backgroundColor: clientTypeDistribution.colors, borderWidth: 0 }],
    }), [clientTypeDistribution]);

    const agePie = useMemo(() => ({
        labels: ageGroupDistribution.labels,
        datasets: [{ data: ageGroupDistribution.data, backgroundColor: ageGroupDistribution.colors, borderWidth: 0 }],
    }), [ageGroupDistribution]);

    const referralStatusPie = useMemo(() => ({
        labels: referralStatusDistribution.labels,
        datasets: [{ data: referralStatusDistribution.data, backgroundColor: referralStatusDistribution.colors, borderWidth: 0 }],
    }), [referralStatusDistribution]);

    const referralAgencyBar = useMemo(() => ({
        labels: referralAgencyDistribution.labels,
        datasets: [{ label: 'Referrals', data: referralAgencyDistribution.data, backgroundColor: '#818cf8', borderRadius: 4 }],
    }), [referralAgencyDistribution]);

    const occupationBar = useMemo(() => ({
        labels: previousOccupations.labels,
        datasets: [{ label: 'Clients', data: previousOccupations.data, backgroundColor: '#6366f1', borderRadius: 4 }],
    }), [previousOccupations]);

    const countryBar = useMemo(() => ({
        labels: lastEmploymentCountries.labels,
        datasets: [{ label: 'Clients', data: lastEmploymentCountries.data, backgroundColor: '#a5b4fc', borderRadius: 4 }],
    }), [lastEmploymentCountries]);

    const barOptions = {
        ...defaultChartOptions,
        indexAxis: 'y',
        scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
    };

    const casesOverTimeOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
    };

    const caseColumns = useMemo(() => [
        { key: 'case_number', title: 'Case #', sortable: true },
        {
            key: 'client', title: 'Client', sortable: true,
            render: (row) => row.client ? `${row.client.first_name} ${row.client.last_name}` : 'N/A',
        },
        {
            key: 'status', title: 'Status', sortable: true,
            render: (row) => (
                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statColorMap[row.status] || 'bg-slate-100 text-slate-800'}`}>{row.status}</span>
            ),
        },
        {
            key: 'created_at', title: 'Created', sortable: true,
            render: (row) => new Date(row.created_at).toLocaleDateString(),
        },
        {
            key: 'id', title: 'Actions', sortable: false,
            render: (row) => (
                <Link href={route('cases.show', row.id)} className="text-indigo-600 hover:text-indigo-900 text-sm font-medium">View</Link>
            ),
        },
    ], []);

    const referralColumns = useMemo(() => [
        { key: 'case_file.case_number', title: 'Case #', sortable: true },
        {
            key: 'case_file.client', title: 'Client', sortable: false,
            render: (row) => row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A',
        },
        { key: 'agency.name', title: 'Agency', sortable: true },
        { key: 'required_services', title: 'Service', sortable: false },
        {
            key: 'status', title: 'Status', sortable: true,
            render: (row) => (
                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statColorMap[row.status] || 'bg-slate-100 text-slate-800'}`}>{row.status}</span>
            ),
        },
        {
            key: 'id', title: 'Actions', sortable: false,
            render: (row) => (
                <Link href={route('referrals.show', row.id)} className="text-indigo-600 hover:text-indigo-900 text-sm font-medium">View</Link>
            ),
        },
    ], []);

    const clientColumns = useMemo(() => [
        {
            key: 'name', title: 'Name', sortable: true,
            render: (row) => [row.first_name, row.middle_name, row.last_name, row.suffix].filter(Boolean).join(' '),
            sortAccessor: (row) => `${row.last_name}, ${row.first_name}`,
        },
        { key: 'sex', title: 'Sex', sortable: true },
        {
            key: 'date_of_birth', title: 'Date of Birth', sortable: true,
            render: (row) => row.date_of_birth ? new Date(row.date_of_birth).toLocaleDateString() : 'N/A',
        },
        {
            key: 'case_number', title: 'Case #', sortable: true,
            render: (row) => row.case_file?.case_number
                ? <Link href={route('cases.show', row.case_file.id)} className="text-indigo-600 hover:text-indigo-900">{row.case_file.case_number}</Link>
                : 'N/A',
        },
        {
            key: 'referrals', title: 'Referrals', sortable: false,
            render: (row) => row.case_file?.referrals?.length ?? 0,
        },
    ], []);

    function paginatorProps(paginator) {
        return {
            totalRecords: paginator.total,
            startIndex: paginator.from,
            endIndex: paginator.to,
            currentPage: paginator.current_page,
            totalPages: paginator.last_page,
            rowsPerPage: paginator.per_page,
            onPageChange: (page) => {
                const url = new URL(window.location);
                url.searchParams.set('page', page);
                window.location = url.toString();
            },
            onRowsPerPageChange: (n) => {
                const url = new URL(window.location);
                url.searchParams.set('per_page', n);
                url.searchParams.delete('page');
                window.location = url.toString();
            },
            hideControlBar: true,
            hidePagination: false,
        };
    }

    return (
        <AppLayout title="Reports">
            <Head title="Reports" />

            <div className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900">Reports Dashboard</h1>
                <p className="text-sm text-slate-500 mt-1">System-wide performance metrics, trends, and data tables.</p>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 mb-6">
                <KpiCard title="Total Cases" value={kpis.totalCases} accent="border-l-indigo-500" icon="folder" />
                <KpiCard title="Open Cases" value={kpis.openCases} accent="border-l-green-500" icon="unfold_more" />
                <KpiCard title="Closed Cases" value={kpis.closedCases} accent="border-l-blue-500" icon="check_circle" />
                <KpiCard title="Avg Days to Closure" value={kpis.avgDaysToClosure} accent="border-l-purple-500" icon="calendar_today" suffix="days" />
                <KpiCard title="Total Referrals" value={kpis.totalReferrals} accent="border-l-orange-500" icon="send" />
                <KpiCard title="Avg Referral Completion" value={avgReferralCompletion} accent="border-l-teal-500" icon="task_alt" suffix="days" />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-semibold text-slate-900">Cases By Status</h3>
                    </div>
                    <div className="h-56">
                        <Doughnut data={caseStatusPie} options={pieOptions} />
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-semibold text-slate-900">Gender Distribution</h3>
                    </div>
                    <div className="h-56">
                        <Doughnut data={genderPie} options={pieOptions} />
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-semibold text-slate-900">Client Type</h3>
                    </div>
                    <div className="h-56">
                        <Doughnut data={clientTypePie} options={pieOptions} />
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-semibold text-slate-900">Cases Over Time (12 Months)</h3>
                        <div className="flex gap-1">
                            <button onClick={() => setCasesOverTimeView('line')}
                                className={`px-2 py-1 text-xs rounded ${casesOverTimeView === 'line' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-100'}`}>Line</button>
                            <button onClick={() => setCasesOverTimeView('bar')}
                                className={`px-2 py-1 text-xs rounded ${casesOverTimeView === 'bar' ? 'bg-indigo-100 text-indigo-700' : 'text-slate-500 hover:bg-slate-100'}`}>Bar</button>
                        </div>
                    </div>
                    <div className="h-64">
                        {casesOverTimeView === 'line' ? (
                            <Line data={casesOverTimeChartData} options={casesOverTimeOptions} />
                        ) : (
                            <Bar data={casesOverTimeChartData} options={{ ...casesOverTimeOptions, borderRadius: 4 }} />
                        )}
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-semibold text-slate-900">Age Group Distribution</h3>
                    </div>
                    <div className="h-64">
                        <Doughnut data={agePie} options={pieOptions} />
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <h3 className="text-sm font-semibold text-slate-900 mb-4">Referral Status</h3>
                    <div className="h-56">
                        <Doughnut data={referralStatusPie} options={pieOptions} />
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <h3 className="text-sm font-semibold text-slate-900 mb-4">Referrals By Agency</h3>
                    <div className="h-56">
                        <Bar data={referralAgencyBar} options={{ ...barOptions, maintainAspectRatio: false }} />
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <h3 className="text-sm font-semibold text-slate-900 mb-4">Client Insights</h3>
                    <div className="space-y-4">
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                            <p className="text-xs font-medium text-slate-500">Top Occupation</p>
                            <p className="text-lg font-bold text-slate-900">{topOccupation.label}</p>
                            <p className="text-xs text-slate-500">{topOccupation.value} clients</p>
                        </div>
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                            <p className="text-xs font-medium text-slate-500">Top Employment Country</p>
                            <p className="text-lg font-bold text-slate-900">{topCountry.label}</p>
                            <p className="text-xs text-slate-500">{topCountry.value} clients</p>
                        </div>
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                            <p className="text-xs font-medium text-slate-500">Most Active Agency</p>
                            <p className="text-lg font-bold text-slate-900">{mostActiveAgency.name}</p>
                            <p className="text-xs text-slate-500">{mostActiveAgency.value} referrals</p>
                        </div>
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                            <p className="text-xs font-medium text-slate-500">Most Requested Service</p>
                            <p className="text-sm font-bold text-slate-900 break-words">{mostRequestedService.name}</p>
                            <p className="text-xs text-slate-500">{mostRequestedService.value} referrals</p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <h3 className="text-sm font-semibold text-slate-900 mb-4">Previous Occupations</h3>
                    <div className="h-64">
                        <Bar data={occupationBar} options={{ ...barOptions, maintainAspectRatio: false }} />
                    </div>
                </div>

                <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-5">
                    <h3 className="text-sm font-semibold text-slate-900 mb-4">Last Employment Countries</h3>
                    <div className="h-64">
                        <Bar data={countryBar} options={{ ...barOptions, maintainAspectRatio: false }} />
                    </div>
                </div>
            </div>

            <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                <div className="border-b border-slate-200 px-5 py-3">
                    <div className="flex gap-4">
                        {['cases', 'referrals', 'clients'].map((tab) => (
                            <button key={tab} onClick={() => setActiveTab(tab)}
                                className={`text-sm font-medium pb-1 border-b-2 transition-colors ${activeTab === tab ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}>
                                {tab === 'cases' ? 'Managed Cases' : tab === 'referrals' ? 'Managed Referrals' : 'Managed Clients'}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="p-5">
                    {activeTab === 'cases' && (
                        <UnifiedTable
                            columns={caseColumns}
                            data={managedCases.data}
                            keyExtractor={(row) => row.id}
                            {...paginatorProps(managedCases)}
                        />
                    )}
                    {activeTab === 'referrals' && (
                        <UnifiedTable
                            columns={referralColumns}
                            data={managedReferrals.data}
                            keyExtractor={(row) => row.id}
                            {...paginatorProps(managedReferrals)}
                        />
                    )}
                    {activeTab === 'clients' && (
                        <UnifiedTable
                            columns={clientColumns}
                            data={managedClients.data}
                            keyExtractor={(row) => row.id}
                            {...paginatorProps(managedClients)}
                        />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
