import { Suspense, lazy, useMemo } from 'react';
import { REGION_VII_PROVINCES } from '@/data/regionVIIBoundaries';

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

// Leaflet (~150KB with styles) loads only when the map actually mounts,
// keeping it out of the initial bundle.
const RegionVIIMapLeaflet = lazy(() => import('./RegionVIIMapLeaflet'));

// Same box as the loaded map so there is no layout shift while leaflet loads.
function MapLoading() {
  return (
    <div
      role="status"
      aria-label="Loading map"
      className="h-full w-full animate-pulse bg-slate-100 dark:bg-slate-800"
    />
  );
}

/**
 * Real interactive choropleth of DMW Region VII (Cebu + Bohol).
 * Boundaries are genuine simplified PSGC municipal polygons grouped by
 * parent province; each municipality is shaded by its province's case
 * count from the payload. Rendered without raster tiles so the map works
 * fully offline.
 *
 * provinces: [{ id, name, count, value }] already matched to Region VII.
 */
export default function RegionVIIMap({ provinces = [], selectedProvince, onProvinceClick }) {
  const renderedMetas = useMemo(
    () => REGION_VII_PROVINCES.filter((meta) => RENDERED_PROVINCE_IDS.includes(meta.id)),
    [],
  );

  return (
    <div>
      <div className="h-[340px] w-full overflow-hidden rounded-[3px] border border-slate-200 dark:border-slate-700">
        <Suspense fallback={<MapLoading />}>
          <RegionVIIMapLeaflet
            provinces={provinces}
            selectedProvince={selectedProvince}
            onProvinceClick={onProvinceClick}
            renderedMetas={renderedMetas}
          />
        </Suspense>
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
