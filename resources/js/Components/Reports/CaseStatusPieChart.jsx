import { useMemo } from 'react';
import { Doughnut } from 'react-chartjs-2';
import ChartSkeleton from './ChartSkeleton';

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  cutout: '55%',
};

export default function CaseStatusPieChart({ data, loading }) {
  const chartData = useMemo(() => {
    if (!data?.labels?.length) return null;
    return {
      labels: data.labels,
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
      <article className="border border-[#cbd5e1] bg-white p-4">
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
          Cases by Status
        </h3>
        <p className="py-8 text-center text-[13px] text-slate-400">
          No case status data available.
        </p>
      </article>
    );
  }

  return (
    <article className="border border-[#cbd5e1] bg-white p-4">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
        Cases by Status
      </h3>
      <div className="flex items-center gap-6">
        <div className="h-28 w-28 shrink-0">
          <Doughnut data={chartData} options={doughnutOptions} />
        </div>
        <div className="flex-1 space-y-1.5">
          {data.labels.map((label, i) => {
            const count = data.data[i] || 0;
            const percent = total > 0 ? Math.round((count / total) * 100) : 0;
            const color = (data.colors || ['#1e3a8a', '#10b981'])[i];
            return (
              <div key={label} className="flex items-center justify-between text-[11px]">
                <span className="inline-flex items-center gap-1.5 text-slate-600">
                  <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: color }} />
                  <span className="font-medium">{label}</span>
                </span>
                <span className="font-bold text-slate-700">
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
