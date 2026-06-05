import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
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
    kpiCards,
    caseTrends,
    referralVolume,
    slaCompliance,
    agencies,
    categories,
    caseManagers,
    statusDistribution,
    categoryDistribution,
    serviceDistribution,
    geographicDistribution,
    clientTypeSplit,
    agingCases,
    stalledReferrals,
    overloadedAgencies,
    bottleneckAnalysis,
    rejectionAnalysis,
    caseManagerScorecard,
    agencyScorecard,
    serviceCompletionRate,
    firstResponseTime,
    satisfactionTrend,
    servqualScores,
    agencySatisfactionRanking,
    feedbackVolume,
    caseVolumeForecast,
    breachProbability,
    peakPeriods,
    capacityForecast,
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

  // Sync filters to URL query params
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
    if (current !== qs) {
      router.get(route('insights.index') + '?' + qs, {}, {
        preserveState: true,
        replace: true,
      });
    }
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

  const handleRefresh = useCallback(() => {
    router.reload({ preserveState: true, preserveScroll: true });
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
            agencies={agencies}
            agency={agency}
            onAgencyChange={handleAgencyChange}
            categories={categories}
            category={category}
            onCategoryChange={handleCategoryChange}
            caseManagers={caseManagers}
            caseManager={caseManager}
            onCaseManagerChange={handleCaseManagerChange}
            clientType={clientType}
            onClientTypeChange={handleClientTypeChange}
          />
        </header>

        <InsightsTabNav activeTab={activeTab} onTabChange={handleTabChange} tabs={allowedTabs} />

        {activeTab === 'executive' && (
          <ExecutiveOverview
            kpiCards={kpiCards}
            caseTrends={caseTrends}
            breachProbability={breachProbability}
            from={fromDate}
            to={toDate}
            onRefresh={handleRefresh}
          />
        )}

        {activeTab === 'trends' && (
          <TrendAnalysis
            caseTrends={caseTrends}
            referralVolume={referralVolume}
            slaCompliance={slaCompliance}
            from={fromDate}
            to={toDate}
          />
        )}

        {activeTab === 'distribution' && (
          <Distributions
            statusDistribution={can('status_distribution') ? statusDistribution : null}
            categoryDistribution={can('category_distribution') ? categoryDistribution : null}
            serviceDistribution={serviceDistribution}
            geographicDistribution={can('geographic') ? geographicDistribution : null}
            clientTypeSplit={can('client_type_split') ? clientTypeSplit : null}
          />
        )}

        {activeTab === 'operational' && (
          <OperationalMonitor
            agingCases={can('aging_cases') ? (agingCases?.details ?? []) : null}
            stalledReferrals={stalledReferrals?.referrals ?? []}
            overloadedAgencies={can('overloaded_agencies') && overloadedAgencies ? overloadedAgencies.labels.map((name, i) => ({
              agency_name: name,
              active_cases: overloadedAgencies.data[i],
              capacity: overloadedAgencies.threshold,
            })) : null}
            bottleneckAnalysis={can('bottleneck_detection') && bottleneckAnalysis ? bottleneckAnalysis.labels.map((label, i) => ({
              label,
              count: bottleneckAnalysis.datasets?.[0]?.data?.[i] ?? 0,
              is_bottleneck: (bottleneckAnalysis.datasets?.[0]?.data?.[i] ?? 0) > 24,
              percentage: Math.min(100, ((bottleneckAnalysis.datasets?.[0]?.data?.[i] ?? 0) / 48) * 100),
            })) : null}
            rejectionAnalysis={rejectionAnalysis ? rejectionAnalysis.labels.map((l, i) => ({ reason: l, count: rejectionAnalysis.data[i] })) : []}
          />
        )}

        {activeTab === 'scorecards' && (
          <Scorecards
            caseManagerScorecard={can('cm_scorecard') ? (caseManagerScorecard?.rows ?? []) : null}
            agencyScorecard={can('agency_scorecard') ? (agencyScorecard?.detailed ?? []) : null}
            serviceCompletionRate={serviceCompletionRate?.services ?? []}
            firstResponseTime={firstResponseTime}
          />
        )}

        {activeTab === 'satisfaction' && (
          <Satisfaction
            satisfactionTrend={satisfactionTrend}
            servqualScores={servqualScores?.dimensions ?? []}
            agencySatisfactionRanking={can('satisfaction_other') ? (agencySatisfactionRanking?.labels?.map?.((l, i) => ({ name: l, score: agencySatisfactionRanking.data?.[i] ?? 0 })) ?? []) : null}
            feedbackVolume={feedbackVolume}
          />
        )}

        {activeTab === 'predictive' && (
          <Predictive
            caseVolumeForecast={caseVolumeForecast}
            breachProbability={breachProbability}
            peakPeriods={peakPeriods}
            capacityForecast={capacityForecast}
          />
        )}

        {activeTab === 'alerts' && (
          <AlertsPanel />
        )}
      </div>
    </AppLayout>
  );
}
