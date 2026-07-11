import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Users, Target, Clock, GitFork, CheckCircle2, Hourglass, ClipboardCheck } from 'lucide-react';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';
import { pageHeadingStyles, COLORS } from '@/Components/Reports/pageHeadingStyles';
import MetricCard from '@/Components/Reports/MetricCard';
import TopServiceRequestedCard from '@/Components/Reports/TopServiceRequestedCard';
import OverviewBanner from '@/Components/Reports/OverviewBanner';
import AttentionSection from '@/Components/Reports/AttentionSection';
import ClientSnapshotCard from '@/Components/Reports/ClientSnapshotCard';
import AgencyWorkloadChart from '@/Components/Reports/AgencyWorkloadChart';
import AvgCompletionCard from '@/Components/Reports/AvgCompletionCard';
import OverdueReferralsCard from '@/Components/Reports/OverdueReferralsCard';
import TrendIndicator from '@/Components/Reports/TrendIndicator';
import Sparkline from '@/Components/Reports/Sparkline';
import DateRangePicker from '@/Components/Reports/DateRangePicker';
import CaseStatusPieChart from '@/Components/Reports/CaseStatusPieChart';
import DateScopeSelect from '@/Components/Reports/DateScopeSelect';
import ReportTabBar from '@/Components/Reports/ReportTabBar';
import ProvinceCityFilter from '@/Components/Reports/ProvinceCityFilter';
import ExportButtons from '@/Components/Reports/ExportButtons';
import { suppressCount } from '@/Components/Reports/suppressCount';
import { useReportFilters } from '@/Hooks/useReportFilters';
import { useLazyProp } from '@/Hooks/useLazyProp';
import AgencyScorecardSection from '@/Pages/Reports/sections/AgencyScorecardSection';
import StatusDistributionSection from '@/Pages/Reports/sections/StatusDistributionSection';
import CycleTimeSection from '@/Pages/Reports/sections/CycleTimeSection';
import GeographicMapSection from '@/Pages/Reports/sections/GeographicMapSection';
import CategorySection from '@/Pages/Reports/sections/CategorySection';
import EmploymentSection from '@/Pages/Reports/sections/EmploymentSection';
import LazyTrendChart from '@/Pages/Reports/sections/LazyTrendChart';
import LazyChartArticle from '@/Pages/Reports/sections/LazyChartArticle';
import ReferralFunnelSection from '@/Pages/Reports/sections/ReferralFunnelSection';
import ReferralTrendsSection from '@/Pages/Reports/sections/ReferralTrendsSection';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler);

