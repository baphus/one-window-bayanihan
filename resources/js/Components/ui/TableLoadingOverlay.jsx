function SkeletonBlock({ className = '' }) {
  return <div className={`rounded bg-slate-200 ${className}`} />;
}

function TableSkeleton({ rows, columns }) {
  const widths = ['w-24', 'w-36', 'w-20', 'w-32', 'w-16', 'w-44', 'w-28'];

  return (
    <div className="flex flex-col gap-3 p-5">
      {Array.from({ length: rows }).map((_, rowIndex) => (
        <div key={rowIndex} className="flex items-center gap-5 border-b border-slate-100 py-[18px] last:border-b-0">
          {Array.from({ length: columns }).map((__, columnIndex) => (
            <SkeletonBlock key={columnIndex} className={`h-4 shrink-0 ${widths[columnIndex % widths.length]}`} />
          ))}
        </div>
      ))}
    </div>
  );
}

function CardSkeleton({ rows }) {
  return (
    <div className="grid grid-cols-1 gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <div className="mb-4 flex items-start justify-between gap-4">
            <div className="space-y-2">
              <SkeletonBlock className="h-4 w-36" />
              <SkeletonBlock className="h-3 w-24" />
            </div>
            <SkeletonBlock className="h-6 w-20 rounded-full" />
          </div>
          <div className="space-y-3">
            <SkeletonBlock className="h-3 w-full" />
            <SkeletonBlock className="h-3 w-5/6" />
            <SkeletonBlock className="h-3 w-2/3" />
          </div>
        </div>
      ))}
    </div>
  );
}

function ListSkeleton({ rows }) {
  return (
    <div className="flex flex-col gap-3 p-5">
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="rounded-lg border border-slate-100 bg-white p-4 shadow-sm">
          <div className="mb-3 flex items-center gap-3">
            <SkeletonBlock className="h-8 w-8 rounded-full" />
            <div className="space-y-2">
              <SkeletonBlock className="h-4 w-40" />
              <SkeletonBlock className="h-3 w-24" />
            </div>
          </div>
          <SkeletonBlock className="h-3 w-full" />
        </div>
      ))}
    </div>
  );
}

export default function TableLoadingOverlay({
  variant = 'table',
  rows = 5,
  columns = 5,
  label = 'Loading updated records…',
}) {
  return (
    <div
      className="absolute inset-0 z-10 bg-white/80 animate-pulse"
      role="status"
      aria-live="polite"
      aria-label={label}
    >
      <span className="sr-only">{label}</span>
      {variant === 'card' ? (
        <CardSkeleton rows={rows} />
      ) : variant === 'list' ? (
        <ListSkeleton rows={rows} />
      ) : (
        <TableSkeleton rows={rows} columns={columns} />
      )}
    </div>
  );
}
