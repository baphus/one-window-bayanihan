import { COLORS } from './pageHeadingStyles';

const bars = [
  { height: 'h-40', width: 'w-12' },
  { height: 'h-28', width: 'w-12' },
  { height: 'h-36', width: 'w-12' },
  { height: 'h-48', width: 'w-12' },
  { height: 'h-24', width: 'w-12' },
];

export default function ChartSkeleton() {
  return (
    <div className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
      <div className="mb-4 w-36 h-3 bg-slate-200 rounded animate-pulse" />
      <div className="flex items-end justify-around gap-2 h-56">
        {bars.map((bar, i) => (
          <div
            key={i}
            className={`${bar.height} ${bar.width} bg-slate-200 rounded-t animate-pulse`}
          />
        ))}
      </div>
    </div>
  );
}
