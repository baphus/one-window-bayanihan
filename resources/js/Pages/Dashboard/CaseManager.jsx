import { usePage } from '@inertiajs/react';
import StatusBadge from '@/Components/ui/StatusBadge';
import RecentTable from '@/Components/ui/RecentTable';
import { formatDisplayDate, formatCaseAge, getCaseAgeInDays, formatStatusLabel } from '@/lib/utils';
import { doughnutOptions, barOptions, barOptionsHorizontal } from '@/Components/Dashboard/chartConfig';
import ActivityItem from '@/Components/Dashboard/ActivityItem';
import DashboardBanner from '@/Components/DashboardBanner';

ChartJS.register(
    CategoryScale, LinearScale, BarElement,
    ArcElement, Title, Tooltip, Legend,
);

export default function CaseManagerDashboard({ dashboard }) {
    const {
        stats,
        allCases = [],
        allReferrals = [],
        casesByProvince = [],
        agencyBreakdown = [],
        casesByCategory = [],
        casesOverTime = [],
        recentActivity = [],
        dashboardNotifications = [],
    } = dashboard || {};

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
            trackerNumber: item.trackerNumber,
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

    const categoryChartData = useMemo(() => {
        if (casesByCategory.length === 0) return null
        return {
            labels: casesByCategory.map((c) => c.name),
            datasets: [{
                label: 'Cases',
                data: casesByCategory.map((c) => c.count),
                backgroundColor: casesByCategory.map((c) => c.color),
                borderRadius: 3,
                barThickness: 18,
            }],
        }
    }, [casesByCategory])

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
        labels: referralStatusStats.map((s) => formatStatusLabel(s.label)),
        datasets: [{ data: referralStatusStats.map((s) => s.count), backgroundColor: referralStatusStats.map((s) => s.hex), borderWidth: 0 }],
    }), [referralStatusStats])

    const casesStatusPieChart = useMemo(() => ({
        labels: casesStatusStats.map((s) => formatStatusLabel(s.label)),
        datasets: [{ data: casesStatusStats.map((s) => s.count), backgroundColor: casesStatusStats.map((s) => s.hex), borderWidth: 0 }],
    }), [casesStatusStats])

    const todayLabel = new Intl.DateTimeFormat('en-US', {
        weekday: 'long', month: 'long', day: '2-digit', year: 'numeric',
    }).format(new Date())

    const activeCasesColumns = [
        {
            key: 'trackerNumber',
            title: 'CASE',
            render: (row) => (
                <button onClick={() => router.visit(`/cases/${row.rowId}`)} className="text-left">
                    <span className="text-xs font-bold text-blue-900 hover:underline font-body">{row.trackerNumber || row.caseNo}</span>
                    <br />
                    <span className="text-[10px] text-slate-400 font-body">{row.caseNo}</span>
                </button>
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
            <DashboardBanner />
            <header data-tour="dashboard-header" className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
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
                </div>
            </header>

            <section data-tour="dashboard-stats" className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
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

                <div className="space-y-6 xl:col-span-4">
                    <SectionCard title="Referral status" dataTour="dashboard-chart">
                        {safeArray(dashboard.referralStatusDistribution).length > 0 ? (
                            <StatusDonut items={dashboard.referralStatusDistribution} />
                        ) : (
                            <p className="text-sm text-slate-500">Status distribution appears once referrals are in play.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Cases per month">
                        {safeArray(caseTrends.labels).length > 0 ? (
                            <TrendBar labels={caseTrends.labels} data={caseTrends.data} />
                        ) : (
                            <p className="text-sm text-slate-500">The trend appears as case activity accumulates.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Agency responsiveness">
                        {scorecard.length > 0 ? (
                            <BarList
                                items={scorecard.map((item) => ({
                                    key: item.agencyId ?? item.agencyName,
                                    label: item.agencyName,
                                    count: item.activeCount,
                                    detail: `${item.overdueCount ?? 0} overdue · ${item.completionRate ?? 0}% completion`,
                                    tone: (item.overdueCount ?? 0) > 0 ? 'rose' : 'blue',
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">A scorecard appears once referrals span multiple agencies.</p>
                        )}
                    </SectionCard>

                    <SectionCard title="Case mix">
                        {categories.length > 0 ? (
                            <BarList
                                items={categories.map((item) => ({
                                    key: item.name,
                                    label: item.name,
                                    count: item.count,
                                    hex: item.color,
                                }))}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">Category demand appears once cases are filed.</p>
                        )}
                    </SectionCard>
                </div>
            </div>
        </div>
    );
}
