import TrendChart from '@/Components/Insights/TrendChart';
import { useState, useEffect, useMemo, useCallback } from 'react';
import axios from 'axios';

const INTERVAL_OPTIONS = [
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
];

export default function TrendAnalysis({
  caseTrends,
  referralVolume,
  slaCompliance,
  from,
  to,
}) {
  const [selectedInterval, setSelectedInterval] = useState('daily');
  const [resolutionTime, setResolutionTime] = useState(null);
  const [agencyWorkload, setAgencyWorkload] = useState(null);
  const [rtLoading, setRtLoading] = useState(false);
  const [awLoading, setAwLoading] = useState(false);
  const [rtError, setRtError] = useState(null);
  const [awError, setAwError] = useState(null);

  const fetchResolutionTime = useCallback(() => {
    setRtLoading(true);
    setRtError(null);
    axios
      .get('/api/insights/trends', {
        params: { type: 'resolution_time', interval: selectedInterval, from, to },
      })
      .then((res) => setResolutionTime(res.data))
      .catch((err) =>
        setRtError(
          err.response?.data?.message || 'Failed to load resolution time data.',
        ),
      )
      .finally(() => setRtLoading(false));
  }, [selectedInterval, from, to]);

  const fetchAgencyWorkload = useCallback(() => {
    setAwLoading(true);
    setAwError(null);
    axios
      .get('/api/insights/trends', {
        params: { type: 'agency_workload', interval: selectedInterval, from, to },
      })
      .then((res) => setAgencyWorkload(res.data))
      .catch((err) =>
        setAwError(
          err.response?.data?.message || 'Failed to load agency workload data.',
        ),
      )
      .finally(() => setAwLoading(false));
  }, [selectedInterval, from, to]);

  useEffect(() => {
    fetchResolutionTime();
  }, [fetchResolutionTime]);

  useEffect(() => {
    fetchAgencyWorkload();
  }, [fetchAgencyWorkload]);

  const resolutionChartData = useMemo(() => {
    if (!resolutionTime || !resolutionTime.labels) return resolutionTime;
    const slaLine = {
      label: 'SLA Target (30 days)',
      data: resolutionTime.labels.map(() => 30),
      borderColor: '#ef4444',
      backgroundColor: 'rgba(239, 68, 68, 0.05)',
      borderDash: [6, 4],
      pointRadius: 0,
      pointHoverRadius: 0,
      borderWidth: 2,
    };
    return {
      ...resolutionTime,
      datasets: [...(resolutionTime.datasets || []), slaLine],
    };
  }, [resolutionTime]);

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

  const hasAnyData =
    caseTrends ||
    referralVolume ||
    slaCompliance ||
    rtLoading ||
    awLoading ||
    resolutionTime ||
    agencyWorkload;

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
          data={caseTrends}
          emptyMessage="No case trend data available. Adjust date range."
        />
        <TrendChart
          title="Referral Volume"
          data={referralVolume}
          emptyMessage="No referral volume data available. Adjust date range."
        />
        <TrendChart
          title="SLA Compliance"
          data={slaCompliance}
          emptyMessage="No SLA compliance data available. Adjust date range."
        />
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <TrendChart
          title="Resolution Time"
          type="line"
          data={resolutionChartData}
          loading={rtLoading}
          error={rtError}
          emptyMessage="No resolution time data available for the selected period."
          options={resolutionTimeOptions}
        />
        <TrendChart
          title="Agency Workload"
          type="area"
          data={agencyWorkload}
          loading={awLoading}
          error={awError}
          emptyMessage="No agency workload data available for the selected period."
          options={stackedAreaOptions}
        />
      </div>

      {!caseTrends && (
        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <p className="py-6 text-center text-[13px] text-slate-400">
            No case trend data available for the selected period.
          </p>
        </article>
      )}
      {!referralVolume && (
        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <p className="py-6 text-center text-[13px] text-slate-400">
            No referral volume data available for the selected period.
          </p>
        </article>
      )}
      {!slaCompliance && (
        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <p className="py-6 text-center text-[13px] text-slate-400">
            No SLA compliance data available for the selected period.
          </p>
        </article>
      )}
    </div>
  );
}
