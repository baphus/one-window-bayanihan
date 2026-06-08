import { useMemo } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { Doughnut, Bar } from 'react-chartjs-2';
import SectionSkeleton from '../SectionSkeleton';
import useInsightsAccess from '@/Hooks/useInsightsAccess';

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  cutout: '55%',
};

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: { beginAtZero: true, ticks: { font: { size: 10 } }, grid: { color: '#f1f5f9' } },
    y: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
};

function EmptyState({ message }) {
  return <p className="py-8 text-center text-[13px] text-slate-400">{message}</p>;
}

function SectionCard({ title, children }) {
  return (
    <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{title}</h3>
      {children}
    </article>
  );
}

function useOperationalQuery(endpoint, filters) {
  return useQuery({
    queryKey: ['insights', 'operational', endpoint, filters],
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

function ErrorPanel({ onRetry }) {
  return (
    <div className="flex flex-col items-center gap-2 py-8 text-center">
      <p className="text-[13px] text-slate-500">Unable to load data</p>
      <button
        onClick={onRetry}
        className="rounded bg-slate-100 px-3 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-200"
      >
        Retry
      </button>
    </div>
  );
}

function AgingCasesTable({ data }) {
  if (!data || !Array.isArray(data) || data.length === 0) return <EmptyState message="No aging cases data available." />;
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-[11px]">
        <thead>
          <tr className="border-b border-[#cbd5e1] text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">
            <th className="pb-2 pr-3">Case #</th>
            <th className="pb-2 pr-3">Client</th>
            <th className="pb-2 pr-3">Status</th>
            <th className="pb-2 pr-3 text-right">Days Aged</th>
            <th className="pb-2 text-right">Priority</th>
          </tr>
        </thead>
        <tbody>
          {data.map((row, i) => (
            <tr key={row.id || i} className="border-b border-[#e2e8f0] last:border-0">
              <td className="py-2 pr-3 font-semibold text-slate-700">{row.case_number || row.case_no || 'N/A'}</td>
              <td className="py-2 pr-3 text-slate-700">{row.client_name || row.client || 'N/A'}</td>
              <td className="py-2 pr-3">
                <span className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-bold uppercase ${
                  row.status === 'OPEN' ? 'bg-blue-100 text-blue-700' :
                  row.status === 'PENDING' ? 'bg-amber-100 text-amber-700' :
                  'bg-slate-100 text-slate-700'
                }`}>{row.status}</span>
              </td>
              <td className={`py-2 pr-3 text-right font-bold ${
                (row.age_days || row.days_aged || row.days || 0) > 30 ? 'text-rose-700' :
                (row.age_days || row.days_aged || row.days || 0) > 14 ? 'text-amber-700' :
                'text-slate-700'
              }`}>{row.age_days || row.days_aged || row.days || 0}d</td>
              <td className={`py-2 text-right font-bold ${
                row.priority === 'HIGH' || row.priority === 'CRITICAL' ? 'text-rose-700' :
                row.priority === 'MEDIUM' ? 'text-amber-700' : 'text-slate-700'
              }`}>{row.priority || 'NORMAL'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function StalledReferralsTable({ data }) {
  if (!data || !Array.isArray(data) || data.length === 0) return <EmptyState message="No stalled referrals data available." />;
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-[11px]">
        <thead>
          <tr className="border-b border-[#cbd5e1] text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">
            <th className="pb-2 pr-3">Referral</th>
            <th className="pb-2 pr-3">Agency</th>
            <th className="pb-2 pr-3">Service</th>
            <th className="pb-2 pr-3 text-right">Days Stalled</th>
            <th className="pb-2 text-right">Last Action</th>
          </tr>
        </thead>
        <tbody>
          {data.map((row, i) => (
            <tr key={row.id || i} className="border-b border-[#e2e8f0] last:border-0">
              <td className="py-2 pr-3 font-semibold text-slate-700">{row.case_number || row.referral_no || row.referral_number || 'N/A'}</td>
              <td className="py-2 pr-3 text-slate-700">{row.agency_name || row.agency || 'N/A'}</td>
              <td className="py-2 pr-3 text-slate-700">{row.service_type || row.service || 'N/A'}</td>
              <td className={`py-2 pr-3 text-right font-bold ${
                (row.days_since_activity || row.days_stalled || row.days || 0) > 30 ? 'text-rose-700' : 'text-amber-700'
              }`}>{row.days_since_activity || row.days_stalled || row.days || 0}d</td>
              <td className="py-2 text-right text-[11px] text-slate-500">
                {row.last_action_date ? new Date(row.last_action_date + 'T00:00:00').toLocaleDateString() : 'N/A'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function OverloadedAgenciesChart({ data }) {
  const chartData = useMemo(() => {
    if (!data || !Array.isArray(data) || data.length === 0) return null;
    return {
      labels: data.map((a) => a.agency_name || a.agency || a.name),
      datasets: [{
        label: 'Active Cases',
        data: data.map((a) => a.active_cases || a.cases || a.count || 0),
        backgroundColor: data.map((a) => {
          const load = a.active_cases || a.cases || a.count || 0;
          const capacity = a.capacity || 20;
          return load > capacity * 0.8 ? '#ef4444' : load > capacity * 0.5 ? '#f59e0b' : '#22c55e';
        }),
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [data]);

  if (!chartData) return <EmptyState message="No overloaded agencies data available." />;
  return (
    <div className="h-64">
      <Bar data={chartData} options={barOptions} />
    </div>
  );
}

function BottleneckAnalysis({ data }) {
  if (!data || !Array.isArray(data) || data.length === 0) return <EmptyState message="No bottleneck analysis data available." />;
  return (
    <div className="space-y-3">
      {data.map((step, i) => {
        const isLast = i === data.length - 1;
        return (
          <div key={step.step || step.name || i} className="flex items-start gap-3">
            <div className="flex flex-col items-center">
              <div className={`flex h-8 w-8 items-center justify-center rounded-full text-[11px] font-bold text-white ${
                step.is_bottleneck ? 'bg-rose-500' : 'bg-slate-400'
              }`}>
                {i + 1}
              </div>
              {!isLast && <div className="h-6 w-0.5 bg-slate-200" />}
            </div>
            <div className="flex-1 pb-4">
              <div className="flex items-center justify-between">
                <span className="text-[12px] font-semibold text-slate-700">{step.label || step.name || step.step}</span>
                <div className="flex items-center gap-2">
                  <span className="text-[11px] font-bold text-slate-700">{step.count || 0}</span>
                  {step.is_bottleneck && (
                    <span className="rounded bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-rose-700">Bottleneck</span>
                  )}
                </div>
              </div>
              <div className="mt-1 h-2 w-full rounded-full bg-slate-100">
                <div
                  className={`h-2 rounded-full transition-all ${step.is_bottleneck ? 'bg-rose-500' : 'bg-slate-300'}`}
                  style={{ width: `${Math.min(100, step.percentage || step.pct || (step.count / (data[0]?.count || 1)) * 100)}%` }}
                />
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

function RejectionAnalysis({ data }) {
  const chartData = useMemo(() => {
    if (!data || !Array.isArray(data) || data.length === 0) return null;
    const total = data.reduce((s, r) => s + (r.count || r.value || 0), 0) || 1;
    return {
      labels: data.map((r) => r.reason || r.label || r.status),
      datasets: [{
        data: data.map((r) => r.count || r.value || 0),
        backgroundColor: ['#f43f5e', '#fb7185', '#fda4af', '#fecdd3', '#ffe4e6'],
        borderWidth: 0,
      }],
      _total: total,
    };
  }, [data]);

  if (!chartData) return <EmptyState message="No rejection analysis data available." />;
  return (
    <div className="flex flex-col gap-4 sm:flex-row">
      <div className="h-40 w-40 shrink-0">
        <Doughnut data={chartData} options={doughnutOptions} />
      </div>
      <div className="flex-1 space-y-1.5">
        {data.map((r) => {
          const count = r.count || r.value || 0;
          const pct = chartData._total > 0 ? Math.round((count / chartData._total) * 100) : 0;
          return (
            <div key={r.reason || r.label || r.status} className="flex items-center justify-between text-[11px]">
              <span className="text-slate-600 font-medium">{r.reason || r.label || r.status}</span>
              <span className="font-bold text-slate-700">{count} ({pct}%)</span>
            </div>
          );
        })}
      </div>
      {data.length > 0 && (
        <div className="w-full overflow-x-auto mt-2">
          <table className="w-full text-[11px]">
            <thead>
              <tr className="border-b border-[#cbd5e1] text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">
                <th className="pb-2 pr-3">Reason</th>
                <th className="pb-2 pr-3 text-right">Count</th>
                <th className="pb-2 text-right">%</th>
              </tr>
            </thead>
            <tbody>
              {data.map((r) => {
                const count = r.count || r.value || 0;
                const pct = chartData._total > 0 ? Math.round((count / chartData._total) * 100) : 0;
                return (
                  <tr key={r.reason || r.label || r.status} className="border-b border-[#e2e8f0] last:border-0">
                    <td className="py-1.5 pr-3 text-slate-700">{r.reason || r.label || r.status}</td>
                    <td className="py-1.5 pr-3 text-right font-bold text-slate-700">{count}</td>
                    <td className="py-1.5 text-right text-slate-600">{pct}%</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export default function OperationalMonitor({ from, to }) {
  const { can } = useInsightsAccess();
  const filters = { from, to };

  const agingQ = useOperationalQuery('aging-cases', filters);
  const stalledQ = useOperationalQuery('stalled-referrals', filters);
  const overloadedQ = useOperationalQuery('overloaded-agencies', filters);
  const bottleneckQ = useOperationalQuery('bottleneck-analysis', filters);
  const rejectionQ = useOperationalQuery('rejection-analysis', filters);

  const isLoading = agingQ.isLoading || stalledQ.isLoading || overloadedQ.isLoading || bottleneckQ.isLoading || rejectionQ.isLoading;
  if (isLoading) {
    return (
      <div className="space-y-4">
        <SectionSkeleton type="table" count={2} />
      </div>
    );
  }

  const agingCases = useMemo(() => {
    if (!can('aging_cases')) return null;
    return agingQ.data?.details ?? [];
  }, [agingQ.data, can]);

  const stalledReferrals = useMemo(() => {
    return stalledQ.data?.referrals ?? [];
  }, [stalledQ.data]);

  const overloadedAgencies = useMemo(() => {
    if (!can('overloaded_agencies') || !overloadedQ.data) return null;
    return overloadedQ.data.labels.map((name, i) => ({
      agency_name: name,
      active_cases: overloadedQ.data.data[i],
      capacity: overloadedQ.data.threshold,
    }));
  }, [overloadedQ.data, can]);

  const bottleneckAnalysis = useMemo(() => {
    if (!can('bottleneck_detection') || !bottleneckQ.data) return null;
    return bottleneckQ.data.labels.map((label, i) => ({
      label,
      count: bottleneckQ.data.datasets?.[0]?.data?.[i] ?? 0,
      is_bottleneck: (bottleneckQ.data.datasets?.[0]?.data?.[i] ?? 0) > 24,
      percentage: Math.min(100, ((bottleneckQ.data.datasets?.[0]?.data?.[i] ?? 0) / 48) * 100),
    }));
  }, [bottleneckQ.data, can]);

  const rejectionAnalysis = useMemo(() => {
    if (!rejectionQ.data) return [];
    return rejectionQ.data.labels.map((l, i) => ({ reason: l, count: rejectionQ.data.data[i] }));
  }, [rejectionQ.data]);

  return (
    <div className="space-y-4">
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <SectionCard title="Aging Cases">
          {agingQ.isError ? (
            <ErrorPanel onRetry={() => agingQ.refetch()} />
          ) : (
            <AgingCasesTable data={agingCases} />
          )}
        </SectionCard>
        <SectionCard title="Stalled Referrals">
          {stalledQ.isError ? (
            <ErrorPanel onRetry={() => stalledQ.refetch()} />
          ) : (
            <StalledReferralsTable data={stalledReferrals} />
          )}
        </SectionCard>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <SectionCard title="Overloaded Agencies">
          {overloadedQ.isError ? (
            <ErrorPanel onRetry={() => overloadedQ.refetch()} />
          ) : (
            <OverloadedAgenciesChart data={overloadedAgencies} />
          )}
        </SectionCard>
        <SectionCard title="Bottleneck Analysis">
          {bottleneckQ.isError ? (
            <ErrorPanel onRetry={() => bottleneckQ.refetch()} />
          ) : (
            <BottleneckAnalysis data={bottleneckAnalysis} />
          )}
        </SectionCard>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <SectionCard title="Rejection Analysis">
          {rejectionQ.isError ? (
            <ErrorPanel onRetry={() => rejectionQ.refetch()} />
          ) : (
            <RejectionAnalysis data={rejectionAnalysis} />
          )}
        </SectionCard>
      </section>
    </div>
  );
}
