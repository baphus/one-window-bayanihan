import { Link, router } from '@inertiajs/react';
import StatusBadge from '@/Components/ui/StatusBadge';

function SeverityDots({ severity }) {
  const config = {
    mild: { count: 1, color: 'bg-amber-400', label: 'Mild' },
    moderate: { count: 2, color: 'bg-orange-400', label: 'Moderate' },
    severe: { count: 3, color: 'bg-rose-500', label: 'Severe' },
  };
  const { count, color, label } = config[severity] ?? config.mild;

  return (
    <span className="flex items-center gap-[3px]" title={label}>
      {Array.from({ length: count }).map((_, i) => (
        <span key={i} className={`h-2 w-2 rounded-full ${color}`} />
      ))}
    </span>
  );
}

export default function OverdueCard({
  referral,
  userRole,
  selected,
  onSelect,
  onSendReminder,
  sending,
}) {
  const canRemind = userRole === 'ADMIN' || userRole === 'CASE_MANAGER';
  const isSending = sending === referral.id;

  return (
    <div className="group bg-white rounded-md border border-slate-200 p-4 hover:border-slate-300 hover:shadow-sm transition-all duration-150">
      <div className="flex items-start gap-3">
        {/* Checkbox (admin/cm only) */}
        {canRemind && (
          <div className="pt-0.5">
            <input
              type="checkbox"
              checked={selected}
              onChange={() => onSelect(referral.id)}
              className="h-4 w-4 rounded border-slate-300 text-blue-900 focus:ring-blue-900 cursor-pointer"
            />
          </div>
        )}

        {/* Severity indicator + stale days */}
        <div className="flex flex-col items-center gap-1 min-w-[36px] pt-0.5">
          <SeverityDots severity={referral.severity} />
          <span className="text-[10px] font-bold text-slate-400 whitespace-nowrap">
            {referral.days_since_last_activity}d
          </span>
        </div>

        {/* Main content */}
        <div className="flex-1 min-w-0">
          {/* Row 1: Case number + client name */}
          <div className="flex items-center gap-2 mb-1">
            <Link
              href={route('referrals.show', referral.id)}
              className="text-sm font-semibold text-blue-900 hover:text-blue-700 truncate"
            >
              {referral.case_number ?? 'N/A'} — {referral.client_name}
            </Link>
          </div>

          {/* Row 2: Meta info */}
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
            <span className="truncate max-w-[180px]" title={referral.required_services}>
              {referral.required_services}
            </span>
            <StatusBadge status={referral.status} size="sm" />
            {referral.agency_name && (
              <span className="flex items-center gap-1">
                <span className="material-symbols-outlined text-[12px]">business</span>
                {referral.agency_name}
              </span>
            )}
          </div>

          {/* Row 3: Compliance + Last activity + Case manager */}
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-[11px]">
            {referral.compliance_progress && (
              <span className="flex items-center gap-1 font-medium text-slate-600">
                <span className="material-symbols-outlined text-[12px] text-orange-500">assignment_turned_in</span>
                Compliance: {referral.compliance_progress}
              </span>
            )}
            <span className="text-slate-400" title={referral.last_activity_date}>
              {referral.last_activity_description}
            </span>
            <span className="flex items-center gap-1 text-slate-400">
              <span className="material-symbols-outlined text-[12px]">person</span>
              {referral.case_manager_name}
            </span>
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center gap-2 shrink-0 pt-0.5">
          <Link
            href={route('referrals.show', referral.id)}
            className="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-md transition-colors"
          >
            View Details
            <span className="material-symbols-outlined text-[14px]">chevron_right</span>
          </Link>
          {canRemind && (
            <button
              onClick={() => onSendReminder(referral.id)}
              disabled={isSending}
              className="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 text-[11px] font-bold rounded-md transition-colors disabled:opacity-50"
            >
              {isSending ? (
                <span className="material-symbols-outlined text-[14px] animate-spin">progress_activity</span>
              ) : (
                <span className="material-symbols-outlined text-[14px]">notifications</span>
              )}
              Remind
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
