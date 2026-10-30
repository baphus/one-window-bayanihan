import { usePage } from '@inertiajs/react';
import { Line } from 'react-chartjs-2';
import { ReportCard, LoadingBlock, EmptyLine } from '@/Pages/Reports/sections/SeniorReport';

export default function CaseVolumeChart() {
  const { casesOverTime: received, closedCasesOverTime: closed } = usePage().props;
  if (received === undefined || closed === undefined) return <LoadingBlock />;
  const labels = [...new Set([...(received?.labels || []), ...(closed?.labels || [])])].sort();
  const datasets = [[received, 'Cases received', '#005288'], [closed, 'Cases closed', '#10b981']].map(([series, label, color]) => ({
    label,
    data: labels.map((month) => {
      const index = series?.labels?.indexOf(month) ?? -1;
      return index < 0 ? 0 : Number(series.datasets?.[0]?.data?.[index] ?? 0);
    }),
    borderColor: color,
    backgroundColor: color,
    borderWidth: 2,
    pointRadius: labels.length > 12 ? 0 : 3,
    tension: 0.2,
  }));
  return <ReportCard title="Case volume over time" takeaway="Cases received by filing month; cases closed by closure month, including cases filed before this period.">
    {labels.length ? <div className="h-64"><Line data={{ labels, datasets }} options={{ responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } }} /></div> : <EmptyLine message="No case activity in this period. Try a longer date range." />}
  </ReportCard>;
}
