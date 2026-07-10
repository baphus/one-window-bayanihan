import { Link } from '@inertiajs/react';
import MetricCard from '@/Components/Reports/MetricCard';
import { useLazyProp } from '@/Hooks/useLazyProp';

function getHref(name) {
  try {
    if (typeof route !== 'function') return null;
    return route(name);
  } catch {
    return null;
  }
}

export default function OverdueReferralsCard({ role }) {
  const [data, isLoading] = useLazyProp('overdueReferrals');

  if (role && role !== 'ADMIN') return null;

  const count = Number(data?.count ?? 0);
  const href = getHref('overdue-referrals.index');

  const card = (
    <MetricCard
      label="Overdue Referrals"
      value={isLoading ? '—' : count}
      accent="border-l-[#c0392b]"
      valueTone="text-rose-700 dark:text-rose-400"
      description={isLoading ? 'Loading overdue count…' : 'Referrals past the follow-up threshold'}
      trailing={!isLoading && count > 0 ? <span className="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">Action</span> : null}
    />
  );

  if (!href) {
    return (
      <button type="button" className="block w-full text-left focus:outline-none" title="Overdue referrals route unavailable">
        {card}
      </button>
    );
  }

  return <Link href={href} className="block focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0b5a8c]/40">{card}</Link>;
}
