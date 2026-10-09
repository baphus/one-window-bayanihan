import KpiCard from '@/Components/ui/KpiCard';
import { useLazyProp } from '@/Hooks/useLazyProp';
import { usePage } from '@inertiajs/react';

export default function AvgCompletionCard({ role }) {
  const [value, isLoading] = useLazyProp('avgReferralCompletion');
  const roles = usePage().props.roles;

  if (role && role !== roles.AGENCY) return null;

  const numericValue = Number(value);
  const formatted = Number.isFinite(numericValue)
    ? `${numericValue.toFixed(Number.isInteger(numericValue) ? 0 : 1)}d`
    : '—';

  return (
    <KpiCard
      title="Avg Completion Time"
      value={isLoading ? '—' : formatted}
      description={isLoading ? 'Loading average…' : 'Average referral completion days'}
    />
  );
}
