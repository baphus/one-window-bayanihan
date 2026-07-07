import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import { Bar } from 'react-chartjs-2';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';
import { useMemo } from 'react';

export default function CategorySection({ pageHeadingStyles }) {
  const barOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { font: { size: 10 }, color: '#64748b' },
      },
      y: {
        grid: { display: false },
        ticks: { font: { size: 10 }, color: '#334155' },
      },
    },
  };

  return (
    <ReportLazySection lazyKey="categoryDistribution" skeleton={<ChartSkeleton />}>
      {(data) => {
        const chartData = data?.length
          ? {
              labels: data.map((c) => c.name),
              datasets: [
                {
                  label: 'Cases',
                  data: data.map((c) => c.count),
                  backgroundColor: data.map((c) => c.color),
                  borderRadius: 3,
                  barThickness: 18,
                },
              ],
            }
          : null;

        return (
          <article
            className="border bg-white dark:bg-slate-900 dark:border-slate-700 p-4 shadow-sm"
            style={{ borderColor: '#e2e8f0' }}
          >
            <h3
              className={`mb-4 ${pageHeadingStyles?.sectionTitle || 'text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400'}`}
            >
              Category Distribution
            </h3>
            {chartData ? (
              <div className="h-56">
                <Bar data={chartData} options={barOptions} />
              </div>
            ) : (
              <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">
                No category data available.
              </p>
            )}
          </article>
        );
      }}
    </ReportLazySection>
  );
}
