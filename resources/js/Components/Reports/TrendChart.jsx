import { useState } from 'react';
import { Line, Bar, Doughnut } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, BarElement, ArcElement,
  PointElement, LineElement,
  Title, Tooltip, Legend, Filler,
} from 'chart.js';
import { cardShell, pageHeadingStyles, COLORS } from './pageHeadingStyles';
import ChartTypeToggle from './ChartTypeToggle';

ChartJS.register(
  CategoryScale, LinearScale, BarElement, ArcElement,
  PointElement, LineElement,
  Title, Tooltip, Legend, Filler,
);

const baseOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: { enabled: true } },
  scales: {
    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: 'rgba(148,163,184,0.15)' } },
    x: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
};

// Build a doughnut dataset from the trend series (aggregate per label).
function toDoughnut(data) {
  const ds = data.datasets?.[0];
  return {
    labels: data.labels,
    datasets: [{
      data: ds?.data ?? [],
      backgroundColor: COLORS.chartPalette,
      borderWidth: 0,
    }],
  };
}

export default function TrendChart({ title, data, options = {}, types = ['line', 'bar'] }) {
  const [chartView, setChartView] = useState(types[0] || 'line');

  if (!data || !data.labels || data.labels.length === 0) {
    return (
      <article className={`${cardShell} p-4`}>
        <h3 className={pageHeadingStyles.sectionTitle}>{title || 'Cases Over Time'}</h3>
        <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">No trend data available.</p>
      </article>
    );
  }

  const mergedOptions = { ...baseOptions, ...options };
  const lineOpts = { ...mergedOptions, elements: { point: { radius: 3, hoverRadius: 5 } } };
  const pieOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: true, position: 'right', labels: { font: { size: 10 } } } },
    cutout: '55%',
  };

  return (
    <article className={`${cardShell} p-4`}>
      <div className="mb-3 flex items-center justify-between">
        <h3 className={pageHeadingStyles.sectionTitle}>{title || 'Trend'}</h3>
        <ChartTypeToggle value={chartView} onChange={setChartView} types={types} />
      </div>
      <div className="h-[220px]">
        {chartView === 'line' && <Line data={data} options={lineOpts} />}
        {chartView === 'bar' && <Bar data={data} options={mergedOptions} />}
        {(chartView === 'pie' || chartView === 'doughnut') && <Doughnut data={toDoughnut(data)} options={pieOpts} />}
      </div>
    </article>
  );
}
