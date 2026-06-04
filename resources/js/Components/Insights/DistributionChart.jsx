import { useMemo, useCallback } from 'react';
import { Doughnut, Pie, Bar } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, BarElement,
  PointElement, LineElement, ArcElement,
  Title, Tooltip, Legend, Filler,
} from 'chart.js';
import ExportButton from '@/Components/Insights/ExportButton';

ChartJS.register(
  CategoryScale, LinearScale, BarElement,
  PointElement, LineElement, ArcElement,
  Title, Tooltip, Legend, Filler,
);

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  cutout: '55%',
};

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { mode: 'index', intersect: false },
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

const barValueLabelPlugin = {
  id: 'barValueLabel',
  afterDraw(chart) {
    const { ctx, data } = chart;
    const isHorizontal = chart.options.indexAxis === 'y';

    ctx.save();
    ctx.font = '9px sans-serif';
    ctx.fillStyle = '#64748b';

    data.datasets.forEach((dataset) => {
      const meta = chart.getDatasetMeta(
        chart.data.datasets.indexOf(dataset),
      );
      meta.data.forEach((element, index) => {
        const value = dataset.data[index];
        if (value === null || value === undefined) return;

        if (isHorizontal) {
          ctx.textAlign = 'left';
          ctx.textBaseline = 'middle';
          ctx.fillText(String(value), element.x + 4, element.y);
        } else {
          ctx.textAlign = 'center';
          ctx.textBaseline = 'bottom';
          ctx.fillText(String(value), element.x, element.y - 4);
        }
      });
    });

    ctx.restore();
  },
};

export default function DistributionChart({
  type = 'doughnut',
  data,
  options = {},
  height = 220,
  loading = false,
  emptyMessage = 'No data available.',
  error = null,
  onRetry,
  title = 'Distribution',
  onSegmentClick,
}) {
  const isCircular = type === 'doughnut' || type === 'pie';
  const isHorizontalBar = type === 'horizontalBar';

  const isEmpty = !data || !data.labels || data.labels.length === 0;

  // Total count for doughnut center text
  const total = useMemo(() => {
    if (!data?.datasets?.[0]?.data) return 0;
    return data.datasets[0].data.reduce((s, v) => s + (v || 0), 0);
  }, [data]);

  // Prepare chart data: ensure borderWidth=0 for circular charts
  const chartData = useMemo(() => {
    if (!data || !data.labels || !data.datasets) return null;

    if (isCircular) {
      return {
        labels: data.labels,
        datasets: data.datasets.map((ds) => ({
          ...ds,
          borderWidth: ds.borderWidth ?? 0,
        })),
      };
    }

    return data;
  }, [data, isCircular]);

  // Merge options
  const mergedOptions = useMemo(() => {
    let base = {};

    if (isCircular) {
      base = { ...doughnutOptions };
    } else {
      base = { ...barOptions };
    }

    if (isHorizontalBar) {
      base.indexAxis = 'y';
    }

    return { ...base, ...options };
  }, [isCircular, isHorizontalBar, options]);

  // Segment click handler (via options.onClick — avoids forwarded-prop warning)
  const handleChartClick = useCallback(
    (_event, elements) => {
      if (elements.length > 0 && onSegmentClick) {
        const index = elements[0].index;
        onSegmentClick(data?.labels?.[index] || '', index);
      }
    },
    [onSegmentClick, data],
  );

  // Convert chart.js data to array-of-objects for export
  const exportableData = useMemo(() => {
    if (!data || !data.labels || data.labels.length === 0) return null;

    return data.labels.map((label, i) => {
      const row = { Label: label };
      data.datasets.forEach((ds) => {
        row[ds.label || 'Value'] = ds.data[i] ?? '';
      });
      return row;
    });
  }, [data]);

  // --- Loading state ---
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

  // --- Error state ---
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

  // --- Empty state ---
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

  // --- Render chart ---
  const renderChart = () => {
    const chartOptions = onSegmentClick
      ? { ...mergedOptions, onClick: handleChartClick }
      : mergedOptions;
    const commonProps = {
      data: chartData,
      options: chartOptions,
    };

    if (isCircular) {
      return (
        <div className="relative">
          <div style={{ height: `${height}px` }}>
            {type === 'doughnut' ? (
              <Doughnut {...commonProps} />
            ) : (
              <Pie {...commonProps} />
            )}
          </div>
          {/* Center text overlay for doughnut */}
          {type === 'doughnut' && (
            <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div className="text-center">
                <span className="text-2xl font-bold text-slate-700">
                  {total}
                </span>
                <p className="text-[10px] text-slate-400">Total</p>
              </div>
            </div>
          )}
        </div>
      );
    }

    // Bar / HorizontalBar
    return (
      <div style={{ height: `${height}px` }}>
        <Bar {...commonProps} plugins={[barValueLabelPlugin]} />
      </div>
    );
  };

  // --- Custom legend for circular charts ---
  const renderLegend = () => {
    if (!isCircular || !chartData?.labels?.length) return null;

    const dataset = chartData.datasets?.[0];
    const colors = dataset?.backgroundColor || [];

    return (
      <div className="mt-3 space-y-1.5">
        {chartData.labels.map((label, i) => {
          const count = dataset?.data?.[i] || 0;
          const percent = total > 0 ? Math.round((count / total) * 100) : 0;
          const color = Array.isArray(colors) ? colors[i] || '#94a3b8' : '#94a3b8';

          return (
            <div
              key={label}
              className="flex items-center justify-between text-[11px]"
            >
              <span className="inline-flex items-center gap-1.5 text-slate-600">
                <span
                  className="h-2 w-2 shrink-0 rounded-full"
                  style={{ backgroundColor: color }}
                />
                <span className="font-medium">{label}</span>
              </span>
              <span className="font-bold text-slate-700">
                {count} ({percent}%)
              </span>
            </div>
          );
        })}
      </div>
    );
  };

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
      {renderChart()}
      {renderLegend()}
    </article>
  );
}
