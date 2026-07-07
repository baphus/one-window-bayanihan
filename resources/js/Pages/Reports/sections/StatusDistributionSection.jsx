import { Doughnut } from 'react-chartjs-2';
import ReportLazySection from '@/Components/Reports/ReportLazySection';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import { COLORS, cardShell, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';
import { formatStatusLabel } from '@/lib/utils';

function toPieFormat(distribution) {
  if (!distribution || !distribution.labels) return [];
  const total = distribution.data.reduce((s, v) => s + v, 0) || 1;
  const colors = distribution.colors || COLORS.chartPalette;
  return distribution.labels.map((label, i) => ({
    label: formatStatusLabel(label),
    count: distribution.data[i] || 0,
    hex: colors[i % colors.length],
    color: '',
    percent: Math.round(((distribution.data[i] || 0) / total) * 100),
  }));
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  cutout: '55%',
};

export default function StatusDistributionSection({ pageHeadingStyles: _styles }) {
  return (
    <ReportLazySection lazyKey="referralStatusDistribution" skeleton={<ChartSkeleton />} emptyMessage="No referral status data available.">
      {(data) => {
        const pieData = toPieFormat(data);
        return (
          <article className={`${cardShell} p-4`}>
            <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Referral Status</h3>
            <div className="flex items-center gap-6">
              <div className="h-28 w-28 shrink-0">
                <Doughnut
                  data={{
                    labels: pieData.map((s) => s.label),
                    datasets: [{
                      data: pieData.map((s) => s.count),
                      backgroundColor: pieData.map((s) => s.hex),
                      borderWidth: 0,
                    }],
                  }}
                  options={doughnutOptions}
                />
              </div>
              <div className="flex-1 space-y-1.5">
                {pieData.map((s) => (
                  <div key={s.label} className="flex items-center justify-between text-[11px]">
                    <span className="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                      <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: s.hex }} />
                      <span className="font-medium">{s.label}</span>
                    </span>
                    <span className="font-bold text-slate-700 dark:text-slate-200">{s.count} ({s.percent}%)</span>
                  </div>
                ))}
              </div>
            </div>
          </article>
        );
      }}
    </ReportLazySection>
  );
}
