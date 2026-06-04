import { useMemo, useState, useCallback, useRef } from 'react';

/**
 * Approximate SVG (viewBox) coordinates for Philippine provinces.
 * Positions are rough centroids used to place data bubbles on the map.
 */
const PROVINCE_POSITIONS = {
  // -- Luzon --
  'NCR': { x: 210, y: 210 },
  'Bulacan': { x: 200, y: 198 },
  'Pampanga': { x: 192, y: 188 },
  'Tarlac': { x: 182, y: 180 },
  'Nueva Ecija': { x: 195, y: 182 },
  'Pangasinan': { x: 170, y: 170 },
  'La Union': { x: 165, y: 160 },
  'Ilocos Sur': { x: 155, y: 148 },
  'Ilocos Norte': { x: 148, y: 130 },
  'Cagayan': { x: 185, y: 125 },
  'Isabela': { x: 195, y: 150 },
  'Zambales': { x: 162, y: 200 },
  'Bataan': { x: 175, y: 212 },
  'Cavite': { x: 205, y: 222 },
  'Laguna': { x: 215, y: 218 },
  'Batangas': { x: 210, y: 232 },
  'Rizal': { x: 220, y: 205 },
  'Quezon': { x: 240, y: 222 },
  'Camarines Norte': { x: 258, y: 235 },
  'Camarines Sur': { x: 265, y: 250 },
  'Albay': { x: 270, y: 262 },
  'Masbate': { x: 255, y: 285 },
  'Sorsogon': { x: 268, y: 272 },
  'Catanduanes': { x: 280, y: 258 },

  // -- Luzon outlying --
  'Palawan': { x: 98, y: 310 },
  'Occidental Mindoro': { x: 165, y: 248 },
  'Oriental Mindoro': { x: 178, y: 252 },
  'Marinduque': { x: 210, y: 255 },
  'Romblon': { x: 200, y: 268 },
  'Aurora': { x: 240, y: 190 },

  // -- Visayas --
  'Aklan': { x: 165, y: 293 },
  'Capiz': { x: 172, y: 298 },
  'Iloilo': { x: 158, y: 310 },
  'Antique': { x: 148, y: 303 },
  'Guimaras': { x: 162, y: 318 },
  'Negros Occidental': { x: 175, y: 315 },
  'Negros Oriental': { x: 185, y: 328 },
  'Cebu': { x: 202, y: 320 },
  'Bohol': { x: 220, y: 332 },
  'Siquijor': { x: 220, y: 345 },
  'Leyte': { x: 240, y: 318 },
  'Southern Leyte': { x: 245, y: 335 },
  'Biliran': { x: 230, y: 305 },
  'Samar': { x: 258, y: 312 },
  'Northern Samar': { x: 250, y: 298 },
  'Eastern Samar': { x: 272, y: 318 },

  // -- Mindanao --
  'Camiguin': { x: 222, y: 358 },
  'Misamis Occidental': { x: 200, y: 372 },
  'Misamis Oriental': { x: 230, y: 368 },
  'Lanao del Norte': { x: 212, y: 382 },
  'Lanao del Sur': { x: 218, y: 400 },
  'Bukidnon': { x: 240, y: 380 },
  'Cotabato': { x: 205, y: 410 },
  'South Cotabato': { x: 200, y: 432 },
  'Sultan Kudarat': { x: 188, y: 420 },
  'Maguindanao': { x: 198, y: 412 },
  'Zamboanga del Norte': { x: 168, y: 380 },
  'Zamboanga del Sur': { x: 160, y: 400 },
  'Zamboanga Sibugay': { x: 150, y: 390 },
  'Davao del Norte': { x: 245, y: 400 },
  'Davao del Sur': { x: 255, y: 422 },
  'Davao de Oro': { x: 255, y: 395 },
  'Davao Occidental': { x: 260, y: 435 },
  'Davao Oriental': { x: 270, y: 412 },
  'Agusan del Norte': { x: 245, y: 375 },
  'Agusan del Sur': { x: 250, y: 392 },
  'Surigao del Norte': { x: 260, y: 370 },
  'Surigao del Sur': { x: 272, y: 385 },
  'Dinagat Islands': { x: 265, y: 362 },
  'Basilan': { x: 165, y: 450 },
  'Sulu': { x: 160, y: 470 },
  'Tawi-Tawi': { x: 155, y: 490 },
};

