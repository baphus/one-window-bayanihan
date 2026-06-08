const RowSkeleton = () => (
  <div className="flex items-center gap-4 py-3">
    <div className="h-3 w-3/12 rounded bg-gray-200 animate-pulse" />
    <div className="h-3 w-4/12 rounded bg-gray-200 animate-pulse" />
    <div className="h-3 w-2/12 rounded bg-gray-200 animate-pulse" />
    <div className="h-3 w-1/12 rounded bg-gray-200 animate-pulse" />
  </div>
);

const variants = {
  card: (i) => (
    <div
      key={i}
      className="h-32 w-full min-w-[16rem] max-w-xs flex-shrink-0 rounded-lg bg-gray-200 animate-pulse sm:w-64"
    />
  ),
  chart: (i) => (
    <div key={i} className="h-64 w-full rounded-lg bg-gray-200 animate-pulse" />
  ),
  table: (i) => (
    <div key={i} className="space-y-1">
      <div className="h-4 w-full rounded bg-gray-300 animate-pulse" />
      <RowSkeleton />
      <RowSkeleton />
      <RowSkeleton />
    </div>
  ),
  text: (i) => (
    <div key={i} className="space-y-2.5">
      <div className="h-3 w-3/4 rounded bg-gray-200 animate-pulse" />
      <div className="h-3 w-full rounded bg-gray-200 animate-pulse" />
      <div className="h-3 w-5/6 rounded bg-gray-200 animate-pulse" />
      <div className="h-3 w-2/3 rounded bg-gray-200 animate-pulse" />
    </div>
  ),
};

const layout = {
  card: 'flex flex-wrap gap-4',
  chart: 'space-y-4',
  table: 'space-y-4',
  text: 'space-y-6',
};

export default function SectionSkeleton({ type = 'card', count = 1 }) {
  const renderFn = variants[type] ?? variants.card;
  const containerClass = layout[type] ?? layout.card;

  const items = Array.from({ length: count }, (_, i) => renderFn(i));

  return <div className={containerClass}>{items}</div>;
}
