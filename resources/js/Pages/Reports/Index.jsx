import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { Building2, Hourglass, TrendingUp, Download } from 'lucide-react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { exportToCsv } from '@/utils/export/exportCsv';
import { pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';
import MetricCard from '@/Components/Reports/MetricCard';
import StatusBadge from '@/Components/Reports/StatusBadge';
import SvgPieChart from '@/Components/Reports/SvgPieChart';
import TrendChart from '@/Components/Reports/TrendChart';
import DateRangePicker, { toCalendarDate, addDays, formatDisplayDate, getQuickRangeDates } from '@/Components/Reports/DateRangePicker';

const DAY_MS = 1000 * 60 * 60 * 24;

function toPieFormat(distribution) {
  if (!distribution || !distribution.labels) return [];
  const total = distribution.data.reduce((s, v) => s + v, 0) || 1;
  const defaultColors = ['#1e3a8a', '#0f766e', '#ea580c', '#6d28d9', '#be123c', '#4338ca'];
  const colors = distribution.colors || defaultColors;
  return distribution.labels.map((label, i) => ({
    label,
    count: distribution.data[i] || 0,
    hex: colors[i % colors.length],
    color: '',
    percent: Math.round(((distribution.data[i] || 0) / total) * 100),
  }));
}

const statusHexMap = {
  PENDING: '#f59e0b', PROCESSING: '#3b82f6', FOR_COMPLIANCE: '#f97316',
  COMPLETED: '#10b981', REJECTED: '#f43f5e', OPEN: '#1e3a8a', CLOSED: '#94a3b8',
};
const statusColorMap = {
  PENDING: 'bg-amber-500', PROCESSING: 'bg-blue-500', FOR_COMPLIANCE: 'bg-orange-500',
  COMPLETED: 'bg-emerald-500', REJECTED: 'bg-rose-500', OPEN: 'bg-blue-900', CLOSED: 'bg-slate-300',
};

function buildBarData(buckets) {
  if (!buckets || !buckets.labels) return [];
  const total = buckets.data.reduce((s, v) => s + v, 0) || 1;
  const colors = ['#1e3a8a', '#0f766e', '#ea580c', '#6d28d9', '#be123c', '#4338ca'];
  const bgColors = ['bg-blue-900', 'bg-teal-700', 'bg-orange-600', 'bg-violet-700', 'bg-rose-700', 'bg-indigo-700'];
  return buckets.labels.map((label, i) => ({
    label,
    count: buckets.data[i] || 0,
    hex: colors[i % colors.length],
    color: bgColors[i % bgColors.length],
    percent: Math.round(((buckets.data[i] || 0) / total) * 100),
  }));
}

function CaseManagerReports({
  kpis, caseStatusDistribution, casesOverTime,
  genderDistribution, clientTypeDistribution, ageGroupDistribution,
  previousOccupations, lastEmploymentCountries,
  topOccupation, topCountry,
  referralStatusDistribution, referralAgencyDistribution,
  mostActiveAgency, avgReferralCompletion, mostRequestedService,
  managedCases, managedReferrals, managedClients,
}) {
  const defaultFromISO = '2026-03-01';
  const defaultToISO = '2026-04-30';
  const [fromDateISO, setFromDateISO] = useState(defaultFromISO);
  const [toDateISO, setToDateISO] = useState(defaultToISO);
  const [quickRange, setQuickRange] = useState('CUSTOM');
  const [searchValue, setSearchValue] = useState('');
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [statusFilter, setStatusFilter] = useState('ALL');
  const [casesCurrentPage, setCasesCurrentPage] = useState(1);
  const [casesRowsPerPage, setCasesRowsPerPage] = useState(10);
  const [referralsCurrentPage, setReferralsCurrentPage] = useState(1);
  const [referralsRowsPerPage, setReferralsRowsPerPage] = useState(10);
  const [clientsCurrentPage, setClientsCurrentPage] = useState(1);
  const [clientsRowsPerPage, setClientsRowsPerPage] = useState(10);

  const caseStatusPie = useMemo(() => toPieFormat(caseStatusDistribution), [caseStatusDistribution]);
  const genderPie = useMemo(() => toPieFormat(genderDistribution), [genderDistribution]);
  const clientTypePie = useMemo(() => toPieFormat(clientTypeDistribution), [clientTypeDistribution]);
  const agePie = useMemo(() => toPieFormat(ageGroupDistribution), [ageGroupDistribution]);
  const referralStatusPie = useMemo(() => toPieFormat(referralStatusDistribution), [referralStatusDistribution]);
  const agencyBarData = useMemo(() => buildBarData(referralAgencyDistribution), [referralAgencyDistribution]);

  const prevOccPie = useMemo(() => {
    if (!previousOccupations?.labels) return [];
    const total = previousOccupations.data.reduce((s, v) => s + v, 0) || 1;
    const colors = ['#0f766e', '#0ea5e9', '#f59e0b', '#7c3aed', '#e11d48'];
    const bgColors = ['bg-teal-700', 'bg-sky-500', 'bg-amber-500', 'bg-violet-600', 'bg-rose-600'];
    return previousOccupations.labels.slice(0, 5).map((label, i) => ({
      label, count: previousOccupations.data[i] || 0,
      hex: colors[i % colors.length], color: bgColors[i % bgColors.length],
      percent: Math.round(((previousOccupations.data[i] || 0) / total) * 100),
    }));
  }, [previousOccupations]);

  const countryPie = useMemo(() => {
    if (!lastEmploymentCountries?.labels) return [];
    const total = lastEmploymentCountries.data.reduce((s, v) => s + v, 0) || 1;
    const colors = ['#1e3a8a', '#ea580c', '#059669', '#9333ea', '#0d9488'];
    const bgColors = ['bg-blue-900', 'bg-orange-600', 'bg-emerald-600', 'bg-purple-600', 'bg-teal-600'];
    return lastEmploymentCountries.labels.slice(0, 5).map((label, i) => ({
      label, count: lastEmploymentCountries.data[i] || 0,
      hex: colors[i % colors.length], color: bgColors[i % bgColors.length],
      percent: Math.round(((lastEmploymentCountries.data[i] || 0) / total) * 100),
    }));
  }, [lastEmploymentCountries]);

  const caseColumns = useMemo(() => [
    { key: 'case_number', title: 'TRACKING ID', render: (row) => <span className="text-[12px] font-bold text-[#0b5a8c]">{row.case_number}</span> },
    { key: 'client', title: 'CLIENT NAME', render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.client ? `${row.client.first_name} ${row.client.last_name}` : 'N/A'}</span> },
    { key: 'client_type', title: 'CLIENT TYPE', render: (row) => <span className="text-[12px] text-slate-700">{row.client_type || 'N/A'}</span> },
    { key: 'status', title: 'STATUS', render: (row) => <StatusBadge status={row.status} /> },
    { key: 'created_at', title: 'CREATED ON', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.created_at?.slice(0, 10))}</span> },
    { key: 'updated_at', title: 'LAST UPDATED', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.updated_at?.slice(0, 10))}</span> },
    { key: 'id', title: 'ACTIONS', render: (row) => <Link href={route('cases.show', row.id)} className="text-[11px] font-bold text-[#0b5a8c] hover:underline">View</Link> },
  ], []);

  const referralColumns = useMemo(() => [
    { key: 'case_file', title: 'TRACKING ID', render: (row) => <span className="text-[12px] font-bold text-[#0b5a8c]">{row.case_file?.case_number || 'N/A'}</span> },
    { key: 'client', title: 'CLIENT NAME', render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A'}</span> },
    { key: 'agency', title: 'AGENCY', render: (row) => <span className="text-[12px] text-slate-700">{row.agency?.name || row.agcy_id || 'N/A'}</span> },
    { key: 'required_services', title: 'SERVICE', render: (row) => <span className="text-[12px] text-slate-700">{row.required_services || 'N/A'}</span> },
    { key: 'status', title: 'STATUS', render: (row) => <StatusBadge status={row.status} /> },
    { key: 'created_at', title: 'CREATED ON', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.created_at?.slice(0, 10))}</span> },
    { key: 'completed_at', title: 'COMPLETED ON', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.updated_at?.slice(0, 10))}</span> },
    { key: 'id', title: 'ACTIONS', render: (row) => <Link href={route('referrals.show', row.id)} className="text-[11px] font-bold text-[#0b5a8c] hover:underline">View</Link> },
  ], []);

  const clientColumns = useMemo(() => [
    { key: 'name', title: 'CLIENT NAME', render: (row) => <span className="text-[12px] font-semibold text-slate-700">{[row.first_name, row.last_name].filter(Boolean).join(' ')}</span> },
    { key: 'sex', title: 'GENDER', render: (row) => <span className="text-[12px] text-slate-700">{row.sex || 'N/A'}</span> },
    { key: 'date_of_birth', title: 'BIRTH DATE', render: (row) => <span className="text-[12px] text-slate-600">{row.date_of_birth ? formatDisplayDate(row.date_of_birth) : 'N/A'}</span> },
    { key: 'cases', title: 'TOTAL CASES', render: (row) => <span className="text-[12px] font-bold text-[#0b5a8c]">{row.case_file ? 1 : 0}</span> },
  ], []);

  function paginatorProps(paginator, page, rowsPerPage, onPageChange, onRowsPerPageChange) {
    return {
      totalRecords: paginator?.total || 0,
      startIndex: paginator?.from || 0,
      endIndex: paginator?.to || 0,
      currentPage: page,
      totalPages: paginator?.last_page || 1,
      rowsPerPage,
      onPageChange,
      onRowsPerPageChange,
      hideControlBar: true,
      hidePagination: false,
    };
  }

  const activeFromDate = useMemo(() => toCalendarDate(fromDateISO), [fromDateISO]);
  const activeToDate = useMemo(() => toCalendarDate(toDateISO), [toDateISO]);

  const handleQuickRange = (option) => {
    setQuickRange(option);
    if (option === 'CUSTOM') return;
    const range = getQuickRangeDates(option);
    setFromDateISO(range.fromISO);
    setToDateISO(range.toISO);
  };

  const resetDateRange = () => {
    setFromDateISO(defaultFromISO);
    setToDateISO(defaultToISO);
    setQuickRange('CUSTOM');
    setStatusFilter('ALL');
    setSearchValue('');
  };

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <header className="flex flex-col gap-4">
        <div>
          <h1 className={pageHeadingStyles.pageTitle}>Reports</h1>
          <p className={pageHeadingStyles.pageSubtitle}>Case manager oversight of referral outcomes and agency workload.</p>
        </div>

        <DateRangePicker
          fromDateISO={fromDateISO}
          toDateISO={toDateISO}
          quickRange={quickRange}
          onFromChange={setFromDateISO}
          onToChange={setToDateISO}
          onQuickRangeSelect={handleQuickRange}
          onReset={resetDateRange}
        />

        <div className="flex flex-col gap-2 rounded-[2px] border border-[#cfd6de] bg-[#f7f9fc] p-2.5 lg:flex-row lg:items-center">
          <div className="flex items-center gap-2 rounded-[2px] border border-[#cbd5e1] bg-white px-2 py-1.5">
            <label className="text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-600">Report Scope</label>
            <select className="h-8 min-w-[220px] border border-[#cbd5e1] bg-white px-2 text-[11px] font-bold text-slate-700 outline-none">
              <option value="ALL">All agencies</option>
            </select>
          </div>
          <button
            type="button"
            onClick={() => {
              const exportRows = [
                { section: 'KPI', metric: 'Total Cases', value: kpis.totalCases },
                { section: 'KPI', metric: 'Open Cases', value: kpis.openCases },
                { section: 'KPI', metric: 'Closed Cases', value: kpis.closedCases },
                { section: 'KPI', metric: 'Avg Days to Closure', value: kpis.avgDaysToClosure },
                { section: 'KPI', metric: 'Total Referrals', value: kpis.totalReferrals },
                { section: 'KPI', metric: 'Avg Referral Completion Days', value: avgReferralCompletion },
              ];
              exportToCsv(
                exportRows,
                [{ header: 'Section', accessor: (r) => r.section }, { header: 'Metric', accessor: (r) => r.metric }, { header: 'Value', accessor: (r) => r.value }],
                'case-manager-report.csv',
              );
            }}
            className="inline-flex h-9 items-center gap-2 border border-[#cbd5e1] bg-white px-3 text-[10px] font-bold uppercase tracking-[0.08em] text-[#0b5a8c] lg:ml-auto"
          >
            <Download className="h-3.5 w-3.5" />
            Export
          </button>
        </div>
      </header>

      <section className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total Cases" value={`${kpis.totalCases}`} accent="border-l-[#1e3a8a]" />
        <MetricCard label="Open Cases" value={`${kpis.openCases}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]" />
        <MetricCard label="Closed Cases" value={`${kpis.closedCases}`} accent="border-l-[#0b7a75]" />
        <MetricCard label="Avg Days to Case Closure" value={kpis.avgDaysToClosure.toFixed(1)} accent="border-l-[#0b5a8c]" description="Average days from case creation to case closure" />
      </section>

      <h2 className={`mb-3 mt-8 ${pageHeadingStyles.sectionTitle}`}>Cases Breakdown</h2>
      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 mb-6">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Cases By Status</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={caseStatusPie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {caseStatusPie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className={`h-2.5 w-2.5 rounded-full shrink-0 ${statusColorMap[item.label] || 'bg-slate-400'}`} />
                  {item.label}
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Client Type Distribution</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={clientTypePie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {clientTypePie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className={`h-2.5 w-2.5 rounded-full shrink-0 ${item.color || 'bg-blue-900'}`} />
                  {item.label}
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
          </div>
        </article>
      </section>

      <h2 className={`mb-3 mt-8 ${pageHeadingStyles.sectionTitle}`}>Demographic Data</h2>
      <section className="grid grid-cols-1 gap-4 md:grid-cols-3 mb-6">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Gender Distribution</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={genderPie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {genderPie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ backgroundColor: item.hex }} />
                  {item.label}
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
            {genderPie.length === 0 && <p className="text-[11px] font-semibold text-slate-500">No gender data available.</p>}
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Client Type Distribution</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={clientTypePie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {clientTypePie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ backgroundColor: item.hex }} />
                  {item.label}
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
            {clientTypePie.length === 0 && <p className="text-[11px] font-semibold text-slate-500">No client type data available.</p>}
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Age Group Distribution</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={agePie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {agePie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ backgroundColor: item.hex }} />
                  {item.label}
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
            {agePie.length === 0 && <p className="text-[11px] font-semibold text-slate-500">No age data available.</p>}
          </div>
        </article>
      </section>

      <section className="mb-6">
        <TrendChart
          title="Cases Over Time"
          precomputedData={casesOverTime?.datasets?.[0] ? { labels: casesOverTime.labels, data: casesOverTime.datasets[0].data } : undefined}
        />
      </section>

      <h2 className={`mb-3 mt-8 ${pageHeadingStyles.sectionTitle}`}>Client Insights</h2>
      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 mb-6">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Previous Occupations</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={prevOccPie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {prevOccPie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className={`h-2.5 w-2.5 rounded-full shrink-0 ${item.color}`} />
                  <span className="truncate max-w-[180px]" title={item.label}>{item.label}</span>
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
            {prevOccPie.length === 0 && <p className="text-[11px] font-semibold text-slate-500">No occupation data available.</p>}
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Last Employment Countries</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={countryPie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {countryPie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className={`h-2.5 w-2.5 rounded-full shrink-0 ${item.color}`} />
                  <span className="truncate max-w-[180px]" title={item.label}>{item.label}</span>
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
            {countryPie.length === 0 && <p className="text-[11px] font-semibold text-slate-500">No country data available.</p>}
          </div>
        </article>
      </section>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 mb-6">
        <article className="rounded-[2px] border border-[#cbd5e1] bg-[#f8fafc] p-4">
          <p className="text-[10px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Top Previous Occupation</p>
          <p className="mt-1 text-[18px] font-black text-slate-800 leading-tight">{topOccupation?.label || 'N/A'}</p>
          <p className="mt-1 text-[12px] font-semibold text-slate-500">{topOccupation?.value || 0} case(s)</p>
        </article>
        <article className="rounded-[2px] border border-[#cbd5e1] bg-[#f8fafc] p-4">
          <p className="text-[10px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Top Last Employment Country</p>
          <p className="mt-1 text-[18px] font-black text-slate-800 leading-tight">{topCountry?.label || 'N/A'}</p>
          <p className="mt-1 text-[12px] font-semibold text-slate-500">{topCountry?.value || 0} case(s)</p>
        </article>
      </section>

      <h2 className={`mb-3 mt-8 ${pageHeadingStyles.sectionTitle}`}>Referrals Breakdown</h2>
      <section className="grid grid-cols-1 gap-3 md:grid-cols-2 mb-4">
        <MetricCard label="Total Referrals" value={`${kpis.totalReferrals}`} accent="border-l-[#0b5a8c]" />
        <MetricCard label="Avg Referral Completion Days" value={`${avgReferralCompletion.toFixed(1)} Days`} accent="border-l-[#0b7a75]" trailing={<div className="h-[4px] w-8 rounded-full bg-[#0b7a75]" />} />
      </section>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-3 mb-6">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Referrals By Status</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={referralStatusPie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {referralStatusPie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className={`h-2.5 w-2.5 rounded-full shrink-0 ${statusColorMap[item.label] || 'bg-slate-400'}`} />
                  {item.label}
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4 flex flex-col">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Referrals By Agency</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={agencyBarData} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2 overflow-y-auto max-h-[120px] pr-2">
            {agencyBarData.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ backgroundColor: item.hex }} />
                  <span className="truncate max-w-[150px]" title={item.label}>{item.label}</span>
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4 flex items-center justify-center">
          <div className="flex flex-col items-center gap-4 bg-[#eff6ff] px-6 py-5 text-[#1e3a8a] shadow-sm w-full">
            <TrendingUp className="h-8 w-8 opacity-70" />
            <div className="text-center min-w-0">
              <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[#1d4ed8]">Most Requested Service</p>
              <p className="truncate text-[20px] font-black leading-tight" title={mostRequestedService?.name}>{mostRequestedService?.name || 'N/A'}</p>
              <p className="text-[13px] text-[#1d4ed8]">{mostRequestedService?.value || 0} referrals</p>
            </div>
          </div>
        </article>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-3 mb-6">
        <article className="flex items-center gap-4 bg-[#0b3f69] px-6 py-5 text-white shadow-sm">
          <div className="flex h-12 w-12 items-center justify-center rounded-[2px] bg-white/10">
            <Building2 className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[#8cc7e7]">Most Active Agency</p>
            <p className="text-3xl font-black leading-tight">{mostActiveAgency?.name || 'N/A'}</p>
            <p className="text-[13px] text-[#bee1f3]">{mostActiveAgency?.value || 0} referrals</p>
          </div>
        </article>

        <article className="flex items-center gap-4 bg-[#9de8db] px-6 py-5 text-[#045f68] shadow-sm">
          <div className="flex h-12 w-12 items-center justify-center rounded-[2px] bg-white/40">
            <Hourglass className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[#0f7f7c]">Average Referral Completion</p>
            <p className="text-3xl font-black leading-tight">{avgReferralCompletion.toFixed(1)} Days</p>
            <p className="text-[13px] text-[#0f7f7c]">From referral creation to completion</p>
          </div>
          <TrendingUp className="ml-auto h-5 w-5 opacity-70" />
        </article>

        <article className="flex items-center gap-4 bg-[#eff6ff] px-6 py-5 text-[#1e3a8a] shadow-sm">
          <div className="flex h-12 w-12 items-center justify-center rounded-[2px] bg-white/70">
            <TrendingUp className="h-6 w-6" />
          </div>
          <div className="min-w-0">
            <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[#1d4ed8]">Most Requested Service</p>
            <p className="truncate text-[24px] font-black leading-tight" title={mostRequestedService?.name}>{mostRequestedService?.name || 'N/A'}</p>
            <p className="text-[13px] text-[#1d4ed8]">{mostRequestedService?.value || 0} referrals</p>
          </div>
        </article>
      </section>

      <section className="border border-[#cbd5e1] bg-white">
        <div className="flex items-center justify-between border-b border-[#cbd5e1] px-4 py-3">
          <h3 className={pageHeadingStyles.sectionTitle}>Managed Cases</h3>
          <button className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700">
            <Download className="h-3.5 w-3.5" />
            Export CSV
          </button>
        </div>
        <UnifiedTable
          variant="embedded"
          data={managedCases?.data || []}
          columns={caseColumns}
          keyExtractor={(row) => row.id}
          {...paginatorProps(managedCases, casesCurrentPage, casesRowsPerPage,
            (page) => setCasesCurrentPage(page),
            (rows) => { setCasesRowsPerPage(rows); setCasesCurrentPage(1); },
          )}
        />
      </section>

      <section className="border border-[#cbd5e1] bg-white">
        <div className="flex items-center justify-between border-b border-[#cbd5e1] px-4 py-3">
          <h3 className={pageHeadingStyles.sectionTitle}>Managed Referrals</h3>
        </div>
        <UnifiedTable
          variant="embedded"
          data={managedReferrals?.data || []}
          columns={referralColumns}
          keyExtractor={(row) => row.id}
          {...paginatorProps(managedReferrals, referralsCurrentPage, referralsRowsPerPage,
            (page) => setReferralsCurrentPage(page),
            (rows) => { setReferralsRowsPerPage(rows); setReferralsCurrentPage(1); },
          )}
        />
      </section>

      <section className="border border-[#cbd5e1] bg-white">
        <div className="flex items-center justify-between border-b border-[#cbd5e1] px-4 py-3">
          <h3 className={pageHeadingStyles.sectionTitle}>Managed Clients</h3>
        </div>
        <UnifiedTable
          variant="embedded"
          data={managedClients?.data || []}
          columns={clientColumns}
          keyExtractor={(row) => row.id}
          {...paginatorProps(managedClients, clientsCurrentPage, clientsRowsPerPage,
            (page) => setClientsCurrentPage(page),
            (rows) => { setClientsRowsPerPage(rows); setClientsCurrentPage(1); },
          )}
        />
      </section>
    </div>
  );
}

function AgencyReports({
  kpis, referralStatusDistribution, avgReferralCompletion, mostRequestedService,
  managedReferrals,
}) {
  const defaultFromISO = new Date().toISOString().slice(0, 10);
  const defaultToISO = new Date().toISOString().slice(0, 10);
  const fromDate = defaultFromISO;
  const toDate = defaultToISO;

  const referralStatusPie = useMemo(() => toPieFormat(referralStatusDistribution), [referralStatusDistribution]);
  const kpiTotal = (kpis?.totalReferrals || 0);
  const kpiCompleted = referralStatusPie.find((s) => s.label === 'COMPLETED')?.count || 0;
  const kpiPending = referralStatusPie.find((s) => s.label === 'PENDING')?.count || 0;
  const completionRate = kpiTotal > 0 ? Math.round((kpiCompleted / kpiTotal) * 100) : 0;

  const referralColumns = useMemo(() => [
    { key: 'case_file', title: 'TRACKING ID', render: (row) => <span className="text-[12px] font-bold text-[#0b5a8c]">{row.case_file?.case_number || 'N/A'}</span> },
    { key: 'client', title: 'CLIENT NAME', render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A'}</span> },
    { key: 'required_services', title: 'SERVICE', render: (row) => <span className="text-[12px] text-slate-700">{row.required_services || 'N/A'}</span> },
    { key: 'status', title: 'STATUS', render: (row) => <StatusBadge status={row.status} /> },
    { key: 'created_at', title: 'CREATED ON', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.created_at?.slice(0, 10))}</span> },
    { key: 'id', title: 'ACTIONS', render: (row) => <Link href={route('referrals.show', row.id)} className="text-[11px] font-bold text-[#0b5a8c] hover:underline">View</Link> },
  ], []);

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <header>
        <h1 className={pageHeadingStyles.pageTitle}>Reports</h1>
        <p className={pageHeadingStyles.pageSubtitle}>Agency performance overview</p>
      </header>

      <section className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
        <MetricCard label="Total Referrals" value={`${kpiTotal}`} accent="border-l-[#0b5a8c]" />
        <MetricCard label="Completed" value={`${kpiCompleted}`} accent="border-l-[#0b7a75]" />
        <MetricCard label="Pending" value={`${kpiPending}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]" />
        <MetricCard label="Avg Completion Time (days)" value={avgReferralCompletion.toFixed(1)} accent="border-l-[#0b5a8c]" description="From Referral Sent to Referral Completion" />
        <MetricCard label="Completion Rate" value={`${completionRate}%`} accent="border-l-[#0b7a75]" trailing={<div className="h-[4px] w-8 rounded-full bg-[#0b7a75]" />} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.05fr_2.2fr]">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referrals By Status</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={referralStatusPie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {referralStatusPie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className={`h-2.5 w-2.5 rounded-full shrink-0 ${statusColorMap[item.label] || 'bg-slate-400'}`} />
                  {item.label}
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Most Requested Service</h3>
          <div className="flex items-center gap-4 bg-[#0b3f69] px-6 py-5 text-white shadow-sm">
            <div className="flex h-12 w-12 items-center justify-center rounded-[2px] bg-white/10 shrink-0">
              <TrendingUp className="h-6 w-6" />
            </div>
            <div className="min-w-0">
              <p className="text-2xl font-black leading-tight truncate" title={mostRequestedService?.name}>{mostRequestedService?.name || 'N/A'}</p>
              <p className="text-[13px] text-[#bee1f3]">{mostRequestedService?.value || 0} referrals</p>
            </div>
          </div>
        </article>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="flex items-center gap-4 bg-[#0b3f69] px-6 py-5 text-white shadow-sm">
          <div className="flex h-12 w-12 items-center justify-center rounded-[2px] bg-white/10">
            <Building2 className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[#8cc7e7]">Most Requested Service</p>
            <p className="text-3xl font-black leading-tight">{mostRequestedService?.name || 'N/A'}</p>
            <p className="text-[13px] text-[#bee1f3]">{mostRequestedService?.value || 0} referrals</p>
          </div>
        </article>
        <article className="flex items-center gap-4 bg-[#9de8db] px-6 py-5 text-[#045f68] shadow-sm">
          <div className="flex h-12 w-12 items-center justify-center rounded-[2px] bg-white/40">
            <Hourglass className="h-6 w-6" />
          </div>
          <div>
            <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[#0f7f7c]">Average Time Per Referral</p>
            <p className="text-3xl font-black leading-tight">{avgReferralCompletion.toFixed(1)} Days</p>
            <p className="text-[13px] text-[#0f7f7c]">From referral sent to completion</p>
          </div>
          <TrendingUp className="ml-auto h-5 w-5 opacity-70" />
        </article>
      </section>

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
  agencyWorkload, caseTypeDistribution, kpis,
}) {
  const referralStatusPie = useMemo(() => toPieFormat(referralStatusDistribution), [referralStatusDistribution]);
  const caseTypePie = useMemo(() => toPieFormat(caseTypeDistribution), [caseTypeDistribution]);
  const workloadBar = useMemo(() => buildBarData(agencyWorkload), [agencyWorkload]);

  const trendData = useMemo(() => {
    if (!caseTrends?.labels) return { labels: [], series: [] };
    return { labels: caseTrends.labels, series: caseTrends.data };
  }, [caseTrends]);

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <header>
        <h1 className={pageHeadingStyles.pageTitle}>Reports</h1>
        <p className={pageHeadingStyles.pageSubtitle}>System-wide performance metrics and trends.</p>
      </header>

      <section className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total Cases" value={`${overview?.totalCases || 0}`} accent="border-l-[#1e3a8a]" />
        <MetricCard label="Open Cases" value={`${overview?.openCases || 0}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]" />
        <MetricCard label="Total Referrals" value={`${overview?.totalReferrals || 0}`} accent="border-l-[#0b7a75]" />
        <MetricCard label="Active Agencies" value={`${overview?.activeAgencies || 0}`} accent="border-l-[#0b5a8c]" />
      </section>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 mb-6">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Referral Status Distribution</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={referralStatusPie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {referralStatusPie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className={`h-2.5 w-2.5 rounded-full shrink-0 ${statusColorMap[item.label] || 'bg-slate-400'}`} />
                  {item.label}
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Case Type Distribution</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={caseTypePie} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2">
            {caseTypePie.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ backgroundColor: item.hex }} />
                  {item.label}
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
          </div>
        </article>
      </section>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 mb-6">
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className="mb-4 text-[14px] font-bold text-blue-900">Agency Workload</h3>
          <div className="flex items-center justify-center">
            <SvgPieChart data={workloadBar} className="w-32 h-32" />
          </div>
          <div className="mt-4 space-y-2 overflow-y-auto max-h-[180px] pr-2">
            {workloadBar.map((item) => (
              <div key={item.label} className="flex items-center justify-between text-[12px]">
                <span className="inline-flex items-center gap-2 text-slate-600">
                  <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ backgroundColor: item.hex }} />
                  <span className="truncate max-w-[200px]" title={item.label}>{item.label}</span>
                </span>
                <span className="font-bold text-slate-700">{item.percent}%</span>
              </div>
            ))}
          </div>
        </article>

        <article className="border border-[#cbd5e1] bg-white p-4 flex items-center justify-center">
          <div className="text-center">
            <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500 mb-2">System Overview</p>
            <div className="grid grid-cols-2 gap-4">
              <div className="bg-[#f8fafc] p-4 rounded border border-[#cbd5e1]">
                <p className="text-[10px] font-bold text-slate-500">Total Cases</p>
                <p className="text-2xl font-black text-blue-900">{kpis?.totalCases || overview?.totalCases || 0}</p>
              </div>
              <div className="bg-[#f8fafc] p-4 rounded border border-[#cbd5e1]">
                <p className="text-[10px] font-bold text-slate-500">Total Referrals</p>
                <p className="text-2xl font-black text-blue-900">{kpis?.totalReferrals || overview?.totalReferrals || 0}</p>
              </div>
              <div className="bg-[#f8fafc] p-4 rounded border border-[#cbd5e1]">
                <p className="text-[10px] font-bold text-slate-500">Active Agencies</p>
                <p className="text-2xl font-black text-blue-900">{overview?.activeAgencies || 0}</p>
              </div>
              <div className="bg-[#f8fafc] p-4 rounded border border-[#cbd5e1]">
                <p className="text-[10px] font-bold text-slate-500">Pending Referrals</p>
                <p className="text-2xl font-black text-amber-600">{overview?.pendingReferrals || 0}</p>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section>
        <article className="border border-[#cbd5e1] bg-white p-4">
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Case Trends (12 Months)</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-[12px]">
              <thead>
                <tr className="border-b border-[#cbd5e1]">
                  <th className="py-2 px-3 font-bold text-slate-600">Month</th>
                  <th className="py-2 px-3 font-bold text-slate-600">Cases Created</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#cbd5e1]">
                {(caseTrends?.labels || []).map((label, i) => (
                  <tr key={label} className="hover:bg-slate-50">
                    <td className="py-2 px-3 text-slate-700">{label}</td>
                    <td className="py-2 px-3 font-bold text-[#0b5a8c]">{(caseTrends?.data || [])[i] || 0}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </article>
      </section>
    </div>
  );
}

export default function ReportsIndex(props) {
  const {
    role, kpis, caseStatusDistribution, casesOverTime,
    genderDistribution, clientTypeDistribution, ageGroupDistribution,
    previousOccupations, lastEmploymentCountries,
    topOccupation, topCountry,
    referralStatusDistribution, referralAgencyDistribution,
    mostActiveAgency, avgReferralCompletion, mostRequestedService,
    overview, caseTrends, agencyWorkload, caseTypeDistribution,
    managedCases, managedReferrals, managedClients,
  } = props;

  if (role === 'AGENCY') {
    return (
      <AppLayout title="Reports">
        <Head title="Reports" />
        <AgencyReports
          kpis={kpis}
          referralStatusDistribution={referralStatusDistribution}
          avgReferralCompletion={avgReferralCompletion || 0}
          mostRequestedService={mostRequestedService}
          managedReferrals={managedReferrals}
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
          caseTypeDistribution={caseTypeDistribution}
          kpis={kpis}
        />
      </AppLayout>
    );
  }

  return (
    <AppLayout title="Reports">
      <Head title="Reports" />
      <CaseManagerReports
        kpis={kpis}
        caseStatusDistribution={caseStatusDistribution}
        casesOverTime={casesOverTime}
        genderDistribution={genderDistribution}
        clientTypeDistribution={clientTypeDistribution}
        ageGroupDistribution={ageGroupDistribution}
        previousOccupations={previousOccupations}
        lastEmploymentCountries={lastEmploymentCountries}
        topOccupation={topOccupation}
        topCountry={topCountry}
        referralStatusDistribution={referralStatusDistribution}
        referralAgencyDistribution={referralAgencyDistribution}
        mostActiveAgency={mostActiveAgency}
        avgReferralCompletion={avgReferralCompletion || 0}
        mostRequestedService={mostRequestedService}
        managedCases={managedCases}
        managedReferrals={managedReferrals}
        managedClients={managedClients}
      />
    </AppLayout>
  );
}
