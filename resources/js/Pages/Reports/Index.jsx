import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useMemo, useCallback, useEffect } from 'react';
import { Building2, TrendingUp, Download, BarChart3, CheckCircle2, Clock, Loader2, XCircle, FileDown, GitFork, CalendarDays, Target, FolderOpen, FolderKanban } from 'lucide-react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';
import { exportToCsv } from '@/utils/export/exportCsv';
import { pageHeadingStyles, COLORS } from '@/Components/Reports/pageHeadingStyles';
import MetricCard from '@/Components/Reports/MetricCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import TrendIndicator from '@/Components/Reports/TrendIndicator';
import DateRangePicker, { formatDisplayDate, getQuickRangeDates } from '@/Components/Reports/DateRangePicker';
import CaseStatusPieChart from '@/Components/Reports/CaseStatusPieChart';
import AiInsightPanel from '@/Components/Reports/AiInsightPanel';
import AgencyScorecardSection from '@/Pages/Reports/sections/AgencyScorecardSection';
import StatusDistributionSection from '@/Pages/Reports/sections/StatusDistributionSection';
import CycleTimeSection from '@/Pages/Reports/sections/CycleTimeSection';
import GeographicSection from '@/Pages/Reports/sections/GeographicSection';
import CategorySection from '@/Pages/Reports/sections/CategorySection';
import EmploymentSection from '@/Pages/Reports/sections/EmploymentSection';
import LazyTrendChart from '@/Pages/Reports/sections/LazyTrendChart';
import LazyChartArticle from '@/Pages/Reports/sections/LazyChartArticle';
import LazyDemographics from '@/Pages/Reports/sections/LazyDemographics';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler);

