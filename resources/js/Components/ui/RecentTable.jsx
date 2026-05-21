import { ChevronRight } from 'lucide-react'

export default function RecentTable({ title, data, columns, keyExtractor, onViewAll }) {
  return (
    <section className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
      <div className="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
        <h3 className="text-[13px] font-bold font-headline text-blue-900">{title}</h3>
        {onViewAll && (
          <button onClick={onViewAll} className="text-[11px] font-bold font-label text-blue-900 hover:text-blue-700 transition-colors flex items-center gap-1">
            View All <ChevronRight className="w-3 h-3" />
          </button>
        )}
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-slate-50/50">
              {columns.map((col) => (
                <th key={col.key} className="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-500 whitespace-nowrap">
                  {col.title}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {data.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-8 text-center text-sm text-slate-400">
                  No records to display.
                </td>
              </tr>
            ) : (
              data.map((row) => (
                <tr key={keyExtractor(row)} className="hover:bg-slate-50 transition-colors">
                  {columns.map((col) => (
                    <td key={`${keyExtractor(row)}-${col.key}`} className="px-4 py-3">
                      {col.render ? col.render(row) : row[col.key]}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </section>
  )
}
