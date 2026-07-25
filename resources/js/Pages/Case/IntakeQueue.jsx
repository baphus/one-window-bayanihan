import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import { formatDisplayDate } from '@/lib/utils';
import TableLoadingOverlay from '@/Components/ui/TableLoadingOverlay';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import { useToast } from '@/Hooks/useToast';

function getClientName(caseItem) {
  if (caseItem.client) {
    return `${caseItem.client.first_name} ${caseItem.client.last_name}`;
  }
  if (caseItem.draft_client_data?.first_name || caseItem.draft_client_data?.last_name) {
    return `${caseItem.draft_client_data.first_name ?? ''} ${caseItem.draft_client_data.last_name ?? ''}`.trim();
  }
  return '—';
}

function getClientEmail(caseItem) {
  if (caseItem.client?.email) return caseItem.client.email;
  if (caseItem.draft_client_data?.email) return caseItem.draft_client_data.email;
  return '—';
}

function getCategoryNames(caseItem) {
  if (caseItem.categories && caseItem.categories.length > 0) {
    return caseItem.categories.map((c) => c.name).join(', ');
  }
  if (caseItem.category) return caseItem.category.name;
  return '—';
}

export default function IntakeQueue({ cases, filters: initialFilters = {} }) {
  const [searchValue, setSearchValue] = useState(initialFilters?.search ?? '');
  const [rejectTarget, setRejectTarget] = useState(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejecting, setRejecting] = useState(false);
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();
  const toast = useToast();
  const searchTimeout = useRef(null);

  useEffect(() => {
    return () => clearTimeout(searchTimeout.current);
  }, []);

  function navigateWith(overrides) {
    const url = new URL(window.location);
    Object.entries(overrides).forEach(([k, v]) => {
      if (v) url.searchParams.set(k, v);
      else url.searchParams.delete(k);
    });
    url.searchParams.delete('page');
    router.get(url.toString(), {}, withLoading({ preserveState: true, replace: true }));
  }

  function handleSearchChange(value) {
    setSearchValue(value);
    clearTimeout(searchTimeout.current);
    searchTimeout.current = setTimeout(() => {
      navigateWith({ search: value || undefined });
    }, 300);
  }

  function goToPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    router.get(url.toString(), {}, withLoading({ preserveState: true, replace: true }));
  }

  function handleReject() {
    if (!rejectTarget || rejectReason.length < 10) return;
    setRejecting(true);
    router.post(route('cases.reject-intake', rejectTarget), {
      deletion_reason: rejectReason,
    }, {
      onSuccess: () => {
        setRejectTarget(null);
        setRejectReason('');
        toast.success('Intake submission rejected.');
      },
      onError: (errors) => {
        const msg = errors?.deletion_reason || Object.values(errors)[0] || 'Failed to reject intake.';
        toast.error(msg);
      },
      onFinish: () => setRejecting(false),
    });
  }

  return (
    <AppLayout title="Intake Queue">
      <Head title="Intake Queue" />

      <div className="pb-6">
        <header className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-extrabold text-slate-900">Intake Queue</h1>
            <p className="text-sm text-slate-500 mt-0.5">
              Self-filed OFW submissions awaiting review and approval.
            </p>
          </div>
        </header>

        <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
          {/* Filter bar */}
          <div className="p-4 bg-slate-50 border-b border-slate-300">
            <div className="flex flex-wrap items-end gap-3">
              <div className="relative flex-1 min-w-[200px]">
                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">
                  search
                </span>
                <input
                  type="text"
                  value={searchValue}
                  onChange={(e) => handleSearchChange(e.target.value)}
                  placeholder="Search by OFW name..."
                  className="w-full h-[40px] pl-10 pr-10 bg-white border border-slate-300 rounded-[2px] text-[14px] text-slate-600 placeholder-slate-400 outline-none focus:ring-1 focus:ring-blue-900 transition-all"
                />
              </div>
            </div>
          </div>

          <div className="relative" aria-busy={tableLoading}>
            {tableLoading && <TableLoadingOverlay />}

            {cases.data.length === 0 ? (
              <div className="flex flex-col items-center justify-center p-12 text-center">
                <span className="material-symbols-outlined mb-3 text-4xl text-slate-300">inbox</span>
                <p className="text-[14px] font-bold text-slate-700">No Pending Intake Submissions</p>
                <p className="mt-1 max-w-sm text-xs text-slate-500">
                  When OFWs submit a case through the self-filing portal, their submissions will appear here for review.
                </p>
              </div>
            ) : (
              <>
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="bg-slate-50 border-b border-slate-300">
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">OFW Name</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Email</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Submitted</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Categories</th>
                      <th className="px-5 py-4 text-[12px] font-extrabold uppercase tracking-widest text-slate-500 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-300 bg-white">
                    {cases.data.map((caseItem) => (
                      <tr key={caseItem.id} className="hover:bg-slate-100 transition-colors">
                        <td className="px-5 py-4 text-xs font-medium text-slate-800">
                          {getClientName(caseItem)}
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-600">
                          {getClientEmail(caseItem)}
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-500">
                          {caseItem.created_at ? formatDisplayDate(caseItem.created_at) : '—'}
                        </td>
                        <td className="px-5 py-4 text-xs text-slate-600">
                          <span className="truncate max-w-[200px] inline-block">
                            {getCategoryNames(caseItem)}
                          </span>
                        </td>
                        <td className="px-5 py-4 text-right">
                          <div className="flex items-center justify-end gap-2">
                            <button
                              onClick={() => router.visit(route('cases.edit-draft', caseItem.id))}
                              className="min-h-[28px] px-3 bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-[3px] transition-colors"
                            >
                              Review
                            </button>
                            <button
                              onClick={() => setRejectTarget(caseItem.id)}
                              className="min-h-[28px] px-3 bg-white text-red-600 border border-red-200 hover:bg-red-50 text-[11px] font-bold rounded-[3px] transition-colors"
                            >
                              Reject
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>

                {/* Pagination */}
                {cases.last_page > 1 && (
                  <div className="px-6 py-4 bg-slate-50 border-t border-slate-300 flex items-center justify-between">
                    <span className="text-xs text-slate-500">
                      Showing {cases.from}–{cases.to} of {cases.total}
                    </span>
                    <div className="flex items-center gap-1">
                      {cases.current_page > 1 && (
                        <button
                          onClick={() => goToPage(cases.current_page - 1)}
                          className="px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-300 rounded hover:bg-slate-100 transition-colors"
                        >
                          Previous
                        </button>
                      )}
                      {cases.current_page < cases.last_page && (
                        <button
                          onClick={() => goToPage(cases.current_page + 1)}
                          className="px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-300 rounded hover:bg-slate-100 transition-colors"
                        >
                          Next
                        </button>
                      )}
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </div>

      {/* Reject Confirmation Dialog */}
      <ConfirmDialog
        open={!!rejectTarget}
        onClose={() => { setRejectTarget(null); setRejectReason(''); }}
        onConfirm={handleReject}
        title="Reject Intake Submission"
        confirmLabel={rejecting ? 'Rejecting...' : 'Reject'}
        confirmVariant="danger"
        disabled={rejecting || rejectReason.length < 10}
      >
        <p className="text-sm text-slate-600 mb-3">
          This will reject the OFW's submission. Please provide a reason (minimum 10 characters):
        </p>
        <textarea
          value={rejectReason}
          onChange={(e) => setRejectReason(e.target.value)}
          placeholder="Reason for rejection..."
          rows={3}
          className="w-full border border-slate-300 rounded-[2px] px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:ring-1 focus:ring-blue-900 outline-none"
        />
        {rejectReason.length > 0 && rejectReason.length < 10 && (
          <p className="text-xs text-red-500 mt-1">Reason must be at least 10 characters.</p>
        )}
      </ConfirmDialog>
    </AppLayout>
  );
}
