import { useEffect, useMemo } from 'react';
import { GeoJSON, MapContainer, Marker, Tooltip, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { REGION_VII_PROVINCES, REGION_VII_GEOJSON } from '@/data/regionVIIBoundaries';

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

// Big-number badge pinned at each province center. Inline styles keep
// the icon self-contained (no global CSS needed).
function countIcon(count, selected) {
  return L.divIcon({
    className: 'rvii-count-badge',
    html: `<span style="display:inline-flex;align-items:center;justify-content:center;min-width:44px;height:44px;padding:0 10px;border-radius:9999px;background:${selected ? '#0b5a8c' : '#ffffff'};color:${selected ? '#ffffff' : '#0b5a8c'};border:3px solid #0b5a8c;font-size:20px;font-weight:900;font-family:Arial,sans-serif;box-shadow:0 1px 4px rgba(15,23,42,0.35);">${count}</span>`,
    iconSize: [44, 44],
    iconAnchor: [22, 22],
  });
}

function FitRegionVII() {
  const map = useMap();
  useEffect(() => {
    map.fitBounds(REGION_VII_BOUNDS, { padding: [12, 12] });
    map.setMaxBounds(REGION_VII_BOUNDS.map(([lat, lng]) => [lat, lng]));
  }, [map]);
  return null;
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
  const counts = useMemo(() => {
    const table = {};
    provinces.forEach((province) => {
      table[province.id] = Number(province.count ?? 0);
    });
    return table;
  }, [provinces]);

  const max = useMemo(() => Math.max(0, ...Object.values(counts)), [counts]);

  const renderedGeoJSON = useMemo(
    () => ({
      ...REGION_VII_GEOJSON,
      features: REGION_VII_GEOJSON.features.filter((feature) =>
        RENDERED_PROVINCE_IDS.includes(feature?.properties?.province),
      ),
    }),
    [],
  );

  const renderedMetas = useMemo(
    () => REGION_VII_PROVINCES.filter((meta) => RENDERED_PROVINCE_IDS.includes(meta.id)),
    [],
  );

  const isSelected = (province) =>
    Boolean(selectedProvince) &&
    [province.value, province.id, province.name].includes(selectedProvince);

  const selectById = (id) => {
    const province = provinces.find((item) => item.id === id);
    onProvinceClick?.(province?.value ?? id);
  };

  const styleFor = (feature) => {
    const id = feature?.properties?.province;
    const province = provinces.find((item) => item.id === id);
    const selected = province ? isSelected(province) : false;
    return {
      fillColor: choroplethFill(counts[id] ?? 0, max),
      fillOpacity: (counts[id] ?? 0) > 0 ? 0.85 : 0.45,
      color: selected ? '#0b5a8c' : '#ffffff',
      weight: selected ? 3 : 1,
    };
  };

  const bindFeature = (feature, layer) => {
    const id = feature?.properties?.province;
    const province = provinces.find((item) => item.id === id);
    const name = province?.name ?? feature?.properties?.name ?? id;
    const count = counts[id] ?? 0;
    layer.bindTooltip(`${name} — ${count} cases`, { sticky: true, direction: 'top' });
    layer.on('click', () => selectById(id));
  };

  return (
    <div>
      <div className="h-[340px] w-full overflow-hidden rounded-[3px] border border-slate-200 dark:border-slate-700">
        <MapContainer
          bounds={REGION_VII_BOUNDS}
          scrollWheelZoom={false}
          attributionControl={false}
          className="h-full w-full"
          style={{ background: '#eaf2f8' }}
        >
          <FitRegionVII />
          <GeoJSON
            key={provinces.map((province) => `${province.id}:${province.count}`).join('|')}
            data={renderedGeoJSON}
            style={styleFor}
            onEachFeature={bindFeature}
          />
          {renderedMetas.map((meta) => {
            const province = provinces.find((item) => item.id === meta.id);
            if (!province) return null;
            return (
              <Marker
                key={meta.id}
                position={meta.center}
                icon={countIcon(province.count ?? 0, isSelected(province))}
                keyboard
                title={`${province.name} ${province.count ?? 0} cases — activate to filter`}
                eventHandlers={{ click: () => selectById(meta.id) }}
              >
                <Tooltip direction="top" offset={[0, -24]} opacity={1}>
                  {province.name} — {province.count ?? 0} cases
                </Tooltip>
              </Marker>
            );
          })}
        </MapContainer>
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
