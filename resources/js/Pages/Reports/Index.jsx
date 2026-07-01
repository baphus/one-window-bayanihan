import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo, useCallback, useEffect, useRef } from 'react';
import { FileDown, ChevronDown, ChevronRight } from 'lucide-react';
import { Doughnut, Bar } from 'react-chartjs-2';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';
import { pageHeadingStyles, COLORS } from '@/Components/Reports/pageHeadingStyles';
import SvgPieChart from '@/Components/Reports/SvgPieChart';
import TrendChart from '@/Components/Reports/TrendChart';
import DateRangePicker, { getQuickRangeDates } from '@/Components/Reports/DateRangePicker';
import CaseStatusPieChart from '@/Components/Reports/CaseStatusPieChart';
import AiInsightPanel from '@/Components/Reports/AiInsightPanel';

import KpiCardsSection from './sections/KpiCardsSection';
import StatusDistributionSection from './sections/StatusDistributionSection';
import AgencyScorecardSection from './sections/AgencyScorecardSection';
import CycleTimeSection from './sections/CycleTimeSection';
import GeographicSection from './sections/GeographicSection';
import CategorySection from './sections/CategorySection';
import EmploymentSection from './sections/EmploymentSection';
import ManagedReferralsSection from './sections/ManagedReferralsSection';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const doughnutOptions = {
  responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '55%',
};

const barOptions = {
  responsive: true, maintainAspectRatio: false, indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
    y: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
};

function makeBarData(labels, data, color, colors) {
  return !labels ? null : { labels, datasets: [{ label: 'Referrals', data, backgroundColor: colors || color || '#1e3a8a', borderRadius: 3, barThickness: 18 }] };
}
function makeIssueData(items) {
  return !items?.length ? null : { labels: items.map(c => c.name), datasets: [{ label: 'Cases', data: items.map(c => c.count), backgroundColor: items.map(c => c.color), borderRadius: 3, barThickness: 18 }] };
}
function ChartArticle({ title, desc, data, options, emptyText, height = 'h-56' }) {
  return (<article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
    <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>{title}</h3>
    {desc && <p className="mb-3 text-[11px] text-slate-500">{desc}</p>}
    {data ? <div className={height}><Bar data={data} options={options || barOptions} /></div>
      : <p className="py-8 text-center text-[13px] text-slate-400">{emptyText}</p>}
  </article>);
}

function toPieFormat(distribution) {
  if (!distribution?.labels) return [];
  const total = distribution.data.reduce((s, v) => s + v, 0) || 1;
  const colors = distribution.colors || COLORS.chartPalette;
  return distribution.labels.map((label, i) => ({ label, count: distribution.data[i] || 0, hex: colors[i % colors.length], color: '', percent: Math.round(((distribution.data[i] || 0) / total) * 100) }));
}

function SectionAccordion({ title, defaultOpen = false, children }) {
  const [open, setOpen] = useState(defaultOpen);
  return (<div className="border bg-white shadow-sm" style={{ borderColor: COLORS.border }}>
    <button type="button" onClick={() => setOpen(!open)}
      className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-slate-50">
      <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{title}</h3>
      {open ? <ChevronDown className="h-3.5 w-3.5 text-slate-400" /> : <ChevronRight className="h-3.5 w-3.5 text-slate-400" />}
    </button>
    {open && <div className="border-t border-[#e2e8f0] p-4">{children}</div>}
  </div>);
}

function useDateRange(initialFrom, initialTo) {
  const [fromDateISO, setFromDateISO] = useState(initialFrom || new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10));
  const [toDateISO, setToDateISO] = useState(initialTo || new Date().toISOString().slice(0, 10));
  const [quickRange, setQuickRange] = useState('1_YEAR');
  useEffect(() => {
    const qs = new URLSearchParams({ from: fromDateISO, to: toDateISO }).toString();
    if (window.location.search.slice(1) !== qs) router.get(route('reports.index') + '?' + qs, {}, { preserveState: true, replace: true });
  }, [fromDateISO, toDateISO]);
  const handleQuickRange = useCallback((option) => { setQuickRange(option); if (option === 'CUSTOM') return; const r = getQuickRangeDates(option); setFromDateISO(r.fromISO); setToDateISO(r.toISO); }, []);
  const resetDateRange = useCallback(() => { const r = getQuickRangeDates('1_YEAR'); setFromDateISO(r.fromISO); setToDateISO(r.toISO); setQuickRange('1_YEAR'); }, []);
  return { fromDateISO, toDateISO, quickRange, setFromDateISO, setToDateISO, handleQuickRange, resetDateRange };
}

