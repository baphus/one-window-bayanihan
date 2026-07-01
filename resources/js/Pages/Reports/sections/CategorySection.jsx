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

export default function CategorySection({ categoryDistribution }) {
  const categoryChartData = useMemo(() => {
    if (!categoryDistribution?.length) return null;
    return {
      labels: categoryDistribution.map(c => c.name),
      datasets: [{
        label: 'Cases',
        data: categoryDistribution.map(c => c.count),
        backgroundColor: categoryDistribution.map(c => c.color),
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [categoryDistribution]);

  if (!categoryChartData) return null;

  return (
    <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
      <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Category Distribution</h3>
      {categoryChartData ? (
        <div className="h-56">
          <Bar data={categoryChartData} options={barOptions} />
        </div>
      ) : (
        <p className="py-8 text-center text-[13px] text-slate-400">No category data available.</p>
      )}
    </article>
  );
}
