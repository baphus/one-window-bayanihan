import MetricCard from '@/Components/Insights/MetricCard';
import TrendChart from '@/Components/Insights/TrendChart';
import DistributionChart from '@/Components/Insights/DistributionChart';
import { Activity, GitFork, Clock, ShieldCheck, Building2, Star } from 'lucide-react';
import { useMemo } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import SectionSkeleton from '../SectionSkeleton';

function useInsightsQuery(endpoint, filters) {
  return useQuery({
    queryKey: ['insights', endpoint, filters],
    queryFn: async () => {
      const params = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null))
      );
      const res = await fetch(`/api/insights/${endpoint}?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });
}

export default function ExecutiveOverview({ from, to }) {
  const filters = { from, to };

  const kpiCardsQuery = useInsightsQuery('kpi-cards', filters);
  const caseTrendsQuery = useInsightsQuery('case-trends', filters);
  const breachProbQuery = useInsightsQuery('breach-probability', filters);

  const kpiKeys = [
    { key: 'active_cases', label: 'Active Cases', color: 'blue', icon: <Activity className="h-3.5 w-3.5" />, format: 'number' },
    { key: 'pending_referrals', label: 'Pending Referrals', color: 'amber', icon: <GitFork className="h-3.5 w-3.5" />, format: 'number' },
    { key: 'avg_resolution_time', label: 'Avg Resolution Time', color: 'purple', icon: <Clock className="h-3.5 w-3.5" />, format: 'days' },
    { key: 'sla_compliance_rate', label: 'SLA Compliance', color: 'green', icon: <ShieldCheck className="h-3.5 w-3.5" />, format: 'percentage' },
    { key: 'agency_composite_score', label: 'Agency Composite Score', color: 'blue', icon: <Building2 className="h-3.5 w-3.5" />, format: 'percentage' },
    { key: 'client_satisfaction', label: 'Client Satisfaction', color: 'green', icon: <Star className="h-3.5 w-3.5" />, format: 'rating' },
  ];

  const breachChartData = useMemo(() => {
    const data = breachProbQuery.data;
    if (!data || data.length === 0) return null;
    const total = data.reduce((s, v) => s + (v.count || 0), 0) || 1;
    return {
      labels: data.map((b) => b.label || b.status),
      datasets: [{
        data: data.map((b) => b.count || b.value || 0),
        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
        borderWidth: 0,
      }],
      _total: total,
    };
  }, [breachProbQuery.data]);

  // Combined loading state — show skeleton while any area is loading for the first time
  if (kpiCardsQuery.isLoading || caseTrendsQuery.isLoading || breachProbQuery.isLoading) {
    return (
      <div className="space-y-5">
        <SectionSkeleton type="card" count={4} />
        <SectionSkeleton type="chart" count={2} />
      </div>
    );
  }

  // ── KPI Cards Row ──────────────────────────────────────────────────────
  function renderKpiSection() {
    if (kpiCardsQuery.isError) {
      return (
        <div className="col-span-full flex flex-col items-center gap-2 py-8 text-center">
          <p className="text-[13px] text-slate-500">Unable to load data</p>
          <button
            onClick={() => kpiCardsQuery.refetch()}
            className="rounded bg-slate-100 px-3 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-200"
          >
            Retry
          </button>
        </div>
      );
    }
    if (!kpiCardsQuery.data) {
      return (
        <div className="col-span-full py-8 text-center text-[13px] text-slate-400">
          No data available
        </div>
      );
    }
    return kpiKeys.map(({ key, label, color, icon, format }) => {
      const kpi = kpiCardsQuery.data?.[key];
      const value = kpi?.value ?? null;
      const change = kpi?.change ?? null;
      const changeDirection = change !== null ? (change > 0 ? 'up' : 'down') : null;
      return (
        <MetricCard
          key={key}
          title={label}
          value={value}
          change={change !== null ? Math.abs(change) : null}
          changeDirection={changeDirection}
          icon={icon}
          color={color}
          format={format}
        />
      );
    });
  }

  // ── Breach Probability Section ─────────────────────────────────────────
  function renderBreachSection() {
    if (breachProbQuery.isError) {
      return (
        <div className="flex flex-col items-center gap-2 py-8 text-center">
          <p className="text-[13px] text-slate-500">Unable to load data</p>
          <button
            onClick={() => breachProbQuery.refetch()}
            className="rounded bg-slate-100 px-3 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-200"
          >
            Retry
          </button>
        </div>
      );
    }
    if (!breachChartData) {
      return (
        <p className="py-8 text-center text-[13px] text-slate-400">No breach data available.</p>
      );
    }
    return (
      <div className="flex flex-col items-center gap-3">
        <div className="h-32 w-32">
          <DistributionChart
            title=""
            data={breachChartData}
            type="doughnut"
            height={128}
          />
        </div>
        <div className="w-full space-y-1.5">
          {breachProbQuery.data?.map((b) => {
            const count = b.count || b.value || 0;
            const total = breachChartData._total;
            const pct = total > 0 ? Math.round((count / total) * 100) : 0;
            const colorMap = { within_sla: 'emerald', warning: 'amber', breached: 'rose' };
            const dotColor = colorMap[b.status] || 'slate';
            return (
              <div key={b.status} className="flex items-center justify-between text-[11px]">
                <span className="inline-flex items-center gap-1.5 text-slate-600">
                  <span className={`h-2 w-2 rounded-full bg-${dotColor}-500 shrink-0`} />
                  <span className="font-medium capitalize">{b.label || b.status}</span>
                </span>
                <span className="font-bold text-slate-700">{count} ({pct}%)</span>
              </div>
            );
          })}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      {/* KPI Cards Row */}
      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {renderKpiSection()}
      </section>

      {/* Charts Row */}
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[2fr_1fr]">
        {caseTrendsQuery.isError ? (
          <div className="flex flex-col items-center gap-2 border border-[#cbd5e1] bg-white p-4 py-8 text-center shadow-sm">
            <p className="text-[13px] text-slate-500">Unable to load data</p>
            <button
              onClick={() => caseTrendsQuery.refetch()}
              className="rounded bg-slate-100 px-3 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-200"
            >
              Retry
            </button>
          </div>
        ) : (
          <TrendChart
            title="Case Trends"
            data={caseTrendsQuery.data}
            emptyMessage="No case trend data available. Adjust date range."
          />
        )}

        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            SLA Breach Probability
          </h3>
          {renderBreachSection()}
        </article>
      </section>

      {/* Quick Links Row */}
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <h3 className="mb-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            Performance Highlights
          </h3>
          <p className="text-[12px] text-slate-500">
            Dive into detailed trends, distribution charts, and operational monitoring
            using the tabs above for a complete performance picture.
          </p>
        </article>
        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <h3 className="mb-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            Forecast Summary
          </h3>
          <p className="text-[12px] text-slate-500">
            View predictive analytics including case volume forecasts, breach probability,
            and capacity planning under the Predictive tab.
          </p>
        </article>
      </section>
    </div>
  );
}