function Header({ fromDateISO, toDateISO, quickRange, setFromDateISO, setToDateISO, handleQuickRange, resetDateRange, subtitle }) {
  const b = "inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700";
  return (<header className="flex flex-col gap-4">
    <div><h1 className={pageHeadingStyles.pageTitle}>Reports</h1><p className={pageHeadingStyles.pageSubtitle}>{subtitle}</p></div>
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <DateRangePicker fromDateISO={fromDateISO} toDateISO={toDateISO} quickRange={quickRange}
        onFromChange={setFromDateISO} onToChange={setToDateISO} onQuickRangeSelect={handleQuickRange} onReset={resetDateRange} />
      <div className="flex items-center gap-3">
        <AiInsightPanel fromDate={fromDateISO} toDate={toDateISO} />
        <button type="button" onClick={() => window.open(route('reports.export-pdf', { from: fromDateISO, to: toDateISO }))} className={b}><FileDown className="h-3.5 w-3.5" /> Export PDF</button>
        <button type="button" onClick={() => window.open(route('reports.export-excel', { from: fromDateISO, to: toDateISO }))} className={b}><FileDown className="h-3.5 w-3.5" /> Export Excel</button>
      </div>
    </div>
  </header>);
}

function CaseManagerReports({
  kpis, referralStatusDistribution, referralAgencyDistribution,
  casesOverTime, genderDistribution, clientTypeDistribution,
  ageGroupDistribution, managedReferrals,
  cycleTimeDistribution, referralAging, agencyScorecard, geographicDistribution,
  categoryDistribution, employmentDistribution, employmentPositionBreakdown,
  caseStatusDistribution, caseIssueDistribution,
  from: initialFrom, to: initialTo,
}) {
  const dr = useDateRange(initialFrom, initialTo);

  const genderPie = useMemo(() => toPieFormat(genderDistribution), [genderDistribution]);
  const clientTypePie = useMemo(() => toPieFormat(clientTypeDistribution), [clientTypeDistribution]);
  const agePie = useMemo(() => toPieFormat(ageGroupDistribution), [ageGroupDistribution]);

  const agencyChartData = makeBarData(referralAgencyDistribution?.labels, referralAgencyDistribution?.data, undefined, referralAgencyDistribution?.colors || ['#1e3a8a']);
  const agingChartData = makeBarData(referralAging?.labels, referralAging?.data, undefined, referralAging?.colors || [COLORS.success, '#84cc16', COLORS.warning, COLORS.danger]);
  const caseIssueChartData = makeIssueData(caseIssueDistribution);

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <Header {...dr} subtitle="Referral operations and agency performance." />
      <KpiCardsSection kpis={kpis} />
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr_1fr]">
        <SectionAccordion title="Referral Status" defaultOpen>
          <StatusDistributionSection referralStatusDistribution={referralStatusDistribution} />
        </SectionAccordion>
        <CaseStatusPieChart data={caseStatusDistribution} />
        <SectionAccordion title="Agency Workload" defaultOpen>
          {agencyChartData ? <div className="h-56"><Bar data={agencyChartData} options={barOptions} /></div>
            : <p className="py-8 text-center text-[13px] text-slate-400">No agency workload data available.</p>}
        </SectionAccordion>
      </section>
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <CycleTimeSection cycleTimeDistribution={cycleTimeDistribution} />
        <ChartArticle title="Referral Aging" desc="How long active referrals have been waiting" data={agingChartData} emptyText="No active referrals pending." />
      </section>
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[2fr_1fr]">
        <AgencyScorecardSection agencyScorecard={agencyScorecard} />
        <section><TrendChart title="Cases Over Time" data={casesOverTime} /></section>
      </section>
      <GeographicSection geographicDistribution={geographicDistribution} />
      <EmploymentSection employmentDistribution={employmentDistribution} employmentPositionBreakdown={employmentPositionBreakdown} />
      <CategorySection categoryDistribution={categoryDistribution} />
      <ChartArticle title="Case Issue Distribution" data={caseIssueChartData} emptyText="No issue data available." />
      <section>
        <h2 className={`mb-3 ${pageHeadingStyles.sectionTitle}`}>Client Demographics</h2>
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
          {[
            { label: 'Gender', data: genderPie },
            { label: 'Client Type', data: clientTypePie },
            { label: 'Age Group', data: agePie, slice: 3 },
          ].map(({ label, data, slice }) => (
            <article key={label} className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
              <h3 className="mb-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">{label}</h3>
              <div className="flex items-center gap-4">
                <div className="h-16 w-16 shrink-0"><SvgPieChart data={data} className="w-16 h-16" /></div>
                <div className="space-y-1">
                  {(slice ? data.slice(0, slice) : data).map((d) => (
                    <div key={d.label} className="flex items-center gap-2 text-[11px]">
                      <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: d.hex }} />
                      <span className="text-slate-600">{d.label}</span>
                      <span className="font-bold text-slate-800">{d.percent}%</span>
                    </div>
                  ))}
                </div>
              </div>
            </article>
          ))}
        </div>
      </section>

      <ManagedReferralsSection managedReferrals={managedReferrals} />
    </div>
  );
}

