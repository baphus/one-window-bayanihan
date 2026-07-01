export default function TableSkeleton({ className, rowCount = 5 }) {
  const widths = ['60%', '40%', '50%', '30%', '45%'];
  const rows = Array.from({ length: rowCount }, (_, i) => (
    <div key={i} className="flex h-12 items-center border-b border-slate-100">
      <div className="mx-4 flex flex-1 gap-4">
        <div className="h-3 animate-pulse rounded bg-gray-200" style={{ width: widths[i % widths.length] }} />
        <div className="h-3 animate-pulse rounded bg-gray-200" style={{ width: widths[(i + 1) % widths.length] }} />
        <div className="h-3 animate-pulse rounded bg-gray-200" style={{ width: widths[(i + 2) % widths.length] }} />
        <div className="h-3 animate-pulse rounded bg-gray-200" style={{ width: widths[(i + 3) % widths.length] }} />
        <div className="h-3 animate-pulse rounded bg-gray-200" style={{ width: widths[(i + 4) % widths.length] }} />
      </div>
    </div>
  ));

  const headerPills = Array.from({ length: 5 }, (_, i) => (
    <div key={i} className="mx-4 h-3 w-16 animate-pulse rounded bg-gray-200" />
  ));

  return (
    <div className={`overflow-hidden rounded-xl border border-slate-200/50 bg-white ${className ?? ''}`}>
      <div className="flex h-10 items-center bg-gray-100/80">{headerPills}</div>
      {rows}
    </div>
  );
}