/**
 * Simplified SVG path strings for the four major island groups.
 * These are stylised polygons, not geographically precise boundaries.
 */
const ISLAND_PATHS = [
  {
    id: 'luzon',
    d: 'M 160,100 L 185,90 L 225,92 L 260,112 L 285,155 L 295,200 L 290,245 L 275,272 L 255,292 L 230,300 L 200,295 L 175,280 L 155,260 L 135,230 L 125,195 L 125,160 L 135,125 L 148,108 Z',
  },
  {
    id: 'mindanao',
    d: 'M 190,368 L 225,358 L 260,368 L 285,392 L 290,428 L 275,458 L 245,468 L 215,462 L 185,442 L 170,412 Z',
  },
  {
    id: 'visayas',
    d: 'M 145,292 L 180,280 L 225,285 L 270,298 L 290,315 L 285,340 L 260,352 L 215,354 L 180,348 L 150,332 L 138,312 Z',
  },
  {
    id: 'palawan',
    d: 'M 90,272 L 105,267 L 115,274 L 118,302 L 115,332 L 108,358 L 95,365 L 85,358 L 80,328 L 78,298 L 82,280 Z',
  },
];

/** Interpolate between light-blue (#bfdbfe) and dark-blue (#1e3a5f). */
function interpolateColor(t) {
  const clamped = Math.max(0, Math.min(1, t));
  const r = Math.round(191 + (30 - 191) * clamped);
  const g = Math.round(219 + (58 - 219) * clamped);
  const b = Math.round(254 + (95 - 254) * clamped);
  return `rgb(${r},${g},${b})`;
}

/** Scale a normalised value [0,1] to a bubble radius between 4 and 30 px. */
function scaleRadius(t) {
  return 4 + Math.max(0, Math.min(1, t)) * 26;
}

