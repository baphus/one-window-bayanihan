import { COLORS, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';

export default function AgencyScorecardSection({ agencyScorecard }) {
  if (!agencyScorecard?.length) return null;

  return (
    <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
      <h3 className={`mb-4 ${pageHeadingStyles.sectionTitle}`}>Agency Scorecard</h3>
      {agencyScorecard?.length > 0 ? (
        <div className="overflow-x-auto">
          <table className="w-full text-[11px]">
            <thead>
              <tr className="border-b text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500" style={{ borderColor: COLORS.border }}>
                <th className="pb-2 pr-3">Agency</th>
                <th className="pb-2 pr-3 text-right">Total</th>
                <th className="pb-2 pr-3 text-right">Completed</th>
                <th className="pb-2 pr-3 text-right">Rate</th>
                <th className="pb-2 pr-3 text-right">Avg Days</th>
                <th className="pb-2 text-right">Pending</th>
              </tr>
            </thead>
            <tbody>
              {agencyScorecard.map((a) => (
                <tr key={a.agency} className="border-b border-[#e2e8f0] last:border-0">
                  <td className="py-2 pr-3 font-semibold text-slate-700">{a.agency}</td>
                  <td className="py-2 pr-3 text-right text-slate-700">{a.total}</td>
                  <td className="py-2 pr-3 text-right text-emerald-700">{a.completed}</td>
                  <td className="py-2 pr-3 text-right font-bold text-slate-700">{a.completionRate}%</td>
                  <td className={`py-2 pr-3 text-right ${a.avgDays <= 7 ? 'text-emerald-700' : a.avgDays <= 14 ? 'text-amber-700' : 'text-rose-700'}`}>{a.avgDays}d</td>
                  <td className="py-2 text-right text-amber-700">{a.pending}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="py-8 text-center text-[13px] text-slate-400">No agency data available.</p>
      )}
    </article>
  );
}
