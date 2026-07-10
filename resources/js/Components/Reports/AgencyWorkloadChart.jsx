import { Bar } from 'react-chartjs-2';
import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import { cardShell, COLORS, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';

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

function toBarData(data) {
  if (!data?.labels?.length) return null;

  return {
    labels: data.labels,
    datasets: [{
      label: 'Referrals',
      data: data.data,
      backgroundColor: data.colors || COLORS.chartPalette,
      borderColor: data.colors || COLORS.chartPalette,
      borderWidth: 1,
      borderRadius: 3,
      barThickness: 16,
    }],
  };
}

export default function AgencyWorkloadChart({ role }) {
  if (role && role !== 'ADMIN') return null;

  return (
    <ReportLazySection lazyKey="agencyWorkload" skeleton={<ChartSkeleton />} emptyMessage="No agency workload data available.">
      {(data) => {
        const chartData = toBarData(data);

        return (
          <article className={`${cardShell} p-4`}>
            <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Agency Workload</h3>
            {chartData ? (
              <div className="h-64">
                <Bar data={chartData} options={barOptions} />
              </div>
            ) : (
              <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">No agency workload data available.</p>
            )}
          </article>
        );
      }}
    </ReportLazySection>
  );
}
