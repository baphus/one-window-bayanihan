import { useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Bar } from 'react-chartjs-2';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import SectionAccordion from '@/Components/Reports/SectionAccordion';
import PhilippinesMap from '@/Components/Reports/PhilippinesMap';
import { COLORS, cardShell } from '@/Components/Reports/pageHeadingStyles';
import { useLazyProp } from '@/Hooks/useLazyProp';

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: 'rgba(148,163,184,0.15)' } },
    y: { ticks: { font: { size: 10 } }, grid: { display: false } },
  },
};

const REGION_VII = {
  cebu: { id: 'cebu', name: 'Cebu' },
  bohol: { id: 'bohol', name: 'Bohol' },
};

function provinceMapId(nameOrId) {
  const text = String(nameOrId ?? '').toLowerCase();
  if (text.includes('cebu')) return 'cebu';
  if (text.includes('bohol')) return 'bohol';
  return text.replace(/\s+/g, '-');
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

function toBarData(provinces) {
  if (!provinces?.length) return null;

  return {
    labels: provinces.map((province) => province.name),
    datasets: [{
      label: 'Cases',
      data: provinces.map((province) => province.count),
      backgroundColor: provinces.map((_, index) => COLORS.chartPalette[index % COLORS.chartPalette.length]),
      borderRadius: 3,
      barThickness: 18,
    }],
  };
}

function GeographicPanel({ geoData, province, onProvinceClick, provinceOptions = [] }) {
  const [view, setView] = useState('map');
  const page = usePage();
  const mapData = page.props.geographicMapData;

  const allProvinces = useMemo(() => toProvinceList(mapData || geoData, provinceOptions, false), [geoData, mapData, provinceOptions]);
  const mapProvinces = useMemo(() => allProvinces.filter((province) => REGION_VII[province.id]), [allProvinces]);
  const barData = useMemo(() => toBarData(allProvinces), [allProvinces]);

  if (!allProvinces.length) {
    return <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">No geographic data available.</p>;
  }

  return (
    <article className={`${cardShell} p-4`}>
      <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div className="inline-flex overflow-hidden rounded-[2px] border border-slate-200 bg-slate-50 text-[10px] font-extrabold uppercase tracking-[0.14em] dark:border-slate-700 dark:bg-slate-800">
          <button
            type="button"
            onClick={() => setView('map')}
            className={`px-3 py-1.5 ${view === 'map' ? 'bg-white text-[#0b5a8c] dark:bg-slate-900 dark:text-[#7fb3e0]' : 'text-slate-500 dark:text-slate-400'}`}
          >
            Map
          </button>
          <button
            type="button"
            onClick={() => setView('bar')}
            className={`px-3 py-1.5 ${view === 'bar' ? 'bg-white text-[#0b5a8c] dark:bg-slate-900 dark:text-[#7fb3e0]' : 'text-slate-500 dark:text-slate-400'}`}
          >
            Bar chart
          </button>
        </div>
      </div>

      {view === 'map' ? (
        mapProvinces.length > 0 ? (
          <PhilippinesMap
            provinces={mapProvinces}
            selectedProvince={province}
            onProvinceClick={onProvinceClick}
          />
        ) : (
          <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">No geographic data available.</p>
        )
      ) : (
        <div className="h-64">
          {barData ? <Bar data={barData} options={barOptions} /> : <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">No geographic data available.</p>}
        </div>
      )}
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
        <div className="rounded-[3px] border border-[#e2e8f0] bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
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