function AgencyReports({
  kpis, referralStatusDistribution, referralTrends,
  avgReferralCompletion, managedReferrals,
  cycleTimeDistribution, agencyScorecard,
  categoryDistribution, caseStatusDistribution,
  from: initialFrom, to: initialTo,
}) {
  const dr = useDateRange(initialFrom, initialTo);

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <Header {...dr} subtitle="Agency performance overview." />
      <KpiCardsSection kpis={kpis} avgReferralCompletion={avgReferralCompletion} />
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr_1fr]">
        <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referral Status</h3>
          <StatusDistributionSection referralStatusDistribution={referralStatusDistribution} />
        </article>
        <CaseStatusPieChart data={caseStatusDistribution} />
        <div className="flex flex-col gap-3">
          <TrendChart title="Referral Trends" data={referralTrends} />
        </div>
      </section>
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <CycleTimeSection cycleTimeDistribution={cycleTimeDistribution} />
        <AgencyScorecardSection agencyScorecard={agencyScorecard} />
      </section>
      <CategorySection categoryDistribution={categoryDistribution} />
      <ManagedReferralsSection managedReferrals={managedReferrals} />
    </div>
  );
}

function AdminReports({
  overview, caseTrends, referralStatusDistribution,
  agencyWorkload, clientTypeDistribution,
  cycleTimeDistribution, referralAging, geographicDistribution, agencyScorecard,
  categoryDistribution, employmentDistribution, employmentPositionBreakdown,
  caseStatusDistribution, referralTypeDistribution, caseIssueDistribution,
  from: initialFrom, to: initialTo,
}) {
  const dr = useDateRange(initialFrom, initialTo);

  const clientTypePie = useMemo(() => toPieFormat(clientTypeDistribution), [clientTypeDistribution]);
  const referralTypePie = useMemo(() => toPieFormat(referralTypeDistribution), [referralTypeDistribution]);

  const adminAgingData = makeBarData(referralAging?.labels, referralAging?.data, undefined, referralAging?.colors || [COLORS.success, '#84cc16', COLORS.warning, COLORS.danger]);
  const workloadChartData = makeBarData(agencyWorkload?.labels, agencyWorkload?.data, '#1e3a8a');
  const caseTrendsChartData = !caseTrends?.labels ? null : { labels: caseTrends.labels, datasets: [{ label: 'Cases', data: caseTrends.data, borderColor: COLORS.accent, backgroundColor: `${COLORS.accent}1A`, fill: true, tension: 0.3 }] };
  const adminCaseIssueData = makeIssueData(caseIssueDistribution);

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <Header {...dr} subtitle="System-wide performance metrics and trends." />
      <KpiCardsSection overview={overview} />
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
          <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referral Status</h3>
          <StatusDistributionSection referralStatusDistribution={referralStatusDistribution} />
        </article>
        {[{ label: 'Referral Type', pie: referralTypePie }, { label: 'Client Type', pie: clientTypePie }].map(({ label, pie }) => (
          <article key={label} className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
            <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>{label}</h3>
            <div className="flex items-center gap-6">
              <div className="h-28 w-28 shrink-0">
                <Doughnut data={{
                  labels: pie.map((s) => s.label),
                  datasets: [{ data: pie.map((s) => s.count), backgroundColor: pie.map((s) => s.hex), borderWidth: 0 }],
                }} options={doughnutOptions} />
              </div>
              <div className="flex-1 space-y-1.5">
                {pie.map((s) => (
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
        ))}
        <CaseStatusPieChart data={caseStatusDistribution} />
      </section>
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <ChartArticle title="Agency Workload" data={workloadChartData} emptyText="No agency workload data available." height="h-64" />
        <GeographicSection geographicDistribution={geographicDistribution} />
      </section>
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <CycleTimeSection cycleTimeDistribution={cycleTimeDistribution} />
        <ChartArticle title="Referral Aging" desc="How long active referrals have been waiting" data={adminAgingData} emptyText="No active referrals pending." />
      </section>
      <EmploymentSection employmentDistribution={employmentDistribution} employmentPositionBreakdown={employmentPositionBreakdown} />
      <CategorySection categoryDistribution={categoryDistribution} />
      <ChartArticle title="Case Issue Distribution" data={adminCaseIssueData} emptyText="No issue data available." />
      <section className="mb-6">
        <TrendChart title="Case Trends (12 Months)" data={caseTrendsChartData} />
      </section>
    </div>
  );
}

const LAZY_KEYS_BY_ROLE = {
  CASE_MANAGER: [
    'referralStatusDistribution', 'referralAgencyDistribution', 'casesOverTime',
    'genderDistribution', 'clientTypeDistribution', 'ageGroupDistribution',
    'cycleTimeDistribution', 'referralAging', 'agencyScorecard', 'geographicDistribution',
    'categoryDistribution', 'employmentDistribution', 'employmentPositionBreakdown',
    'caseStatusDistribution', 'caseIssueDistribution',
  ],
  ADMIN: [
    'caseTrends', 'referralStatusDistribution', 'agencyWorkload', 'clientTypeDistribution',
    'cycleTimeDistribution', 'referralAging', 'geographicDistribution', 'agencyScorecard',
    'categoryDistribution', 'employmentDistribution', 'employmentPositionBreakdown',
    'caseStatusDistribution', 'referralTypeDistribution', 'caseIssueDistribution',
  ],
  AGENCY: [
    'referralStatusDistribution', 'referralTrends', 'cycleTimeDistribution',
    'agencyScorecard', 'categoryDistribution', 'caseStatusDistribution',
  ],
};

export default function ReportsIndex(props) {
  const { role, from: f, to: t, ...rest } = props;
  const from = f || (new URLSearchParams(window.location.search)).get('from') || undefined;
  const to = t || (new URLSearchParams(window.location.search)).get('to') || undefined;
  const allProps = { ...rest, from, to, avgReferralCompletion: rest.avgReferralCompletion || 0 };
  const Comp = role === 'AGENCY' ? AgencyReports : role === 'ADMIN' ? AdminReports : CaseManagerReports;

  // Batch-fetch all Inertia::lazy() props on mount in a single request
  const fetchedRef = useRef(false);
  useEffect(() => {
    if (fetchedRef.current) return;
    fetchedRef.current = true;
    const keys = LAZY_KEYS_BY_ROLE[role] || LAZY_KEYS_BY_ROLE.CASE_MANAGER;
    router.reload({ only: keys, preserveState: true, preserveScroll: true });
  }, []);

  return (
    <AppLayout title="Reports"><Head title="Reports" />
      <Comp {...allProps} />
    </AppLayout>
  );
}