export default function GeoMap({
  data = null,
  loading = false,
  onProvinceClick,
  emptyMessage = 'No geographic data available for the selected filters.',
}) {
  const containerRef = useRef(null);
  const [tooltip, setTooltip] = useState(null);

  // Build lookup: province -> total
  const provinceMap = useMemo(() => {
    if (!Array.isArray(data)) return new Map();
    const map = new Map();
    for (const entry of data) {
      if (entry && entry.province && typeof entry.total === 'number') {
        map.set(entry.province, entry.total);
      }
    }
    return map;
  }, [data]);

  // Min / max for scaling
  const stats = useMemo(() => {
    const values = Array.from(provinceMap.values()).filter((v) => v > 0);
    if (values.length === 0) return { min: 0, max: 0 };
    return { min: Math.min(...values), max: Math.max(...values) };
  }, [provinceMap]);

  const hasData = provinceMap.size > 0 && stats.max > 0;
  const range = stats.max - stats.min;

  // Bubbles with computed colour & radius
  const visibleBubbles = useMemo(() => {
    const result = [];
    for (const [name, count] of provinceMap.entries()) {
      const pos = PROVINCE_POSITIONS[name];
      if (!pos || count <= 0) continue;
      const t = range > 0 ? (count - stats.min) / range : 0.5;
      result.push({
        name,
        count,
        x: pos.x,
        y: pos.y,
        color: interpolateColor(t),
        r: Math.max(3, scaleRadius(t)),
      });
    }
    return result;
  }, [provinceMap, range, stats.min]);

  // Mark provinces present in the position map but missing from data (dim dots)
  const absentProvinces = useMemo(() => {
    if (!hasData) return [];
    return Object.entries(PROVINCE_POSITIONS)
      .filter(([name]) => !provinceMap.has(name))
      .map(([name, pos]) => ({ name, x: pos.x, y: pos.y }));
  }, [hasData, provinceMap]);

  // ── Event handlers ────────────────────────────────────────────────

  const handleBubbleEnter = useCallback((e, province, count) => {
    if (!containerRef.current) return;
    const rect = containerRef.current.getBoundingClientRect();
    setTooltip({
      province,
      count,
      x: e.clientX - rect.left,
      y: e.clientY - rect.top,
    });
  }, []);

  const handleBubbleMove = useCallback((e) => {
    setTooltip((prev) => {
      if (!prev || !containerRef.current) return prev;
      const rect = containerRef.current.getBoundingClientRect();
      return { ...prev, x: e.clientX - rect.left, y: e.clientY - rect.top };
    });
  }, []);

  const handleBubbleLeave = useCallback(() => {
    setTooltip(null);
  }, []);

  const handleBubbleClick = useCallback(
    (name) => {
      if (onProvinceClick) onProvinceClick(name);
    },
    [onProvinceClick],
  );

  // ── Loading skeleton ──────────────────────────────────────────────

  if (loading) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            Geographic Distribution
          </h3>
        </div>
        <div
          className="animate-pulse rounded bg-gray-200"
          style={{ height: '320px' }}
        />
      </article>
    );
  }

  // ── Empty state ───────────────────────────────────────────────────

  if (!hasData) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            Geographic Distribution
          </h3>
        </div>
        <p className="py-8 text-center text-[13px] text-slate-400">
          {emptyMessage}
        </p>
      </article>
    );
  }

  // ── Render map ────────────────────────────────────────────────────

  return (
    <article className="border border-[#cbd5e1] bg-white p-4">
      <div className="mb-3 flex items-center justify-between">
        <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
          Geographic Distribution
        </h3>
        <span className="text-[10px] font-medium text-slate-400">
          {visibleBubbles.length} province{visibleBubbles.length !== 1 ? 's' : ''}
        </span>
      </div>

      <div
        ref={containerRef}
        className="relative w-full overflow-hidden rounded"
        onMouseMove={handleBubbleMove}
      >
        <svg
          viewBox="0 0 500 600"
          className="w-full"
          preserveAspectRatio="xMidYMid meet"
          style={{ maxHeight: '320px' }}
          aria-label="Philippine geographic distribution map"
        >
          {/* Ocean background */}
          <rect x="0" y="0" width="500" height="600" fill="#f0f9ff" rx="4" />

          {/* Subtle latitude/longitude grid */}
          <g stroke="#dbeafe" strokeWidth="0.5" opacity="0.55">
            <line x1="0" y1="150" x2="500" y2="150" />
            <line x1="0" y1="300" x2="500" y2="300" />
            <line x1="0" y1="450" x2="500" y2="450" />
            <line x1="125" y1="0" x2="125" y2="600" />
            <line x1="250" y1="0" x2="250" y2="600" />
            <line x1="375" y1="0" x2="375" y2="600" />
          </g>

          {/* Island shapes */}
          <g fill="#e0f2fe" stroke="#7dd3fc" strokeWidth="1.5" opacity="0.8">
            {ISLAND_PATHS.map((island) => (
              <path key={island.id} d={island.d} />
            ))}
          </g>

          {/* Faint dots for provinces without data */}
          {absentProvinces.length > 0 && (
            <g fill="#cbd5e1" opacity="0.35">
              {absentProvinces.map((p) => (
                <circle key={`absent-${p.name}`} cx={p.x} cy={p.y} r={2.5} />
              ))}
            </g>
          )}

          {/* Data bubbles */}
          <g>
            {visibleBubbles.map((p) => (
              <circle
                key={p.name}
                cx={p.x}
                cy={p.y}
                r={p.r}
                fill={p.color}
                opacity="0.88"
                stroke="#ffffff"
                strokeWidth="2"
                className="cursor-pointer transition-[stroke-width,opacity] duration-150 hover:stroke-[3] hover:opacity-100"
                style={{ filter: 'drop-shadow(0 1px 3px rgba(0,0,0,0.18))' }}
                onMouseEnter={(e) => handleBubbleEnter(e, p.name, p.count)}
                onMouseLeave={handleBubbleLeave}
                onClick={() => handleBubbleClick(p.name)}
                role="button"
                tabIndex={0}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleBubbleClick(p.name);
                  }
                }}
              />
            ))}
          </g>
        </svg>

        {/* Tooltip */}
        {tooltip && (
          <div
            className="pointer-events-none absolute z-10 rounded-md bg-slate-800 px-3 py-1.5 text-xs text-white shadow-lg"
            style={{
              left: `${Math.min(tooltip.x, 460)}px`,
              top: `${Math.max(tooltip.y - 10, 0)}px`,
              transform: 'translate(-50%, -100%)',
            }}
          >
            <p className="whitespace-nowrap font-semibold">
              {tooltip.province}
            </p>
            <p className="whitespace-nowrap text-slate-300">
              {tooltip.count.toLocaleString()} case{tooltip.count !== 1 ? 's' : ''}
            </p>
          </div>
        )}
      </div>
    </article>
  );
}
