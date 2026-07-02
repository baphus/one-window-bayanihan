import { useLazyProp } from '@/Hooks/useLazyProp';

export default function ReportLazySection({ lazyKey, skeleton, children, emptyMessage = null }) {
  const [data, isLoading, error] = useLazyProp(lazyKey);

  if (isLoading) return skeleton;
  if (error) {
    return (
      <div className="border bg-white p-4 shadow-sm" style={{ borderColor: '#e2e8f0' }}>
        <p className="py-8 text-center text-[13px] text-slate-400">Failed to load this section.</p>
      </div>
    );
  }
  if (data === null || data === undefined) {
    return emptyMessage ? (
      <div className="border bg-white p-4 shadow-sm" style={{ borderColor: '#e2e8f0' }}>
        <p className="py-8 text-center text-[13px] text-slate-400">{emptyMessage}</p>
      </div>
    ) : null;
  }
  return typeof children === 'function' ? children(data) : children;
}
