import { Bar } from 'react-chartjs-2';
import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';

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

export default function CycleTimeSection({ pageHeadingStyles }) {
  return (
    <ReportLazySection lazyKey="cycleTimeDistribution" skeleton={<ChartSkeleton />}>
      {(data) => {
        const chartData = data?.labels ? {
          labels: data.labels,
          datasets: [{
            label: 'Referrals',
            data: data.data,
            backgroundColor: data.colors || [COLORS.success, '#84cc16', COLORS.warning, COLORS.danger],
            borderRadius: 3,
            barThickness: 18,
          }],
        } : null;

        return (
          <article
            className="border bg-white dark:bg-slate-900 dark:border-slate-700 p-4 shadow-sm"
            style={{ borderColor: COLORS.border }}
          >
            <h3
              className={`mb-4 ${
                pageHeadingStyles?.sectionTitle ||
                'text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400'
              }`}
            >
              Cycle Time Distribution
            </h3>
            <p className="mb-3 text-[11px] text-slate-500 dark:text-slate-400">
              Time from referral creation to completion
            </p>
            {chartData ? (
              <div className="h-56">
                <Bar data={chartData} options={barOptions} />
              </div>
            ) : (
              <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">
                No completed referrals yet.
              </p>
            )}
          </article>
        );
      }}
    </ReportLazySection>
  );
}
