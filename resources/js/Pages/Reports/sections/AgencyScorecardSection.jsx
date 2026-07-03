import ReportLazySection from '@/Components/Reports/ReportLazySection';
import TableSkeleton from '@/Components/Reports/TableSkeleton';

export default function AgencyScorecardSection({ pageHeadingStyles }) {
  return (
    <ReportLazySection
      lazyKey="agencyScorecard"
      skeleton={<TableSkeleton rowCount={5} />}
      emptyMessage="No agency data available."
    >
      {(data) => (
        <article
          className="border bg-white p-4 shadow-sm"
          style={{ borderColor: '#e2e8f0' }}
        >
          <h3
            className={`mb-4 ${
              pageHeadingStyles?.sectionTitle ||
              'text-[11px] font-bold uppercase tracking-wider text-slate-500'
            }`}
          >
            Agency Scorecard
          </h3>
          {data?.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full text-[11px]">
                <thead>
                  <tr
                    className="border-b text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500"
                    style={{ borderColor: '#e2e8f0' }}
                  >
                    <th className="pb-2 pr-3">Agency</th>
                    <th className="pb-2 pr-3 text-right">Total</th>
                    <th className="pb-2 pr-3 text-right">Completed</th>
                    <th className="pb-2 pr-3 text-right">Rate</th>
                    <th className="pb-2 pr-3 text-right">Avg Days</th>
                    <th className="pb-2 text-right">Pending</th>
                  </tr>
                </thead>
                <tbody>
                  {data.map((a) => (
                    <tr
                      key={a.agency}
                      className="border-b border-slate-200 last:border-0"
                    >
                      <td className="py-2 pr-3 font-semibold text-slate-700">
                        {a.agency}
                      </td>
                      <td className="py-2 pr-3 text-right text-slate-700">
                        {a.total}
                      </td>
                      <td className="py-2 pr-3 text-right text-emerald-700">
                        {a.completed}
                      </td>
                      <td className="py-2 pr-3 text-right font-bold text-slate-700">
                        {a.completionRate}%
                      </td>
                      <td
                        className={`py-2 pr-3 text-right ${
                          a.avgDays <= 7
                            ? 'text-emerald-700'
                            : a.avgDays <= 14
                              ? 'text-amber-700'
                              : 'text-rose-700'
                        }`}
                      >
                        {a.avgDays}d
                      </td>
                      <td className="py-2 text-right text-amber-700">
                        {a.pending}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400">
              No agency data available.
            </p>
          )}
        </article>
      )}
    </ReportLazySection>
  );
}
