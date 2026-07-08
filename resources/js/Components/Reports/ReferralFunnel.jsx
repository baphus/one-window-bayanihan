import { Bar } from 'react-chartjs-2';
import { statusColor } from './pageHeadingStyles';
import { formatStatusLabel } from '@/lib/utils';

// Ordered referral pipeline funnel (Pending -> Processing -> For Compliance ->
// Completed; Rejected is a de-emphasized terminal). Honors the status toggle
// state via `hidden` (array of status slugs to omit).
const FUNNEL_ORDER = ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'];

export default function ReferralFunnel({ distribution, hidden = [], height = 220 }) {
  if (!distribution?.labels?.length) {
    return (
      <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">
        No referral data available.
      </p>
    );
  }

  const map = {};
  distribution.labels.forEach((label, i) => {
    map[label] = distribution.data?.[i] || 0;
  });

  const order = FUNNEL_ORDER.filter((s) => s in map && !hidden.includes(s));
  const labels = order.map(formatStatusLabel);
  const values = order.map((s) => map[s]);
  const colors = order.map((s) => statusColor(s));

  const data = {
    labels,
    datasets: [
      {
        data: values,
        backgroundColor: colors,
        borderRadius: 4,
        borderWidth: 0,
        barThickness: 22,
      },
    ],
  };

  const options = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { enabled: true },
    },
    scales: {
      x: {
        beginAtZero: true,
        ticks: { precision: 0, font: { size: 10 } },
        grid: { color: 'rgba(148,163,184,0.15)' },
      },
      y: { ticks: { font: { size: 11 } }, grid: { display: false } },
    },
  };

  return (
    <div style={{ height }}>
      <Bar data={data} options={options} />
    </div>
  );
}
