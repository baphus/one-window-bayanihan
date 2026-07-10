import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Clock3, ArrowRight } from 'lucide-react';
import { useLazyProp } from '@/Hooks/useLazyProp';
import { cardShell, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';

function getHref(name) {
  try {
    if (typeof route !== 'function') return null;
    return route(name);
  } catch {
    return null;
  }
}

function AlertCard({ tone, icon, title, description, href }) {
  const toneClasses = tone === 'rose'
    ? 'border-rose-200 bg-rose-50/80 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-100'
    : 'border-amber-200 bg-amber-50/80 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100';

  const content = (
    <article className={`flex h-full items-start gap-3 rounded-[3px] border px-4 py-3 ${toneClasses}`}>
      <span className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/70 dark:bg-black/20">
        {icon}
      </span>
      <div className="min-w-0 flex-1">
        <h4 className="text-[11px] font-extrabold uppercase tracking-[0.14em]">{title}</h4>
        <p className="mt-1 text-[13px] leading-5 text-slate-700 dark:text-slate-200">{description}</p>
      </div>
      <ArrowRight className="mt-1 h-4 w-4 shrink-0 opacity-60" />
    </article>
  );

  if (href) {
    return <Link href={href} className="block focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5a8c]/40">{content}</Link>;
  }

  return (
    <button type="button" className="block w-full text-left focus:outline-none">
      {content}
    </button>
  );
}

export default function AttentionSection() {
  const [statusData, statusLoading] = useLazyProp('referralStatusDistribution');
  const [overdueData, overdueLoading] = useLazyProp('overdueReferrals');

  const alerts = useMemo(() => {
    if (statusLoading || overdueLoading) return [];

    const pendingIndex = Array.isArray(statusData?.labels)
      ? statusData.labels.findIndex((label) => String(label).toUpperCase().includes('PENDING'))
      : -1;
    const pendingCount = pendingIndex >= 0
      ? Number(statusData?.data?.[pendingIndex] ?? 0)
      : Number(statusData?.data?.[0] ?? 0);
    const overdueCount = Number(overdueData?.count ?? 0);

    const items = [];

    if (overdueCount > 0) {
      items.push({
        tone: 'rose',
        icon: <AlertTriangle className="h-4 w-4 text-rose-600" />,
        title: 'Overdue referrals',
        description: `${overdueCount} referral${overdueCount === 1 ? '' : 's'} need follow-up now.`,
        href: getHref('overdue-referrals.index'),
      });
    }

    if (pendingCount > 10) {
      items.push({
        tone: 'amber',
        icon: <Clock3 className="h-4 w-4 text-amber-600" />,
        title: 'Pending workload',
        description: `${pendingCount} referrals pending — review caseload.`,
        href: getHref('referrals.index'),
      });
    }

    return items;
  }, [overdueData, overdueLoading, statusData, statusLoading]);

  if (statusLoading || overdueLoading || alerts.length === 0) return null;

  return (
    <section className={`${cardShell} p-4`}>
      <div className="mb-3 flex items-center justify-between gap-3">
        <h3 className={pageHeadingStyles.sectionTitle}>Attention Required</h3>
        <span className="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">Live signals</span>
      </div>
      <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
        {alerts.map((alert) => (
          <AlertCard key={alert.title} {...alert} />
        ))}
      </div>
    </section>
  );
}
