import { useState } from 'react';
import { Line, Bar } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, BarElement,
  PointElement, LineElement,
  Title, Tooltip, Legend, Filler,
} from 'chart.js';
import { pageHeadingStyles } from './pageHeadingStyles';

ChartJS.register(
  CategoryScale, LinearScale, BarElement,
  PointElement, LineElement,
  Title, Tooltip, Legend, Filler,
);

const baseOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
    x: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
};

export default function TrendChart({
  title,
  data,
  options = {},
}) {
  const [chartView, setChartView] = useState('line');

  if (!data || !data.labels || data.labels.length === 0) {
    return (
      <article className="border border-slate-300 bg-white p-4">
        <h3 className={pageHeadingStyles.sectionTitle}>{title || 'Cases Over Time'}</h3>
        <p className="py-8 text-center text-[13px] text-slate-400">No trend data available.</p>
      </article>
    );
  }

  const mergedOptions = { ...baseOptions, ...options };
  const lineOpts = {
    ...mergedOptions,
    elements: { point: { radius: 3, hoverRadius: 5 } },
  };

  return (
    <article className="border border-slate-300 bg-white p-4">
      <div className="mb-3 flex items-center justify-between">
        <h3 className={pageHeadingStyles.sectionTitle}>{title || 'Trend'}</h3>
        <div className="inline-flex overflow-hidden rounded-[2px] border border-slate-300 bg-white">
          <button
            type="button"
            onClick={() => setChartView('line')}
            className={`h-7 px-2.5 text-[11px] font-semibold ${chartView === 'line' ? 'bg-[#0b5a8c] text-white' : 'text-slate-600 hover:bg-slate-50'}`}
          >
            Line
          </button>
          <button
            type="button"
            onClick={() => setChartView('bar')}
            className={`h-7 px-2.5 text-[11px] font-semibold ${chartView === 'bar' ? 'bg-[#0b5a8c] text-white' : 'text-slate-600 hover:bg-slate-50'}`}
          >
            Bar
          </button>
        </div>
      </div>
      <div className="h-[220px]">
        {chartView === 'line' ? (
          <Line data={data} options={lineOpts} />
        ) : (
          <Bar data={data} options={mergedOptions} />
        )}
      </div>
    </article>
  );
}
