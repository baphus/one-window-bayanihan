import { useMemo } from 'react';
import { cardShell, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';
import { useLazyProp } from '@/Hooks/useLazyProp';

function OverviewBannerSkeleton() {
  return (
    <article className={`${cardShell} p-4`}>
      <div className="mb-3 h-3 w-28 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-6">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="rounded-[3px] border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/60">
            <div className="h-3 w-14 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
            <div className="mt-2 h-5 w-10 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
          </div>
        ))}
      </div>
    </article>
  );
}

export default function OverviewBanner() {
  const [overview, isLoading] = useLazyProp('overview');

  const stats = useMemo(() => {
    if (!overview) return [];
    return [
      { label: 'Total cases', value: overview.totalCases ?? 0 },
      { label: 'Open', value: overview.openCases ?? 0 },
      { label: 'Closed', value: overview.closedCases ?? 0 },
      { label: 'Referrals', value: overview.totalReferrals ?? 0 },
      { label: 'Pending', value: overview.pendingReferrals ?? 0 },
      { label: 'Active agencies', value: overview.activeAgencies ?? 0 },
    ];
  }, [overview]);

  if (isLoading) return <OverviewBannerSkeleton />;
  if (!stats.length) return null;

  return (
    <article className={`${cardShell} p-4`}>
      <div className="mb-3 flex items-center justify-between gap-3">
        <h3 className={pageHeadingStyles.sectionTitle}>Case Overview</h3>
        <span className="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">Summary</span>
      </div>
      <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-[12px] leading-5 text-slate-700 dark:text-slate-300">
        {stats.map((item, index) => (
          <span key={item.label} className="inline-flex items-center gap-3">
            <strong className="font-black text-slate-900 dark:text-slate-100">{item.value}</strong>
            <span>{item.label}</span>
            {index < stats.length - 1 ? <span className="text-slate-300 dark:text-slate-600">·</span> : null}
          </span>
        ))}
      </div>
    </article>
  );
}
