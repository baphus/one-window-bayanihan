import { useMemo } from 'react';
import { Line, Bar } from 'react-chartjs-2';
import MetricCard from '@/Components/Insights/MetricCard';
import { ShieldCheck, AlertTriangle, Ban, Users } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { keepPreviousData } from '@tanstack/react-query';
import SectionSkeleton from '../SectionSkeleton';

const lineOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: true, position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 10 } } },
    tooltip: { mode: 'index', intersect: false },
  },
  scales: {
    y: { beginAtZero: true, ticks: { font: { size: 10 } }, grid: { color: '#f1f5f9' } },
    x: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
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

function CaseVolumeForecast({ data }) {
  const chartData = useMemo(() => {
    if (!data || !data.labels || data.labels.length === 0) return null;

    const datasets = [];

    if (data.actual) {
      datasets.push({
        label: 'Actual',
        data: data.actual,
        borderColor: '#0b5a8c',
        backgroundColor: 'rgba(11,90,140,0.08)',
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        pointBackgroundColor: '#0b5a8c',
      });
    }

    if (data.moving_avg || data.movingAverage) {
      datasets.push({
        label: 'Moving Avg',
        data: data.moving_avg || data.movingAverage,
        borderColor: '#f59e0b',
        backgroundColor: 'transparent',
        borderDash: [4, 3],
        tension: 0.3,
        pointRadius: 0,
      });
    }

    if (data.projected || data.forecast) {
      datasets.push({
        label: 'Projected',
        data: data.projected || data.forecast,
        borderColor: '#8b5cf6',
        backgroundColor: 'rgba(139,92,246,0.08)',
        borderDash: [6, 4],
        fill: true,
        tension: 0.3,
        pointRadius: 2,
        pointBackgroundColor: '#8b5cf6',
      });
    }

    if (datasets.length === 0) {
      // If no structured data, treat entire data as a single dataset
      return {
        labels: data.labels,
        datasets: [{
          label: 'Cases',
          data: data.data || data.values || [],
          borderColor: '#0b5a8c',
          backgroundColor: 'rgba(11,90,140,0.08)',
          fill: true,
          tension: 0.3,
        }],
      };
    }

    return { labels: data.labels, datasets };
  }, [data]);

  if (!chartData) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Case Volume Forecast</h3>
        <p className="py-8 text-center text-[13px] text-slate-400">No forecast data available.</p>
      </article>
    );
  }

  return (
    <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Case Volume Forecast</h3>
      <p className="mb-3 text-[11px] text-slate-500">Actual vs projected case volume with moving average</p>
      <div className="h-64">
        <Line data={chartData} options={lineOptions} />
      </div>
    </article>
  );
}

function BreachProbabilityCards({ data }) {
  if (!data || data.length === 0) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Breach Probability</h3>
        <p className="py-8 text-center text-[13px] text-slate-400">No breach probability data available.</p>
      </article>
    );
  }

  const getItem = (status) => data.find((d) => (d.status || d.label || '').toLowerCase() === status);

  const withinSla = getItem('within_sla') || getItem('within sla') || getItem('compliant') || {};
  const warning = getItem('warning') || getItem('at_risk') || getItem('risk') || {};
  const breached = getItem('breached') || getItem('breach') || getItem('overdue') || {};

  return (
    <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Breach Probability</h3>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="rounded border border-emerald-200 bg-emerald-50 p-3 text-center">
          <ShieldCheck className="mx-auto h-5 w-5 text-emerald-600" />
          <p className="mt-1 text-[20px] font-black text-emerald-700">{withinSla.count || withinSla.value || 0}</p>
          <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-emerald-600">Within SLA</p>
        </div>
        <div className="rounded border border-amber-200 bg-amber-50 p-3 text-center">
          <AlertTriangle className="mx-auto h-5 w-5 text-amber-600" />
          <p className="mt-1 text-[20px] font-black text-amber-700">{warning.count || warning.value || 0}</p>
          <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-600">At Risk</p>
        </div>
        <div className="rounded border border-rose-200 bg-rose-50 p-3 text-center">
          <Ban className="mx-auto h-5 w-5 text-rose-600" />
          <p className="mt-1 text-[20px] font-black text-rose-700">{breached.count || breached.value || 0}</p>
          <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-rose-600">Breached</p>
        </div>
      </div>
    </article>
  );
}

