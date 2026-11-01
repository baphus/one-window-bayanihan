import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';

import DateRangePicker from '@/Components/Reports/DateRangePicker';
import AgencyFilter from '@/Components/Reports/AgencyFilter';
import ProvinceCityFilter from '@/Components/Reports/ProvinceCityFilter';
import ExportButtons from '@/Components/Reports/ExportButtons';
import { useReportFilters } from '@/Hooks/useReportFilters';
import { useLazyProp } from '@/Hooks/useLazyProp';
import CasesTab from '@/Pages/Reports/sections/CasesTab';
import ReferralsTab from '@/Pages/Reports/sections/ReferralsTab';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, PointElement, LineElement, Title, Tooltip, Legend, Filler);

export function ReportsDashboard({
  printMode = false,
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
  const [mostRequestedService] = useLazyProp('mostRequestedService');
  const roles = usePage().props.roles;

  // PSGC dataset loads lazily so it stays out of the initial chunk; the city
  // lookup below falls back to the server-provided options until it resolves.
  const [addressData, setAddressData] = useState(null);

  useEffect(() => {
    let live = true;
    import('@/data/philippine-addresses').then((mod) => {
      if (live) setAddressData(mod);
    });
    return () => { live = false; };
  }, []);

  const localCityOptions = useMemo(() => {
    if (!province) return cityOptions || [];
    const selected = provinceOptions.find((p) => p.value === province);
    if (selected && addressData) {
      const psgcCode = Object.values(addressData.philippineAddressData.provincesByRegion)
        .flat()
        .find((p) => p.name.toLowerCase() === selected.label.toLowerCase())?.code;
      if (psgcCode) {
        const cities = addressData.getCitiesByProvince(psgcCode);
        if (cities.length > 0) return cities.map((c) => ({ value: c.code, label: c.name }));
      }
    }
    return cityOptions || [];
  }, [province, cityOptions, provinceOptions, addressData]);

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

  const roleSubtitle = role === roles.AGENCY
    ? 'Agency performance overview.'
    : role === roles.ADMIN
      ? 'System-wide performance metrics and trends.'
      : 'Numbers for the whole organization, on one page.';
  const metrics = [
    ['Active caseload', kpis?.openCases ?? 0],
    ['Completed referrals', kpis?.completedReferrals ?? 0],
    ['Completion rate', `${kpis?.completionRate ?? 0}%`],
    ['Avg resolution', `${kpis?.avgResolutionDays ?? 0}d`],
    ['Total referrals', kpis?.totalReferrals ?? 0],
    ['Pending', kpis?.pendingReferrals ?? 0],
    ['For compliance', kpis?.forComplianceReferrals ?? 0],
    ...(role === roles.CASE_MANAGER ? [['Most active service', mostRequestedService?.name || 'Not available']] : []),
  ];

  const mapProps = { province, setProvince: printMode ? undefined : setProvince, setCity: printMode ? undefined : setCity, provinceOptions: provinceOptions || [] };

  return (
    <div className="mx-auto w-full max-w-[1440px] space-y-4 pb-4">
      <header data-tour="reports-header" className="flex flex-col gap-4 mb-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.02em] text-black dark:text-white">
              Reports
            </h1>
            <p className="text-sm text-slate-600 dark:text-slate-300 font-body mt-0.5">{roleSubtitle}</p>
          </div>
          <div className={printMode || hasPendingChanges ? 'hidden' : 'flex items-center gap-3'}>
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
        <div data-tour="reports-filters" role="group" aria-label="Report filters" className={printMode ? 'hidden' : 'flex min-w-0 items-center gap-2 overflow-x-auto py-1'}>
          <DateRangePicker
            fromDateISO={fromDateISO} toDateISO={toDateISO} quickRange={quickRange}
            onFromChange={setFromDateISO} onToChange={setToDateISO} onQuickRangeSelect={handleQuickRange}
          />
          {role !== roles.AGENCY && <AgencyFilter agencyOptions={agencyOptions || []} agencyId={agencyId} onChange={setAgencyId} />}
          {role === roles.CASE_MANAGER && <ProvinceCityFilter
            provinceOptions={provinceOptions || []} cityOptions={localCityOptions}
            province={province} city={city} onProvinceChange={setProvince} onCityChange={setCity}
          />}
          {hasPendingChanges && <button type="button" onClick={applyFilters} disabled={!fromDateISO || !toDateISO || fromDateISO > toDateISO}
            className="inline-flex h-9 shrink-0 items-center rounded-md bg-primary px-3 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50">
            Apply
          </button>}
          {(quickRange !== '30_DAYS' || (role !== roles.AGENCY && agencyId) || province || city) && <button type="button" onClick={() => { resetDateRange(); setAgencyId(null); setProvince(null); setCity(null); }}
            className="inline-flex h-9 shrink-0 items-center rounded-md px-3 text-sm font-medium text-slate-600 hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-primary dark:text-slate-300">
            Clear filters
          </button>}
        </div>
      </header>

      <div className="py-1">
        <p className="mb-1 text-xs text-slate-600 dark:text-slate-300">Applied period: {initialFrom || fromDateISO} to {initialTo || toDateISO}</p>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          <strong className="text-slate-700 dark:text-slate-300">How to read this page:</strong>{' '}
          All numbers cover cases filed in the chosen period. Percentages use only clients with
          that detail recorded. Some clients appear in more than one row. Small groups may be
          hidden in the PDF export to protect privacy.
        </p>
      </div>

      <div data-tour="reports-tabs" role="tablist" aria-label="Report parts" className={printMode ? 'hidden' : 'flex gap-1 border-b border-slate-200 dark:border-slate-700'}>
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
            className={`rounded-t-md px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary ${
              activeTab === tab.key
                ? 'border-b-2 border-black text-black dark:border-white dark:text-white'
                : 'border-b-2 border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <section data-tour="reports-kpis" aria-label="Key performance indicators" className="rounded-xl border border-slate-200 bg-white px-4 py-2 dark:border-slate-700 dark:bg-slate-900">
        <dl className="grid grid-cols-2 gap-x-6 sm:grid-cols-3 lg:grid-cols-4">
          {metrics.map(([label, value]) => (
            <div key={label} className="min-w-0 border-b border-slate-100 py-3 dark:border-slate-800">
              <dt className="text-xs font-medium text-slate-600 dark:text-slate-300">{label}</dt>
              <dd className={label === 'Most active service' ? 'mt-1 text-sm font-semibold text-black dark:text-white' : 'mt-1 text-xl font-semibold tabular-nums text-black dark:text-white'}>{value}</dd>
            </div>
          ))}
        </dl>
      </section>

      {printMode ? (
        <>
          <h2 className="text-lg font-semibold">Cases</h2>
          <CasesTab mapProps={mapProps} />
          <h2 className="text-lg font-semibold">Referrals</h2>
          <ReferralsTab />
        </>
      ) : activeTab === 'cases' ? (
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
