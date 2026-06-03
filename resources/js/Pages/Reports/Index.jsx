import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useMemo, useCallback, useEffect } from 'react';
import { Building2, TrendingUp, Download, BarChart3, CheckCircle2, Clock, Loader2, XCircle, FileDown } from 'lucide-react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { Doughnut, Bar } from 'react-chartjs-2';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';
import { exportToCsv } from '@/utils/export/exportCsv';
import { pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';
import MetricCard from '@/Components/Reports/MetricCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import SvgPieChart from '@/Components/Reports/SvgPieChart';
import TrendChart from '@/Components/Reports/TrendChart';
import TrendIndicator from '@/Components/Reports/TrendIndicator';
import DateRangePicker, { formatDisplayDate, getQuickRangeDates } from '@/Components/Reports/DateRangePicker';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const statusHexMap = {
  PENDING: '#f59e0b', PROCESSING: '#3b82f6', FOR_COMPLIANCE: '#f97316',
  COMPLETED: '#10b981', REJECTED: '#f43f5e', OPEN: '#1e3a8a', CLOSED: '#94a3b8',
};
const statusColorMap = {
  PENDING: 'bg-amber-500', PROCESSING: 'bg-blue-500', FOR_COMPLIANCE: 'bg-orange-500',
  COMPLETED: 'bg-emerald-500', REJECTED: 'bg-rose-500', OPEN: 'bg-blue-900', CLOSED: 'bg-slate-300',
};

function toPieFormat(distribution) {
  if (!distribution || !distribution.labels) return [];
  const total = distribution.data.reduce((s, v) => s + v, 0) || 1;
  const colors = distribution.colors || ['#1e3a8a', '#0f766e', '#ea580c', '#6d28d9', '#be123c', '#4338ca'];
  return distribution.labels.map((label, i) => ({
    label,
    count: distribution.data[i] || 0,
    hex: colors[i % colors.length],
    color: '',
    percent: Math.round(((distribution.data[i] || 0) / total) * 100),
  }));
}

function StatusIcon({ status }) {
  const icons = {
    PENDING: <Loader2 className="h-3 w-3 text-amber-500" />,
    PROCESSING: <Clock className="h-3 w-3 text-blue-500" />,
    COMPLETED: <CheckCircle2 className="h-3 w-3 text-emerald-500" />,
    REJECTED: <XCircle className="h-3 w-3 text-rose-500" />,
    FOR_COMPLIANCE: <Clock className="h-3 w-3 text-orange-500" />,
  };
  return icons[status] || null;
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  cutout: '55%',
};

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
    y: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
};

