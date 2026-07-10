import { useMemo } from 'react';
import MetricCard from '@/Components/Reports/MetricCard';
import { useLazyProp } from '@/Hooks/useLazyProp';

export default function TopServiceRequestedCard({ role }) {
  const [data, isLoading] = useLazyProp('mostRequestedService');

  const { value, count } = useMemo(() => {
    const name = data?.name;
    const validName = name && name !== 'N/A' ? name : null;
    const rawCount = Number(data?.value ?? 0);

    return {
      value: validName || '—',
      count: rawCount > 0 ? rawCount : 0,
    };
  }, [data]);

  if (role && role !== 'CASE_MANAGER') return null;

  return (
    <MetricCard
      label="Top Service Requested"
      value={isLoading ? '—' : value}
      accent="border-l-[#d9663b]"
      description={isLoading ? 'Loading top service…' : null}
      trailing={!isLoading && count > 0 ? <span className="text-[11px] font-bold text-slate-500 dark:text-slate-400">{count} requests this period</span> : null}
    />
  );
}
