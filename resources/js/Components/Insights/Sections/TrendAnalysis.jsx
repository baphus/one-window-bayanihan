import { useState, useMemo } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import TrendChart from '@/Components/Insights/TrendChart';
import SectionSkeleton from '@/Components/Insights/SectionSkeleton';

const INTERVAL_OPTIONS = [
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
];

export default function TrendAnalysis({ from, to }) {
  const [selectedInterval, setSelectedInterval] = useState('daily');
  const filters = useMemo(() => ({ from, to }), [from, to]);

  const caseTrendsQ = useQuery({
    queryKey: ['insights', 'case-trends', filters],
    queryFn: async () => {
      const params = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null)),
      );
      const res = await fetch(`/api/insights/case-trends?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const referralVolumeQ = useQuery({
    queryKey: ['insights', 'referral-volume', filters],
    queryFn: async () => {
      const params = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null)),
      );
      const res = await fetch(`/api/insights/referral-volume?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const slaComplianceQ = useQuery({
    queryKey: ['insights', 'sla-compliance', filters],
    queryFn: async () => {
      const params = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null)),
      );
      const res = await fetch(`/api/insights/sla-compliance?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const resolutionTime = useQuery({
    queryKey: ['insights', 'trends', 'resolution_time', selectedInterval, filters],
    queryFn: async () => {
      const params = new URLSearchParams({
        type: 'resolution_time',
        interval: selectedInterval,
        ...Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null)),
      });
      const res = await fetch(`/api/insights/trends?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const agencyWorkload = useQuery({
    queryKey: ['insights', 'trends', 'agency_workload', selectedInterval, filters],
    queryFn: async () => {
      const params = new URLSearchParams({
        type: 'agency_workload',
        interval: selectedInterval,
        ...Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null)),
      });
      const res = await fetch(`/api/insights/trends?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const resolutionChartData = useMemo(() => {
    const data = resolutionTime.data;
    if (!data || !data.labels) return data;
    const slaLine = {
      label: 'SLA Target (30 days)',
      data: data.labels.map(() => 30),
      borderColor: '#ef4444',
      backgroundColor: 'rgba(239, 68, 68, 0.05)',
      borderDash: [6, 4],
      pointRadius: 0,
      pointHoverRadius: 0,
      borderWidth: 2,
    };
    return {
      ...data,
      datasets: [...(data.datasets || []), slaLine],
    };
  }, [resolutionTime.data]);

  const stackedAreaOptions = useMemo(
    () => ({
      scales: {
        y: {
          beginAtZero: true,
          stacked: true,
          ticks: { font: { size: 10 } },
          grid: { color: '#f1f5f9' },
          title: {
            display: true,
            text: 'Referrals',
            font: { size: 10 },
          },
        },
        x: {
          stacked: true,
          ticks: { font: { size: 10 } },
          grid: { display: false },
        },
      },
    }),
    [],
  );

  const resolutionTimeOptions = useMemo(
    () => ({
      scales: {
        y: {
          beginAtZero: true,
          ticks: { font: { size: 10 } },
          grid: { color: '#f1f5f9' },
          title: {
            display: true,
            text: 'Days',
            font: { size: 10 },
          },
        },
        x: {
          ticks: { font: { size: 10 } },
          grid: { display: false },
        },
      },
    }),
    [],
  );

  const isInitialLoading =
    caseTrendsQ.isLoading ||
    referralVolumeQ.isLoading ||
    slaComplianceQ.isLoading ||
    resolutionTime.isLoading ||
    agencyWorkload.isLoading;

  if (isInitialLoading) {
    return <SectionSkeleton type="card" count={1} />;
  }

  const hasAnyData =
    caseTrendsQ.data ||
    referralVolumeQ.data ||
    slaComplianceQ.data ||
    resolutionTime.data ||
    agencyWorkload.data;

  if (!hasAnyData) {
    return (
      <div className="flex items-center justify-center py-16">
        <p className="text-[13px] text-slate-400">No trend data available. Adjust date range.</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <span className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
          Interval:
        </span>
        <div className="flex overflow-hidden rounded-md border border-[#cbd5e1] bg-white">
          {INTERVAL_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => setSelectedInterval(opt.value)}
              className={`px-3 py-1.5 text-[11px] font-semibold transition-colors ${
                selectedInterval === opt.value
                  ? 'bg-slate-800 text-white'
                  : 'text-slate-600 hover:bg-slate-50'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <TrendChart
          title="Case Trends"
          data={caseTrendsQ.data}
          loading={caseTrendsQ.isLoading}
          error={caseTrendsQ.isError ? 'Failed to load case trends.' : null}
          onRetry={() => caseTrendsQ.refetch()}
          emptyMessage="No case trend data available. Adjust date range."
        />
        <TrendChart
          title="Referral Volume"
          data={referralVolumeQ.data}
          loading={referralVolumeQ.isLoading}
          error={referralVolumeQ.isError ? 'Failed to load referral volume.' : null}
          onRetry={() => referralVolumeQ.refetch()}
          emptyMessage="No referral volume data available. Adjust date range."
        />
        <TrendChart
          title="SLA Compliance"
          data={slaComplianceQ.data}
          loading={slaComplianceQ.isLoading}
          error={slaComplianceQ.isError ? 'Failed to load SLA compliance.' : null}
          onRetry={() => slaComplianceQ.refetch()}
          emptyMessage="No SLA compliance data available. Adjust date range."
        />
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <TrendChart
          title="Resolution Time"
          type="line"
          data={resolutionChartData}
          loading={resolutionTime.isLoading}
          error={resolutionTime.isError ? 'Failed to load resolution time data.' : null}
          onRetry={() => resolutionTime.refetch()}
          emptyMessage="No resolution time data available for the selected period."
          options={resolutionTimeOptions}
        />
        <TrendChart
          title="Agency Workload"
          type="area"
          data={agencyWorkload.data}
          loading={agencyWorkload.isLoading}
          error={agencyWorkload.isError ? 'Failed to load agency workload data.' : null}
          onRetry={() => agencyWorkload.refetch()}
          emptyMessage="No agency workload data available for the selected period."
          options={stackedAreaOptions}
        />
      </div>

      {!caseTrendsQ.data && (
        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <p className="py-6 text-center text-[13px] text-slate-400">
            No case trend data available for the selected period.
          </p>
        </article>
      )}
      {!referralVolumeQ.data && (
        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <p className="py-6 text-center text-[13px] text-slate-400">
            No referral volume data available for the selected period.
          </p>
        </article>
      )}
      {!slaComplianceQ.data && (
        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <p className="py-6 text-center text-[13px] text-slate-400">
            No SLA compliance data available for the selected period.
          </p>
        </article>
      )}
    </div>
  );
}
