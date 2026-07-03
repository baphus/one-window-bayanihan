import { useState, useMemo } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
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
      grid: { color: '#f1f5f9' },
    },
    y: {
      ticks: { font: { size: 10 } },
      grid: { display: false },
    },
  },
};

function SectionAccordion({ title, defaultOpen = false, children }) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="border bg-white shadow-sm" style={{ borderColor: COLORS.border }}>
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-slate-50"
      >
        <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{title}</h3>
        {open ? (
          <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
        ) : (
          <ChevronRight className="h-3.5 w-3.5 text-slate-400" />
        )}
      </button>
      {open && <div className="border-t border-slate-200 p-4">{children}</div>}
    </div>
  );
}

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
      <p className="py-8 text-center text-[13px] text-slate-400">
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
