import { useMemo } from 'react';
import TrendChart from '@/Components/Insights/TrendChart';
import { Radar } from 'react-chartjs-2';

const radarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    r: {
      beginAtZero: true,
      max: 5,
      ticks: { stepSize: 1, font: { size: 10 } },
      grid: { color: '#e2e8f0' },
      pointLabels: { font: { size: 11, weight: 'bold' }, color: '#475569' },
    },
  },
  plugins: {
    legend: { position: 'top', labels: { font: { size: 11 }, usePointStyle: true } },
    tooltip: {
      callbacks: {
        label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.r}/5`,
      },
    },
  },
};

const SERVQUAL_DIMENSIONS = ['Tangibility', 'Reliability', 'Responsiveness', 'Assurance', 'Empathy'];

function ServqualChart({ data }) {
  const { chartData, gaps } = useMemo(() => {
    const empty = { chartData: null, gaps: [] };
    if (!data || data.length === 0) return empty;

    const hasNamedDimensions = data.some((d) => d.dimension || d.name);

    if (hasNamedDimensions) {
      const labels = data.map((d) => d.dimension || d.name);
      const hasDual = data.some((d) => d.expectation !== undefined || d.expected !== undefined);
      if (hasDual) {
        const expectations = data.map((d) => d.expectation ?? d.expected ?? 0);
        const perceptions = data.map((d) => d.perception ?? d.perceived ?? d.score ?? d.value ?? 0);
        const g = labels.map((label, i) => ({
          dimension: label,
          gap: Number((perceptions[i] - expectations[i]).toFixed(2)),
          expectation: expectations[i],
          perception: perceptions[i],
        }));
        return {
          chartData: {
            labels,
            datasets: [
              {
                label: 'Expectation',
                data: expectations,
                backgroundColor: 'rgba(37, 99, 235, 0.2)',
                borderColor: 'rgba(37, 99, 235, 0.8)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(37, 99, 235, 0.8)',
                pointBorderColor: '#fff',
                pointBorderWidth: 1,
                pointRadius: 4,
              },
              {
                label: 'Perception',
                data: perceptions,
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                borderColor: 'rgba(16, 185, 129, 0.8)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(16, 185, 129, 0.8)',
                pointBorderColor: '#fff',
                pointBorderWidth: 1,
                pointRadius: 4,
              },
            ],
          },
          gaps: g,
        };
      }
      const values = data.map((d) => d.score || d.value || 0);
      return {
        chartData: {
          labels,
          datasets: [{
            label: 'Perception',
            data: values,
            backgroundColor: 'rgba(16, 185, 129, 0.2)',
            borderColor: 'rgba(16, 185, 129, 0.8)',
            borderWidth: 2,
            pointBackgroundColor: 'rgba(16, 185, 129, 0.8)',
            pointBorderColor: '#fff',
            pointBorderWidth: 1,
            pointRadius: 4,
          }],
        },
        gaps: [],
      };
    }

    const values = SERVQUAL_DIMENSIONS.map((dim) => data[dim] || data[dim.toLowerCase()] || 0);
    return {
      chartData: {
        labels: SERVQUAL_DIMENSIONS,
        datasets: [{
          label: 'Perception',
          data: values,
          backgroundColor: 'rgba(16, 185, 129, 0.2)',
          borderColor: 'rgba(16, 185, 129, 0.8)',
          borderWidth: 2,
          pointBackgroundColor: 'rgba(16, 185, 129, 0.8)',
          pointBorderColor: '#fff',
          pointBorderWidth: 1,
          pointRadius: 4,
        }],
      },
      gaps: [],
    };
  }, [data]);

  if (!chartData) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">SERVQUAL Scores</h3>
        <p className="py-8 text-center text-[13px] text-slate-400">No SERVQUAL data available.</p>
      </article>
    );
  }

  return (
    <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">SERVQUAL Scores</h3>
      <p className="mb-3 text-[11px] text-slate-500">Expectation vs Perception — 5-dimension service quality (1-5 scale)</p>
      <div className="h-64">
        <Radar data={chartData} options={radarOptions} />
      </div>
      {gaps.length > 0 && (
        <div className="mt-4 border-t border-[#e2e8f0] pt-3">
          <h4 className="mb-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Gap (Perception − Expectation)</h4>
          <div className="grid grid-cols-5 gap-2">
            {gaps.map((g) => (
              <div key={g.dimension} className="text-center">
                <span className="block text-[10px] font-semibold text-slate-600">{g.dimension}</span>
                <span
                  className={`text-[13px] font-extrabold ${
                    g.gap > 0
                      ? 'text-emerald-600'
                      : g.gap < 0
                        ? 'text-rose-600'
                        : 'text-slate-400'
                  }`}
                >
                  {g.gap > 0 ? '+' : ''}{g.gap.toFixed(2)}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
    </article>
  );
}

function AgencyRanking({ data }) {
  if (!data || data.length === 0) {
    return (
      <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
        <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Agency Satisfaction Ranking</h3>
        <p className="py-8 text-center text-[13px] text-slate-400">No ranking data available.</p>
      </article>
    );
  }

  return (
    <article className="border border-[#cbd5e1] bg-white p-4 shadow-sm">
      <h3 className="mb-4 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Agency Satisfaction Ranking</h3>
      <div className="overflow-x-auto">
        <table className="w-full text-[11px]">
          <thead>
            <tr className="border-b border-[#cbd5e1] text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500">
              <th className="pb-2 pr-3">#</th>
              <th className="pb-2 pr-3">Agency</th>
              <th className="pb-2 pr-3 text-right">Score</th>
              <th className="pb-2 text-right">Trend</th>
            </tr>
          </thead>
          <tbody>
            {data.map((row, i) => {
              const score = row.score || row.average_score || row.avg_score || row.satisfaction || 0;
              const trend = row.trend || row.change || null;
              return (
                <tr key={row.id || row.name || i} className="border-b border-[#e2e8f0] last:border-0">
                  <td className="py-2 pr-3 font-bold text-slate-400">#{i + 1}</td>
                  <td className="py-2 pr-3 font-semibold text-slate-700">{row.agency || row.name || row.agency_name}</td>
                  <td className={`py-2 pr-3 text-right font-bold ${
                    score >= 4 ? 'text-emerald-700' : score >= 3 ? 'text-amber-700' : 'text-rose-700'
                  }`}>{Number(score).toFixed(1)}</td>
                  <td className="py-2 text-right text-[11px]">
                    {trend !== null && trend !== undefined ? (
                      <span className={trend > 0 ? 'text-emerald-600' : trend < 0 ? 'text-rose-600' : 'text-slate-400'}>
                        {trend > 0 ? '▲' : trend < 0 ? '▼' : '—'} {trend !== 0 ? `${Math.abs(trend)}%` : ''}
                      </span>
                    ) : (
                      <span className="text-slate-300">—</span>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </article>
  );
}

export default function Satisfaction({
  satisfactionTrend,
  servqualScores,
  agencySatisfactionRanking,
  feedbackVolume,
}) {
  return (
    <div className="space-y-4">
      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <TrendChart
          title="Satisfaction Trend"
          data={satisfactionTrend}
          emptyMessage="No satisfaction trend data available."
        />
        <ServqualChart data={servqualScores} />
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <AgencyRanking data={agencySatisfactionRanking} />
        <TrendChart
          title="Feedback Volume"
          data={feedbackVolume}
          emptyMessage="No feedback volume data available."
        />
      </section>
    </div>
  );
}
