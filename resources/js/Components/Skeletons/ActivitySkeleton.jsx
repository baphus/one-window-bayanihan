export default function ActivitySkeleton({ className, itemCount = 3 }) {
  const items = Array.from({ length: itemCount }, (_, i) => (
    <div key={i} className="flex items-start gap-4 p-4">
      <div className="h-10 w-10 shrink-0 animate-pulse rounded-full bg-gray-200" />
      <div className="flex-1 space-y-2">
        <div className="h-3 w-3/4 animate-pulse rounded bg-gray-200" />
        <div className="h-3 w-1/2 animate-pulse rounded bg-gray-200" />
      </div>
    </div>
  ));

  return (
    <div className={`space-y-4 ${className ?? ''}`}>{items}</div>
  );
}