function ReportsDashboard({
  // Eager props
  kpis,
  dateScope: initialDateScope, province: initialProvince, city: initialCity,
  provinceOptions, cityOptions,
  from: initialFrom, to: initialTo,
  role, referenceData,
}) {
  const [activeTab, setActiveTab] = useState('overview');
  const [dateScope, setDateScope] = useState(initialDateScope || 'case_created_at');
  const [province, setProvince] = useState(initialProvince || null);
  const [city, setCity] = useState(initialCity || null);
  const [caseStatusDist, caseStatusLoading] = useLazyProp('caseStatusDistribution');
  const [casesSeries] = useLazyProp('casesOverTime');

  const caseSparkline = casesSeries?.datasets?.[0]?.data;
  const referralStatuses = referenceData?.referralStatuses || [];

  const extraDeps = {
    ...(role === 'CASE_MANAGER' ? { date_scope: dateScope } : {}),
    province,
    city,
  };
  const { fromDateISO, setFromDateISO, toDateISO, setToDateISO, quickRange, handleQuickRange, resetDateRange } = useReportFilters(
    initialFrom, initialTo, extraDeps,
  );

  const roleSubtitle = role === 'AGENCY'
    ? 'Agency performance overview.'
    : role === 'ADMIN'
      ? 'System-wide performance metrics and trends.'
      : 'Your caseload, referral throughput, and where cases need attention.';
  const heroCols = role === 'CASE_MANAGER' ? 'xl:grid-cols-5' : 'xl:grid-cols-4';

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <header data-tour="reports-header" className="flex flex-col gap-4 mb-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
              Reports
            </h1>
            <p className="text-sm text-slate-400 font-body mt-0.5">{roleSubtitle}</p>
          </div>
          <div data-tour="reports-filters" className="flex items-center gap-3">
            <DateRangePicker
              fromDateISO={fromDateISO}
              toDateISO={toDateISO}
              quickRange={quickRange}
              onFromChange={setFromDateISO}
              onToChange={setToDateISO}
              onQuickRangeSelect={handleQuickRange}
              onReset={resetDateRange}
            />
            {role === 'CASE_MANAGER' && <DateScopeSelect value={dateScope} onChange={setDateScope} />}
            <ExportButtons
              fromDateISO={fromDateISO}
              toDateISO={toDateISO}
              dateScope={role === 'CASE_MANAGER' ? dateScope : undefined}
              province={province}
              city={city}
            />
          </div>
        </div>
        <div data-tour="reports-tabs" className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          {role === 'CASE_MANAGER' && (
            <ProvinceCityFilter
              provinceOptions={provinceOptions || []}
              cityOptions={cityOptions || []}
              province={province}
              city={city}
              onProvinceChange={setProvince}
              onCityChange={setCity}
            />
          )}
          <ReportTabBar value={activeTab} onChange={setActiveTab} role={role} />
        </div>
      </header>

      {/* ── KPI hero: primary "am I on track" tier ── */}
      <section data-tour="reports-kpis" className={`grid grid-cols-1 gap-3 sm:grid-cols-2 ${heroCols}`}>
        <MetricCard label="Active Caseload" value={`${suppressCount(kpis?.openCases ?? 0)}`}
          icon={<Users className="w-4 h-4 text-[#0b5a8c]" />}
          sparkline={<Sparkline data={caseSparkline} color={COLORS.primary} />} />
        <MetricCard label="Completed This Period" value={`${suppressCount(kpis?.completedReferrals ?? 0)}`}
          icon={<CheckCircle2 className="w-4 h-4 text-[#3f915f]" />}
          trailing={<TrendIndicator change={kpis?.kpiChanges?.completedReferrals} />} />
        <MetricCard label="Completion Rate" value={`${kpis?.completionRate || 0}%`}
          icon={<Target className="w-4 h-4 text-[#0b7a75]" />}
          trailing={<TrendIndicator change={kpis?.kpiChanges?.completionRate} />} />
        <MetricCard label="Avg Resolution" value={`${kpis?.avgResolutionDays ?? 0}d`}
          icon={<Hourglass className="w-4 h-4 text-[#9b51b0]" />}
          description="Time from case open to close" />
        <TopServiceRequestedCard role={role} />
      </section>

      {/* ── KPI hero: volume strip ── */}
      <section className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <MetricCard label="Total Referrals" value={`${suppressCount(kpis?.totalReferrals ?? 0)}`}
          icon={<GitFork className="w-4 h-4 text-[#0b5a8c]" />}
          trailing={<TrendIndicator change={kpis?.kpiChanges?.totalReferrals} />} />
        <MetricCard label="Pending" value={`${suppressCount(kpis?.pendingReferrals ?? 0)}`} valueTone="text-[#9a5b1a] dark:text-amber-400"
          icon={<Clock className="w-4 h-4 text-[#9a5b1a]" />}
          trailing={<TrendIndicator change={kpis?.kpiChanges?.pendingReferrals} />} />
        <MetricCard label="For Compliance" value={`${suppressCount(kpis?.forComplianceReferrals ?? 0)}`} valueTone="text-[#d9663b]"
          icon={<ClipboardCheck className="w-4 h-4 text-[#d9663b]" />} />
      </section>

      {activeTab === 'overview' && <OverviewBanner />}
      {activeTab === 'overview' && <AttentionSection />}

      {/* ── OVERVIEW ── */}
      {activeTab === 'overview' && (
        <>
          <section data-tour="reports-charts" className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div className="xl:col-span-2">
              <ReferralFunnelSection referralStatuses={referralStatuses} />
            </div>
            <StatusDistributionSection />
          </section>

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <LazyTrendChart lazyKey="casesOverTime" title="Cases Over Time" />
            <AgencyScorecardSection role={role} />
          </section>
        </>
      )}

      {/* ── PERFORMANCE ── */}
      {activeTab === 'performance' && (
        <>
          <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <StatusDistributionSection />
            <LazyChartArticle lazyKey="referralAging" title="Referral Aging" desc="How long active referrals have been waiting" emptyText="No active referrals pending." />
            <CycleTimeSection />
          </section>

          <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <AvgCompletionCard role={role} />
            <OverdueReferralsCard role={role} />
          </section>

          <ReferralFunnelSection referralStatuses={referralStatuses} />

          <section className="mb-2">
            <LazyTrendChart lazyKey="caseTrends" title="Case Trends (12 Months)" />
          </section>

          <ReferralTrendsSection role={role} />
        </>
      )}

      {/* ── AGENCIES & SERVICES ── */}
      {activeTab === 'agencies' && (
        <>
          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <AgencyScorecardSection role={role} />
            <CategorySection />
          </section>

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <AgencyWorkloadChart role={role} />
            <LazyChartArticle lazyKey="referralAgencyDistribution" title="Referrals by Agency" emptyText="No agency referral data available." />
          </section>

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <LazyChartArticle lazyKey="caseIssueDistribution" title="Case Issue Distribution" emptyText="No issue data available." />
          </section>
        </>
      )}

      {/* ── CASELOAD & CLIENTS ── */}
      {activeTab === 'clients' && (
        <>
          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <CaseStatusPieChart data={caseStatusDist} loading={caseStatusLoading} />
            <CategorySection />
          </section>

          <ClientSnapshotCard />

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <LazyChartArticle lazyKey="vulnerabilityDistribution" title="Vulnerability Indicators" emptyText="No vulnerability data available." />
            <LazyChartArticle lazyKey="clientTypeDistribution" title="Client Type" emptyText="No client type data available." />
          </section>

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <GeographicMapSection province={province} setProvince={setProvince} setCity={setCity} provinceOptions={provinceOptions || []} />
            <LazyChartArticle lazyKey="cityDistribution" title="City/Municipality Distribution" emptyText="No city-level data available." />
          </section>

          <EmploymentSection />
        </>
      )}
    </div>
  );
}

export default function ReportsIndex(props) {
  return (
    <AppLayout title="Reports">
      <Head title="Reports" />
      <ReportsDashboard {...props} />
    </AppLayout>
  );
}
