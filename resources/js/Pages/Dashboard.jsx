import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { Doughnut, Bar } from 'react-chartjs-2';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, BarElement,
    ArcElement, Title, Tooltip, Legend,
} from 'chart.js';
import { FolderCheck, Users, ArrowRightLeft, Plus, Send, Eye, ChevronRight, AlertTriangle, Clock, CheckCircle2, Loader2, XCircle, TrendingUp, TrendingDown, UserPlus, Pencil, Trash2, LogIn, LogOut } from 'lucide-react';
import KpiCard from '@/Components/ui/KpiCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import RecentTable from '@/Components/ui/RecentTable';
import NotificationBell from '@/Components/ui/NotificationBell';
import { formatDisplayDate, formatDisplayDateTime } from '@/lib/utils';

ChartJS.register(
    CategoryScale, LinearScale, BarElement,
    ArcElement, Title, Tooltip, Legend,
);

const pieOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } } }, cutout: '55%' };

function AgencyDashboard({ stats, recentReferrals, recentActivity, dashboardNotifications }) {
    return (
        <div className="max-w-7xl mx-auto pb-6">
            <header className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
                <div>
                    <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Agency Dashboard</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Overview of your agency's referrals and performance.</p>
                </div>
                <div className="flex items-center gap-3">
                    <NotificationBell notifications={dashboardNotifications ?? []} />
                </div>
            </header>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <KpiCard title="Total Referrals" value={stats.totalReferrals} accent="border-l-blue-900" icon="send" />
                <KpiCard title="Pending" value={stats.pendingReferrals} accent="border-l-yellow-500" icon="hourglass" />
                <KpiCard title="Processing" value={stats.processingReferrals} accent="border-l-blue-500" icon="sync" />
                <KpiCard title="Completed" value={stats.completedReferrals} accent="border-l-green-500" icon="check_circle" />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                <div className="lg:col-span-8">
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
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
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

                <div className="rounded-lg bg-white shadow-sm border border-slate-200">
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

const actionConfig = {
  CREATE: { icon: UserPlus, bg: 'bg-emerald-50', text: 'text-emerald-600', ring: 'ring-emerald-200', label: 'Created' },
  UPDATE: { icon: Pencil, bg: 'bg-blue-50', text: 'text-blue-600', ring: 'ring-blue-200', label: 'Updated' },
  DELETE: { icon: Trash2, bg: 'bg-rose-50', text: 'text-rose-600', ring: 'ring-rose-200', label: 'Removed' },
  VIEW: { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: 'Viewed' },
  LOGIN: { icon: LogIn, bg: 'bg-indigo-50', text: 'text-indigo-600', ring: 'ring-indigo-200', label: 'Signed In' },
  LOGOUT: { icon: LogOut, bg: 'bg-orange-50', text: 'text-orange-600', ring: 'ring-orange-200', label: 'Signed Out' },
}

function ActivityItem({ title, desc, time, logoSrc, actionType, actor, message, detail }) {
  const cfg = actionConfig[actionType] || { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: '' }
  const ActionIcon = cfg.icon
  const displayTitle = message ?? title
  const displayDesc = title === desc ? '' : (detail || desc)

  return (
    <div className="relative flex items-start gap-3">
      {actionType ? (
        <span className={`shrink-0 flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white ${cfg.bg} shadow-sm`}>
          <ActionIcon className={`w-3.5 h-3.5 ${cfg.text}`} />
        </span>
      ) : (
        <span className="shrink-0 absolute -left-[25px] top-0 h-4 w-4 overflow-hidden rounded-full border border-white bg-white shadow-sm">
          <img src={logoSrc} alt="Activity source" className="h-full w-full object-contain p-[1px]" />
        </span>
      )}
      <div className="space-y-0.5 min-w-0">
        <p className="text-xs font-bold text-slate-900 font-body leading-snug">{displayTitle}</p>
        {displayDesc && (
          <p className="text-[11px] text-slate-500 font-body leading-relaxed line-clamp-2">{displayDesc}</p>
        )}
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

function getCaseAgeInDays(timestamp) {
  const parsed = new Date(timestamp)
  if (Number.isNaN(parsed.getTime())) return 0
  return Math.floor(Math.max(0, Date.now() - parsed.getTime()) / (24 * 60 * 60 * 1000))
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

  const openCount = allCases.filter((c) => c.status === 'OPEN').length
  const closedCount = allCases.filter((c) => c.status === 'CLOSED').length
  const totalReferrals = allReferrals.length
  const completedReferralsCount = allReferrals.filter((r) => r.status === 'COMPLETED').length
  const pendingCount = allReferrals.filter((r) => r.status === 'PENDING').length
  const processingCount = allReferrals.filter((r) => r.status === 'PROCESSING').length
  const rejectedCount = allReferrals.filter((r) => r.status === 'REJECTED').length
  const averageReferralCompletionRate = totalReferrals > 0 ? Math.round((completedReferralsCount / totalReferrals) * 100) : 0

  const attentionItems = useMemo(() => {
    const items = []

    allReferrals.forEach((ref) => {
      const daysOld = getCaseAgeInDays(ref.createdAt)
      if (ref.status === 'PENDING' && daysOld >= 5) {
        items.push({
          id: `pending-${ref.id}`,
          type: 'warning',
          title: 'Overdue Referral Follow-up',
          desc: `${ref.caseNo} · ${ref.agencyName} · ${daysOld} days pending`,
          action: () => router.visit(`/referrals`),
        })
      }
      if (ref.status === 'REJECTED') {
        items.push({
          id: `rejected-${ref.id}`,
          type: 'error',
          title: 'Referral Returned — Needs Re-assignment',
          desc: `${ref.caseNo} · ${ref.agencyName} · ${ref.service}`,
          action: () => router.visit(`/referrals`),
        })
      }
    })

    allCases.forEach((c) => {
      const caseReferrals = allReferrals.filter((r) => r.caseId === c.id)
      const daysOpen = getCaseAgeInDays(c.createdAt)
      if (c.status === 'OPEN' && caseReferrals.length === 0 && daysOpen >= 7) {
        items.push({
          id: `no-ref-${c.id}`,
          type: 'info',
          title: 'Case Requires Referral',
          desc: `${c.caseNo} · ${c.clientName} · Open for ${daysOpen} days without referral`,
          action: () => router.visit(`/cases/${c.id}`),
        })
      }
    })

    return items.slice(0, 4)
  }, [allCases, allReferrals])

  const recentCaseRows = sortedCases.slice(0, 6).map((item) => {
    const latestRef = latestReferralByCaseId[item.id]
    const ageDays = getCaseAgeInDays(item.createdAt)
    const ageColor = ageDays <= 3 ? 'text-emerald-600' : ageDays <= 10 ? 'text-amber-600' : 'text-rose-600'
    return {
      rowId: item.id,
      caseNo: item.caseNo,
      clientName: item.clientName,
      clientType: item.clientType === 'Overseas Filipino Worker' ? 'OFW' : 'NOK',
      age: formatCaseAge(item.createdAt),
      ageDays,
      ageColor,
      agencyName: latestRef?.agencyName ?? '—',
      status: item.status,
      referralStatus: latestRef?.status ?? null,
    }
  })

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
    ]
  }, [allCases, openCount, closedCount])

  const agencyChartData = useMemo(() => ({
    labels: agencyBreakdown.map((a) => a.agencyName),
    datasets: [{
      label: 'Referrals',
      data: agencyBreakdown.map((a) => a.count),
      backgroundColor: ['#1e3a8a', '#0f766e', '#ea580c', '#6d28d9', '#be123c'],
      borderRadius: 3,
      barThickness: 20,
    }],
  }), [agencyBreakdown])

  const provinceChartData = useMemo(() => {
    const sorted = [...casesByProvince].sort((a, b) => b.count - a.count)
    return {
      labels: sorted.map((p) => p.province),
      datasets: [{
        label: 'Cases',
        data: sorted.map((p) => p.count),
        backgroundColor: '#1e3a8a',
        borderRadius: 3,
        barThickness: 18,
      }],
    }
  }, [casesByProvince])

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

  const barOptionsHorizontal = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
      y: { ticks: { font: { size: 10 } }, grid: { display: false } },
    },
  }

  const todayLabel = new Intl.DateTimeFormat('en-US', {
    weekday: 'long', month: 'long', day: '2-digit', year: 'numeric',
  }).format(new Date())

  const activeCasesColumns = [
    {
      key: 'caseNo',
      title: 'CASE',
      render: (row) => (
        <button onClick={() => router.visit(`/cases/${row.rowId}`)} className="text-xs font-bold text-blue-900 hover:underline font-body">{row.caseNo}</button>
      ),
    },
    {
      key: 'clientName',
      title: 'CLIENT',
      render: (row) => (
        <div className="flex items-center gap-1.5">
          <span className="text-xs text-slate-900 font-body">{row.clientName}</span>
          <span className="text-[9px] font-bold uppercase text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">{row.clientType}</span>
        </div>
      ),
    },
    {
      key: 'age',
      title: 'AGE',
      render: (row) => <span className={`text-xs font-bold font-body ${row.ageColor}`}>{row.age}</span>,
    },
    {
      key: 'agencyName',
      title: 'AGENCY',
      render: (row) => <span className="text-xs text-slate-600 font-body">{row.agencyName}</span>,
    },
    {
      key: 'status',
      title: 'STATUS',
      render: (row) => (
        <div className="flex items-center gap-1">
          <StatusBadge status={row.status} />
          {row.referralStatus && <StatusBadge status={row.referralStatus} />}
        </div>
      ),
    },
  ]

  return (
    <div className="max-w-7xl mx-auto pb-6">
      <header className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900 flex items-center gap-2">
            Good morning! <span role="img" aria-label="wave">👋</span>
          </h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">{todayLabel}</p>
        </div>
        <div className="flex items-center gap-3">
          <div className="relative hidden md:block">
            <input
              type="text"
              placeholder="Search cases, clients..."
              className="w-64 h-10 pl-9 pr-4 bg-slate-50 border border-slate-200 rounded-lg text-[13px] text-slate-600 placeholder-slate-400 outline-none focus:ring-2 focus:ring-blue-900/20 focus:border-blue-900/30 transition"
              onFocus={() => router.visit('/cases')}
            />
            <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <NotificationBell notifications={dashboardNotifications} />
        </div>
      </header>

      <section className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Active Cases</p>
            <span className="p-1.5 bg-blue-50 rounded-lg"><FolderCheck className="w-4 h-4 text-blue-900" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{openCount}</p>
          <div className="flex items-center gap-1.5 mt-1.5">
            <TrendingUp className="w-3 h-3 text-emerald-500" />
            <span className="text-[11px] font-bold text-emerald-600">+{sortedCases.filter((c) => getCaseAgeInDays(c.createdAt) <= 7 && c.status === 'OPEN').length} this week</span>
          </div>
          <p className="text-[10px] text-slate-400 mt-0.5">Out of {stats.totalCases} total cases</p>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Clients Served</p>
            <span className="p-1.5 bg-violet-50 rounded-lg"><Users className="w-4 h-4 text-violet-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{stats.uniqueClientCount ?? 0}</p>
          <div className="flex items-center gap-1.5 mt-1.5">
            <span className="text-[11px] font-medium text-slate-500">{stats.ofwCount ?? 0} OFW · {stats.nokCount ?? 0} NOK</span>
          </div>
          <p className="text-[10px] text-slate-400 mt-0.5">Overseas Filipino Workers / Next of Kin</p>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending Referrals</p>
            <span className="p-1.5 bg-amber-50 rounded-lg"><ArrowRightLeft className="w-4 h-4 text-amber-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{pendingCount}</p>
          <div className="flex items-center gap-1.5 mt-1.5">
            {pendingCount > 0 ? (
              <><AlertTriangle className="w-3 h-3 text-amber-500" /><span className="text-[11px] font-bold text-amber-600">Needs attention</span></>
            ) : (
              <><CheckCircle2 className="w-3 h-3 text-emerald-500" /><span className="text-[11px] font-bold text-emerald-600">All clear</span></>
            )}
          </div>
          <p className="text-[10px] text-slate-400 mt-0.5">{processingCount} processing · {rejectedCount} rejected</p>
        </div>

        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
          <div className="flex items-start justify-between mb-2">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Avg Resolution Time</p>
            <span className="p-1.5 bg-teal-50 rounded-lg"><Clock className="w-4 h-4 text-teal-600" /></span>
          </div>
          <p className="text-2xl font-black text-slate-900">{Number(stats.averageCaseDaysToClose ?? 0).toFixed(1)} <span className="text-sm font-bold text-slate-400">days</span></p>
          <div className="flex items-center gap-1.5 mt-1.5">
            <TrendingDown className="w-3 h-3 text-emerald-500" />
            <span className="text-[11px] font-bold text-emerald-600">{completedReferralsCount} completed referrals</span>
          </div>
          <p className="text-[10px] text-slate-400 mt-0.5">{averageReferralCompletionRate}% completion rate</p>
        </div>
      </section>

      {attentionItems.length > 0 && (
        <section className="mb-6">
          <div className="flex items-center gap-2 mb-3">
            <AlertTriangle className="w-4 h-4 text-amber-500" />
            <h2 className="text-[13px] font-bold font-headline text-slate-700">Attention Required</h2>
            <span className="text-[10px] font-bold text-white bg-amber-500 px-1.5 py-0.5 rounded-full">{attentionItems.length}</span>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            {attentionItems.map((item) => (
              <button
                key={item.id}
                onClick={item.action}
                className="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-amber-300/50 transition-all text-left group"
              >
                <div className="flex items-center gap-2 mb-1.5">
                  <span className={`w-1.5 h-1.5 rounded-full shrink-0 ${item.type === 'error' ? 'bg-rose-500' : item.type === 'warning' ? 'bg-amber-500' : 'bg-blue-500'}`} />
                  <span className="text-[11px] font-bold text-slate-700 group-hover:text-blue-900 transition-colors">{item.title}</span>
                </div>
                <p className="text-[11px] text-slate-500 leading-relaxed pl-3.5">{item.desc}</p>
              </button>
            ))}
          </div>
        </section>
      )}

      <div className="grid grid-cols-12 gap-4">
        <div className="col-span-12 lg:col-span-8 space-y-4">
          <RecentTable
            title="Active Cases"
            data={recentCaseRows}
            columns={activeCasesColumns}
            keyExtractor={(row) => row.rowId}
            onViewAll={() => router.visit('/cases')}
          />

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
              <h3 className="text-[13px] font-bold font-headline text-slate-700 mb-3">Cases by Status</h3>
              <div className="flex items-center gap-4">
                <div className="w-20 h-20 shrink-0">
                  <Doughnut data={casesStatusPieChart} options={doughnutOptions} />
                </div>
                <div className="space-y-2 flex-1">
                  {casesStatusStats.filter((s) => s.count > 0).map((stat) => (
                    <div key={stat.label} className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <span className={`w-2 h-2 rounded-full shrink-0 ${stat.label === 'Open' ? 'bg-blue-900' : 'bg-slate-300'}`} />
                        <span className="text-[11px] font-medium text-slate-600">{stat.label}</span>
                      </div>
                      <span className="text-[11px] font-bold text-slate-800">{stat.count} ({stat.percent}%)</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
              <h3 className="text-[13px] font-bold font-headline text-slate-700 mb-3">Referral Status</h3>
              <div className="flex items-center gap-4">
                <div className="w-20 h-20 shrink-0">
                  <Doughnut data={referralPieChart} options={doughnutOptions} />
                </div>
                <div className="space-y-2 flex-1">
                  {referralStatusStats.map((stat) => (
                    <div key={stat.label} className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <span className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: stat.hex }} />
                        <span className="text-[11px] font-medium text-slate-600">{stat.label}</span>
                      </div>
                      <span className="text-[11px] font-bold text-slate-800">{stat.count} ({stat.percent}%)</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
              <h3 className="text-[13px] font-bold font-headline text-slate-700 mb-3">Cases by Province</h3>
              <div className="h-44">
                <Bar data={provinceChartData} options={barOptionsHorizontal} />
              </div>
            </div>

            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
              <h3 className="text-[13px] font-bold font-headline text-slate-700 mb-3">Agency Referral Load</h3>
              <div className="h-44">
                <Bar data={agencyChartData} options={barOptionsHorizontal} />
              </div>
            </div>
          </div>

          <section className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <h3 className="text-[13px] font-bold font-headline text-slate-700 mb-3">Case Creation Trend</h3>
            <div className="h-32">
              <Bar data={casesOverTimeChart} options={barOptions} />
            </div>
          </section>
        </div>

        <div className="col-span-12 lg:col-span-4 space-y-3">
          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Quick Actions</h3>
            <div className="space-y-2">
              <button
                onClick={() => router.visit('/cases/create')}
                className="w-full py-2.5 px-3.5 bg-blue-900 text-white rounded-lg flex items-center justify-between hover:bg-blue-800 active:scale-[0.98] transition-all shadow-sm"
              >
                <span className="flex items-center gap-2 text-[12px] font-bold">
                  <Plus className="w-3.5 h-3.5" /> New Case
                </span>
                <ChevronRight className="w-3.5 h-3.5 opacity-60" />
              </button>
              <button
                onClick={() => router.visit('/referrals')}
                className="w-full py-2.5 px-3.5 bg-white text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between hover:bg-slate-50 transition-all"
              >
                <span className="flex items-center gap-2 text-[12px] font-bold">
                  <Send className="w-3.5 h-3.5 text-slate-400" /> New Referral
                </span>
                <ChevronRight className="w-3.5 h-3.5 text-slate-300" />
              </button>
              <button
                onClick={() => router.visit('/cases')}
                className="w-full py-2.5 px-3.5 bg-white text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between hover:bg-slate-50 transition-all"
              >
                <span className="flex items-center gap-2 text-[12px] font-bold">
                  <FolderCheck className="w-3.5 h-3.5 text-slate-400" /> All Cases
                </span>
                <ChevronRight className="w-3.5 h-3.5 text-slate-300" />
              </button>
              <button
                onClick={() => router.visit('/referrals')}
                className="w-full py-2.5 px-3.5 bg-white text-slate-700 border border-slate-200 rounded-lg flex items-center justify-between hover:bg-slate-50 transition-all"
              >
                <span className="flex items-center gap-2 text-[12px] font-bold">
                  <Eye className="w-3.5 h-3.5 text-slate-400" /> All Referrals
                </span>
                <ChevronRight className="w-3.5 h-3.5 text-slate-300" />
              </button>
            </div>
          </div>

          <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Client Snapshot</h3>
            <div className="grid grid-cols-2 gap-2">
              <div className="bg-slate-50 rounded-lg p-3">
                <p className="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-1">OFW</p>
                <p className="text-lg font-black text-slate-900">{stats.ofwCount ?? 0}</p>
              </div>
              <div className="bg-slate-50 rounded-lg p-3">
                <p className="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-1">NOK</p>
                <p className="text-lg font-black text-slate-900">{stats.nokCount ?? 0}</p>
              </div>
              <div className="bg-slate-50 rounded-lg p-3">
                <p className="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-1">Processing</p>
                <p className="text-lg font-black text-amber-600">{processingCount}</p>
              </div>
              <div className="bg-slate-50 rounded-lg p-3">
                <p className="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-1">Rejected</p>
                <p className="text-lg font-black text-rose-600">{rejectedCount}</p>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="px-4 py-3 border-b border-slate-100">
              <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Recent Activity</h3>
            </div>
            <div className="p-4">
              <div className="relative pl-4 border-l-2 border-slate-100 space-y-4">
                {recentActivity.length === 0 ? (
                  <p className="text-xs text-slate-400 py-2">No recent activity.</p>
                ) : (
                  recentActivity.slice(0, 5).map((activity) => (
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
                onClick={() => router.visit('/audit-logs')}
                className="w-full mt-3 text-[11px] font-bold font-label text-blue-900 hover:text-blue-700 transition-colors text-center"
              >
                VIEW ALL ACTIVITY
              </button>
            </div>
          </div>
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
                <AgencyDashboard
                    stats={stats}
                    recentReferrals={recentReferrals}
                    recentActivity={recentActivity}
                    dashboardNotifications={dashboardNotifications}
                />
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
