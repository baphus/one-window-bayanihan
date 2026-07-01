export default function CardSkeleton({ className }) {
  return (
    <div className={`h-[120px] w-full rounded-lg border border-slate-200/50 bg-white p-4 ${className ?? ''}`}>
      <div className="h-4 w-1/3 animate-pulse rounded bg-gray-200" />
      <div className="mt-3 h-8 w-1/2 animate-pulse rounded bg-gray-200" />
    </div>
  );
}
