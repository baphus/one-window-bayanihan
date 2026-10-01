import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { Users, Target, Clock, GitFork, CheckCircle2, Hourglass, ClipboardCheck } from 'lucide-react';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';
import MetricCard from '@/Components/Reports/MetricCard';
import TopServiceRequestedCard from '@/Components/Reports/TopServiceRequestedCard';
import TrendIndicator from '@/Components/Reports/TrendIndicator';
import Sparkline from '@/Components/Reports/Sparkline';
import DateRangePicker from '@/Components/Reports/DateRangePicker';
import AgencyFilter from '@/Components/Reports/AgencyFilter';
import ProvinceCityFilter from '@/Components/Reports/ProvinceCityFilter';
import ExportButtons from '@/Components/Reports/ExportButtons';
import { useReportFilters } from '@/Hooks/useReportFilters';
import { useLazyProp } from '@/Hooks/useLazyProp';
import { philippineAddressData, getCitiesByProvince } from '@/data/philippine-addresses';
import CasesTab from '@/Pages/Reports/sections/CasesTab';
import ReferralsTab from '@/Pages/Reports/sections/ReferralsTab';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler);

// Province name → PSGC code lookup built from PSGC master data
const provinceNameToPsgcCode = Object.values(philippineAddressData.provincesByRegion)
  .flat()
  .reduce((acc, p) => { acc[p.name.toLowerCase()] = p.code; return acc; }, {});

