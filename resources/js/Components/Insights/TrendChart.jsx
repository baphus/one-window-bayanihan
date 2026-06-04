import { useMemo } from 'react';
import { Line } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  CategoryScale, LinearScale,
  PointElement, LineElement,
  Title, Tooltip, Legend, Filler,
} from 'chart.js';
import ExportButton from '@/Components/Insights/ExportButton';

ChartJS.register(
  CategoryScale, LinearScale,
  PointElement, LineElement,
  Title, Tooltip, Legend, Filler,
);

const baseOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: true,
      position: 'bottom',
      labels: { boxWidth: 12, padding: 12, font: { size: 10 } },
    },
    tooltip: {
      mode: 'index',
      intersect: false,
      callbacks: {
        label(context) {
          let label = context.dataset.label || '';
          let val = context.parsed.y;
          if (label) label += ': ';
          if (Number.isInteger(val)) label += val;
          else label += val.toFixed(1);
          return label;
        },
      },
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: { font: { size: 10 } },
      grid: { color: '#f1f5f9' },
    },
    x: {
      ticks: { font: { size: 10 } },
      grid: { display: false },
    },
  },
};

export default function TrendChart({
  type = 'line',
  data,
  options = {},
  height = 220,
  loading = false,
  emptyMessage = 'No trend data available.',
  error = null,
  onRetry,
  title = 'Trend',
}) {
  const chartData = useMemo(() => {
    if (!data || !data.labels || !data.datasets) return null;

    if (type === 'area') {
      return {
        labels: data.labels,
        datasets: data.datasets.map((ds) => {
          const borderColor = ds.borderColor || '#0b5a8c';
          const bgColor =
            ds.backgroundColor ||
            borderColor.replace('rgb(', 'rgba(').replace(')', ', 0.15)');
          return { ...ds, fill: true, backgroundColor: bgColor };
        }),
      };
    }

    return data;
  }, [data, type]);

  const mergedOptions = useMemo(
    () => ({ ...baseOptions, ...options }),
    [options],
  );

  const isEmpty = !data || !data.labels || data.labels.length === 0;

  // Convert chart.js data to array-of-objects for export
  const exportableData = useMemo(() => {
    const source = chartData || data;
    if (!source || !source.labels || source.labels.length === 0) return null;

    const headers = [
      'Label',
      ...source.datasets.map((ds) => ds.label || 'Value'),
    ];

    return source.labels.map((label, i) => {
      const row = { Label: label };
      source.datasets.forEach((ds) => {
        row[ds.label || 'Value'] = ds.data[i] ?? '';
      });
      return row;
    });
  }, [chartData, data]);

  // Loading state
  if (loading) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            {title}
          </h3>
        </div>
        <div
          className="animate-pulse rounded bg-gray-200"
          style={{ height: `${height}px` }}
        />
      </article>
    );
  }

  // Error state
  if (error) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            {title}
          </h3>
        </div>
        <div className="flex flex-col items-center gap-2 py-8 text-center">
          <p className="text-[13px] text-red-500">{error}</p>
          {onRetry && (
            <button
              type="button"
              onClick={onRetry}
              className="rounded border border-[#cbd5e1] bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
            >
              Retry
            </button>
          )}
        </div>
      </article>
    );
  }

  // Empty state
  if (isEmpty) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            {title}
          </h3>
        </div>
        <p className="py-8 text-center text-[13px] text-slate-400">
          {emptyMessage}
        </p>
      </article>
    );
  }

  return (
    <article className="border border-[#cbd5e1] bg-white p-4">
      <div className="mb-3 flex items-center justify-between">
        <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
          {title}
        </h3>
        <ExportButton
          data={exportableData}
          filename={title}
          type="csv"
          chartType={type}
        />
      </div>
      <div style={{ height: `${height}px` }}>
        <Line data={chartData} options={mergedOptions} />
      </div>
    </article>
  );
}
