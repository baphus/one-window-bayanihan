import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import SectionAccordion from '@/Components/Reports/SectionAccordion';
import RegionVIIMap, { matchRegionVII, RENDERED_PROVINCE_IDS } from '@/Components/Reports/RegionVIIMap';
import { cardShell } from '@/Components/Reports/pageHeadingStyles';
import { useLazyProp } from '@/Hooks/useLazyProp';

// Region VII matching lives in RegionVIIMap; this alias keeps the
// province-list builders below unchanged.
function provinceMapId(nameOrId) {
  return matchRegionVII(nameOrId) ?? String(nameOrId ?? '').toLowerCase().replace(/\s+/g, '-');
}

function normalizeProvinceValue(name, provinceOptions = []) {
  const match = provinceOptions.find((option) => {
    const value = String(option.value ?? '').toLowerCase();
    const label = String(option.label ?? '').toLowerCase();
    const target = String(name).toLowerCase();
    return value === target || label === target;
  });

  return match?.value ?? name;
}

function toProvinceList(source, provinceOptions = [], filterRegionVII = false) {
  if (Array.isArray(source?.provinces) && source.provinces.length > 0) {
    const provinces = source.provinces.map((province) => ({
      id: provinceMapId(province.name ?? province.id),
      name: province.name,
      count: Number(province.cases ?? province.count ?? 0),
      value: province.value ?? normalizeProvinceValue(province.name, provinceOptions),
      color: province.color,
    }));

    return filterRegionVII ? provinces.filter((province) => REGION_VII[province.id]) : provinces;
  }

  const labels = source?.labels ?? [];
  const data = source?.data ?? [];

  const provinces = labels.map((label, index) => {
    const id = provinceMapId(label);
    return {
      id,
      name: String(label),
      count: Number(data[index] ?? 0),
      value: normalizeProvinceValue(label, provinceOptions),
      color: undefined,
    };
  });

  return filterRegionVII ? provinces.filter((province) => REGION_VII[province.id]) : provinces;
}

function GeographicPanel({ geoData, province, onProvinceClick, provinceOptions = [] }) {
  const page = usePage();
  const mapData = page.props.geographicMapData;

  const allProvinces = useMemo(() => toProvinceList(mapData || geoData, provinceOptions, false), [geoData, mapData, provinceOptions]);
  // Rendered scope is Cebu + Bohol only (see RENDERED_PROVINCE_IDS).
  const mapProvinces = useMemo(
    () => allProvinces.filter((province) => RENDERED_PROVINCE_IDS.includes(province.id)),
    [allProvinces],
  );

  if (!mapProvinces.length) {
    return <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">No geographic data available.</p>;
  }

  return (
    <article className={`${cardShell} p-4`}>
      <RegionVIIMap
        provinces={mapProvinces}
        selectedProvince={province}
        onProvinceClick={onProvinceClick}
      />
      <ul aria-label="Cases by province" className="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
        {mapProvinces.map((item) => {
          const selected = province && [item.value, item.id, item.name].includes(province);
          return (
            <li key={item.id}>
              <button
                type="button"
                onClick={() => onProvinceClick?.(item.value ?? item.id)}
                aria-pressed={Boolean(selected)}
                aria-label={`${item.name} ${item.count} cases — filter to this province`}
                className={`flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${
                  selected
                    ? 'bg-primary/10 text-primary'
                    : 'text-slate-800 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800'
                }`}
              >
                <span>{item.name}</span>
                <span className="shrink-0 font-black text-slate-900 dark:text-slate-100">
                  {item.count} {item.count === 1 ? 'case' : 'cases'}
                </span>
              </button>
            </li>
          );
        })}
      </ul>
    </article>
  );
}

export default function GeographicMapSection({ province, setProvince, setCity, provinceOptions = [] }) {
  const [geoData, geoLoading] = useLazyProp('geographicDistribution');
  const [mapData, mapLoading] = useLazyProp('geographicMapData');
  const hasGeoData = Boolean(geoData?.labels?.length || mapData?.provinces?.length);

  return (
    <SectionAccordion title="Geographic Distribution" defaultOpen>
      {(geoLoading || mapLoading) && !hasGeoData ? <ChartSkeleton /> : null}
      {!geoLoading && !mapLoading && !hasGeoData ? (
        <div className="rounded-[3px] border border-surface-variant bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">No geographic data available.</p>
        </div>
      ) : null}
      {hasGeoData ? (
        <GeographicPanel
          geoData={mapData?.provinces?.length ? mapData : geoData}
          province={province}
          provinceOptions={provinceOptions}
          onProvinceClick={(value) => {
            setProvince?.(value);
            setCity?.(null);
          }}
        />
      ) : null}
    </SectionAccordion>
  );
}
