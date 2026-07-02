import { useMemo } from 'react';
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
    x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
    y: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
};

export default function CycleTimeSection({ pageHeadingStyles }) {
  return (
    <ReportLazySection lazyKey="cycleTimeDistribution" skeleton={<ChartSkeleton />}>
      {(data) => {
        const chartData = useMemo(() => {
          if (!data?.labels) return null;
          return {
            labels: data.labels,
            datasets: [{
              label: 'Referrals',
              data: data.data,
              backgroundColor: data.colors || [COLORS.success, '#84cc16', COLORS.warning, COLORS.danger],
              borderRadius: 3,
              barThickness: 18,
            }],
          };
        }, [data]);

        return (
          <article
            className="border bg-white p-4 shadow-sm"
            style={{ borderColor: COLORS.border }}
          >
            <h3
              className={`mb-4 ${
                pageHeadingStyles?.sectionTitle ||
                'text-[11px] font-bold uppercase tracking-wider text-slate-500'
              }`}
            >
              Cycle Time Distribution
            </h3>
            <p className="mb-3 text-[11px] text-slate-500">
              Time from referral creation to completion
            </p>
            {chartData ? (
              <div className="h-56">
                <Bar data={chartData} options={barOptions} />
              </div>
            ) : (
              <p className="py-8 text-center text-[13px] text-slate-400">
                No completed referrals yet.
              </p>
            )}
          </article>
        );
      }}
    </ReportLazySection>
  );
}
