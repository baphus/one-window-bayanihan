import MetricCard from '@/Components/Reports/MetricCard';
import { useLazyProp } from '@/Hooks/useLazyProp';

export default function AvgCompletionCard({ role }) {
  const [value, isLoading] = useLazyProp('avgReferralCompletion');

  if (role && role !== 'AGENCY') return null;

  const numericValue = Number(value);
  const formatted = Number.isFinite(numericValue)
    ? `${numericValue.toFixed(Number.isInteger(numericValue) ? 0 : 1)}d`
    : '—';

  return (
    <MetricCard
      label="Avg Completion Time"
      value={isLoading ? '—' : formatted}
      accent="border-l-[#3f915f]"
      description={isLoading ? 'Loading average…' : 'Average referral completion days'}
    />
  );
}
