import { useMemo } from 'react';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import { cardShell, COLORS, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';
import { useLazyProp } from '@/Hooks/useLazyProp';

function getTotal(distribution) {
  return distribution?.data?.reduce((sum, value) => sum + Number(value || 0), 0) ?? 0;
}

function StatBlock({ label, children }) {
  return (
    <div className="rounded-[3px] border border-slate-200 bg-slate-50 px-3 py-3 dark:border-slate-700 dark:bg-slate-800/50">
      <p className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{label}</p>
      <div className="mt-2 text-[13px] leading-5 text-slate-700 dark:text-slate-200">{children}</div>
    </div>
  );
}

function Pill({ children, color }) {
  return (
    <span className="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold" style={{ borderColor: color, color }}>
      {children}
    </span>
  );
}

export default function ClientSnapshotCard() {
  const [genderData, genderLoading] = useLazyProp('genderDistribution');
  const [clientTypeData, clientTypeLoading] = useLazyProp('clientTypeDistribution');
  const [vulnerabilityData, vulnerabilityLoading] = useLazyProp('vulnerabilityDistribution');

  const loading = genderLoading || clientTypeLoading || vulnerabilityLoading;

  const { totalClients, genderStats, clientTypeStats, vulnerabilityItems } = useMemo(() => {
    const genderTotal = getTotal(genderData);
    const labels = genderData?.labels ?? [];
    const values = genderData?.data ?? [];
    const genderMap = Object.fromEntries(labels.map((label, i) => [String(label).toLowerCase(), Number(values[i] ?? 0)]));

    const clientTypeLabels = clientTypeData?.labels ?? [];
    const clientTypeValues = clientTypeData?.data ?? [];
    const clientTypeMap = Object.fromEntries(clientTypeLabels.map((label, i) => [String(label).toLowerCase(), Number(clientTypeValues[i] ?? 0)]));

    const vulnerabilityLabels = vulnerabilityData?.labels ?? [];
    const vulnerabilityValues = vulnerabilityData?.data ?? [];
    const vulnerabilityList = vulnerabilityLabels
      .map((label, i) => ({ label: String(label), count: Number(vulnerabilityValues[i] ?? 0) }))
      .filter((item) => item.count > 0 && item.label.toLowerCase() !== 'none');

    return {
      totalClients: genderTotal,
      genderStats: {
        male: genderMap.male ?? genderMap.m ?? 0,
        female: genderMap.female ?? genderMap.f ?? 0,
      },
      clientTypeStats: {
        ofw: clientTypeMap.ofw ?? 0,
        nok: clientTypeMap.nok ?? clientTypeMap['next of kin'] ?? clientTypeMap.next_of_kin ?? 0,
      },
      vulnerabilityItems: vulnerabilityList,
    };
  }, [clientTypeData, genderData, vulnerabilityData]);

  if (loading) return <ChartSkeleton />;

  if (!totalClients && !vulnerabilityItems.length && !clientTypeData?.labels?.length) {
    return null;
  }

  return (
    <article className={`${cardShell} p-4`}>
      <div className="mb-3 flex items-center justify-between gap-3">
        <h3 className={pageHeadingStyles.sectionTitle}>Client Demographics</h3>
        <span className="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">Compact summary</span>
      </div>

      <div className="grid grid-cols-1 gap-3 xl:grid-cols-3">
        <StatBlock label="Total clients">
          <div className="text-[28px] font-black leading-none text-slate-900 dark:text-slate-100">{totalClients}</div>
          <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">All clients in the current filter.</p>
        </StatBlock>

        <StatBlock label="Client type · sex split">
          <p className="font-semibold text-slate-700 dark:text-slate-200">
            {clientTypeStats.ofw} OFW · {clientTypeStats.nok} NOK
          </p>
          <p className="mt-1 font-semibold text-slate-700 dark:text-slate-200">
            {genderStats.male} Male · {genderStats.female} Female
          </p>
        </StatBlock>

        <StatBlock label="Vulnerability indicators">
          <div className="flex flex-wrap gap-2">
            {vulnerabilityItems.length > 0 ? vulnerabilityItems.map((item) => (
              <Pill key={item.label} color={COLORS.primary}>{item.label} {item.count}</Pill>
            )) : <span className="text-[13px] text-slate-400 dark:text-slate-500">None flagged.</span>}
          </div>
        </StatBlock>
      </div>
    </article>
  );
}
