import { useRef, useEffect, useMemo, memo } from 'react';
import { Line } from 'react-chartjs-2';
import { Chart as ChartJS, Filler, LineElement, PointElement, LinearScale, CategoryScale, Tooltip } from 'chart.js';
import ExportButton from '@/Components/Insights/ExportButton';

ChartJS.register(Filler, LineElement, PointElement, LinearScale, CategoryScale, Tooltip);

const colorMap = {
  blue: {
    border: '#0b5a8c',
    line: '#0b5a8c',
    fillTop: 'rgba(11,90,140,0.18)',
    fillBottom: 'rgba(11,90,140,0.02)',
    bgLight: 'bg-[#eef4f8]',
  },
  green: {
    border: '#10b981',
    line: '#10b981',
    fillTop: 'rgba(16,185,129,0.18)',
    fillBottom: 'rgba(16,185,129,0.02)',
    bgLight: 'bg-[#ecfdf5]',
  },
  red: {
    border: '#ef4444',
    line: '#ef4444',
    fillTop: 'rgba(239,68,68,0.18)',
    fillBottom: 'rgba(239,68,68,0.02)',
    bgLight: 'bg-[#fef2f2]',
  },
  amber: {
    border: '#f59e0b',
    line: '#f59e0b',
    fillTop: 'rgba(245,158,11,0.18)',
    fillBottom: 'rgba(245,158,11,0.02)',
    bgLight: 'bg-[#fffbeb]',
  },
  purple: {
    border: '#8b5cf6',
    line: '#8b5cf6',
    fillTop: 'rgba(139,92,246,0.18)',
    fillBottom: 'rgba(139,92,246,0.02)',
    bgLight: 'bg-[#f5f3ff]',
  },
  teal: {
    border: '#14b8a6',
    line: '#14b8a6',
    fillTop: 'rgba(20,184,166,0.18)',
    fillBottom: 'rgba(20,184,166,0.02)',
    bgLight: 'bg-[#f0fdfa]',
  },
};

function formatValue(value, format) {
  if (value === null || value === undefined) return '—';
  switch (format) {
    case 'percentage':
      return `${value}%`;
    case 'days':
      return `${value}d`;
    case 'rating':
      return Number(value).toFixed(1);
    case 'number':
    default:
      return typeof value === 'number' ? value.toLocaleString() : String(value);
  }
}

function getSparklineOptions(colors) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 600 },
    plugins: {
      legend: { display: false },
      tooltip: { enabled: false },
    },
    scales: {
      x: { display: false, grid: { display: false }, ticks: { display: false } },
      y: { display: false, grid: { display: false }, ticks: { display: false } },
    },
    elements: {
      point: { radius: 0, hoverRadius: 0 },
      line: { borderWidth: 2, tension: 0.35, fill: true },
    },
  };
}

function buildSparklineData(sparklineData, colors) {
  if (!sparklineData || sparklineData.length === 0) return null;

  return {
    labels: sparklineData.map((_, i) => i),
    datasets: [
      {
        data: sparklineData,
        borderColor: colors.line,
        backgroundColor: (ctx) => {
          if (!ctx.chart.chartArea) return colors.fillTop;
          const { top, bottom } = ctx.chart.chartArea;
          const gradient = ctx.chart.ctx.createLinearGradient(0, top, 0, bottom);
          gradient.addColorStop(0, colors.fillTop);
          gradient.addColorStop(1, colors.fillBottom);
          return gradient;
        },
        fill: true,
        pointRadius: 0,
        borderWidth: 2,
        tension: 0.35,
      },
    ],
  };
}

function Skeleton() {
  return (
    <article className="border border-[#d5dbe3] border-l-[3px] border-l-slate-200 bg-[#f8fafc] px-4 py-3">
      <div className="animate-pulse">
        <div className="flex items-center gap-2">
          <div className="h-2.5 w-5 rounded bg-slate-200" />
          <div className="h-2.5 w-28 rounded bg-slate-200" />
        </div>
        <div className="mt-3 flex items-end justify-between">
          <div className="space-y-2">
            <div className="h-8 w-36 rounded bg-slate-200" />
            <div className="h-2.5 w-16 rounded bg-slate-200" />
          </div>
          <div className="h-[40px] w-24 rounded bg-slate-100" />
        </div>
      </div>
    </article>
  );
}

