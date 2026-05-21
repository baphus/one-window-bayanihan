import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { Doughnut, Bar } from 'react-chartjs-2';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, BarElement,
    ArcElement, Title, Tooltip, Legend,
} from 'chart.js';
import { FolderCheck, Users, ArrowRightLeft, Plus, Send, Eye, ChevronRight } from 'lucide-react';
import KpiCard from '@/Components/ui/KpiCard';
import RecentTable from '@/Components/ui/RecentTable';
import NotificationBell from '@/Components/ui/NotificationBell';

ChartJS.register(
    CategoryScale, LinearScale, BarElement,
    ArcElement, Title, Tooltip, Legend,
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

function formatDisplayDate(dateStr) {
  if (!dateStr) return 'N/A'
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
}

function formatCaseAge(timestamp) {
  const parsed = new Date(timestamp)
  if (Number.isNaN(parsed.getTime())) return 'N/A'
  const ageInMs = Math.max(0, Date.now() - parsed.getTime())
  const oneDayInMs = 24 * 60 * 60 * 1000
  const ageInDays = Math.floor(ageInMs / oneDayInMs)
  if (ageInDays > 0) return `${ageInDays} day${ageInDays === 1 ? '' : 's'}`
  const ageInHours = Math.floor(ageInMs / (60 * 60 * 1000))
  if (ageInHours > 0) return `${ageInHours} hr${ageInHours === 1 ? '' : 's'}`
  const ageInMinutes = Math.floor(ageInMs / (60 * 1000))
  return `${Math.max(1, ageInMinutes)} min`
}

function ActivityItem({ title, desc, time, logoSrc }) {
  return (
    <div className="relative">
      <span className="absolute -left-[25px] top-0 h-4 w-4 overflow-hidden rounded-full border border-white bg-white shadow-sm">
        <img src={logoSrc} alt="Activity source" className="h-full w-full object-contain p-[1px]" />
      </span>
      <div className="space-y-0.5">
        <p className="text-xs font-bold text-slate-900 font-body">{title}</p>
        <p className="text-[11px] text-slate-500 font-body leading-relaxed">{desc}</p>
        <p className="text-[9px] font-bold uppercase tracking-widest text-blue-800">{time}</p>
      </div>
    </div>
  )
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
  },
  cutout: '50%',
}

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
    x: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
}

