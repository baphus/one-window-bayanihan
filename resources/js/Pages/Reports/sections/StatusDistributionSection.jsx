import { useState, useMemo } from 'react';
import { Doughnut } from 'react-chartjs-2';
import { Loader2, Clock, CheckCircle2, XCircle } from 'lucide-react';
import { COLORS } from '@/Components/Reports/pageHeadingStyles';

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  cutout: '55%',
};

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

function StatusIcon({ status }) {
  const icons = {
    PENDING: <Loader2 className="h-3 w-3 text-amber-500" />,
    PROCESSING: <Clock className="h-3 w-3 text-blue-500" />,
    COMPLETED: <CheckCircle2 className="h-3 w-3 text-emerald-500" />,
    REJECTED: <XCircle className="h-3 w-3 text-rose-500" />,
    FOR_COMPLIANCE: <Clock className="h-3 w-3 text-orange-500" />,
  };
  return icons[status] || null;
}

export default function StatusDistributionSection({ referralStatusDistribution }) {
  const referralStatusPie = useMemo(() => toPieFormat(referralStatusDistribution), [referralStatusDistribution]);

  if (!referralStatusPie?.length) return null;

  return (
    <div className="flex items-center gap-6">
      <div className="h-28 w-28 shrink-0">
        <Doughnut data={{
          labels: referralStatusPie.map((s) => s.label),
          datasets: [{ data: referralStatusPie.map((s) => s.count), backgroundColor: referralStatusPie.map((s) => s.hex), borderWidth: 0 }],
        }} options={doughnutOptions} />
      </div>
      <div className="flex-1 space-y-1.5">
        {referralStatusPie.map((s) => (
          <div key={s.label} className="flex items-center justify-between text-[11px]">
            <span className="inline-flex items-center gap-1.5 text-slate-600">
              <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: s.hex }} />
              <span className="font-medium">{s.label}</span>
            </span>
            <span className="font-bold text-slate-700">{s.count} ({s.percent}%)</span>
          </div>
        ))}
      </div>
    </div>
  );
}
