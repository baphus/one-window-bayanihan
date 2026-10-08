import { useEffect, useMemo } from 'react';
import { GeoJSON, MapContainer, Marker, Tooltip, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { REGION_VII_GEOJSON } from '@/data/regionVIIBoundaries';
import {
  REGION_VII_BOUNDS,
  RENDERED_PROVINCE_IDS,
  choroplethFill,
} from './RegionVIIMap';

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

// Leaflet-bound internals, loaded on demand via React.lazy in RegionVIIMap.
export default function RegionVIIMapLeaflet({ provinces = [], selectedProvince, onProvinceClick, renderedMetas = [] }) {
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
  );
}