function PeakPeriodsHeatmap({ data }) {
  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const hours = Array.from({ length: 24 }, (_, i) => i);

  const gridData = useMemo(() => {
    if (!data || data.length === 0) return null;
    // data can be: array of {day, hour, count} or 2D array
    if (Array.isArray(data)) {
      if (data.length > 0 && typeof data[0] === 'object' && 'day' in data[0]) {
        return data;
      }
    }
    return null;
  }, [data]);

  const getIntensity = (day, hour) => {
    if (!gridData) return 0;
    const match = gridData.find((d) => {
      const dDay = typeof d.day === 'number' ? days[d.day] : d.day;
      return dDay === day && d.hour === hour;
    });
    return match ? (match.count || match.value || 0) : 0;
  };

  const maxVal = useMemo(() => {
    if (!gridData) return 1;
    return Math.max(1, ...gridData.map((d) => d.count || d.value || 0));
  }, [gridData]);

  const getBg = (val) => {
    if (val === 0) return 'bg-slate-50';
    const intensity = Math.min(1, val / maxVal);
    if (intensity <= 0.2) return 'bg-blue-100';
    if (intensity <= 0.4) return 'bg-blue-200';
    if (intensity <= 0.6) return 'bg-blue-300';
    if (intensity <= 0.8) return 'bg-blue-400';
    return 'bg-blue-500';
  };

  if (!gridData) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Peak Periods</h3>
        <p className="py-8 text-center text-[13px] text-slate-400">No peak period data available.</p>
      </article>
    );
  }

  return (
    <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Peak Periods (Hourly Heatmap)</h3>
      <div className="overflow-x-auto">
        <div className="inline-block">
          <div className="flex items-end gap-0.5">
            <div className="w-8 shrink-0" />
            {hours.map((h) => (
              <div key={h} className="w-[14px] shrink-0 text-center">
                <span className="text-[7px] font-semibold text-slate-400">{h}</span>
              </div>
            ))}
          </div>
          {days.map((day) => (
            <div key={day} className="mt-0.5 flex items-center gap-0.5">
              <div className="w-8 shrink-0 text-right">
                <span className="text-[8px] font-semibold text-slate-500">{day}</span>
              </div>
              {hours.map((hour) => {
                const val = getIntensity(day, hour);
                return (
                  <div
                    key={`${day}-${hour}`}
                    className={`h-[14px] w-[14px] shrink-0 rounded-sm ${getBg(val)}`}
                    title={`${day} ${hour}:00 - ${val} cases`}
                  />
                );
              })}
            </div>
          ))}
        </div>
      </div>
    </article>
  );
}

function CapacityForecast({ data }) {
  const chartData = useMemo(() => {
    if (!data || data.length === 0) return null;

    const labels = data.map((d) => d.name || d.case_manager || d.cm || d.label);
    const current = data.map((d) => d.current || d.current_load || d.active || 0);
    const projected = data.map((d) => d.projected || d.forecast || d.expected || 0);

    return {
      labels,
      datasets: [
        {
          label: 'Current Load',
          data: current,
          backgroundColor: '#0b5a8c',
          borderRadius: 3,
          barThickness: 14,
        },
        {
          label: 'Projected Load',
          data: projected,
          backgroundColor: 'rgba(139,92,246,0.6)',
          borderRadius: 3,
          barThickness: 14,
        },
      ],
    };
  }, [data]);

  if (!chartData) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Capacity Forecast</h3>
        <p className="py-8 text-center text-[13px] text-slate-400">No capacity forecast data available.</p>
      </article>
    );
  }

  const capacityOptions = {
    ...barOptions,
    plugins: {
      ...barOptions.plugins,
      legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 9 } } },
    },
  };

  return (
    <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Capacity Forecast</h3>
      <p className="mb-3 text-[11px] text-slate-500">Current vs projected caseload per case manager</p>
      <div className="h-64">
        <Bar data={chartData} options={capacityOptions} />
      </div>
    </article>
  );
}

export default function Predictive({ from, to }) {
  const filters = {};

  const forecastQ = useQuery({
    queryKey: ['insights', 'case-volume-forecast'],
    queryFn: async () => {
      const res = await fetch(`/api/insights/case-volume-forecast`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const breachQ = useQuery({
    queryKey: ['insights', 'breach-probability'],
    queryFn: async () => {
      const res = await fetch(`/api/insights/breach-probability`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const peaksQ = useQuery({
    queryKey: ['insights', 'peak-periods'],
    queryFn: async () => {
      const res = await fetch(`/api/insights/peak-periods`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const capacityQ = useQuery({
    queryKey: ['insights', 'capacity-forecast'],
    queryFn: async () => {
      const res = await fetch(`/api/insights/capacity-forecast`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const isLoading = forecastQ.isLoading || breachQ.isLoading || peaksQ.isLoading || capacityQ.isLoading;
  if (isLoading) return <SectionSkeleton type="chart" count={2} />;

  return (
    <div className="space-y-4">
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[2fr_1fr]">
        <CaseVolumeForecast data={forecastQ.data} />
        <BreachProbabilityCards data={breachQ.data} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <PeakPeriodsHeatmap data={peaksQ.data} />
        <CapacityForecast data={capacityQ.data} />
      </section>
    </div>
  );
}
