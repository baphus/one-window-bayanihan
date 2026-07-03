import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { GitFork, Target, Clock, AlertTriangle, FolderOpen } from 'lucide-react';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';
import { pageHeadingStyles, COLORS } from '@/Components/Reports/pageHeadingStyles';
import MetricCard from '@/Components/Reports/MetricCard';
import TrendIndicator from '@/Components/Reports/TrendIndicator';
import DateRangePicker from '@/Components/Reports/DateRangePicker';
import CaseStatusPieChart from '@/Components/Reports/CaseStatusPieChart';
import DateScopeSelect from '@/Components/Reports/DateScopeSelect';
import ReportTabBar from '@/Components/Reports/ReportTabBar';
import ProvinceCityFilter from '@/Components/Reports/ProvinceCityFilter';
import OverdueSection from '@/Components/Reports/OverdueSection';
import ExportButtons from '@/Components/Reports/ExportButtons';
import { suppressCount } from '@/Components/Reports/suppressCount';
import { useReportFilters } from '@/Hooks/useReportFilters';
import { useLazyProp } from '@/Hooks/useLazyProp';
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

function ReportsDashboard({
  // Eager props
  kpis,
  dateScope: initialDateScope, province: initialProvince, city: initialCity,
  provinceOptions, cityOptions,
  from: initialFrom, to: initialTo,
  // Role
  role, overdueReferrals,
}) {
  const [activeTab, setActiveTab] = useState('all');
  const [dateScope, setDateScope] = useState(initialDateScope || 'case_created_at');
  const [province, setProvince] = useState(initialProvince || null);
  const [city, setCity] = useState(initialCity || null);
  const [caseStatusDist, caseStatusLoading] = useLazyProp('caseStatusDistribution');
  const [overview] = useLazyProp('overview');

  const extraDeps = role === 'CASE_MANAGER' ? { date_scope: dateScope, province, city } : {};
  const { fromDateISO, setFromDateISO, toDateISO, setToDateISO, quickRange, handleQuickRange, resetDateRange } = useReportFilters(
    initialFrom, initialTo, extraDeps,
  );

  const roleSubtitle = role === 'AGENCY'
    ? 'Agency performance overview.'
    : role === 'ADMIN'
      ? 'System-wide performance metrics and trends.'
      : 'Referral operations and agency performance.';

  return (
    <div className="mx-auto max-w-7xl space-y-5 pb-4">
      <header className="flex flex-col gap-4">
        <div>
          <h1 className={pageHeadingStyles.pageTitle}>Reports</h1>
          <p className={pageHeadingStyles.pageSubtitle}>{roleSubtitle}</p>
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
            {role === 'CASE_MANAGER' && <DateScopeSelect value={dateScope} onChange={setDateScope} />}
            <ExportButtons fromDateISO={fromDateISO} toDateISO={toDateISO} />
          </div>
        </div>
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
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
          <ReportTabBar value={activeTab} onChange={setActiveTab} />
        </div>
      </header>

      {/* KPI Row — always visible */}
      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <MetricCard label="Total Referrals" value={`${suppressCount(kpis?.totalReferrals || 0)}`} accent="border-l-[#0b5a8c]"
          icon={<GitFork className="h-3.5 w-3.5 text-[#0b5a8c]" />}
          trailing={<TrendIndicator change={kpis?.kpiChanges?.totalReferrals} />} />
        <MetricCard label="Total Cases" value={`${suppressCount(overview?.totalCases ?? 0)}`} accent="border-l-[#1e3a8a]"
          icon={<FolderOpen className="h-3.5 w-3.5 text-[#1e3a8a]" />} />
        <MetricCard label="Completion Rate" value={`${kpis?.completionRate || 0}%`} accent="border-l-[#0b7a75]"
          icon={<Target className="h-3.5 w-3.5 text-[#0b7a75]" />}
          trailing={<TrendIndicator change={kpis?.kpiChanges?.completionRate} />} />
        <MetricCard label="Pending" value={`${suppressCount(kpis?.pendingReferrals || 0)}`} accent="border-l-[#9a5b1a]" valueTone="text-[#9a5b1a]"
          icon={<Clock className="h-3.5 w-3.5 text-[#9a5b1a]" />}
          trailing={<TrendIndicator change={kpis?.kpiChanges?.pendingReferrals} />} />
        <MetricCard label="Overdue" value={`${suppressCount(overdueReferrals?.count ?? 0)}`} accent="border-l-[#dc2626]"
          valueTone="text-[#dc2626]"
          icon={<AlertTriangle className="h-3.5 w-3.5 text-[#dc2626]" />} />
      </section>

      {/* ── ALL DATA ── */}
      {activeTab === 'all' && (
        <>
          <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <StatusDistributionSection />
            <LazyChartArticle lazyKey="referralAging" title="Referral Aging" desc="How long active referrals have been waiting" emptyText="No active referrals pending." />
            <CycleTimeSection />
          </section>

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <CaseStatusPieChart data={caseStatusDist} loading={caseStatusLoading} />
            <CategorySection />
          </section>

          <LazyDemographics />

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <AgencyScorecardSection />
            <GeographicSection />
          </section>

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <LazyChartArticle lazyKey="caseIssueDistribution" title="Case Issue Distribution" emptyText="No issue data available." />
            <LazyTrendChart lazyKey="casesOverTime" title="Cases Over Time" />
          </section>

          <section className="mb-6">
            <LazyTrendChart lazyKey="caseTrends" title="Case Trends (12 Months)" />
          </section>

          <OverdueSection />
        </>
      )}

      {/* ── CASES ── */}
      {activeTab === 'cases' && (
        <>
          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <CaseStatusPieChart data={caseStatusDist} loading={caseStatusLoading} />
            <CategorySection />
          </section>

          <LazyChartArticle lazyKey="caseIssueDistribution" title="Case Issue Distribution" emptyText="No issue data available." />

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <LazyTrendChart lazyKey="casesOverTime" title="Cases Over Time" />
            <LazyTrendChart lazyKey="caseTrends" title="Case Trends (12 Months)" />
          </section>
        </>
      )}

      {/* ── REFERRALS ── */}
      {activeTab === 'referrals' && (
        <>
          <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <StatusDistributionSection />
            <LazyChartArticle lazyKey="referralAging" title="Referral Aging" desc="How long active referrals have been waiting" emptyText="No active referrals pending." />
            <CycleTimeSection />
          </section>

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <AgencyScorecardSection />
            <GeographicSection />
          </section>

          <OverdueSection />
        </>
      )}

      {/* ── DEMOGRAPHICS ── */}
      {activeTab === 'demographics' && (
        <>
          <LazyDemographics />
          <EmploymentSection />
          <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <GeographicSection />
            <LazyChartArticle lazyKey="cityDistribution" title="City/Municipality Distribution" emptyText="No city-level data available." />
          </section>
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
