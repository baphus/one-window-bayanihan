import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';

/**
 * Stakeholder directory — the internal register of partner agencies.
 *
 * Presentation follows the register pages: a card that holds a tab strip, the
 * agency table, and the shared table footer.
 */

function AgencyLogo({ agency }) {
  const [hasError, setHasError] = useState(false);

  if (hasError || !agency.logo_url) {
    return (
      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-fixed">
        <span className="text-sm font-bold text-primary">{agency.short?.charAt(0)}</span>
      </div>
    );
  }

  return (
    <img
      src={agency.logo_url}
      alt={agency.short}
      className="h-10 w-10 shrink-0 rounded-lg object-contain"
      referrerPolicy="no-referrer"
      loading="lazy"
      onError={() => setHasError(true)}
    />
  );
}

export default function StakeholderIndex({ agencies }) {
  const columns = useMemo(() => [
    {
      key: 'agency',
      title: 'Agency',
      sortable: false,
      render: (row) => (
        <div className="flex items-center gap-3">
          <AgencyLogo agency={row} />
          <div className="min-w-0">
            <Link
              href={route('stakeholders.show', row.id)}
              className="block truncate text-sm font-bold text-primary hover:underline"
            >
              {row.short}
            </Link>
            <p className="truncate text-xs text-slate-500">{row.name}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'services',
      title: 'Services offered',
      sortable: false,
      render: (row) => {
        const services = row.services ?? [];

        return (
          <div>
            {services.map((service) => (
              <p key={service.id} className="text-[13.5px] leading-5 text-slate-700">
                {service.name}
              </p>
            ))}
            <p className="mt-1 text-xs text-slate-400">
              {services.length} {services.length === 1 ? 'service' : 'services'}
            </p>
          </div>
        );
      },
    },
    {
      key: 'active_referrals',
      title: 'Active referrals',
      sortable: false,
      render: (row) => (
        <span className="text-[13.5px] text-slate-700">{row.active_referrals_count ?? 0}</span>
      ),
    },
    {
      key: 'actions',
      title: 'Action',
      sortable: false,
      render: (row) => (
        <Link
          href={route('stakeholders.show', row.id)}
          className="inline-flex min-h-[28px] items-center rounded border border-primary bg-primary px-2.5 text-[11px] font-bold text-white transition-colors hover:bg-primary-container"
        >
          View details
        </Link>
      ),
    },
  ], []);

  return (
    <AppLayout title="Stakeholders">
      <Head title="Stakeholders" />

      <div data-tour="stakeholders-header" className="mb-6">
        <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
          Stakeholders
        </h1>
        <p className="text-sm text-slate-400 font-body mt-0.5">
          Partner agencies and organizations involved in the One Window Bayanihan system.
        </p>
      </div>

      <div data-tour="stakeholders-table" className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        {/* Tab strip */}
        <div className="flex items-center justify-between gap-4 border-b border-slate-200 px-4">
          <div className="-mb-px border-b-2 border-primary px-3 py-3 text-[13px] font-semibold text-primary">
            All agencies
            <span className="ml-1.5 tabular-nums">{agencies.length}</span>
          </div>
          <p className="hidden text-[13px] text-slate-500 sm:block">Agency and service directory</p>
        </div>

        {/* Control bar */}
        <div className="flex items-center justify-end gap-4 border-b border-slate-200 px-4 py-3">
          <p className="text-[12.5px] text-slate-500">
            {agencies.length} {agencies.length === 1 ? 'agency' : 'agencies'}
          </p>
        </div>

        {/* Agency register */}
        <UnifiedTable
          columns={columns}
          data={agencies}
          keyExtractor={(row) => row.id}
          hideControlBar
          hidePagination
          emptyStateMessage="No agencies found"
        />

        {/* Register footer */}
        <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 md:flex-row">
          <p className="text-[13px] text-slate-500">
            Showing{' '}
            <span className="font-semibold text-slate-700">
              {agencies.length > 0 ? 1 : 0}–{agencies.length}
            </span>{' '}
            of <span className="font-semibold text-slate-700">{agencies.length}</span> agencies
          </p>
          <p className="text-[13px] text-slate-500">Page 1 of 1</p>
        </div>
      </div>
    </AppLayout>
  );
}
