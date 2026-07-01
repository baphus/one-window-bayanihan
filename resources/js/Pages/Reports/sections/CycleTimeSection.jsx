import { useMemo } from 'react';
import { Bar } from 'react-chartjs-2';
import { COLORS, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';

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

export default function CycleTimeSection({ cycleTimeDistribution }) {
  const cycleTimeChartData = useMemo(() => {
    if (!cycleTimeDistribution?.labels) return null;
    return {
      labels: cycleTimeDistribution.labels,
      datasets: [{
        label: 'Referrals',
        data: cycleTimeDistribution.data,
        backgroundColor: cycleTimeDistribution.colors || [COLORS.success, '#84cc16', COLORS.warning, COLORS.danger],
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [cycleTimeDistribution]);

  if (!cycleTimeChartData) return null;

  return (
    <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
      <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Cycle Time Distribution</h3>
      <p className="mb-3 text-[11px] text-slate-500">Time from referral creation to completion</p>
      {cycleTimeChartData ? (
        <div className="h-56">
          <Bar data={cycleTimeChartData} options={barOptions} />
        </div>
      ) : (
        <p className="py-8 text-center text-[13px] text-slate-400">No completed referrals yet.</p>
      )}
    </article>
  );
}
