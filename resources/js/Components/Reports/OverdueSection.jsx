import { AlertTriangle, Clock } from 'lucide-react';
import { useLazyProp } from '@/Hooks/useLazyProp';
import { COLORS, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';
import ChartSkeleton from '@/Components/Reports/ChartSkeleton';
import { Link } from '@inertiajs/react';

export default function OverdueSection() {
  const [data, isLoading, error] = useLazyProp('overdueReferrals');

  if (isLoading) return <ChartSkeleton />;
  if (error) {
    return (
      <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
        <p className="py-8 text-center text-[13px] text-slate-400">Failed to load overdue data.</p>
      </article>
    );
  }

  const count = data?.count ?? 0;
  const referrals = data?.referrals?.data ?? [];

  return (
    <article className="border bg-white p-4 shadow-sm" style={{ borderColor: COLORS.border }}>
      <div className="flex items-center justify-between mb-4">
        <h3 className={pageHeadingStyles.sectionTitle}>Overdue Referrals</h3>
        <span className="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-[11px] font-bold text-rose-700">
          <AlertTriangle className="h-3.5 w-3.5" />
          {count} Overdue
        </span>
      </div>

      {count > 0 ? (
        <p className="mb-3 text-[11px] text-slate-500">
          Referrals exceeding 14-day threshold without completion
        </p>
      ) : (
        <p className="py-8 text-center text-[13px] text-slate-400">No overdue referrals.</p>
      )}

      {referrals.length > 0 && (
        <div className="space-y-2">
          {referrals.slice(0, 5).map((ref) => {
            const daysOpen = ref.created_at
              ? Math.floor((Date.now() - new Date(ref.created_at).getTime()) / (1000 * 60 * 60 * 24))
              : 0;
            return (
              <div key={ref.id} className="flex items-center justify-between border-b border-slate-100 pb-2 last:border-0">
                <div className="flex items-center gap-2 min-w-0">
                  <Clock className="h-3 w-3 shrink-0 text-rose-400" />
                  <div className="min-w-0">
                    <Link href={route('referrals.show', ref.id)} className="text-[12px] font-semibold text-slate-700 hover:text-[#0b5a8c] truncate block">
                      {ref.case_file?.case_number || 'N/A'}
                    </Link>
                    <p className="text-[10px] text-slate-500 truncate">
                      {ref.case_file?.client ? `${ref.case_file.client.first_name} ${ref.case_file.client.last_name}` : ''}
                      {ref.agency ? ` · ${ref.agency.name}` : ''}
                    </p>
                  </div>
                </div>
                <span className="shrink-0 text-[11px] font-bold text-rose-600">{daysOpen}d</span>
              </div>
            );
          })}
        </div>
      )}
    </article>
  );
}
