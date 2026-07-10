import { useMemo } from 'react';
import { Bar } from 'react-chartjs-2';
import { Briefcase } from 'lucide-react';
import MetricCard from '@/Components/Reports/MetricCard';

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: 'rgba(148,163,184,0.15)' } },
    y: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
};

export default function EmploymentAnalytics({
  employmentDistribution,
  employmentPositionBreakdown,
}) {
  const totalEmployed = useMemo(() => {
    if (!employmentDistribution?.data) return 0;
    return employmentDistribution.data.reduce((s, v) => s + v, 0);
  }, [employmentDistribution]);

  const countryChartData = useMemo(() => {
    if (!employmentDistribution?.labels?.length) return null;
    return {
      labels: employmentDistribution.labels,
      datasets: [{
        label: 'Clients',
        data: employmentDistribution.data,
        backgroundColor: '#0b5a8c',
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [employmentDistribution]);

  const totalPositions = useMemo(() => {
    if (!employmentPositionBreakdown) return 0;
    // Prefer the server-computed total_distinct (unlimited count) over
    // labels.length which is capped at top 10 results in the chart.
    return employmentPositionBreakdown.total_distinct
      ?? employmentPositionBreakdown.labels?.length
      ?? 0;
  }, [employmentPositionBreakdown]);

  return (
    <section className="space-y-4">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <MetricCard
          label="Employed Clients"
          value={`${totalEmployed}`}
          accent="border-l-[#0b5a8c]"
          trailing={
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[#0b5a8c]/10">
              <Briefcase className="h-4 w-4 text-[#0b5a8c]" />
            </div>
          }
        />
        <MetricCard
          label="Position Types"
          value={`${totalPositions}`}
          accent="border-l-[#6366f1]"
          trailing={
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50">
              <Briefcase className="h-4 w-4 text-indigo-500" />
            </div>
          }
        />
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <article className="border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
          <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
            Last Country of Employment
          </h3>
          {countryChartData ? (
            <div className="h-64">
              <Bar data={countryChartData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">
              No employment data available.
            </p>
          )}
        </article>

        <article className="border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
          <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
            Position Breakdown
          </h3>
          {employmentPositionBreakdown?.labels?.length > 0 ? (
            <div className="max-h-64 overflow-y-auto">
              <table className="w-full text-[11px]">
                <thead>
                  <tr className="border-b border-slate-300 dark:border-slate-700 text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 dark:text-slate-400">
                    <th className="pb-2 pr-3">Position</th>
                    <th className="pb-2 text-right">Clients</th>
                  </tr>
                </thead>
                <tbody>
                  {employmentPositionBreakdown.labels.map((label, i) => (
                    <tr key={label} className="border-b border-slate-200 dark:border-slate-700 last:border-0">
                      <td className="py-1.5 pr-3 font-medium capitalize text-slate-700 dark:text-slate-200">
                        {label.replace(/_/g, ' ').toLowerCase()}
                      </td>
                      <td className="py-1.5 text-right font-bold text-slate-700 dark:text-slate-200">
                        {employmentPositionBreakdown.data[i] || 0}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">
              No position data available.
            </p>
          )}
        </article>
      </div>
    </section>
  );
}