function CaseManagerDashboard({
  stats,
  allCases = [],
  allReferrals = [],
  casesByProvince = [],
  agencyBreakdown = [],
  casesOverTime = [],
  recentActivity = [],
  dashboardNotifications = [],
}) {
  const sortedCases = useMemo(
    () => [...allCases].sort((a, b) => new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime()),
    [allCases],
  )

  const latestReferralByCaseId = useMemo(() => {
    const acc = {}
    allReferrals.forEach((ref) => {
      const existing = acc[ref.caseId]
      if (!existing || new Date(ref.updatedAt).getTime() > new Date(existing.updatedAt).getTime()) {
        acc[ref.caseId] = ref
      }
    })
    return acc
  }, [allReferrals])

  const recentCaseRows = sortedCases.slice(0, 5).map((item) => ({
    rowId: item.id,
    caseNo: item.caseNo,
    clientName: item.clientName,
    clientType: item.clientType,
    createdOn: formatDisplayDate(item.createdAt),
    caseAge: formatCaseAge(item.createdAt),
    referredTo: latestReferralByCaseId[item.id]?.agencyName ?? '—',
  }))

  const openCount = allCases.filter((c) => c.status === 'OPEN').length
  const closedCount = allCases.filter((c) => c.status === 'CLOSED').length
  const totalReferrals = allReferrals.length
  const completedReferralsCount = allReferrals.filter((r) => r.status === 'COMPLETED').length
  const pendingCount = allReferrals.filter((r) => r.status === 'PENDING').length
  const closureReadyCount = completedReferralsCount
  const averageReferralCompletionRate = totalReferrals > 0 ? Math.round((completedReferralsCount / totalReferrals) * 100) : 0

  const referralStatusStats = useMemo(() => {
    const total = totalReferrals || 1
    const statuses = ['PENDING', 'PROCESSING', 'COMPLETED', 'REJECTED']
    const colors = ['#f59e0b', '#3b82f6', '#10b981', '#f43f5e']
    const counts = statuses.map((s) => allReferrals.filter((r) => r.status === s).length)
    return statuses.map((s, i) => ({ label: s, count: counts[i], hex: colors[i], percent: Math.round((counts[i] / total) * 100) })).filter((s) => s.count > 0)
  }, [allReferrals, totalReferrals])

  const casesStatusStats = useMemo(() => {
    const total = allCases.length || 1
    return [
      { label: 'Open', count: openCount, hex: '#1e3a8a', percent: Math.round((openCount / total) * 100) },
      { label: 'Closed', count: closedCount, hex: '#cbd5e1', percent: Math.round((closedCount / total) * 100) },
    ].filter((s) => s.count > 0)
  }, [allCases, openCount, closedCount])

  const casesByProvinceStats = useMemo(() => {
    const total = allCases.length || 1
    const colors = ['#0f766e', '#ea580c', '#1e3a8a', '#6d28d9', '#be123c', '#4338ca', '#0e7490', '#a21caf']
    return casesByProvince.map((item, i) => ({
      label: item.province,
      count: item.count,
      hex: colors[i % colors.length],
      percent: Math.round((item.count / total) * 100),
    }))
  }, [casesByProvince, allCases])

  const agencyStats = useMemo(() => {
    const total = agencyBreakdown.reduce((s, a) => s + a.count, 0) || 1
    const colors = ['#1e3a8a', '#0f766e', '#ea580c', '#6d28d9', '#be123c', '#4338ca', '#0e7490', '#a21caf']
    return agencyBreakdown.map((item, i) => ({
      label: item.agencyName,
      count: item.count,
      hex: colors[i % colors.length],
      percent: Math.round((item.count / total) * 100),
    }))
  }, [agencyBreakdown])

  const casesOverTimeMax = casesOverTime.reduce((acc, item) => Math.max(acc, item.count), 1)

  const casesOverTimeChart = useMemo(() => ({
    labels: casesOverTime.map((m) => m.label),
    datasets: [{
      label: 'Cases',
      data: casesOverTime.map((m) => m.count),
      backgroundColor: '#1e3a8acc',
      borderColor: '#1e3a8a',
      borderWidth: 1,
      borderRadius: 2,
    }],
  }), [casesOverTime])

  const referralPieChart = useMemo(() => ({
    labels: referralStatusStats.map((s) => s.label),
    datasets: [{ data: referralStatusStats.map((s) => s.count), backgroundColor: referralStatusStats.map((s) => s.hex), borderWidth: 0 }],
  }), [referralStatusStats])

  const casesStatusPieChart = useMemo(() => ({
    labels: casesStatusStats.map((s) => s.label),
    datasets: [{ data: casesStatusStats.map((s) => s.count), backgroundColor: casesStatusStats.map((s) => s.hex), borderWidth: 0 }],
  }), [casesStatusStats])

  const provincePieChart = useMemo(() => ({
    labels: casesByProvinceStats.map((s) => s.label),
    datasets: [{ data: casesByProvinceStats.map((s) => s.count), backgroundColor: casesByProvinceStats.map((s) => s.hex), borderWidth: 0 }],
  }), [casesByProvinceStats])

  const agencyPieChart = useMemo(() => ({
    labels: agencyStats.map((s) => s.label),
    datasets: [{ data: agencyStats.map((s) => s.count), backgroundColor: agencyStats.map((s) => s.hex), borderWidth: 0 }],
  }), [agencyStats])

  const todayLabel = new Intl.DateTimeFormat('en-US', {
    weekday: 'long', month: 'long', day: '2-digit', year: 'numeric',
  }).format(new Date())

  const recentCasesColumns = [
    { key: 'caseNo', title: 'TRACKING ID', render: (row) => <span className="text-xs text-slate-900 font-body">{row.caseNo}</span> },
    { key: 'clientName', title: 'CLIENT NAME', render: (row) => <span className="text-xs text-slate-900 font-body">{row.clientName}</span> },
    { key: 'clientType', title: 'CLIENT TYPE', render: (row) => <span className="text-xs text-slate-900 font-body">{row.clientType}</span> },
    { key: 'createdOn', title: 'CREATED ON', render: (row) => <span className="text-xs text-slate-900 font-body">{row.createdOn}</span> },
    { key: 'caseAge', title: 'CASE AGE', render: (row) => <span className="text-xs text-slate-900 font-body">{row.caseAge}</span> },
    { key: 'referredTo', title: 'REFERRED TO', render: (row) => <span className="text-xs text-slate-900 font-body">{row.referredTo}</span> },
  ]

  return (
    <div className="max-w-7xl mx-auto pb-4">
      <header className="flex flex-col md:flex-row md:items-end justify-between gap-2 mb-4">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight leading-tight text-blue-900 flex items-center gap-2 mb-1">
            Welcome back! <span role="img" aria-label="wave">👋</span>
          </h1>
          <p className="text-xs text-slate-500 font-body mt-0 flex items-center gap-2">
            Today is {todayLabel}
          </p>
        </div>
        <div className="self-start md:self-auto">
          <NotificationBell notifications={dashboardNotifications} />
        </div>
      </header>

      <section className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
        <KpiCard
          title="TOTAL CASES"
          value={String(openCount)}
          trend="OPEN CASES"
          description={`Out of ${stats.totalCases} total managed cases`}
          icon={<FolderCheck className="w-5 h-5 text-blue-800 opacity-50" />}
        />
        <KpiCard
          title="TOTAL CLIENTS"
          value={String(stats.uniqueClientCount ?? 0)}
          trend={`${stats.ofwCount ?? 0} OFW • ${stats.nokCount ?? 0} NOK`}
          description="Overseas Filipino Workers / Next of Kin"
          icon={<Users className="w-5 h-5 text-blue-800 opacity-50" />}
        />
        <KpiCard
          title="PENDING REFERRALS"
          value={String(pendingCount)}
          description="Awaiting agency confirmation"
          icon={<ArrowRightLeft className="w-5 h-5 text-blue-800 opacity-50" />}
        />
        <KpiCard
          title="READY FOR CLOSURE"
          value={String(closureReadyCount)}
          trend="Referrals marked as Completed"
          description="Milestones completed"
          icon={<FolderCheck className="w-5 h-5 text-blue-800 opacity-50" />}
        />
        <KpiCard
          title="AVG REFERRAL COMPLETION RATE"
          value={`${averageReferralCompletionRate}%`}
          trend={`${completedReferralsCount} completed referrals`}
          description="Completed out of total referrals"
          icon={<FolderCheck className="w-5 h-5 text-blue-800 opacity-50" />}
        />
        <KpiCard
          title="AVG DAYS TO CASE CLOSURE"
          value={Number(stats.averageCaseDaysToClose ?? 0).toFixed(1)}
          trend={`${closedCount} closed cases`}
          description="Average days from case creation to closure"
          icon={<FolderCheck className="w-5 h-5 text-blue-800 opacity-50" />}
        />
      </section>

      <div className="grid grid-cols-12 gap-4">
        <div className="col-span-12 lg:col-span-8 space-y-4">
          <RecentTable
            title="Recent Cases"
            data={recentCaseRows}
            columns={recentCasesColumns}
            keyExtractor={(row) => row.rowId}
            onViewAll={() => router.visit('/cases')}
          />

          <h2 className="text-[13px] font-bold font-headline text-slate-500 mb-3 uppercase tracking-wider">Cases Breakdown</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
              <h3 className="text-[15px] font-bold font-headline text-blue-900 mb-4">Cases by Status</h3>
              <div className="relative flex justify-center items-center py-2">
                <div className="w-24 h-24 shrink-0">
                  <Doughnut data={casesStatusPieChart} options={doughnutOptions} />
                </div>
                <div className="ml-6 space-y-1.5 flex-1">
                  {casesStatusStats.map((stat) => (
                    <div key={stat.label} className="flex items-center justify-between">
                      <div className="flex items-center gap-1.5">
                        <span className={`w-2 h-2 rounded-full shrink-0 ${stat.label === 'Open' ? 'bg-blue-900' : 'bg-slate-300'}`} />
                        <span className="text-[11px] font-medium text-slate-800 font-label truncate">{stat.label}</span>
                      </div>
                      <span className="text-[11px] font-bold text-slate-500 ml-2">{stat.percent}%</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
              <h3 className="text-[15px] font-bold font-headline text-blue-900 mb-4">Cases by Province</h3>
              <div className="relative flex justify-center items-center py-2">
                <div className="w-24 h-24 shrink-0">
                  <Doughnut data={provincePieChart} options={doughnutOptions} />
                </div>
                <div className="ml-6 space-y-1.5 flex-1">
                  {casesByProvinceStats.map((stat) => (
                    <div key={stat.label} className="flex items-center justify-between">
                      <div className="flex items-center gap-1.5">
                        <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: stat.hex }} />
                        <span className="text-[11px] font-medium text-slate-800 font-label truncate">{stat.label}</span>
                      </div>
                      <span className="text-[11px] font-bold text-slate-500 ml-2">{stat.percent}%</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>

          <section className="bg-white p-4 rounded-xl shadow-sm border border-slate-200 mb-6">
            <h3 className="text-[15px] font-bold font-headline text-blue-900 mb-3">Cases Over Time</h3>
            <div className="h-36">
              <Bar data={casesOverTimeChart} options={barOptions} />
            </div>
          </section>

          <h2 className="text-[13px] font-bold font-headline text-slate-500 mb-3 uppercase tracking-wider">Referrals Breakdown</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
              <h3 className="text-[15px] font-bold font-headline text-blue-900 mb-4">Referrals by Status</h3>
              <div className="relative flex justify-center items-center py-2">
                <div className="w-24 h-24 shrink-0">
                  <Doughnut data={referralPieChart} options={doughnutOptions} />
                </div>
                <div className="ml-6 space-y-1.5 flex-1">
                  {referralStatusStats.map((stat) => (
                    <div key={stat.label} className="flex items-center justify-between">
                      <div className="flex items-center gap-1.5">
                        <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: stat.hex }} />
                        <span className="text-[11px] font-medium text-slate-800 font-label truncate">{stat.label}</span>
                      </div>
                      <span className="text-[11px] font-bold text-slate-500 ml-2">{stat.percent}%</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
              <h3 className="text-[15px] font-bold font-headline text-blue-900 mb-4">Referrals by Agency</h3>
              <div className="relative flex justify-center items-center py-2">
                <div className="w-24 h-24 shrink-0">
                  <Doughnut data={agencyPieChart} options={doughnutOptions} />
                </div>
                <div className="ml-6 space-y-1.5 flex-1 h-24 overflow-y-auto pr-1">
                  {agencyStats.map((stat) => (
                    <div key={stat.label} className="flex items-center justify-between">
                      <div className="flex items-center gap-1.5">
                        <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: stat.hex }} />
                        <span className="text-[11px] font-medium text-slate-800 font-label truncate max-w-[120px]">{stat.label}</span>
                      </div>
                      <span className="text-[11px] font-bold text-slate-500 ml-2">{stat.percent}%</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>

          <section className="space-y-2">
            <div className="flex items-center justify-between">
              <h3 className="text-[15px] font-bold font-headline text-blue-900">Client Breakdown: <span className="text-slate-900 text-xs">Current referral load</span></h3>
            </div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
              <div className="bg-white p-3 rounded-lg border border-slate-200 hover:border-blue-900/30 transition-all cursor-default group shadow-sm flex flex-col">
                <p className="text-[9px] font-bold uppercase tracking-widest text-slate-500 mb-0.5 group-hover:text-blue-900">OFW CLIENTS</p>
                <p className="text-xl font-black text-slate-900">{stats.ofwCount ?? 0}</p>
                <p className="text-[10px] font-medium text-teal-700 font-label mt-auto">Current records</p>
              </div>
              <div className="bg-white p-3 rounded-lg border border-slate-200 hover:border-blue-900/30 transition-all cursor-default group shadow-sm flex flex-col">
                <p className="text-[9px] font-bold uppercase tracking-widest text-slate-500 mb-0.5 group-hover:text-blue-900">NEXT OF KIN</p>
                <p className="text-xl font-black text-slate-900">{stats.nokCount ?? 0}</p>
                <p className="text-[10px] font-medium text-teal-700 font-label mt-auto">Current records</p>
              </div>
              <div className="bg-white p-3 rounded-lg border border-slate-200 hover:border-blue-900/30 transition-all cursor-default group shadow-sm flex flex-col">
                <p className="text-[9px] font-bold uppercase tracking-widest text-slate-500 mb-0.5 group-hover:text-blue-900">PROCESSING</p>
                <p className="text-xl font-black text-slate-900">{stats.processingReferrals ?? 0}</p>
                <p className="text-[10px] font-medium text-teal-700 font-label mt-auto">In progress</p>
              </div>
              <div className="bg-white p-3 rounded-lg border border-slate-200 hover:border-blue-900/30 transition-all cursor-default group shadow-sm flex flex-col">
                <p className="text-[9px] font-bold uppercase tracking-widest text-slate-500 mb-0.5 group-hover:text-blue-900">REJECTED</p>
                <p className="text-xl font-black text-slate-900">{stats.rejectedReferrals ?? 0}</p>
                <p className="text-[10px] font-medium text-teal-700 font-label mt-auto">Requires follow-up</p>
              </div>
            </div>
          </section>
        </div>

        <div className="col-span-12 lg:col-span-4 space-y-4">
          <section className="space-y-2">
            <button
              onClick={() => router.visit('/cases/create')}
              className="w-full py-3 px-4 bg-orange-500 text-white rounded-lg flex items-center justify-between shadow-sm shadow-orange-500/20 hover:scale-[1.02] active:scale-[0.98] transition-all"
            >
              <span className="flex items-center gap-2 text-[12px] font-bold font-label">
                <Plus className="w-4 h-4" /> Create New Case
              </span>
              <ChevronRight className="w-4 h-4" />
            </button>
            <button
              onClick={() => router.visit('/referrals')}
              className="w-full py-3 px-4 bg-white text-blue-900 border border-slate-200 rounded-lg flex items-center gap-2 hover:bg-slate-50 transition-all shadow-sm"
            >
              <Send className="w-4 h-4 text-blue-900 opacity-80" />
              <span className="text-[12px] font-bold font-label">Create Referral</span>
            </button>
            <button
              onClick={() => router.visit('/referrals')}
              className="w-full py-3 px-4 bg-white text-blue-900 border border-slate-200 rounded-lg flex items-center gap-2 hover:bg-slate-50 transition-all shadow-sm"
            >
              <Eye className="w-4 h-4 text-blue-900 opacity-80" />
              <span className="text-[12px] font-bold font-label">View Referrals</span>
            </button>
          </section>

          <section className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
            <div className="px-4 py-3 border-b border-slate-100">
              <h3 className="text-[10px] font-bold uppercase tracking-[0.15em] text-blue-900">Recent Activity</h3>
            </div>
            <div className="p-4">
              <div className="relative pl-4 border-l-2 border-slate-100 space-y-6">
                {recentActivity.length === 0 ? (
                  <p className="text-xs text-slate-400 py-2">No recent activity.</p>
                ) : (
                  recentActivity.slice(0, 4).map((activity) => (
                    <ActivityItem
                      key={activity.id}
                      title={activity.title}
                      desc={activity.desc}
                      time={activity.time?.toUpperCase() ?? ''}
                      logoSrc={activity.logoSrc ?? '/logo.png'}
                    />
                  ))
                )}
              </div>
              <button
                onClick={() => router.visit('/audit-logs')}
                className="w-full mt-4 text-[11px] font-bold font-label text-blue-900 hover:text-blue-700 transition-colors"
              >
                VIEW ALL ACTIVITIES
              </button>
            </div>
          </section>
        </div>
      </div>
    </div>
  )
}

export default function Dashboard(props) {
    const {
        role, recentCases, recentReferrals, recentLogs,
        allCases, allReferrals, casesByProvince, agencyBreakdown,
        casesOverTime, recentActivity, dashboardNotifications,
        ...stats
    } = props;

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
                allCases={allCases ?? []}
                allReferrals={allReferrals ?? []}
                casesByProvince={casesByProvince ?? []}
                agencyBreakdown={agencyBreakdown ?? []}
                casesOverTime={casesOverTime ?? []}
                recentActivity={recentActivity ?? []}
                dashboardNotifications={dashboardNotifications ?? []}
            />
        </AppLayout>
    );
}
