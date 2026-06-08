import { useMemo } from 'react';
import { Bar } from 'react-chartjs-2';
import MetricCard from '@/Components/Insights/MetricCard';
import { Clock, Zap } from 'lucide-react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import SectionSkeleton from '../SectionSkeleton';

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    y: { beginAtZero: true, ticks: { font: { size: 10 } }, grid: { color: '#f1f5f9' }, max: 100 },
    x: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
};

function ScorecardTable({ title, data, columns }) {
  if (!data || data.length === 0) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{title}</h3>
        <p className="py-8 text-center text-[13px] text-slate-400">No {title.toLowerCase()} data available.</p>
      </article>
    );
  }

  return (
    <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{title}</h3>
      <div className="overflow-x-auto">
        <table className="w-full text-[11px]">
          <thead>
            <tr className="border-b border-[#cbd5e1] text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">
              {columns.map((col) => (
                <th key={col.key} className={`pb-2 pr-3 ${col.align === 'right' ? 'text-right' : ''} ${col.last ? '' : ''}`}>
                  {col.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.map((row, i) => (
              <tr key={row.id || row.name || i} className="border-b border-[#e2e8f0] last:border-0">
                {columns.map((col) => (
                  <td key={col.key} className={`py-2 pr-3 ${col.align === 'right' ? 'text-right' : ''} ${col.bold ? 'font-bold' : ''} ${col.colorFn ? col.colorFn(row) : 'text-slate-700'}`}>
                    {col.render ? col.render(row) : row[col.key] ?? 'N/A'}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </article>
  );
}

function CompletionRateChart({ data }) {
  const chartData = useMemo(() => {
    if (!data || data.length === 0) return null;
    return {
      labels: data.map((d) => d.service || d.name || d.label),
      datasets: [{
        label: 'Completion Rate %',
        data: data.map((d) => d.rate || d.completion_rate || d.completionRate || 0),
        backgroundColor: data.map((d) => {
          const rate = d.rate || d.completion_rate || d.completionRate || 0;
          return rate >= 80 ? '#22c55e' : rate >= 50 ? '#f59e0b' : '#ef4444';
        }),
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [data]);

  if (!chartData) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Service Completion Rate</h3>
        <p className="py-8 text-center text-[13px] text-slate-400">No completion rate data available.</p>
      </article>
    );
  }

  return (
    <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Service Completion Rate</h3>
      <div className="h-56">
        <Bar data={chartData} options={barOptions} />
      </div>
    </article>
  );
}

export default function Scorecards({ from, to }) {
  const filters = { from, to };

  const cmQ = useQuery({
    queryKey: ['insights', 'cm-scorecard', filters],
    queryFn: async () => {
      const params = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null)),
      );
      const res = await fetch(`/api/insights/case-manager-scorecard?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const agencyQ = useQuery({
    queryKey: ['insights', 'agency-scorecard', filters],
    queryFn: async () => {
      const params = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null)),
      );
      const res = await fetch(`/api/insights/agency-scorecard?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const completionQ = useQuery({
    queryKey: ['insights', 'service-completion-rate', filters],
    queryFn: async () => {
      const params = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null)),
      );
      const res = await fetch(`/api/insights/service-completion-rate?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const frtQ = useQuery({
    queryKey: ['insights', 'first-response-time', filters],
    queryFn: async () => {
      const params = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([_, v]) => v != null)),
      );
      const res = await fetch(`/api/insights/first-response-time?${params}`);
      if (!res.ok) throw new Error(`Failed: ${res.status}`);
      return res.json();
    },
    staleTime: 5 * 60 * 1000,
    placeholderData: keepPreviousData,
  });

  const isLoading = cmQ.isLoading || agencyQ.isLoading || completionQ.isLoading || frtQ.isLoading;
  if (isLoading) return <SectionSkeleton type="table" count={2} />;

  const caseManagerScorecard = cmQ.data?.rows ?? [];
  const agencyScorecard = agencyQ.data?.detailed ?? [];
  const serviceCompletionRate = completionQ.data?.services ?? [];
  const firstResponseTime = frtQ.data;

  const cmColumns = [
    { key: 'name', label: 'Case Manager', bold: true },
    { key: 'total', label: 'Total', align: 'right' },
    { key: 'completed', label: 'Completed', align: 'right' },
    { key: 'rate', label: 'Rate %', align: 'right', bold: true,
      render: (row) => {
        const rate = row.rate || row.completion_rate || row.completionRate || 0;
        const color = rate >= 80 ? 'text-emerald-700' : rate >= 50 ? 'text-amber-700' : 'text-rose-700';
        return <span className={color}>{rate}%</span>;
      }
    },
    { key: 'avg_days', label: 'Avg Days', align: 'right',
      render: (row) => {
        const days = row.avg_days || row.avgDays || row.average_days || 0;
        const color = days <= 7 ? 'text-emerald-700' : days <= 14 ? 'text-amber-700' : 'text-rose-700';
        return <span className={color}>{days}d</span>;
      }
    },
  ];

  const agColumns = [
    { key: 'name', label: 'Agency', bold: true },
    { key: 'total', label: 'Total', align: 'right' },
    { key: 'completed', label: 'Completed', align: 'right' },
    { key: 'rate', label: 'Rate %', align: 'right', bold: true,
      render: (row) => {
        const rate = row.rate || row.completion_rate || row.completionRate || 0;
        const color = rate >= 80 ? 'text-emerald-700' : rate >= 50 ? 'text-amber-700' : 'text-rose-700';
        return <span className={color}>{rate}%</span>;
      }
    },
    { key: 'avg_days', label: 'Avg Days', align: 'right',
      render: (row) => {
        const days = row.avg_days || row.avgDays || row.average_days || 0;
        const color = days <= 7 ? 'text-emerald-700' : days <= 14 ? 'text-amber-700' : 'text-rose-700';
        return <span className={color}>{days}d</span>;
      }
    },
  ];

  const frt = firstResponseTime;
  const frtValue = frt?.value ?? frt?.avg_hours ?? frt?.average ?? null;

  return (
    <div className="space-y-4">
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <ScorecardTable
          title="Case Manager Scorecard"
          data={caseManagerScorecard}
          columns={cmColumns}
        />
        <ScorecardTable
          title="Agency Scorecard"
          data={agencyScorecard}
          columns={agColumns}
        />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <CompletionRateChart data={serviceCompletionRate} />
        <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
          <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            First Response Time
          </h3>
          {frtValue !== null ? (
            <div className="flex items-center justify-center py-4">
              <div className="text-center">
                <p className="text-[40px] font-black text-[#0f172a]">{frtValue}</p>
                <p className="text-[11px] font-semibold text-slate-500">hours (avg)</p>
                {frt?.change !== null && frt?.change !== undefined && (
                  <span className={`inline-flex items-center gap-1 mt-1 text-[11px] font-bold ${
                    frt.change > 0 ? 'text-rose-600' : 'text-emerald-600'
                  }`}>
                    {frt.change > 0 ? '▲' : '▼'} {Math.abs(frt.change)}%
                    <span className="text-[9px] font-semibold text-slate-400">vs prev</span>
                  </span>
                )}
              </div>
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No first response time data available.</p>
          )}
        </article>
      </section>
    </div>
  );
}
