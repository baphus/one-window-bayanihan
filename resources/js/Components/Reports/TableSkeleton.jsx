export default function TableSkeleton({ rowCount = 5 }) {
  const rows = Array.from({ length: rowCount }, (_, i) => i);

  return (
    <div className="overflow-x-auto animate-pulse">
      <table className="w-full text-[11px]">
        <thead>
          <tr
            className="border-b text-left text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500"
            style={{ borderColor: '#cbd5e1' }}
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
          {rows.map((i) => (
            <tr key={i} className="border-b border-slate-200 last:border-0">
              <td className="py-2 pr-3">
                <div className="h-3 rounded-md bg-slate-200" style={{ width: `${90 + (i % 3) * 25}px` }} />
              </td>
              <td className="py-2 pr-3 text-right">
                <div className="ml-auto h-3 w-8 rounded-md bg-slate-200" />
              </td>
              <td className="py-2 pr-3 text-right">
                <div className="ml-auto h-3 w-10 rounded-md bg-slate-200" />
              </td>
              <td className="py-2 pr-3 text-right">
                <div className="ml-auto h-3 w-9 rounded-md bg-slate-200" />
              </td>
              <td className="py-2 pr-3 text-right">
                <div className="ml-auto h-3 w-7 rounded-md bg-slate-200" />
              </td>
              <td className="py-2 text-right">
                <div className="ml-auto h-3 w-8 rounded-md bg-slate-200" />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
