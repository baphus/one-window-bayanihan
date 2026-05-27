import { TrendingUp, TrendingDown } from 'lucide-react';

export default function TrendIndicator({ change, inverse = false }) {
  if (change === 0 || change === undefined || change === null) return null;

  const isPositive = inverse ? change < 0 : change > 0;
  const Icon = isPositive ? TrendingUp : TrendingDown;
  const color = isPositive ? 'text-emerald-600' : 'text-rose-600';

  return (
    <span className={`inline-flex items-center gap-0.5 text-[11px] font-bold ${color}`}>
      <Icon className="h-3 w-3" />
      {Math.abs(change)}%
    </span>
  );
}
