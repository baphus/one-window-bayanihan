import { useMemo } from 'react';
import { Doughnut } from 'react-chartjs-2';
import ChartSkeleton from './ChartSkeleton';
import { formatStatusLabel } from '@/lib/utils';

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  cutout: '55%',
};

const cardClass = 'rounded-[3px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900';

export default function CaseStatusPieChart({ data, loading }) {
  const chartData = useMemo(() => {
    if (!data?.labels?.length) return null;
    return {
      labels: data.labels.map(formatStatusLabel),
      datasets: [{
        data: data.data,
        backgroundColor: data.colors || ['#1e3a8a', '#10b981'],
        borderWidth: 0,
      }],
    };
  }, [data]);

  const total = useMemo(() => {
    if (!data?.data) return 0;
    return data.data.reduce((s, v) => s + v, 0);
  }, [data]);

  if (loading) return <ChartSkeleton />;

  if (!chartData) {
    return (
      <article className={cardClass}>
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
          Cases by Status
        </h3>
        <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">
          No case status data available.
        </p>
      </article>
    );
  }

  return (
    <article className={cardClass}>
      <div className="mb-4 flex items-start justify-between gap-3">
        <div>
          <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
            Cases by Status
          </h3>
          <p className="mt-1 text-[12px] text-slate-500 dark:text-slate-400">Open and closed case distribution.</p>
        </div>
        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
          {total} total
        </span>
      </div>
      <div className="flex items-center gap-6">
        <div className="relative h-28 w-28 shrink-0 rounded-full bg-slate-50 p-2 dark:bg-slate-800" aria-label={`Cases by status chart, ${total} total cases`}>
          <Doughnut data={chartData} options={doughnutOptions} />
          <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
            <span className="text-lg font-extrabold leading-none text-slate-900 dark:text-slate-100">{total}</span>
            <span className="mt-0.5 text-[9px] font-bold uppercase tracking-wider text-slate-400">Cases</span>
          </div>
        </div>
        <div className="flex-1 space-y-1.5">
          {data.labels.map((label, i) => {
            const count = data.data[i] || 0;
            const percent = total > 0 ? Math.round((count / total) * 100) : 0;
            const color = (data.colors || ['#1e3a8a', '#10b981'])[i];
            return (
              <div key={label} className="flex items-center justify-between text-[11px]">
                <span className="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                  <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: color }} />
                  <span className="font-medium">{formatStatusLabel(label)}</span>
                </span>
                <span className="font-bold text-slate-700 dark:text-slate-200">
                  {count} ({percent}%)
                </span>
              </div>
            );
          })}
        </div>
      </div>
    </article>
  );
}
