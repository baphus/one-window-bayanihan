import { useMemo } from 'react';
import { REGION_VII_GEOJSON, REGION_VII_PROVINCES } from '@/data/regionVIIBoundaries';

/**
 * Rendered scope: Cebu + Bohol only. Since the Negros Island Region
 * reorganization these are the two provinces in Region VII — the boundary
 * data file still carries all four provinces, but only these render.
 */
export const RENDERED_PROVINCE_IDS = ['cebu', 'bohol'];

/**
 * Cebu + Bohol footprint with a small margin.
 * [southWest, northEast] in [lat, lng].
 */
export const REGION_VII_BOUNDS = [
  [9.3, 123.2],
  [11.45, 124.7],
];

export const NO_DATA_FILL = '#e2e8f0';

// Light → dark sequential blues. Index 0 is the lightest non-zero step.
export const CHOROPLETH_STEPS = ['#bfdbfe', '#93c5fd', '#3b82f6', '#0b5a8c'];

/**
 * Match a payload province name (PSGC-resolved, e.g. "CEBU") to a
 * Region VII key. Returns the key or null when outside the region.
 */
export function matchRegionVII(nameOrId) {
  const text = String(nameOrId ?? '').toLowerCase();
  if (text.includes('cebu')) return 'cebu';
  if (text.includes('bohol')) return 'bohol';
  if (text.includes('negros oriental')) return 'negros-oriental';
  if (text.includes('siquijor')) return 'siquijor';
  return null;
}

/**
 * Fill color for a province count relative to the highest count.
 * Zero (or an empty dataset) renders light gray, never a data blue.
 */
export function choroplethFill(count, max) {
  const total = Number(count ?? 0);
  const peak = Number(max ?? 0);
  if (!(peak > 0) || !(total > 0)) return NO_DATA_FILL;
  const ratio = Math.min(1, total / peak);
  const index = Math.min(CHOROPLETH_STEPS.length - 1, Math.floor(ratio * CHOROPLETH_STEPS.length));
  return CHOROPLETH_STEPS[index];
}

// Static SVG projection: 1 degree = 100 units, north up.
const [[SOUTH, WEST], [NORTH, EAST]] = REGION_VII_BOUNDS;
const VIEW_W = Math.round((EAST - WEST) * 100);
const VIEW_H = Math.round((NORTH - SOUTH) * 100);

function project([lng, lat]) {
  return [(lng - WEST) * 100, (NORTH - lat) * 100];
}

function ringPoints(ring) {
  return ring.map((coord) => project(coord).join(',')).join(' ');
}

function featurePolygons(feature) {
  const { type, coordinates } = feature.geometry ?? {};
  if (type === 'Polygon') return coordinates;
  if (type === 'MultiPolygon') return coordinates.flat();
  return [];
}

/**
 * Static SVG choropleth of DMW Region VII (Cebu + Bohol).
 * Boundaries are genuine simplified PSGC municipal polygons grouped by
 * parent province; each municipality is shaded by its province's case
 * count from the payload. No map library, no tiles — works fully offline.
 *
 * provinces: [{ id, name, count, value }] already matched to Region VII.
 * The keyboard-accessible province list in GeographicMapSection carries
 * the same counts and filter actions; the SVG is the visual layer.
 */
export default function RegionVIIMap({ provinces = [], selectedProvince, onProvinceClick }) {
  const renderedMetas = useMemo(
    () => REGION_VII_PROVINCES.filter((meta) => RENDERED_PROVINCE_IDS.includes(meta.id)),
    [],
  );

  const counts = useMemo(() => {
    const table = {};
    provinces.forEach((province) => {
      table[province.id] = Number(province.count ?? 0);
    });
    return table;
  }, [provinces]);

  const max = useMemo(() => Math.max(0, ...Object.values(counts)), [counts]);

  const renderedFeatures = useMemo(
    () => REGION_VII_GEOJSON.features.filter((feature) =>
      RENDERED_PROVINCE_IDS.includes(feature?.properties?.province),
    ),
    [],
  );

  const isSelected = (province) =>
    Boolean(selectedProvince) &&
    [province.value, province.id, province.name].includes(selectedProvince);

  const selectById = (id) => {
    const province = provinces.find((item) => item.id === id);
    onProvinceClick?.(province?.value ?? id);
  };

  return (
    <div>
      <div className="h-[340px] w-full overflow-hidden rounded-[3px] border border-slate-200 dark:border-slate-700">
        <svg
          data-testid="region-vii-map"
          role="img"
          aria-label="Map of Cebu and Bohol shaded by case count"
          viewBox={`0 0 ${VIEW_W} ${VIEW_H}`}
          className="h-full w-full"
          style={{ background: '#eaf2f8' }}
        >
          {renderedMetas.map((meta) => {
            const province = provinces.find((item) => item.id === meta.id);
            const count = counts[meta.id] ?? 0;
            const selected = province ? isSelected(province) : false;
            const [cx, cy] = project([meta.center[1], meta.center[0]]);
            return (
              <g
                key={meta.id}
                data-testid={`province-${meta.id}`}
                onClick={() => selectById(meta.id)}
                style={{ cursor: 'pointer' }}
              >
                <title>{`${province?.name ?? meta.name} — ${count} cases`}</title>
                {renderedFeatures
                  .filter((feature) => feature?.properties?.province === meta.id)
                  .flatMap((feature, featureIndex) =>
                    featurePolygons(feature).map((ring, ringIndex) => (
                      <polygon
                        key={`${featureIndex}-${ringIndex}`}
                        data-feature
                        data-province={meta.id}
                        points={ringPoints(ring)}
                        fill={choroplethFill(count, max)}
                        fillOpacity={count > 0 ? 0.85 : 0.45}
                        stroke={selected ? '#0b5a8c' : '#ffffff'}
                        strokeWidth={selected ? 0.8 : 0.3}
                      />
                    )),
                  )}
                <g data-testid={`badge-${meta.id}`}>
                  <circle cx={cx} cy={cy} r={7} fill={selected ? '#0b5a8c' : '#ffffff'} stroke="#0b5a8c" strokeWidth={1} />
                  <text
                    x={cx}
                    y={cy}
                    textAnchor="middle"
                    dominantBaseline="central"
                    fontSize={6}
                    fontWeight={900}
                    fontFamily="Arial, sans-serif"
                    fill={selected ? '#ffffff' : '#0b5a8c'}
                  >
                    {count}
                  </text>
                </g>
              </g>
            );
          })}
        </svg>
      </div>
      <div
        className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-base font-bold text-slate-700 dark:text-slate-200 md:text-lg"
        aria-label="Map legend: darker blue means more cases"
      >
        <span className="inline-flex items-center gap-2">
          <span className="inline-block h-4 w-8 rounded-sm" style={{ background: NO_DATA_FILL }} />
          No cases
        </span>
        <span className="inline-flex items-center gap-2">
          <span
            className="inline-block h-4 w-24 rounded-sm"
            style={{ background: `linear-gradient(to right, ${CHOROPLETH_STEPS.join(',')})` }}
          />
          Fewer → more cases
        </span>
      </div>
    </div>
  );
}
