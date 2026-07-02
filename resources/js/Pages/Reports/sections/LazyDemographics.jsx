import { useMemo } from 'react';
import { useLazyProp } from '@/Hooks/useLazyProp';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import SvgPieChart from '@/Components/Reports/SvgPieChart';
import { COLORS, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';

function toPieFormat(distribution) {
  if (!distribution || !distribution.labels) return [];
  const total = distribution.data.reduce((s, v) => s + v, 0) || 1;
  const colors = distribution.colors || COLORS.chartPalette;
  return distribution.labels.map((label, i) => ({
    label,
    count: distribution.data[i] || 0,
    hex: colors[i % colors.length],
    color: '',
    percent: Math.round(((distribution.data[i] || 0) / total) * 100),
  }));
}

function PieColumn({ title, data, fallback = 'No data available.' }) {
  return (
    <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
      <h3 className="mb-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider">{title}</h3>
      {data && data.length > 0 ? (
        <div className="flex items-center gap-4">
          <div className="h-16 w-16 shrink-0">
            <SvgPieChart data={data} className="w-16 h-16" />
          </div>
          <div className="space-y-1">
            {data.map((item) => (
              <div key={item.label} className="flex items-center gap-2 text-[11px]">
                <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: item.hex }} />
                <span className="text-slate-600">{item.label}</span>
                <span className="font-bold text-slate-800">{item.percent}%</span>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <p className="py-8 text-center text-[13px] text-slate-400">{fallback}</p>
      )}
    </article>
  );
}

export default function LazyDemographics() {
  const [genderData, genderLoading] = useLazyProp('genderDistribution');
  const [clientTypeData, clientTypeLoading] = useLazyProp('clientTypeDistribution');
  const [ageData, ageLoading] = useLazyProp('ageGroupDistribution');

  if (genderLoading || clientTypeLoading || ageLoading) {
    return <ChartSkeleton />;
  }

  const genderPie = useMemo(() => toPieFormat(genderData), [genderData]);
  const clientTypePie = useMemo(() => toPieFormat(clientTypeData), [clientTypeData]);
  const agePie = useMemo(() => toPieFormat(ageData), [ageData]);

  return (
    <section>
      <h2 className={`mb-3 ${pageHeadingStyles.sectionTitle || 'text-[11px] font-bold uppercase tracking-wider text-slate-500'}`}>
        Client Demographics
      </h2>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <PieColumn title="Gender" data={genderPie} />
        <PieColumn title="Client Type" data={clientTypePie} />
        <PieColumn title="Age Group" data={agePie} />
      </div>
    </section>
  );
}
