import { useLazyProp } from '@/Hooks/useLazyProp';

export default function ReportLazySection({ lazyKey, skeleton, children, emptyMessage = null }) {
  const [data, isLoading, error] = useLazyProp(lazyKey);

  if (isLoading) return skeleton;
  if (error) {
    return (
      <div className="rounded-[3px] border border-[#e2e8f0] bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">Failed to load this section.</p>
      </div>
    );
  }
  if (data === null || data === undefined) {
    return emptyMessage ? (
      <div className="rounded-[3px] border border-[#e2e8f0] bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <p className="py-8 text-center text-[13px] text-slate-400 dark:text-slate-500">{emptyMessage}</p>
      </div>
    ) : null;
  }
  return typeof children === 'function' ? children(data) : children;
}
