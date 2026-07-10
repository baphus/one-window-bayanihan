import ReportLazySection from '@/Components/Reports/ReportLazySection';
import TableSkeleton from '@/Components/Reports/TableSkeleton';

export default function AgencyScorecardSection({ pageHeadingStyles, role }) {
  return (
    <ReportLazySection
      lazyKey="agencyScorecard"
      skeleton={<TableSkeleton rowCount={5} />}
      emptyMessage="No agency data available."
    >
      {(data) => (
        <article
          className="border bg-white dark:bg-slate-900 dark:border-slate-700 p-4 shadow-sm"
          style={{ borderColor: '#e2e8f0' }}
        >
          <h3
            className={`mb-4 ${
              pageHeadingStyles?.sectionTitle ||
              'text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400'
            }`}
          >
            Agency Scorecard
          </h3>
          {data?.length > 0 ? (
            role === 'AGENCY' ? (
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                {(() => {
                  const a = data[0];
                  return (
                    <>
                      <div className="flex flex-col items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3">
                        <span className="text-2xl font-bold text-slate-800 dark:text-slate-100">
                          {a.total}
                        </span>
                        <span className="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                          Total Referrals
                        </span>
                      </div>
                      <div className="flex flex-col items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3">
                        <span className="text-2xl font-bold text-emerald-700">
                          {a.completed}
                        </span>
                        <span className="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                          Completed
                        </span>
                      </div>
                      <div className="flex flex-col items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3">
                        <span className="text-2xl font-bold text-slate-800 dark:text-slate-100">
                          {a.completionRate}%
                        </span>
                        <span className="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                          Completion Rate
                        </span>
                      </div>
                      <div className="flex flex-col items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3">
                        <span
                          className={`text-2xl font-bold ${
                            a.avgDays <= 7
                              ? 'text-emerald-700'
                              : a.avgDays <= 14
                                ? 'text-amber-700'
                                : 'text-rose-700'
                          }`}
                        >
                          {a.avgDays}d
                        </span>
                        <span className="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                          Avg Days
                        </span>
                      </div>
                      <div className="flex flex-col items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3">
                        <span className="text-2xl font-bold text-amber-700">
                          {a.pending}
                        </span>
                        <span className="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                          Pending
                        </span>
                      </div>
                    </>
                  );
                })()}
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-[11px]">
                  <thead>
                    <tr
                      className="border-b dark:border-slate-700 text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 dark:text-slate-400"
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
                        className="border-b border-slate-200 dark:border-slate-700 last:border-0"
                      >
                        <td className="py-2 pr-3 font-semibold text-slate-700 dark:text-slate-200">
                          {a.agency}
                        </td>
                        <td className="py-2 pr-3 text-right text-slate-700 dark:text-slate-200">
                          {a.total}
                        </td>
                        <td className="py-2 pr-3 text-right text-emerald-700">
                          {a.completed}
                        </td>
                        <td className="py-2 pr-3 text-right font-bold text-slate-700 dark:text-slate-200">
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
            )
          ) : (
            <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">
              No agency data available.
            </p>
          )}
        </article>
      )}
    </ReportLazySection>
  );
}
