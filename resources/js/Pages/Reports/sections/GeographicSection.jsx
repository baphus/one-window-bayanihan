import { useState, useMemo } from 'react';
import { Bar } from 'react-chartjs-2';
import { ChevronDown, ChevronRight } from 'lucide-react';
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

export default function GeographicSection({ geographicDistribution }) {
  const geoChartData = useMemo(() => {
    if (!geographicDistribution?.labels) return null;
    return {
      labels: geographicDistribution.labels,
      datasets: [{
        label: 'Cases',
        data: geographicDistribution.data,
        backgroundColor: COLORS.primary,
        borderRadius: 3,
        barThickness: 18,
      }],
    };
  }, [geographicDistribution]);

  const [open, setOpen] = useState(true);

  return (
    <div className="border bg-white shadow-sm" style={{ borderColor: COLORS.border }}>
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-slate-50"
      >
        <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Geographic Distribution</h3>
        {open ? <ChevronDown className="h-3.5 w-3.5 text-slate-400" /> : <ChevronRight className="h-3.5 w-3.5 text-slate-400" />}
      </button>
      {open && (
        <div className="border-t border-[#e2e8f0] p-4">
          {geoChartData ? (
            <div className="h-56">
              <Bar data={geoChartData} options={barOptions} />
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">No geographic data available.</p>
          )}
        </div>
      )}
    </div>
  );
}