function CaseManagerReports({
  kpis, referralStatusDistribution, referralAgencyDistribution,
  casesOverTime, genderDistribution, clientTypeDistribution,
  ageGroupDistribution, mostRequestedService, managedReferrals,
  cycleTimeDistribution, referralAging, agencyScorecard, geographicDistribution,
  categoryDistribution,
  from: initialFrom, to: initialTo,
}) {
  const [fromDateISO, setFromDateISO] = useState(initialFrom || new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10));
  const [toDateISO, setToDateISO] = useState(initialTo || new Date().toISOString().slice(0, 10));
  const [quickRange, setQuickRange] = useState('1_YEAR');

  useEffect(() => {
    const params = new URLSearchParams();
    params.set('from', fromDateISO);
    params.set('to', toDateISO);
    const qs = params.toString();
    const current = window.location.search.slice(1);
    if (current !== qs) {
      router.get(route('reports.index') + '?' + qs, {}, { preserveState: true, replace: true });
    }
  }, [fromDateISO, toDateISO]);

  const handleQuickRange = useCallback((option) => {
    setQuickRange(option);
    if (option === 'CUSTOM') return;
    const range = getQuickRangeDates(option);
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
  }, []);

  const resetDateRange = useCallback(() => {
    const range = getQuickRangeDates('1_YEAR');
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
    setQuickRange('1_YEAR');
  }, []);

  const referralStatusPie = useMemo(() => toPieFormat(referralStatusDistribution), [referralStatusDistribution]);
  const genderPie = useMemo(() => toPieFormat(genderDistribution), [genderDistribution]);
  const clientTypePie = useMemo(() => toPieFormat(clientTypeDistribution), [clientTypeDistribution]);
  const agePie = useMemo(() => toPieFormat(ageGroupDistribution), [ageGroupDistribution]);

  const cycleTimeChartData = useMemo(() => {
    if (!cycleTimeDistribution?.labels) return null;
    return {
      labels: cycleTimeDistribution.labels,
      datasets: [{
        label: 'Referrals',
        data: cycleTimeDistribution.data,
        backgroundColor: cycleTimeDistribution.colors || ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'],
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [cycleTimeDistribution]);

  const agingChartData = useMemo(() => {
    if (!referralAging?.labels) return null;
    return {
      labels: referralAging.labels,
      datasets: [{
        label: 'Referrals',
        data: referralAging.data,
        backgroundColor: referralAging.colors || ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'],
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [referralAging]);

  const geoChartData = useMemo(() => {
    if (!geographicDistribution?.labels) return null;
    return {
      labels: geographicDistribution.labels,
      datasets: [{
        label: 'Cases',
        data: geographicDistribution.data,
        backgroundColor: '#0b5a8c',
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [geographicDistribution]);

  const categoryChartData = useMemo(() => {
    if (!categoryDistribution?.length) return null;
    return {
      labels: categoryDistribution.map(c => c.name),
      datasets: [{
        label: 'Cases',
        data: categoryDistribution.map(c => c.count),
        backgroundColor: categoryDistribution.map(c => c.color),
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [categoryDistribution]);

  const agencyChartData = useMemo(() => {
    if (!referralAgencyDistribution?.labels) return null;
    return {
      labels: referralAgencyDistribution.labels,
      datasets: [{
        label: 'Referrals',
        data: referralAgencyDistribution.data,
        backgroundColor: (referralAgencyDistribution.colors || ['#1e3a8a']).map((c) => c),
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [referralAgencyDistribution]);

  const referralColumns = useMemo(() => [
    { key: 'case_file', title: 'TRACKING ID', render: (row) => <span className="text-[12px] font-bold text-[#0b5a8c]">{row.case_file?.case_number || 'N/A'}</span> },
    { key: 'client', title: 'CLIENT', render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A'}</span> },
    { key: 'agency', title: 'AGENCY', render: (row) => <span className="text-[12px] text-slate-700">{row.agency?.name || row.agcy_id || 'N/A'}</span> },
    { key: 'required_services', title: 'SERVICE', render: (row) => <span className="text-[12px] text-slate-700">{row.required_services || 'N/A'}</span> },
    { key: 'status', title: 'STATUS', render: (row) => (
      <StatusBadge status={row.status} showIcon={false} />
    )},
    { key: 'created_at', title: 'CREATED', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.created_at?.slice(0, 10))}</span> },
    { key: 'id', title: '', render: (row) => <Link href={route('referrals.show', row.id)} className="text-[11px] font-bold text-[#0b5a8c] hover:underline">View</Link> },
  ], []);

  function paginatorProps(paginator) {
    return {
      totalRecords: paginator?.total || 0,
      startIndex: paginator?.from || 0,
      endIndex: paginator?.to || 0,
      currentPage: paginator?.current_page || 1,
      totalPages: paginator?.last_page || 1,
      rowsPerPage: paginator?.per_page || 10,
      hideControlBar: true,
      hidePagination: false,
    };
  }

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <header className="flex flex-col gap-4">
        <div>
          <h1 className={pageHeadingStyles.pageTitle}>Reports</h1>
          <p className={pageHeadingStyles.pageSubtitle}>Referral operations and agency performance.</p>
        </div>
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <DateRangePicker
            fromDateISO={fromDateISO}
            toDateISO={toDateISO}
            quickRange={quickRange}
            onFromChange={setFromDateISO}
            onToChange={setToDateISO}
            onQuickRangeSelect={handleQuickRange}
            onReset={resetDateRange}
          />
          <button
            type="button"
            onClick={() => window.open(route('reports.export-pdf', { from: fromDateISO, to: toDateISO }))}
            className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
          >
            <FileDown className="h-3.5 w-3.5" />
            Export PDF
          </button>
        </div>
      </header>

      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total Referrals" value={`${kpis.totalReferrals}`} accent="border-l-[#0b5a8c]"
          trailing={<TrendIndicator change={kpis.kpiChanges?.totalReferrals} />} />
        <MetricCard label="Completion Rate" value={`${kpis.completionRate}%`} accent="border-l-[#0b7a75]"
          trailing={<TrendIndicator change={kpis.kpiChanges?.completionRate} />} />
        <MetricCard label="Avg Completion Days" value={`${kpis.avgCompletionDays}d`} accent="border-l-[#1e3a8a]" description="From referral creation to completion"
          trailing={<TrendIndicator change={kpis.kpiChanges?.avgCompletionDays} inverse />} />
        <MetricCard label="Pending" value={`${kpis.pendingReferrals}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]"
          trailing={<TrendIndicator change={kpis.kpiChanges?.pendingReferrals} />} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referral Status</h3>
          <div className="flex items-center gap-6">
            <div className="h-28 w-28 shrink-0">
              <Doughnut data={{
                labels: referralStatusPie.map((s) => s.label),
                datasets: [{ data: referralStatusPie.map((s) => s.count), backgroundColor: referralStatusPie.map((s) => s.hex), borderWidth: 0 }],
              }} options={doughnutOptions} />
            </div>
            <div className="flex-1 space-y-1.5">
              {referralStatusPie.map((s) => (
                <div key={s.label} className="flex items-center justify-between text-[11px]">
                  <span className="inline-flex items-center gap-1.5 text-slate-600">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: s.hex }} />
                    <span className="font-medium">{s.label}</span>
                  </span>
                  <span className="font-bold text-slate-700">{s.count} ({s.percent}%)</span>
                </div>
              ))}
            </div>
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Agency Workload</h3>
          {agencyChartData ? (
            <div className="h-56">
              <Bar data={agencyChartData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No agency workload data available.</p>
          )}
        </article>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Cycle Time Distribution</h3>
          <p className="mb-3 text-[11px] text-slate-500">Time from referral creation to completion</p>
          {cycleTimeChartData ? (
            <div className="h-56">
              <Bar data={cycleTimeChartData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No completed referrals yet.</p>
          )}
        </article>
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referral Aging</h3>
          <p className="mb-3 text-[11px] text-slate-500">How long active referrals have been waiting</p>
          {agingChartData ? (
            <div className="h-56">
              <Bar data={agingChartData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No active referrals pending.</p>
          )}
        </article>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[2fr_1fr]">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Agency Scorecard</h3>
          {agencyScorecard?.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full text-[11px]">
                <thead>
                  <tr className="border-b border-[#cbd5e1] text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">
                    <th className="pb-2 pr-3">Agency</th>
                    <th className="pb-2 pr-3 text-right">Total</th>
                    <th className="pb-2 pr-3 text-right">Completed</th>
                    <th className="pb-2 pr-3 text-right">Rate</th>
                    <th className="pb-2 pr-3 text-right">Avg Days</th>
                    <th className="pb-2 text-right">Pending</th>
                  </tr>
                </thead>
                <tbody>
                  {agencyScorecard.map((a) => (
                    <tr key={a.agency} className="border-b border-[#e2e8f0] last:border-0">
                      <td className="py-2 pr-3 font-semibold text-slate-700">{a.agency}</td>
                      <td className="py-2 pr-3 text-right text-slate-700">{a.total}</td>
                      <td className="py-2 pr-3 text-right text-emerald-700">{a.completed}</td>
                      <td className="py-2 pr-3 text-right font-bold text-slate-700">{a.completionRate}%</td>
                      <td className={`py-2 pr-3 text-right ${a.avgDays <= 7 ? 'text-emerald-700' : a.avgDays <= 14 ? 'text-amber-700' : 'text-rose-700'}`}>{a.avgDays}d</td>
                      <td className="py-2 text-right text-amber-700">{a.pending}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No agency data available.</p>
          )}
        </article>
        <section>
          <TrendChart title="Cases Over Time" data={casesOverTime} />
        </section>
      </section>

      <section className="border border-[#cbd5e1] bg-white p-4">
        <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Geographic Distribution</h3>
        {geoChartData ? (
          <div className="h-56">
            <Bar data={geoChartData} options={barOptions} />
          </div>
        ) : (
          <p className="py-8 text-center text-[13px] text-slate-400">No geographic data available.</p>
        )}
      </section>

      <article className="border border-[#cbd5e1] bg-white p-4">
        <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Category Distribution</h3>
        {categoryChartData ? (
          <div className="h-56">
            <Bar data={categoryChartData} options={barOptions} />
          </div>
        ) : (
          <p className="py-8 text-center text-[13px] text-slate-400">No category data available.</p>
        )}
      </article>

      <section>
        <h2 className={`mb-3 ${pageHeadingStyles.sectionTitle}`}>Client Demographics</h2>
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
          <article className="border border-[#cbd5e1] bg-white p-4">
            <h3 className="mb-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Gender</h3>
            <div className="flex items-center gap-4">
              <div className="h-16 w-16 shrink-0">
                <SvgPieChart data={genderPie} className="w-16 h-16" />
              </div>
              <div className="space-y-1">
                {genderPie.map((g) => (
                  <div key={g.label} className="flex items-center gap-2 text-[11px]">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: g.hex }} />
                    <span className="text-slate-600">{g.label}</span>
                    <span className="font-bold text-slate-800">{g.percent}%</span>
                  </div>
                ))}
              </div>
            </div>
          </article>

          <article className="border border-[#cbd5e1] bg-white p-4">
            <h3 className="mb-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Client Type</h3>
            <div className="flex items-center gap-4">
              <div className="h-16 w-16 shrink-0">
                <SvgPieChart data={clientTypePie} className="w-16 h-16" />
              </div>
              <div className="space-y-1">
                {clientTypePie.map((t) => (
                  <div key={t.label} className="flex items-center gap-2 text-[11px]">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: t.hex }} />
                    <span className="text-slate-600">{t.label}</span>
                    <span className="font-bold text-slate-800">{t.percent}%</span>
                  </div>
                ))}
              </div>
            </div>
          </article>

          <article className="border border-[#cbd5e1] bg-white p-4">
            <h3 className="mb-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Age Group</h3>
            <div className="flex items-center gap-4">
              <div className="h-16 w-16 shrink-0">
                <SvgPieChart data={agePie} className="w-16 h-16" />
              </div>
              <div className="space-y-1">
                {agePie.slice(0, 3).map((a) => (
                  <div key={a.label} className="flex items-center gap-2 text-[11px]">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: a.hex }} />
                    <span className="text-slate-600">{a.label}</span>
                    <span className="font-bold text-slate-800">{a.percent}%</span>
                  </div>
                ))}
              </div>
            </div>
          </article>
        </div>
      </section>

      <section className="border border-[#cbd5e1] bg-white">
        <div className="flex items-center justify-between border-b border-[#cbd5e1] px-4 py-3">
          <h3 className={pageHeadingStyles.sectionTitle}>Referral Detail</h3>
          <button
            type="button"
            onClick={() => {
              const rows = (managedReferrals?.data || []).map((r) => ({
                caseNo: r.case_file?.case_number || '',
                client: r.case_file?.client ? `${r.case_file.client.first_name} ${r.case_file.client.last_name}` : '',
                agency: r.agency?.name || '',
                service: r.required_services || '',
                status: r.status,
                created: r.created_at,
              }));
              exportToCsv(
                rows,
                [
                  { header: 'Case No', accessor: (r) => r.caseNo },
                  { header: 'Client', accessor: (r) => r.client },
                  { header: 'Agency', accessor: (r) => r.agency },
                  { header: 'Service', accessor: (r) => r.service },
                  { header: 'Status', accessor: (r) => r.status },
                  { header: 'Created', accessor: (r) => r.created },
                ],
                'referral-detail.csv',
              );
            }}
            className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
          >
            <Download className="h-3.5 w-3.5" />
            Export CSV
          </button>
        </div>
        <UnifiedTable
          variant="embedded"
          data={managedReferrals?.data || []}
          columns={referralColumns}
          keyExtractor={(row) => row.id}
          {...paginatorProps(managedReferrals)}
          searchPlaceholder="Search case no, client, agency, service..."
        />
      </section>
    </div>
  );
}

function AgencyReports({
  kpis, referralStatusDistribution, referralTrends,
  avgReferralCompletion, managedReferrals,
  cycleTimeDistribution, agencyScorecard,
  categoryDistribution,
  from: initialFrom, to: initialTo,
}) {
  const [fromDateISO, setFromDateISO] = useState(initialFrom || new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10));
  const [toDateISO, setToDateISO] = useState(initialTo || new Date().toISOString().slice(0, 10));
  const [quickRange, setQuickRange] = useState('1_YEAR');

  useEffect(() => {
    const params = new URLSearchParams();
    params.set('from', fromDateISO);
    params.set('to', toDateISO);
    const qs = params.toString();
    const current = window.location.search.slice(1);
    if (current !== qs) {
      router.get(route('reports.index') + '?' + qs, {}, { preserveState: true, replace: true });
    }
  }, [fromDateISO, toDateISO]);

  const handleQuickRange = useCallback((option) => {
    setQuickRange(option);
    if (option === 'CUSTOM') return;
    const range = getQuickRangeDates(option);
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
  }, []);

  const resetDateRange = useCallback(() => {
    const range = getQuickRangeDates('1_YEAR');
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
    setQuickRange('1_YEAR');
  }, []);

  const referralStatusPie = useMemo(() => toPieFormat(referralStatusDistribution), [referralStatusDistribution]);

  const agencyCycleTimeData = useMemo(() => {
    if (!cycleTimeDistribution?.labels) return null;
    return {
      labels: cycleTimeDistribution.labels,
      datasets: [{
        label: 'Referrals',
        data: cycleTimeDistribution.data,
        backgroundColor: cycleTimeDistribution.colors || ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'],
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [cycleTimeDistribution]);

  const agencyCategoryData = useMemo(() => {
    if (!categoryDistribution?.length) return null;
    return {
      labels: categoryDistribution.map(c => c.name),
      datasets: [{
        label: 'Cases',
        data: categoryDistribution.map(c => c.count),
        backgroundColor: categoryDistribution.map(c => c.color),
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [categoryDistribution]);

  const referralColumns = useMemo(() => [
    { key: 'case_file', title: 'TRACKING ID', render: (row) => <span className="text-[12px] font-bold text-[#0b5a8c]">{row.case_file?.case_number || 'N/A'}</span> },
    { key: 'client', title: 'CLIENT', render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A'}</span> },
    { key: 'required_services', title: 'SERVICE', render: (row) => <span className="text-[12px] text-slate-700">{row.required_services || 'N/A'}</span> },
    { key: 'status', title: 'STATUS', render: (row) => <StatusBadge status={row.status} /> },
    { key: 'created_at', title: 'CREATED', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.created_at?.slice(0, 10))}</span> },
    { key: 'id', title: '', render: (row) => <Link href={route('referrals.show', row.id)} className="text-[11px] font-bold text-[#0b5a8c] hover:underline">View</Link> },
  ], []);

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <header className="flex flex-col gap-4">
        <div>
          <h1 className={pageHeadingStyles.pageTitle}>Reports</h1>
          <p className={pageHeadingStyles.pageSubtitle}>Agency performance overview.</p>
        </div>
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <DateRangePicker
            fromDateISO={fromDateISO}
            toDateISO={toDateISO}
            quickRange={quickRange}
            onFromChange={setFromDateISO}
            onToChange={setToDateISO}
            onQuickRangeSelect={handleQuickRange}
            onReset={resetDateRange}
          />
          <button
            type="button"
            onClick={() => window.open(route('reports.export-pdf', { from: fromDateISO, to: toDateISO }))}
            className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
          >
            <FileDown className="h-3.5 w-3.5" />
            Export PDF
          </button>
        </div>
      </header>

      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <MetricCard label="Total Referrals" value={`${kpis.totalReferrals}`} accent="border-l-[#0b5a8c]"
          trailing={<TrendIndicator change={kpis.kpiChanges?.totalReferrals} />} />
        <MetricCard label="Completed" value={`${kpis.completedReferrals}`} accent="border-l-[#0b7a75]"
          trailing={<TrendIndicator change={kpis.kpiChanges?.completedReferrals} />} />
        <MetricCard label="Pending" value={`${kpis.pendingReferrals}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]"
          trailing={<TrendIndicator change={kpis.kpiChanges?.pendingReferrals} />} />
        <MetricCard label="Avg Completion" value={`${avgReferralCompletion.toFixed(1)}d`} accent="border-l-[#0b5a8c]" description="From referral sent to completion"
          trailing={<TrendIndicator change={kpis.kpiChanges?.avgCompletionDays} inverse />} />
        <MetricCard label="Completion Rate" value={`${kpis.completionRate}%`} accent="border-l-[#0b7a75]"
          trailing={<TrendIndicator change={kpis.kpiChanges?.completionRate} />} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_2fr]">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referral Status</h3>
          <div className="flex items-center gap-6">
            <div className="h-28 w-28 shrink-0">
              <Doughnut data={{
                labels: referralStatusPie.map((s) => s.label),
                datasets: [{ data: referralStatusPie.map((s) => s.count), backgroundColor: referralStatusPie.map((s) => s.hex), borderWidth: 0 }],
              }} options={doughnutOptions} />
            </div>
            <div className="flex-1 space-y-1.5">
              {referralStatusPie.map((s) => (
                <div key={s.label} className="flex items-center justify-between text-[11px]">
                  <span className="inline-flex items-center gap-1.5 text-slate-600">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: s.hex }} />
                    <span className="font-medium">{s.label}</span>
                  </span>
                  <span className="font-bold text-slate-700">{s.count} ({s.percent}%)</span>
                </div>
              ))}
            </div>
          </div>
        </article>

        <TrendChart title="Referral Trends" data={referralTrends} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Cycle Time Distribution</h3>
          <p className="mb-3 text-[11px] text-slate-500">Time from referral creation to completion</p>
          {agencyCycleTimeData ? (
            <div className="h-56">
              <Bar data={agencyCycleTimeData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No completed referrals yet.</p>
          )}
        </article>
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Agency Scorecard</h3>
          {agencyScorecard?.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full text-[11px]">
                <thead>
                  <tr className="border-b border-[#cbd5e1] text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">
                    <th className="pb-2 pr-3">Agency</th>
                    <th className="pb-2 pr-3 text-right">Total</th>
                    <th className="pb-2 pr-3 text-right">Completed</th>
                    <th className="pb-2 pr-3 text-right">Rate</th>
                    <th className="pb-2 pr-3 text-right">Avg Days</th>
                    <th className="pb-2 text-right">Pending</th>
                  </tr>
                </thead>
                <tbody>
                  {agencyScorecard.map((a) => (
                    <tr key={a.agency} className="border-b border-[#e2e8f0] last:border-0">
                      <td className="py-2 pr-3 font-semibold text-slate-700">{a.agency}</td>
                      <td className="py-2 pr-3 text-right text-slate-700">{a.total}</td>
                      <td className="py-2 pr-3 text-right text-emerald-700">{a.completed}</td>
                      <td className="py-2 pr-3 text-right font-bold text-slate-700">{a.completionRate}%</td>
                      <td className={`py-2 pr-3 text-right ${a.avgDays <= 7 ? 'text-emerald-700' : a.avgDays <= 14 ? 'text-amber-700' : 'text-rose-700'}`}>{a.avgDays}d</td>
                      <td className="py-2 text-right text-amber-700">{a.pending}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No agency data available.</p>
          )}
        </article>
      </section>

      <article className="border border-[#cbd5e1] bg-white p-4">
        <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Category Distribution</h3>
        {agencyCategoryData ? (
          <div className="h-56">
            <Bar data={agencyCategoryData} options={barOptions} />
          </div>
        ) : (
          <p className="py-8 text-center text-[13px] text-slate-400">No category data available.</p>
        )}
      </article>

      <section className="border border-[#cbd5e1] bg-white">
        <div className="flex items-center justify-between border-b border-[#cbd5e1] px-4 py-3">
          <h3 className={pageHeadingStyles.sectionTitle}>Referred Cases</h3>
        </div>
        <UnifiedTable
          variant="embedded"
          data={managedReferrals?.data || []}
          columns={referralColumns}
          keyExtractor={(row) => row.id}
          searchPlaceholder="Search case no, client, service..."
          totalRecords={managedReferrals?.total || 0}
          startIndex={managedReferrals?.from || 0}
          endIndex={managedReferrals?.to || 0}
          currentPage={managedReferrals?.current_page || 1}
          totalPages={managedReferrals?.last_page || 1}
          rowsPerPage={managedReferrals?.per_page || 10}
          hidePagination={false}
          hideControlBar
        />
      </section>
    </div>
  );
}

function AdminReports({
  overview, caseTrends, referralStatusDistribution,
  agencyWorkload, clientTypeDistribution,
  cycleTimeDistribution, referralAging, geographicDistribution, agencyScorecard,
  categoryDistribution,
  from: initialFrom, to: initialTo,
}) {
  const [fromDateISO, setFromDateISO] = useState(initialFrom || new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10));
  const [toDateISO, setToDateISO] = useState(initialTo || new Date().toISOString().slice(0, 10));
  const [quickRange, setQuickRange] = useState('1_YEAR');

  useEffect(() => {
    const params = new URLSearchParams();
    params.set('from', fromDateISO);
    params.set('to', toDateISO);
    const qs = params.toString();
    const current = window.location.search.slice(1);
    if (current !== qs) {
      router.get(route('reports.index') + '?' + qs, {}, { preserveState: true, replace: true });
    }
  }, [fromDateISO, toDateISO]);

  const handleQuickRange = useCallback((option) => {
    setQuickRange(option);
    if (option === 'CUSTOM') return;
    const range = getQuickRangeDates(option);
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
  }, []);

  const resetDateRange = useCallback(() => {
    const range = getQuickRangeDates('1_YEAR');
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
    setQuickRange('1_YEAR');
  }, []);

  const referralStatusPie = useMemo(() => toPieFormat(referralStatusDistribution), [referralStatusDistribution]);
  const clientTypePie = useMemo(() => toPieFormat(clientTypeDistribution), [clientTypeDistribution]);

  const adminCycleTimeData = useMemo(() => {
    if (!cycleTimeDistribution?.labels) return null;
    return {
      labels: cycleTimeDistribution.labels,
      datasets: [{
        label: 'Referrals',
        data: cycleTimeDistribution.data,
        backgroundColor: cycleTimeDistribution.colors || ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'],
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [cycleTimeDistribution]);

  const adminAgingData = useMemo(() => {
    if (!referralAging?.labels) return null;
    return {
      labels: referralAging.labels,
      datasets: [{
        label: 'Referrals',
        data: referralAging.data,
        backgroundColor: referralAging.colors || ['#22c55e', '#84cc16', '#f59e0b', '#ef4444'],
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [referralAging]);

  const adminGeoData = useMemo(() => {
    if (!geographicDistribution?.labels) return null;
    return {
      labels: geographicDistribution.labels,
      datasets: [{
        label: 'Cases',
        data: geographicDistribution.data,
        backgroundColor: '#0b5a8c',
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [geographicDistribution]);

  const workloadChartData = useMemo(() => {
    if (!agencyWorkload?.labels) return null;
    return {
      labels: agencyWorkload.labels,
      datasets: [{
        label: 'Referrals',
        data: agencyWorkload.data,
        backgroundColor: '#1e3a8a',
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [agencyWorkload]);

  const caseTrendsChartData = useMemo(() => {
    if (!caseTrends?.labels) return null;
    return {
      labels: caseTrends.labels,
      datasets: [{
        label: 'Cases',
        data: caseTrends.data,
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99, 102, 241, 0.1)',
        fill: true,
        tension: 0.3,
      }],
    };
  }, [caseTrends]);

  const adminCategoryData = useMemo(() => {
    if (!categoryDistribution?.length) return null;
    return {
      labels: categoryDistribution.map(c => c.name),
      datasets: [{
        label: 'Cases',
        data: categoryDistribution.map(c => c.count),
        backgroundColor: categoryDistribution.map(c => c.color),
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [categoryDistribution]);

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <header className="flex flex-col gap-4">
        <div>
          <h1 className={pageHeadingStyles.pageTitle}>Reports</h1>
          <p className={pageHeadingStyles.pageSubtitle}>System-wide performance metrics and trends.</p>
        </div>
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <DateRangePicker
            fromDateISO={fromDateISO}
            toDateISO={toDateISO}
            quickRange={quickRange}
            onFromChange={setFromDateISO}
            onToChange={setToDateISO}
            onQuickRangeSelect={handleQuickRange}
            onReset={resetDateRange}
          />
          <button
            type="button"
            onClick={() => window.open(route('reports.export-pdf', { from: fromDateISO, to: toDateISO }))}
            className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
          >
            <FileDown className="h-3.5 w-3.5" />
            Export PDF
          </button>
        </div>
      </header>

      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total Cases" value={`${overview?.totalCases || 0}`} accent="border-l-[#1e3a8a]" />
        <MetricCard label="Open Cases" value={`${overview?.openCases || 0}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]" />
        <MetricCard label="Total Referrals" value={`${overview?.totalReferrals || 0}`} accent="border-l-[#0b7a75]" />
        <MetricCard label="Active Agencies" value={`${overview?.activeAgencies || 0}`} accent="border-l-[#0b5a8c]" />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referral Status</h3>
          <div className="flex items-center gap-6">
            <div className="h-28 w-28 shrink-0">
              <Doughnut data={{
                labels: referralStatusPie.map((s) => s.label),
                datasets: [{ data: referralStatusPie.map((s) => s.count), backgroundColor: referralStatusPie.map((s) => s.hex), borderWidth: 0 }],
              }} options={doughnutOptions} />
            </div>
            <div className="flex-1 space-y-1.5">
              {referralStatusPie.map((s) => (
                <div key={s.label} className="flex items-center justify-between text-[11px]">
                  <span className="inline-flex items-center gap-1.5 text-slate-600">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: s.hex }} />
                    <span className="font-medium">{s.label}</span>
                  </span>
                  <span className="font-bold text-slate-700">{s.count} ({s.percent}%)</span>
                </div>
              ))}
            </div>
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Client Type</h3>
          <div className="flex items-center gap-6">
            <div className="h-28 w-28 shrink-0">
              <Doughnut data={{
                labels: clientTypePie.map((s) => s.label),
                datasets: [{ data: clientTypePie.map((s) => s.count), backgroundColor: clientTypePie.map((s) => s.hex), borderWidth: 0 }],
              }} options={doughnutOptions} />
            </div>
            <div className="flex-1 space-y-1.5">
              {clientTypePie.map((s) => (
                <div key={s.label} className="flex items-center justify-between text-[11px]">
                  <span className="inline-flex items-center gap-1.5 text-slate-600">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: s.hex }} />
                    <span className="font-medium">{s.label}</span>
                  </span>
                  <span className="font-bold text-slate-700">{s.count} ({s.percent}%)</span>
                </div>
              ))}
            </div>
          </div>
        </article>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Agency Workload</h3>
          {workloadChartData ? (
            <div className="h-64">
              <Bar data={workloadChartData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No agency workload data available.</p>
          )}
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Geographic Distribution</h3>
          {adminGeoData ? (
            <div className="h-64">
              <Bar data={adminGeoData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No geographic data available.</p>
          )}
        </article>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Cycle Time Distribution</h3>
          <p className="mb-3 text-[11px] text-slate-500">Time from referral creation to completion</p>
          {adminCycleTimeData ? (
            <div className="h-56">
              <Bar data={adminCycleTimeData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No completed referrals yet.</p>
          )}
        </article>
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referral Aging</h3>
          <p className="mb-3 text-[11px] text-slate-500">How long active referrals have been waiting</p>
          {adminAgingData ? (
            <div className="h-56">
              <Bar data={adminAgingData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No active referrals pending.</p>
          )}
        </article>
      </section>

      <article className="border border-[#cbd5e1] bg-white p-4">
        <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Category Distribution</h3>
        {adminCategoryData ? (
          <div className="h-56">
            <Bar data={adminCategoryData} options={barOptions} />
          </div>
        ) : (
          <p className="py-8 text-center text-[13px] text-slate-400">No category data available.</p>
        )}
      </article>

      <section className="mb-6">
        <TrendChart title="Case Trends (12 Months)" data={caseTrendsChartData} />
      </section>
    </div>
  );
}

export default function ReportsIndex(props) {
  const {
    role, kpis, referralStatusDistribution, referralAgencyDistribution,
    casesOverTime, genderDistribution, clientTypeDistribution,
    ageGroupDistribution, mostRequestedService,
    cycleTimeDistribution, referralAging, agencyScorecard, geographicDistribution,
    overview, caseTrends, agencyWorkload,
    referralTrends, avgReferralCompletion,
    managedReferrals, categoryDistribution,
  } = props;

  const from = (new URLSearchParams(window.location.search)).get('from') || undefined;
  const to = (new URLSearchParams(window.location.search)).get('to') || undefined;

  if (role === 'AGENCY') {
    return (
      <AppLayout title="Reports">
        <Head title="Reports" />
        <AgencyReports
          kpis={kpis}
          referralStatusDistribution={referralStatusDistribution}
          referralTrends={referralTrends}
          avgReferralCompletion={avgReferralCompletion || 0}
          managedReferrals={managedReferrals}
          cycleTimeDistribution={cycleTimeDistribution}
          agencyScorecard={agencyScorecard}
          categoryDistribution={categoryDistribution}
          from={from}
          to={to}
        />
      </AppLayout>
    );
  }

  if (role === 'ADMIN') {
    return (
      <AppLayout title="Reports">
        <Head title="Reports" />
        <AdminReports
          overview={overview}
          caseTrends={caseTrends}
          referralStatusDistribution={referralStatusDistribution}
          agencyWorkload={agencyWorkload}
          clientTypeDistribution={clientTypeDistribution}
          cycleTimeDistribution={cycleTimeDistribution}
          referralAging={referralAging}
          geographicDistribution={geographicDistribution}
          agencyScorecard={agencyScorecard}
          categoryDistribution={categoryDistribution}
          from={from}
          to={to}
        />
      </AppLayout>
    );
  }

  return (
    <AppLayout title="Reports">
      <Head title="Reports" />
      <CaseManagerReports
        kpis={kpis}
        referralStatusDistribution={referralStatusDistribution}
        referralAgencyDistribution={referralAgencyDistribution}
        casesOverTime={casesOverTime}
        genderDistribution={genderDistribution}
        clientTypeDistribution={clientTypeDistribution}
        ageGroupDistribution={ageGroupDistribution}
        mostRequestedService={mostRequestedService}
        managedReferrals={managedReferrals}
        cycleTimeDistribution={cycleTimeDistribution}
        referralAging={referralAging}
        agencyScorecard={agencyScorecard}
        geographicDistribution={geographicDistribution}
        categoryDistribution={categoryDistribution}
        from={from}
        to={to}
      />
    </AppLayout>
  );
}
