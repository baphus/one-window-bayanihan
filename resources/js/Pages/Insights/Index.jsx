import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, BarElement, ArcElement,
  PointElement, LineElement, Title, Tooltip, Legend, Filler,
  RadarController, RadialLinearScale,
} from 'chart.js';

import InsightsTabNav from '@/Components/Insights/InsightsTabNav';
import InsightsFilterBar from '@/Components/Insights/InsightsFilterBar';
import ExecutiveOverview from '@/Components/Insights/Sections/ExecutiveOverview';
import TrendAnalysis from '@/Components/Insights/Sections/TrendAnalysis';
import Distributions from '@/Components/Insights/Sections/Distributions';
import OperationalMonitor from '@/Components/Insights/Sections/OperationalMonitor';
import Scorecards from '@/Components/Insights/Sections/Scorecards';
import Satisfaction from '@/Components/Insights/Sections/Satisfaction';
import Predictive from '@/Components/Insights/Sections/Predictive';
import AlertsPanel from '@/Components/Insights/Sections/AlertsPanel';
import useInsightsAccess from '@/Hooks/useInsightsAccess';

ChartJS.register(
  CategoryScale, LinearScale, BarElement, ArcElement,
  PointElement, LineElement, Title, Tooltip, Legend, Filler,
  RadarController, RadialLinearScale,
);

function toISODateInputValue(date) {
  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function getDefaultFrom() {
  const d = new Date();
  d.setMonth(d.getMonth() - 6);
  return toISODateInputValue(d);
}

function getDefaultTo() {
  return toISODateInputValue(new Date());
}

export default function InsightsIndex(props) {
  const {
    tab: initialTab,
    from: initialFrom,
    to: initialTo,
  } = props;

  const { can, allowedTabs } = useInsightsAccess();

  const initialAllowed = initialTab && allowedTabs.includes(initialTab) ? initialTab : allowedTabs[0];
  const [activeTab, setActiveTab] = useState(initialAllowed);
  const [fromDate, setFromDate] = useState(initialFrom || getDefaultFrom());
  const [toDate, setToDate] = useState(initialTo || getDefaultTo());
  const [agency, setAgency] = useState('');
  const [category, setCategory] = useState('');
  const [caseManager, setCaseManager] = useState('');
  const [clientType, setClientType] = useState('');
  const [activePreset, setActivePreset] = useState(() => {
    if (initialFrom && initialTo) {
      // Determine if the current range matches a preset
      const sixMonthsAgo = new Date();
      sixMonthsAgo.setMonth(sixMonthsAgo.getMonth() - 6);
      const defaultFrom = toISODateInputValue(sixMonthsAgo);
      if (initialFrom === defaultFrom) return '6M';
      return 'Custom';
    }
    return '6M';
  });

  const isInitialRender = useRef(true);

  // Sync filters to URL query params (silent replaceState, no Inertia reload)
  useEffect(() => {
    const params = new URLSearchParams();
    params.set('from', fromDate);
    params.set('to', toDate);
    if (activeTab !== 'executive') {
      params.set('tab', activeTab);
    }
    if (agency) params.set('agency', agency);
    if (category) params.set('category', category);
    if (caseManager) params.set('case_manager', caseManager);
    if (clientType) params.set('client_type', clientType);
    const qs = params.toString();
    const current = window.location.search.slice(1);
    if (current !== qs && !isInitialRender.current) {
      window.history.replaceState(null, '', route('insights.index') + '?' + qs);
    }
    isInitialRender.current = false;
  }, [fromDate, toDate, activeTab, agency, category, caseManager, clientType]);

  const handleFromChange = useCallback((d) => {
    setFromDate(d);
    setActivePreset('Custom');
  }, []);

  const handleToChange = useCallback((d) => {
    setToDate(d);
    setActivePreset('Custom');
  }, []);

  const handlePresetChange = useCallback((preset) => {
    setActivePreset(preset);
  }, []);

  const handleAgencyChange = useCallback((val) => setAgency(val), []);
  const handleCategoryChange = useCallback((val) => setCategory(val), []);
  const handleCaseManagerChange = useCallback((val) => setCaseManager(val), []);
  const handleClientTypeChange = useCallback((val) => setClientType(val), []);

  const handleReset = useCallback(() => {
    const sixMonthsAgo = new Date();
    sixMonthsAgo.setMonth(sixMonthsAgo.getMonth() - 6);
    setFromDate(toISODateInputValue(sixMonthsAgo));
    setToDate(toISODateInputValue(new Date()));
    setActivePreset('6M');
    setAgency('');
    setCategory('');
    setCaseManager('');
    setClientType('');
  }, []);

  const handleTabChange = useCallback((tab) => {
    setActiveTab(tab);
  }, []);

  return (
    <AppLayout title="Insights">
      <Head title="Insights" />
      <div className="mx-auto max-w-7xl space-y-5 pb-4">
        <header className="flex flex-col gap-4">
          <div>
            <h1 className="text-3xl md:text-[34px] font-black leading-tight tracking-tight text-slate-900">
              Insights
            </h1>
            <p className="mt-1 text-[14px] leading-6 text-slate-600">
              Data-driven analytics and operational intelligence.
            </p>
          </div>
          <InsightsFilterBar
            fromDate={fromDate}
            toDate={toDate}
            onFromChange={handleFromChange}
            onToChange={handleToChange}
            activePreset={activePreset}
            onPresetChange={handlePresetChange}
            onReset={handleReset}
            agency={agency}
            onAgencyChange={handleAgencyChange}
            category={category}
            onCategoryChange={handleCategoryChange}
            caseManager={caseManager}
            onCaseManagerChange={handleCaseManagerChange}
            clientType={clientType}
            onClientTypeChange={handleClientTypeChange}
          />
        </header>

        <InsightsTabNav activeTab={activeTab} onTabChange={handleTabChange} tabs={allowedTabs} />

        {activeTab === 'executive' && (
          <ExecutiveOverview
            from={fromDate}
            to={toDate}
          />
        )}

        {activeTab === 'trends' && (
          <TrendAnalysis
            from={fromDate}
            to={toDate}
          />
        )}

        {activeTab === 'distribution' && (
          <Distributions
            from={fromDate}
            to={toDate}
          />
        )}

        {activeTab === 'operational' && (
          <OperationalMonitor
            from={fromDate}
            to={toDate}
          />
        )}

        {activeTab === 'scorecards' && (
          <Scorecards
            from={fromDate}
            to={toDate}
          />
        )}

        {activeTab === 'satisfaction' && (
          <Satisfaction
            from={fromDate}
            to={toDate}
          />
        )}

        {activeTab === 'predictive' && (
          <Predictive
            from={fromDate}
            to={toDate}
          />
        )}

        {activeTab === 'alerts' && (
          <AlertsPanel />
        )}
      </div>
    </AppLayout>
  );
}
