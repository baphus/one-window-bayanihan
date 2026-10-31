import { FileDown } from 'lucide-react';

export default function ExportButtons({ fromDateISO, toDateISO, dateScope, province, city, agencyId, disabled = false }) {
  const params = {
    from: fromDateISO,
    to: toDateISO,
    ...(dateScope ? { date_scope: dateScope } : {}),
    ...(province ? { province } : {}),
    ...(city ? { city } : {}),
    ...(agencyId ? { agency_id: agencyId } : {}),
  };

  return (
    <button
      type="button"
      disabled={disabled}
      title={disabled ? 'Apply filters before printing.' : undefined}
      onClick={() => window.open(route('reports.print', params), '_blank', 'noopener,noreferrer')}
      className="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-md border border-success bg-success px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-success/90 focus:outline-none focus:ring-2 focus:ring-success focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    >
      <FileDown className="h-4 w-4" />
      Print / Save PDF
    </button>
  );
}
