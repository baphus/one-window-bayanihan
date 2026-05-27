import { useMemo, useState } from 'react';
import { pageHeadingStyles } from './pageHeadingStyles';

const DAY_MS = 1000 * 60 * 60 * 24;

function toCalendarDate(isoDate) {
  const [year, month, day] = isoDate.split('-').map(Number);
  return new Date(year, month - 1, day);
}

function addDays(date, days) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
}

function differenceInDays(start, end) {
  return Math.floor((end.getTime() - start.getTime()) / DAY_MS);
}

function formatMonthLabel(date, withYear) {
  const month = date.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
  return withYear ? `${month} ${date.getFullYear()}` : month;
}

function formatDayLabel(date) {
  const month = date.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${month} ${day}`;
}

function getTrendGranularity(totalDays) {
  if (totalDays <= 31) return 'day';
  if (totalDays <= 180) return 'week';
  if (totalDays <= 1095) return 'month';
  return 'year';
}

function getLinePointX(index, points, width = 530, inset = 20) {
  if (points <= 1) return width / 2;
  return inset + index * ((width - inset * 2) / (points - 1));
}

export function buildTrendData(from, to, rows, dateField = 'createdOn') {
  const totalDays = Math.max(1, differenceInDays(from, to) + 1);
  const granularity = getTrendGranularity(totalDays);
  const labels = [];
  const series = [];

  if (granularity === 'day') {
    for (let day = 0; day < totalDays; day += 1) {
      labels.push(formatDayLabel(addDays(from, day)));
      series.push(0);
    }
    rows.forEach((row) => {
      const idx = differenceInDays(from, toCalendarDate(row[dateField]));
      if (idx >= 0 && idx < series.length) series[idx] += 1;
    });
  }

  if (granularity === 'week') {
    const weekCount = Math.ceil(totalDays / 7);
    for (let week = 0; week < weekCount; week += 1) {
      const start = addDays(from, week * 7);
      const end = addDays(start, Math.min(6, differenceInDays(start, to)));
      labels.push(`${formatDayLabel(start)}-${formatDayLabel(end)}`);
      series.push(0);
    }
    rows.forEach((row) => {
      const idx = Math.floor(differenceInDays(from, toCalendarDate(row[dateField])) / 7);
      if (idx >= 0 && idx < series.length) series[idx] += 1;
    });
  }

  if (granularity === 'month') {
    const monthStart = new Date(from.getFullYear(), from.getMonth(), 1);
    const monthEnd = new Date(to.getFullYear(), to.getMonth(), 1);
    const includeYear = monthStart.getFullYear() !== monthEnd.getFullYear();
    for (let cursor = new Date(monthStart.getFullYear(), monthStart.getMonth(), 1);
      cursor <= monthEnd;
      cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1)) {
      labels.push(formatMonthLabel(cursor, includeYear));
      series.push(0);
    }
    rows.forEach((row) => {
      const created = toCalendarDate(row[dateField]);
      const idx = (created.getFullYear() - monthStart.getFullYear()) * 12 + (created.getMonth() - monthStart.getMonth());
      if (idx >= 0 && idx < series.length) series[idx] += 1;
    });
  }

  if (granularity === 'year') {
    const startYear = from.getFullYear();
    const endYear = to.getFullYear();
    for (let year = startYear; year <= endYear; year += 1) {
      labels.push(`${year}`);
      series.push(0);
    }
    rows.forEach((row) => {
      const idx = toCalendarDate(row[dateField]).getFullYear() - startYear;
      if (idx >= 0 && idx < series.length) series[idx] += 1;
    });
  }

  return { granularity, labels, series };
}

export default function TrendChart({
  title,
  fromDate,
  toDate,
  rows,
  dateField = 'createdOn',
  precomputedData,
}) {
  const [chartView, setChartView] = useState('line');
  const [hoveredIndex, setHoveredIndex] = useState(null);

  const activeFrom = useMemo(() => fromDate ? toCalendarDate(fromDate) : new Date(), [fromDate]);
  const activeTo = useMemo(() => toDate ? toCalendarDate(toDate) : new Date(), [toDate]);

  const trendData = useMemo(() => {
    if (precomputedData) {
      return {
        granularity: 'month',
        labels: precomputedData.labels || [],
        series: precomputedData.data || [],
      };
    }
    return buildTrendData(activeFrom, activeTo, rows || [], dateField);
  }, [activeFrom, activeTo, rows, dateField, precomputedData]);

  const linePath = useMemo(() => {
    if (trendData.series.length === 0) return '';
    const min = Math.min(...trendData.series);
    const max = Math.max(...trendData.series);
    const range = Math.max(1, max - min);
    return trendData.series
      .map((value, index) => {
        const x = getLinePointX(index, trendData.series.length);
        const y = 20 + (1 - (value - min) / range) * (220 - 40);
        return `${index === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`;
      })
      .join(' ');
  }, [trendData.series]);

  const geometry = useMemo(() => {
    const minValue = Math.min(...trendData.series, 0);
    const maxValue = Math.max(...trendData.series, 1);
    const range = Math.max(1, maxValue - minValue);
    const points = trendData.series.map((value, idx) => {
      const x = getLinePointX(idx, trendData.series.length);
      const y = 20 + (1 - (value - minValue) / range) * (220 - 40);
      return { x, y, value, label: trendData.labels[idx] };
    });
    const slotWidth = points.length > 1 ? points[1].x - points[0].x : 44;
    const barWidth = Math.max(4, Math.min(22, slotWidth - 2, slotWidth * 0.72));
    const baselineY = 20 + (1 - (0 - minValue) / range) * (220 - 40);
    return { points, minValue, maxValue, range, slotWidth, barWidth, baselineY };
  }, [trendData]);

  const areaPath = useMemo(() => {
    if (geometry.points.length === 0 || !linePath) return '';
    const first = geometry.points[0];
    const last = geometry.points[geometry.points.length - 1];
    return `${linePath} L ${last.x.toFixed(1)} ${geometry.baselineY.toFixed(1)} L ${first.x.toFixed(1)} ${geometry.baselineY.toFixed(1)} Z`;
  }, [geometry, linePath]);

  const labelStep = useMemo(() => {
    if (trendData.labels.length <= 8) return 1;
    return Math.ceil(trendData.labels.length / 6);
  }, [trendData.labels.length]);

  const trendTitle =
    trendData.granularity === 'day' ? 'Daily Trend'
      : trendData.granularity === 'week' ? 'Weekly Trend'
        : trendData.granularity === 'month' ? 'Monthly Trend'
          : 'Yearly Trend';

  return (
    <article className="border border-[#cbd5e1] bg-white p-4">
      <div className="mb-3 flex items-center justify-between">
        <h3 className={pageHeadingStyles.sectionTitle}>{title || 'Cases Over Time'}</h3>
        <div className="flex items-center gap-2">
          <span className="text-[11px] font-semibold text-[#0b5a8c]">{trendTitle}</span>
          <div className="inline-flex overflow-hidden rounded-[2px] border border-[#cbd5e1] bg-white">
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
      </div>
      <div className="relative">
        {hoveredIndex !== null && geometry.points[hoveredIndex] && (
          <div className="pointer-events-none absolute right-2 top-2 z-10 rounded-[3px] border border-[#dbe7f3] bg-white/95 px-2.5 py-1.5 shadow-sm">
            <p className="text-[11px] font-medium text-slate-600">{geometry.points[hoveredIndex].label}</p>
            <p className="text-[14px] font-bold text-[#0b5a8c]">{geometry.points[hoveredIndex].value} case(s)</p>
          </div>
        )}

        <svg
          width="100%"
          viewBox="0 0 530 220"
          preserveAspectRatio="none"
          className="h-[220px]"
          onMouseLeave={() => setHoveredIndex(null)}
        >
          <defs>
            <linearGradient id="trend-area-gradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#0b5a8c" stopOpacity="0.22" />
              <stop offset="100%" stopColor="#0b5a8c" stopOpacity="0.02" />
            </linearGradient>
          </defs>

          {[0, 1, 2, 3, 4].map((idx) => (
            <line key={`grid-${idx}`} x1="20" x2="510" y1={30 + idx * 40} y2={30 + idx * 40} stroke="#e2e8f0" strokeWidth="1" />
          ))}

          <g fill="#64748b" fontSize="9" fontWeight="600">
            {[0, 1, 2, 3, 4].map((idx) => {
              const y = 30 + idx * 40;
              const value = (geometry.maxValue - (geometry.range / 4) * idx).toFixed(0);
              return <text key={`y-${idx}`} x="4" y={y + 2}>{value}</text>;
            })}
          </g>

          {chartView === 'line' && areaPath && <path d={areaPath} fill="url(#trend-area-gradient)" />}

          {chartView === 'line' ? (
            <>
              <path d={linePath} fill="none" stroke="#0b5a8c" strokeWidth="1.8" strokeLinecap="round" />
              {geometry.points.map((point, idx) => {
                const isHovered = hoveredIndex === idx;
                return (
                  <circle
                    key={`pt-${idx}`}
                    cx={point.x}
                    cy={point.y}
                    r={isHovered ? 3.6 : 2.2}
                    fill={isHovered ? '#075985' : '#0b5a8c'}
                  />
                );
              })}
            </>
          ) : (
            geometry.points.map((point, idx) => {
              const isHovered = hoveredIndex === idx;
              const barHeight = Math.max(0, geometry.baselineY - point.y);
              if (barHeight <= 0) return null;
              return (
                <rect
                  key={`bar-${idx}`}
                  x={point.x - geometry.barWidth / 2}
                  y={point.y}
                  width={geometry.barWidth}
                  height={barHeight}
                  rx="3"
                  fill={isHovered ? '#075985' : '#0b5a8c'}
                  opacity={isHovered ? 0.95 : 0.82}
                />
              );
            })
          )}

          {geometry.points.map((point, idx) => (
            <rect
              key={`hit-${idx}`}
              x={point.x - geometry.slotWidth / 2}
              y="18"
              width={geometry.slotWidth}
              height="186"
              fill="transparent"
              onMouseEnter={() => setHoveredIndex(idx)}
            />
          ))}

          <g fill="#64748b" fontSize="9" fontWeight="500">
            {trendData.labels.map((label, index) => {
              const shouldRender = index % labelStep === 0 || index === trendData.labels.length - 1;
              if (!shouldRender) return null;
              return <text key={`lbl-${index}`} x={getLinePointX(index, trendData.labels.length) - 12} y="214">{label}</text>;
            })}
          </g>
        </svg>
      </div>
    </article>
  );
}
