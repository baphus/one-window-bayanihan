import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { CardHeader, InfoField, InfoCell } from '@/Components/ui/CardSection';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import StatusBadge from '@/Components/ui/StatusBadge';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import { formatResolvedAddress } from '@/lib/addressResolver';
import { useState, useCallback } from 'react';

function formatAddress(addr) {
  return formatResolvedAddress(addr, '');
}

export default function ClientShow({ client, cases }) {
  const profile = client.profile || {};
  const sourceCase = client.source_case || {};
  const account = client.account || {};
  const accountState = account.state;

  const [currentPage, setCurrentPage] = useState(cases.current_page);
  const [isLoading, setIsLoading] = useState(false);
  const [avatarFailed, setAvatarFailed] = useState(false);

  const handlePageChange = useCallback((page) => {
    setIsLoading(true);
    router.get(
      route('clients.show', [client.kind, client.id]),
      { page },
      {
        preserveState: true,
        preserveScroll: true,
        only: ['cases'],
        onSuccess: () => setCurrentPage(page),
        onFinish: () => setIsLoading(false),
      }
    );
  }, [client.kind, client.id]);

  const fullName = [profile.first_name, profile.middle_name, profile.last_name, profile.suffix]
    .filter(Boolean)
    .join(' ') || 'N/A';

  const address = {
    region: profile.address_region,
    province: profile.address_province,
    city_municipality: profile.address_city_municipality,
    barangay: profile.address_barangay,
    street: profile.address_street,
  };
  const hasAddress = Object.values(address).some(Boolean);

  const hasEmployment = profile.employment_employer_name || profile.employment_position || profile.employment_country;
  const initials = [profile.first_name, profile.last_name].filter(Boolean).map((name) => name[0]).join('').toUpperCase() || '?';

  const caseColumns = [
    {
      key: 'case_number',
      title: 'CASE NO.',
      className: 'w-[18%]',
      render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.case_number}</span>,
    },
    {
      key: 'client_type',
      title: 'CLIENT TYPE',
      className: 'w-[14%]',
      render: (row) => (
        <span className="text-[12px] text-slate-600">
          {row.client_type === 'OFW' ? 'OFW' : 'Next of Kin'}
        </span>
      ),
    },
    {
      key: 'intake_date',
      title: 'INTAKE DATE',
      className: 'w-[14%]',
      render: (row) => (
        <span className="text-[12px] text-slate-600">
          {row.intake_date ? formatDisplayDate(row.intake_date) : '—'}
        </span>
      ),
    },
    {
      key: 'status',
      title: 'STATUS',
      className: 'w-[12%]',
      render: (row) => <StatusBadge status={row.status} variant="soft" size="sm" />,
    },
    {
      key: 'approver',
      title: 'APPROVER',
      className: 'w-[15%]',
      render: (row) => <span className="text-[12px] text-slate-600">{row.approver || '—'}</span>,
    },
    {
      key: 'created_at',
      title: 'DATE CREATED',
      className: 'w-[14%]',
      render: (row) => (
        <div>
          <p className="text-[12px] text-slate-600">{formatDisplayDate(row.created_at)}</p>
          <p className="text-[10px] text-slate-400">{formatDisplayTime(row.created_at)}</p>
        </div>
      ),
    },
    {
      key: 'closed_at',
      title: 'CLOSED',
      className: 'w-[12%]',
      render: (row) => (
        <span className="text-[12px] text-slate-600">
          {row.closed_at ? formatDisplayDate(row.closed_at) : '—'}
        </span>
      ),
    },
    {
      key: 'actions',
      title: 'ACTIONS',
      className: 'w-[10%] text-right',
      render: (row) => (
        <Link
          href={row.show_url}
          className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-50 hover:text-blue-900"
          title="View case"
          aria-label={`View case ${row.case_number}`}
        >
          <span className="material-symbols-outlined text-[15px]">visibility</span>
        </Link>
      ),
    },
  ];

  return (
    <AppLayout title="Client Details">
      <Head title="Client Details" />

      <div className="mb-5">
        <Link
          href={route('clients.index')}
          className="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-500 transition hover:text-blue-900"
        >
          <span className="material-symbols-outlined text-[16px]">arrow_back</span>
          Back to Clients
        </Link>
      </div>

      <div className="mx-auto max-w-[1440px]">
        <div className="mb-5">
          <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.02em] text-[#172333]">
            Client Details
          </h1>
          <p className="mt-2 text-[11px] leading-5 text-[#607080]">
            Client profile and case history
          </p>
        </div>

        <div className="grid grid-cols-1 gap-4">
          <main className="min-w-0 space-y-4">
            <section className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
              <CardHeader title="Client Profile" meta="From most recent case" />
              <div className="flex flex-col gap-4 border-b border-[#dce3eb] px-5 py-4 sm:flex-row sm:items-center">
                <div
                  className="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-blue-50 bg-blue-100 text-2xl font-bold text-blue-900"
                  role={!account.avatar_url || avatarFailed ? 'img' : undefined}
                  aria-label={!account.avatar_url || avatarFailed ? `${fullName} initials` : undefined}
                >
                  {account.avatar_url && !avatarFailed ? (
                    <img src={account.avatar_url} alt={`${fullName} profile photo`} className="h-full w-full object-cover" onError={() => setAvatarFailed(true)} />
                  ) : (
                    <span aria-hidden="true">{initials}</span>
                  )}
                </div>
                <div className="min-w-0">
                  <h2 className="break-words text-xl font-bold text-[#172333]">{fullName}</h2>
                  <p className="mt-1 text-[12px] text-slate-500">
                    {account.avatar_url && !avatarFailed ? 'Photo from linked account' : 'Initials from client profile'}
                  </p>
                </div>
              </div>
              <div className="grid grid-cols-1 gap-3 border-b border-[#dce3eb] px-5 py-3 sm:grid-cols-2">
                <InfoField label="Directory record" value={client.kind === 'account' ? 'Linked account' : 'Unlinked case'} />
                <InfoField
                  label="Latest case"
                  value={sourceCase.created_at ? formatDisplayDate(sourceCase.created_at) : 'N/A'}
                />
              </div>
              <div className="grid grid-cols-1 md:grid-cols-3 [&>div]:border-0">
                <InfoCell label="Full name" value={fullName} />
                <InfoCell label="Date of birth" value={profile.date_of_birth ? formatDisplayDate(profile.date_of_birth) : null} />
                <InfoCell label="Age" value={profile.current_age ?? '—'} />
                <InfoCell label="Sex" value={profile.sex} />
                <InfoCell label="Civil status" value={profile.civil_status === 'OTHER' ? profile.civil_status_other : profile.civil_status} />
                <InfoCell label="Deployment mode" value={profile.deployment_mode} />
                <InfoCell label="Employment status" value={profile.employment_status} />
                <InfoCell label="Email" value={profile.email} />
                <InfoCell label="Contact no." value={profile.contact_number} />
              </div>
              {hasAddress && (
                <div className="border-t border-[#dce3eb] px-5 py-3">
                  <p className="mb-1.5 text-[12px] font-medium text-slate-500">Address</p>
                  <p className="text-[13px] font-semibold text-slate-800">
                    {formatAddress(address) || 'No address recorded'}
                  </p>
                </div>
              )}
              {hasEmployment && (
                <div className="border-t border-[#dce3eb] px-5 py-3">
                  <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                      <p className="text-[12px] font-medium text-slate-500">Employer</p>
                      <p className="mt-0.5 text-[13px] font-semibold text-slate-800">{profile.employment_employer_name || 'N/A'}</p>
                    </div>
                    <div>
                      <p className="text-[12px] font-medium text-slate-500">Employer address</p>
                      <p className="mt-0.5 text-[13px] font-semibold text-slate-800">{profile.employment_employer_address || 'N/A'}</p>
                    </div>
                    <div>
                      <p className="text-[12px] font-medium text-slate-500">Position</p>
                      <p className="mt-0.5 text-[13px] font-semibold text-slate-800">{profile.employment_position || 'N/A'}</p>
                    </div>
                    <div>
                      <p className="text-[12px] font-medium text-slate-500">Country</p>
                      <p className="mt-0.5 text-[13px] font-semibold text-slate-800">{profile.employment_country || 'N/A'}</p>
                    </div>
                    <div>
                      <p className="text-[12px] font-medium text-slate-500">Last position</p>
                      <p className="mt-0.5 text-[13px] font-semibold text-slate-800">{profile.employment_last_position || 'N/A'}</p>
                    </div>
                    <div>
                      <p className="text-[12px] font-medium text-slate-500">Date of arrival</p>
                      <p className="mt-0.5 text-[13px] font-semibold text-slate-800">
                        {profile.employment_date_of_arrival ? formatDisplayDate(profile.employment_date_of_arrival) : 'N/A'}
                      </p>
                    </div>
                  </div>
                </div>
              )}
              {sourceCase.case_number && (
                <div className="border-t border-[#dce3eb] px-5 py-3 text-[11px] text-slate-500">
                  Source: Case {sourceCase.case_number}
                  {sourceCase.intake_date && ` · ${formatDisplayDate(sourceCase.intake_date)}`}
                </div>
              )}
            </section>

            <section className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
              <CardHeader title="Account" />
              {accountState === 'Active' || accountState === 'Inactive' ? (
                <div className="space-y-3 px-5 pb-5">
                  <InfoField label="Email" value={account.email} />
                  <InfoField
                    label="Status"
                    value={
                      <StatusBadge
                        status={accountState === 'Active' ? 'ACTIVE' : 'INACTIVE'}
                        variant="soft"
                        size="sm"
                      />
                    }
                  />
                </div>
              ) : (
                <div className="px-5 pb-5">
                  <p className="text-[12px] text-slate-500">
                    {accountState === 'No account linked'
                      ? 'No account linked. This is an unlinked case.'
                      : 'Account unavailable.'}
                  </p>
                </div>
              )}
            </section>

            <section className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
              <CardHeader title="Case History" meta={`${cases.total} case${cases.total !== 1 ? 's' : ''}`} />
              <div className="min-w-0 space-y-3 px-3 pb-5 sm:px-5">
                <div className="min-w-0 overflow-x-auto">
                <UnifiedTable
                  variant="embedded"
                  data={cases.data}
                  columns={caseColumns}
                  keyExtractor={(row) => row.id}
                  hideControlBar
                  hidePagination
                  isLoading={isLoading}
                  emptyStateMessage="No cases found."
                />
                </div>

                {cases.last_page > 1 && (
                  <div className="mt-4 flex items-center justify-between border-t border-slate-200 pt-4">
                    <p className="text-[12px] text-slate-600">
                      Showing {cases.from} to {cases.to} of {cases.total} cases
                    </p>
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => handlePageChange(currentPage - 1)}
                        disabled={currentPage === 1 || isLoading}
                        className="h-8 px-3 text-[12px] font-semibold text-slate-600 hover:text-blue-900 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        Previous
                      </button>
                      <span className="text-[12px] text-slate-600">
                        Page {currentPage} of {cases.last_page}
                      </span>
                      <button
                        type="button"
                        onClick={() => handlePageChange(currentPage + 1)}
                        disabled={currentPage === cases.last_page || isLoading}
                        className="h-8 px-3 text-[12px] font-semibold text-slate-600 hover:text-blue-900 disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        Next
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </section>
          </main>

        </div>
      </div>
    </AppLayout>
  );
}
