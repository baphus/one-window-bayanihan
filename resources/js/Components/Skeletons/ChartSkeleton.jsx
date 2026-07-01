export default function ChartSkeleton({ className }) {
  return (
    <div className={`aspect-video w-full rounded-xl border border-slate-200/50 bg-white p-6 ${className ?? ''}`}>
      <div className="mx-auto h-10 w-10 animate-pulse rounded-full bg-gray-200" />
      <div className="mt-4 h-24 w-full animate-pulse rounded bg-gray-100/50" />
      <div className="mx-auto mt-2 h-16 w-3/4 animate-pulse rounded bg-gray-100/50" />
    </div>
  );
}