function ReportsDashboard({
  // Eager props
  kpis,
  province: initialProvince, city: initialCity,
  agencyId: initialAgencyId, agencyOptions,
  provinceOptions, cityOptions,
  from: initialFrom, to: initialTo,
  role,
}) {
  const [activeTab, setActiveTab] = useState('cases');
  const [province, setProvince] = useState(initialProvince || null);
  const [city, setCity] = useState(initialCity || null);
  const [agencyId, setAgencyId] = useState(initialAgencyId || null);
  const [casesSeries] = useLazyProp('casesOverTime');
  const [referralSeries] = useLazyProp('referralTrends');

  const caseSparkline = casesSeries?.datasets?.[0]?.data;
  const referralSparkline = referralSeries?.datasets?.[0]?.data;
  const localCityOptions = useMemo(() => {
    if (!province) return cityOptions || [];
    const selected = provinceOptions.find((p) => p.value === province);
    if (selected) {
      const psgcCode = provinceNameToPsgcCode[selected.label.toLowerCase()];
      if (psgcCode) {
        const cities = getCitiesByProvince(psgcCode);
        if (cities.length > 0) return cities.map((c) => ({ value: c.code, label: c.name }));
      }
    }
    return cityOptions || [];
  }, [province, cityOptions, provinceOptions]);

  // Everything on this page uses the case filed date. The server defaults
  // date_scope to case_created_at when it is absent, so no scope selector.
  const extraDeps = {
    province,
    city,
    agency_id: agencyId,
  };
  const appliedExtraDeps = {
    province: initialProvince || null,
    city: initialCity || null,
    agency_id: initialAgencyId || null,
  };
  const {
    fromDateISO, setFromDateISO,
    toDateISO, setToDateISO,
    quickRange, handleQuickRange, resetDateRange,
    applyFilters, hasPendingChanges,
  } = useReportFilters(
    initialFrom, initialTo, extraDeps, appliedExtraDeps,
  );

  const roleSubtitle = role === 'AGENCY'
    ? 'Agency performance overview.'
    : role === 'ADMIN'
      ? 'System-wide performance metrics and trends.'
      : 'Numbers for the whole organization, on one page.';
  const heroCols = role === 'CASE_MANAGER' ? 'xl:grid-cols-5' : 'xl:grid-cols-4';

  const mapProps = { province, setProvince, setCity, provinceOptions: provinceOptions || [] };

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
          <div className="flex items-center gap-3">
            <ExportButtons
              fromDateISO={fromDateISO}
              toDateISO={toDateISO}
              dateScope="case_created_at"
              province={province}
              city={city}
              agencyId={agencyId}
              disabled={hasPendingChanges}
            />
          </div>
        </div>
        <div data-tour="reports-filters" className="flex flex-col gap-4">
          {role !== 'AGENCY' && (
            <div className="flex flex-wrap items-center gap-3">
              <AgencyFilter
                agencyOptions={agencyOptions || []}
                agencyId={agencyId}
                onChange={setAgencyId}
              />
              {role === 'CASE_MANAGER' && (
                <ProvinceCityFilter
                  provinceOptions={provinceOptions || []}
                  cityOptions={localCityOptions}
                  province={province}
                  city={city}
                  onProvinceChange={setProvince}
                  onCityChange={setCity}
                />
              )}
            </div>
          )}
          <div className="flex flex-wrap items-center gap-3">
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
              onClick={applyFilters}
              disabled={!hasPendingChanges}
              className={`inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                hasPendingChanges
                  ? 'bg-primary text-white hover:bg-primary-container focus:ring-primary'
                  : 'cursor-not-allowed border border-slate-200 bg-slate-100 text-slate-400'
              }`}
            >
              Show
            </button>
          </div>
        </div>
      </header>

      <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <p className="text-sm text-slate-500 dark:text-slate-400">
          <strong className="text-slate-700 dark:text-slate-300">How to read this page:</strong>{' '}
          All numbers cover cases filed in the chosen period. Percentages use only clients with
          that detail recorded. Some clients appear in more than one row. Small groups may be
          hidden in the PDF or Excel export to protect privacy.
        </p>
      </div>

      <div data-tour="reports-tabs" role="tablist" aria-label="Report parts" className="grid grid-cols-2 gap-0 overflow-hidden rounded-xl border-2 border-primary">
        {[
          { key: 'cases', label: 'Cases' },
          { key: 'referrals', label: 'Referrals' },
        ].map((tab) => (
          <button
            key={tab.key}
            type="button"
            role="tab"
            aria-selected={activeTab === tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-3 text-base font-extrabold transition-colors focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary ${
              activeTab === tab.key
                ? 'bg-primary text-white'
                : 'bg-white text-primary hover:bg-slate-50 dark:bg-slate-900'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* ── KPI hero ── */}
      <section data-tour="reports-kpis" className={`grid grid-cols-1 gap-3 sm:grid-cols-2 ${heroCols}`}>
        <MetricCard label="Active Caseload" value={`${kpis?.openCases ?? 0}`}
          icon={<Users className="w-4 h-4 text-primary" />}
          sparkline={<Sparkline data={caseSparkline} color={COLORS.primary} />} />
        <MetricCard label="Completed This Period" value={`${kpis?.completedReferrals ?? 0}`}
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
        <MetricCard label="Total Referrals" value={`${kpis?.totalReferrals ?? 0}`}
          icon={<GitFork className="w-4 h-4 text-primary" />}
          trailing={<TrendIndicator change={kpis?.kpiChanges?.totalReferrals} />}
          sparkline={<Sparkline data={referralSparkline} color={COLORS.primary} />} />
        <MetricCard label="Pending" value={`${kpis?.pendingReferrals ?? 0}`} valueTone="text-[#9a5b1a] dark:text-amber-400"
          icon={<Clock className="w-4 h-4 text-[#9a5b1a]" />}
          trailing={<TrendIndicator change={kpis?.kpiChanges?.pendingReferrals} />} />
        <MetricCard label="For Compliance" value={`${kpis?.forComplianceReferrals ?? 0}`} valueTone="text-[#d9663b]"
          icon={<ClipboardCheck className="w-4 h-4 text-[#d9663b]" />} />
      </section>

      {activeTab === 'cases' ? (
        <div role="tabpanel" aria-label="Cases">
          <CasesTab mapProps={mapProps} />
        </div>
      ) : (
        <div role="tabpanel" aria-label="Referrals">
          <ReferralsTab />
        </div>
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
