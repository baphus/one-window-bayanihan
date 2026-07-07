import { useMemo } from 'react';
import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import SectionAccordion from '@/Components/Reports/SectionAccordion';
import { Bar } from 'react-chartjs-2';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: {
      beginAtZero: true,
      ticks: { stepSize: 1, font: { size: 10 } },
      grid: { color: 'rgba(148,163,184,0.15)' },
    },
    y: {
      ticks: { font: { size: 10 } },
      grid: { display: false },
    },
  },
};

function GeographicChart({ geoData }) {
  const chartData = useMemo(() => {
    if (!geoData?.labels?.length) return null;
    return {
      labels: geoData.labels,
      datasets: [
        {
          label: 'Cases',
          data: geoData.data,
          backgroundColor: geoData.labels.map(
            (_, i) => COLORS.chartPalette[i % COLORS.chartPalette.length],
          ),
          borderRadius: 3,
          barThickness: 18,
        },
      ],
    };
  }, [geoData]);

  if (!chartData) {
    return (
      <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">
        No geographic data available.
      </p>
    );
  }

  return (
    <div className="h-56">
      <Bar data={chartData} options={barOptions} />
    </div>
  );
}

export default function GeographicSection() {
  return (
    <SectionAccordion title="Geographic Distribution" defaultOpen>
      <ReportLazySection
        lazyKey="geographicDistribution"
        skeleton={<ChartSkeleton />}
        emptyMessage="No geographic data available."
      >
        {(data) => <GeographicChart geoData={data} />}
      </ReportLazySection>
    </SectionAccordion>
  );
}