function CaseManagerReports({
  kpis, managedReferrals, caseStatusDistribution,
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

  const referralColumns = useMemo(() => [
    { key: 'case_file', title: 'TRACKING ID', render: (row) => <span className="text-[12px] font-bold" style={{ color: COLORS.primary }}>{row.case_file?.case_number || 'N/A'}</span> },
    { key: 'client', title: 'CLIENT', render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A'}</span> },
    { key: 'agency', title: 'AGENCY', render: (row) => <span className="text-[12px] text-slate-700">{row.agency?.name || row.agcy_id || 'N/A'}</span> },
    { key: 'required_services', title: 'SERVICE', render: (row) => <span className="text-[12px] text-slate-700">{row.required_services || 'N/A'}</span> },
    { key: 'status', title: 'STATUS', render: (row) => (
      <StatusBadge status={row.status} showIcon={false} />
    )},
    { key: 'created_at', title: 'CREATED', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.created_at?.slice(0, 10))}</span> },
    { key: 'id', title: '', render: (row) => <Link href={route('referrals.show', row.id)} className="text-[11px] font-bold" style={{ color: COLORS.primary }}>View</Link> },
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
          <div className="flex items-center gap-3">
            <AiInsightPanel fromDate={fromDateISO} toDate={toDateISO} />
            <button
              type="button"
              onClick={() => window.open(route('reports.export-pdf', { from: fromDateISO, to: toDateISO }))}
              className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
            >
              <FileDown className="h-3.5 w-3.5" />
              Export PDF
            </button>
            <button
              type="button"
              onClick={() => window.open(route('reports.export-excel', { from: fromDateISO, to: toDateISO }))}
              className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
            >
              <FileDown className="h-3.5 w-3.5" />
              Export Excel
            </button>
          </div>
        </div>
      </header>

      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total Referrals" value={`${kpis.totalReferrals}`} accent="border-l-[#0b5a8c]"
          icon={<GitFork className="h-3.5 w-3.5 text-[#0b5a8c]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.totalReferrals} />} />
        <MetricCard label="Completion Rate" value={`${kpis.completionRate}%`} accent="border-l-[#0b7a75]"
          icon={<Target className="h-3.5 w-3.5 text-[#0b7a75]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.completionRate} />} />
        <MetricCard label="Avg Completion Days" value={`${kpis.avgCompletionDays}d`} accent="border-l-[#1e3a8a]" description="From referral creation to completion"
          icon={<CalendarDays className="h-3.5 w-3.5 text-[#1e3a8a]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.avgCompletionDays} inverse />} />
        <MetricCard label="Pending" value={`${kpis.pendingReferrals}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]"
          icon={<Clock className="h-3.5 w-3.5 text-[#9a5b1a]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.pendingReferrals} />} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr_1fr]">
        <StatusDistributionSection />
        <CaseStatusPieChart data={caseStatusDistribution} />
        <LazyChartArticle lazyKey="referralAgencyDistribution" title="Agency Workload" emptyText="No agency workload data available." />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <CycleTimeSection />
        <LazyChartArticle lazyKey="referralAging" title="Referral Aging" desc="How long active referrals have been waiting" emptyText="No active referrals pending." />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[2fr_1fr]">
        <AgencyScorecardSection />
        <section>
          <LazyTrendChart lazyKey="casesOverTime" title="Cases Over Time" />
        </section>
      </section>

      <GeographicSection />

      <EmploymentSection />

      <CategorySection />

      <LazyChartArticle lazyKey="caseIssueDistribution" title="Case Issue Distribution" emptyText="No issue data available." />

      <LazyDemographics />

      <section className="border bg-white shadow-sm" style={{ borderColor: COLORS.border }}>
        <div className="flex items-center justify-between border-b px-4 py-3" style={{ borderColor: COLORS.border }}>
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
  kpis, avgReferralCompletion, managedReferrals,
  caseStatusDistribution,
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

  const referralColumns = useMemo(() => [
    { key: 'case_file', title: 'TRACKING ID', render: (row) => <span className="text-[12px] font-bold" style={{ color: COLORS.primary }}>{row.case_file?.case_number || 'N/A'}</span> },
    { key: 'client', title: 'CLIENT', render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A'}</span> },
    { key: 'required_services', title: 'SERVICE', render: (row) => <span className="text-[12px] text-slate-700">{row.required_services || 'N/A'}</span> },
    { key: 'status', title: 'STATUS', render: (row) => <StatusBadge status={row.status} /> },
    { key: 'created_at', title: 'CREATED', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.created_at?.slice(0, 10))}</span> },
    { key: 'id', title: '', render: (row) => <Link href={route('referrals.show', row.id)} className="text-[11px] font-bold" style={{ color: COLORS.primary }}>View</Link> },
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
          <div className="flex items-center gap-3">
            <AiInsightPanel fromDate={fromDateISO} toDate={toDateISO} />
            <button
              type="button"
              onClick={() => window.open(route('reports.export-pdf', { from: fromDateISO, to: toDateISO }))}
              className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
            >
              <FileDown className="h-3.5 w-3.5" />
              Export PDF
            </button>
            <button
              type="button"
              onClick={() => window.open(route('reports.export-excel', { from: fromDateISO, to: toDateISO }))}
              className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
            >
              <FileDown className="h-3.5 w-3.5" />
              Export Excel
            </button>
          </div>
        </div>
      </header>

      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <MetricCard label="Total Referrals" value={`${kpis.totalReferrals}`} accent="border-l-[#0b5a8c]"
          icon={<GitFork className="h-3.5 w-3.5 text-[#0b5a8c]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.totalReferrals} />} />
        <MetricCard label="Completed" value={`${kpis.completedReferrals}`} accent="border-l-[#0b7a75]"
          icon={<CheckCircle2 className="h-3.5 w-3.5 text-[#0b7a75]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.completedReferrals} />} />
        <MetricCard label="Pending" value={`${kpis.pendingReferrals}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]"
          icon={<Clock className="h-3.5 w-3.5 text-[#9a5b1a]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.pendingReferrals} />} />
        <MetricCard label="Avg Completion" value={`${avgReferralCompletion.toFixed(1)}d`} accent="border-l-[#0b5a8c]" description="From referral sent to completion"
          icon={<CalendarDays className="h-3.5 w-3.5 text-[#0b5a8c]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.avgCompletionDays} inverse />} />
        <MetricCard label="Completion Rate" value={`${kpis.completionRate}%`} accent="border-l-[#0b7a75]"
          icon={<Target className="h-3.5 w-3.5 text-[#0b7a75]" />}
          trailing={<TrendIndicator change={kpis.kpiChanges?.completionRate} />} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr_1fr]">
        <StatusDistributionSection />
        <CaseStatusPieChart data={caseStatusDistribution} />
        <div className="flex flex-col gap-3">
          <LazyTrendChart lazyKey="referralTrends" title="Referral Trends" />
        </div>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <CycleTimeSection />
        <AgencyScorecardSection />
      </section>

      <CategorySection />

      <section className="border bg-white" style={{ borderColor: COLORS.border }}>
        <div className="flex items-center justify-between border-b px-4 py-3" style={{ borderColor: COLORS.border }}>
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
  employmentDistribution, employmentPositionBreakdown,
  caseStatusDistribution,
  referralTypeDistribution,
  caseIssueDistribution,
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
  const referralTypePie = useMemo(() => toPieFormat(referralTypeDistribution), [referralTypeDistribution]);

  const adminCycleTimeData = useMemo(() => {
    if (!cycleTimeDistribution?.labels) return null;
    return {
      labels: cycleTimeDistribution.labels,
      datasets: [{
        label: 'Referrals',
        data: cycleTimeDistribution.data,
        backgroundColor: cycleTimeDistribution.colors || [COLORS.success, '#84cc16', COLORS.warning, COLORS.danger],
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
        backgroundColor: referralAging.colors || [COLORS.success, '#84cc16', COLORS.warning, COLORS.danger],
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
        backgroundColor: COLORS.primary,
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
        borderColor: COLORS.accent,
        backgroundColor: `${COLORS.accent}1A`,
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

  const adminCaseIssueData = useMemo(() => {
    if (!caseIssueDistribution?.length) return null;
    return {
      labels: caseIssueDistribution.map(c => c.name),
      datasets: [{
        label: 'Cases',
        data: caseIssueDistribution.map(c => c.count),
        backgroundColor: caseIssueDistribution.map(c => c.color),
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [caseIssueDistribution]);

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
          <div className="flex items-center gap-3">
            <AiInsightPanel fromDate={fromDateISO} toDate={toDateISO} />
            <button
              type="button"
              onClick={() => window.open(route('reports.export-pdf', { from: fromDateISO, to: toDateISO }))}
              className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
            >
              <FileDown className="h-3.5 w-3.5" />
              Export PDF
            </button>
            <button
              type="button"
              onClick={() => window.open(route('reports.export-excel', { from: fromDateISO, to: toDateISO }))}
              className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
            >
              <FileDown className="h-3.5 w-3.5" />
              Export Excel
            </button>
          </div>
        </div>
      </header>

      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total Cases" value={`${overview?.totalCases || 0}`} accent="border-l-[#1e3a8a]"
          icon={<FolderOpen className="h-3.5 w-3.5 text-[#1e3a8a]" />} />
        <MetricCard label="Open Cases" value={`${overview?.openCases || 0}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]"
          icon={<FolderKanban className="h-3.5 w-3.5 text-[#9a5b1a]" />} />
        <MetricCard label="Total Referrals" value={`${overview?.totalReferrals || 0}`} accent="border-l-[#0b7a75]"
          icon={<GitFork className="h-3.5 w-3.5 text-[#0b7a75]" />} />
        <MetricCard label="Active Agencies" value={`${overview?.activeAgencies || 0}`} accent="border-l-[#0b5a8c]"
          icon={<Building2 className="h-3.5 w-3.5 text-[#0b5a8c]" />} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
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

        <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referral Type</h3>
          <div className="flex items-center gap-6">
            <div className="h-28 w-28 shrink-0">
              <Doughnut data={{
                labels: referralTypePie.map((s) => s.label),
                datasets: [{ data: referralTypePie.map((s) => s.count), backgroundColor: referralTypePie.map((s) => s.hex), borderWidth: 0 }],
              }} options={doughnutOptions} />
            </div>
            <div className="flex-1 space-y-1.5">
              {referralTypePie.map((s) => (
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

        <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
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

        <CaseStatusPieChart data={caseStatusDistribution} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Agency Workload</h3>
          {workloadChartData ? (
            <div className="h-64">
              <Bar data={workloadChartData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No agency workload data available.</p>
          )}
        </article>

        <SectionAccordion title="Geographic Distribution" defaultOpen>
          {adminGeoData ? (
            <div className="h-64">
              <Bar data={adminGeoData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No geographic data available.</p>
          )}
        </SectionAccordion>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
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
        <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
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

      <SectionAccordion title="Employment Analytics" defaultOpen>
        <EmploymentAnalytics
          employmentDistribution={employmentDistribution}
          employmentPositionBreakdown={employmentPositionBreakdown}
        />
      </SectionAccordion>

      <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
        <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Category Distribution</h3>
        {adminCategoryData ? (
          <div className="h-56">
            <Bar data={adminCategoryData} options={barOptions} />
          </div>
        ) : (
          <p className="py-8 text-center text-[13px] text-slate-400">No category data available.</p>
        )}
      </article>

      <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
        <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Case Issue Distribution</h3>
        {adminCaseIssueData ? (
          <div className="h-56">
            <Bar data={adminCaseIssueData} options={barOptions} />
          </div>
        ) : (
          <p className="py-8 text-center text-[13px] text-slate-400">No issue data available.</p>
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
    employmentDistribution, employmentPositionBreakdown,
    caseStatusDistribution,
    referralTypeDistribution,
    caseIssueDistribution,
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
          caseStatusDistribution={caseStatusDistribution}
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
          employmentDistribution={employmentDistribution}
          employmentPositionBreakdown={employmentPositionBreakdown}
          caseStatusDistribution={caseStatusDistribution}
          caseIssueDistribution={caseIssueDistribution}
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
        employmentDistribution={employmentDistribution}
        employmentPositionBreakdown={employmentPositionBreakdown}
          caseStatusDistribution={caseStatusDistribution}
          referralTypeDistribution={referralTypeDistribution}
          caseIssueDistribution={caseIssueDistribution}
          from={from}
          to={to}
        />
    </AppLayout>
  );
}