function MetricCard({
  title,
  value,
  change = null,
  changeDirection = null,
  sparklineData = null,
  icon = null,
  color = 'blue',
  targetValue = null,
  format = 'number',
  loading = false,
  onClick = null,
  lastUpdated = null,
}) {
  const chartRef = useRef(null);
  const colors = colorMap[color] || colorMap.blue;

  const formatted = formatValue(value, format);
  const hasValue = value !== null && value !== undefined;

  const hasSparklineData = sparklineData && sparklineData.length > 0;
  const chartData = hasSparklineData ? buildSparklineData(sparklineData, colors) : null;
  const chartOptions = hasSparklineData ? getSparklineOptions(colors) : null;

  const showTrend = change !== null && change !== undefined;

  const exportableData = useMemo(() => {
    if (!hasValue) return null;
    const row = { Metric: title, Value: formatted };
    if (showTrend) {
      row.Change = `${changeDirection === 'up' ? '+' : changeDirection === 'down' ? '-' : ''}${change ?? ''}%`;
    }
    if (lastUpdated) {
      try {
        row['Last Updated'] = new Date(lastUpdated).toLocaleDateString();
      } catch {
        row['Last Updated'] = String(lastUpdated);
      }
    }
    return [row];
  }, [title, formatted, hasValue, showTrend, change, changeDirection, lastUpdated]);

  useEffect(() => {
    const chart = chartRef.current;
    return () => {
      if (chart && typeof chart.destroy === 'function') {
        chart.destroy();
      }
    };
  }, [sparklineData]);

  if (loading) {
    return <Skeleton />;
  }

  if (!hasValue) {
    return (
      <article
        className={`border border-[#d5dbe3] border-l-[3px] bg-[#f8fafc] px-4 py-3 ${onClick ? 'cursor-pointer' : ''}`}
        style={{ borderLeftColor: colors.border }}
        onClick={onClick}
        role={onClick ? 'button' : undefined}
        tabIndex={onClick ? 0 : undefined}
        onKeyDown={onClick ? (e) => { if (e.key === 'Enter' || e.key === ' ') onClick(e); } : undefined}
      >
        <div className="flex items-center gap-2">
          <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">
            {icon && <span className="mr-1.5 inline-flex items-center justify-center h-5 w-5 rounded bg-slate-100">{icon}</span>}
            {title}
          </p>
        </div>
        <div className="mt-1 flex items-end justify-between">
          <div>
            <p className="text-[33px] font-black leading-none text-slate-300">—</p>
            <p className="mt-0.5 text-[10px] font-semibold text-slate-400">No data</p>
          </div>
        </div>
      </article>
    );
  }

  let trendArrow = null;
  let trendColor = 'text-slate-400';
  if (showTrend) {
    if (changeDirection === 'up') {
      trendArrow = '▲';
      trendColor = 'text-emerald-600';
    } else if (changeDirection === 'down') {
      trendArrow = '▼';
      trendColor = 'text-rose-600';
    } else {
      trendArrow = '—';
      trendColor = 'text-slate-400';
    }
  }

  const showSparkline = chartData !== null;

  const showTarget = targetValue !== null && targetValue !== undefined && hasValue;

  const tooltipLines = [];
  tooltipLines.push(`${title}: ${formatted}`);
  if (showTrend) {
    const dirLabel = changeDirection === 'up' ? '↑' : changeDirection === 'down' ? '↓' : '—';
    tooltipLines.push(`Change: ${dirLabel} ${Math.abs(change)}%`);
  }
  if (lastUpdated) {
    try {
      const d = new Date(lastUpdated);
      tooltipLines.push(`Updated: ${d.toLocaleDateString()}`);
    } catch {
      // lastUpdated was not a valid date string
    }
  }

  return (
    <article
      className={`group relative border border-[#d5dbe3] border-l-[3px] bg-[#f8fafc] px-4 py-3 transition-shadow hover:shadow-sm ${onClick ? 'cursor-pointer' : ''}`}
      style={{ borderLeftColor: colors.border }}
      onClick={onClick}
      role={onClick ? 'button' : undefined}
      tabIndex={onClick ? 0 : undefined}
      onKeyDown={onClick ? (e) => { if (e.key === 'Enter' || e.key === ' ') onClick(e); } : undefined}
    >
      {tooltipLines.length > 0 && (
        <div className="pointer-events-none absolute -top-1 left-1/2 z-50 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md border border-[#d5dbe3] bg-white px-3 py-1.5 text-[11px] font-medium text-slate-700 opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100">
          {tooltipLines.map((line, i) => (
            <div key={i}>{line}</div>
          ))}
          <div className="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-white" />
        </div>
      )}

      <div className="flex items-center gap-2">
        <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
          {icon && (
            <span className="mr-1.5 inline-flex items-center justify-center h-5 w-5 rounded bg-slate-100/80">
              {icon}
            </span>
          )}
          {title}
        </p>
        {showTrend && (
          <span className={`inline-flex items-center gap-0.5 text-[11px] font-bold ${trendColor}`}>
            <span className="text-[10px]">{trendArrow}</span>
            {Math.abs(change)}%
            <span className="text-[9px] font-semibold text-slate-400">vs prev</span>
          </span>
        )}
        <span className="ml-auto">
          <ExportButton
            data={exportableData}
            filename={title}
            type="csv"
          />
        </span>
      </div>

      <div className="mt-1 flex items-end justify-between gap-4">
        <div className="shrink-0">
          <p className="text-[33px] font-black leading-none text-[#0f172a]">{formatted}</p>
          {showTarget && (
            <p className="mt-0.5 text-[10px] font-semibold text-slate-400">
              Target: {formatValue(targetValue, format)}
            </p>
          )}
        </div>

        {showSparkline && (
          <div className="h-[40px] flex-1 max-w-[120px]">
            <Line
              ref={chartRef}
              data={chartData}
              options={chartOptions}
            />
          </div>
        )}
      </div>
    </article>
  );
}

export default memo(MetricCard);
