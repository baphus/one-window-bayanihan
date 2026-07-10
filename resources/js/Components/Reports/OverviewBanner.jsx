import { useMemo } from 'react';
import { pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';
import { useLazyProp } from '@/Hooks/useLazyProp';

function OverviewBannerSkeleton() {
  return (
    <article className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
      <div className="mb-4 h-3 w-28 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
      <div className="grid grid-cols-3 gap-4 sm:grid-cols-6">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i}>
            <div className="h-6 w-12 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
            <div className="mt-1.5 h-3 w-16 animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
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
    <article className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
      <div className="mb-4 flex items-center justify-between">
        <h3 className={pageHeadingStyles.sectionTitle}>Case Overview</h3>
        <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Summary</span>
      </div>
      <div className="grid grid-cols-3 gap-4 sm:grid-cols-6">
        {stats.map((item) => (
          <div key={item.label}>
            <p className="text-lg font-black text-slate-900 sm:text-xl lg:text-2xl">{item.value}</p>
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{item.label}</p>
          </div>
        ))}
      </div>
    </article>
  );
}
