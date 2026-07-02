import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
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

export default function LazyChartArticle({ lazyKey, title, desc, emptyText }) {
  return (
    <ReportLazySection lazyKey={lazyKey} skeleton={<ChartSkeleton />} emptyMessage={emptyText}>
      {(data) => {
        if (!data?.labels?.length) {
          return (
            <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
              <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>{title}</h3>
              {desc && <p className="mb-3 text-[11px] text-slate-500">{desc}</p>}
              <p className="py-8 text-center text-[13px] text-slate-400">{emptyText}</p>
            </article>
          );
        }

        const chartData = {
          labels: data.labels,
          datasets: [
            {
              data: data.data,
              backgroundColor: data.colors || COLORS.chartPalette,
              borderColor: data.colors || COLORS.chartPalette,
              borderWidth: 1,
            },
          ],
        };

        return (
          <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
            <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>{title}</h3>
            {desc && <p className="mb-3 text-[11px] text-slate-500">{desc}</p>}
            <div className="h-56">
              <Bar data={chartData} options={barOptions} />
            </div>
          </article>
        );
      }}
    </ReportLazySection>
  );
}
