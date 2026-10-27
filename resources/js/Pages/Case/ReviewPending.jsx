import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { PageHeader, SectionCard, MaterialSymbol } from '@/Components/Dashboard/primitives';
import StatusBadge from '@/Components/ui/StatusBadge';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import safeRoute from '@/utils/safeRoute';

const vulnStyles = {
  PWD: 'bg-purple-100 text-purple-800',
  'Senior Citizen': 'bg-orange-100 text-orange-800',
  'Solo Parent': 'bg-pink-100 text-pink-800',
  'Indigenous Person': 'bg-teal-100 text-teal-800',
  None: 'bg-slate-100 text-slate-500',
};

function clientName(row) {
  const c = row.client;
  if (!c) return 'Unknown client';
  return [c.first_name, c.middle_name, c.last_name, c.suffix].filter(Boolean).join(' ') || 'Unknown client';
}

function caseHasCategories(row) {
  if (Array.isArray(row?.categories) && row.categories.length > 0) return true;
  if (row?.category) return true;
  if (Array.isArray(row?.category_ids) && row.category_ids.length > 0) return true;
  if (row?.case_issue_id || row?.case_issue) return true;
  return false;
}

function excerpt(text, len = 140) {
  if (!text) return 'No summary provided.';
  return text.length > len ? `${text.slice(0, len).trim()}…` : text;
}

function ReviewDetail({ caseFile, categories, caseIssues, onClose }) {
  const needsClassification = !caseHasCategories(caseFile);
  const [categoryIds, setCategoryIds] = useState([]);
  const [caseIssueId, setCaseIssueId] = useState('');
  const [localIssues, setLocalIssues] = useState(caseIssues);
  const [showAddIssue, setShowAddIssue] = useState(false);
  const [newIssueName, setNewIssueName] = useState('');
  const [addingIssue, setAddingIssue] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [rejectOpen, setRejectOpen] = useState(false);
  const [docFile, setDocFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [formError, setFormError] = useState('');
  const [processing, setProcessing] = useState(false);

  const client = caseFile.client || {};
  const vuln = caseFile.vulnerability_indicator || 'None';

  // Keep the dropdown in step with server props without discarding issues
  // added inline during this review.
  useEffect(() => { setLocalIssues(caseIssues); }, [caseIssues]);

  async function handleQuickAddIssue() {
    const name = newIssueName.trim();
    if (!name || addingIssue) return;
    setAddingIssue(true);
    try {
      const res = await window.axios.post(route('case-issues.quick'), { name });
      const newIssue = res.data;
      setLocalIssues((prev) => [...prev, newIssue]);
      setCaseIssueId(newIssue.id);
      setNewIssueName('');
      setShowAddIssue(false);
      setFormError('');
    } catch (err) {
      setFormError(err.response?.data?.errors?.name?.[0] || 'Failed to add issue.');
    } finally {
      setAddingIssue(false);
    }
  }

  function toggleCategory(id) {
    const sid = String(id);
    setCategoryIds((prev) => (prev.includes(sid) ? prev.filter((x) => x !== sid) : [...prev, sid]));
    setFormError('');
  }

  function handleApprove() {
    if (needsClassification) {
      if (categoryIds.length === 0) {
        setFormError('Pick at least one category before approving.');
        return;
      }
      if (!caseIssueId) {
        setFormError('Pick an issue or concern before approving.');
        return;
      }
    }
    setProcessing(true);
    router.post(
      safeRoute('cases.review-approve', caseFile.id, `/cases/${caseFile.id}/review-approve`),
      needsClassification ? { category_ids: categoryIds, case_issue_id: caseIssueId } : {},
      {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onFinish: () => setProcessing(false),
      },
    );
  }

  function handleRejectConfirm() {
    setProcessing(true);
    router.post(
      safeRoute('cases.review-reject', caseFile.id, `/cases/${caseFile.id}/review-reject`),
      { deletion_reason: rejectReason.trim() },
      {
        preserveScroll: true,
        onSuccess: () => {
          setRejectOpen(false);
          onClose();
        },
        onError: (errors) => {
          setFormError(errors?.deletion_reason || 'Could not reject this case.');
        },
        onFinish: () => setProcessing(false),
      },
    );
  }

  function handleDocUpload() {
    if (!docFile) return;
    setUploading(true);
    router.post(
      safeRoute('cases.documents.store', caseFile.id, `/cases/${caseFile.id}/documents`),
      { file: docFile },
      {
        preserveScroll: true,
        onSuccess: () => setDocFile(null),
        onFinish: () => setUploading(false),
      },
    );
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" onClick={onClose}>
      <div
        className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg owb-modal-animate"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={`Review case ${caseFile.case_number || ''}`}
      >
        <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h2 className="text-[16px] font-extrabold text-slate-900">Review case request</h2>
            <p className="mt-0.5 text-[12px] text-slate-500">
              {clientName(caseFile)} · Submitted {formatDisplayDate(caseFile.created_at)}
            </p>
          </div>
          <button type="button" onClick={onClose} aria-label="Close review" className="rounded-md p-1.5 text-slate-400 transition-colors hover:text-slate-600">
            <span className="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <div className="space-y-4 px-5 py-4">
          <div className="rounded-md border border-slate-200 bg-slate-50 p-3">
            <p className="text-[11px] font-bold uppercase tracking-widest text-slate-500">Full summary</p>
            <p className="mt-1 whitespace-pre-wrap text-[13px] leading-relaxed text-slate-700">{caseFile.summary || 'No summary provided.'}</p>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="rounded-md border border-slate-200 p-3">
              <p className="text-[11px] font-bold uppercase tracking-widest text-slate-500">Client</p>
              <p className="mt-1 text-[13px] font-semibold text-slate-800">{clientName(caseFile)}</p>
              <p className="text-[12px] text-slate-500">{client.email || 'No email'} · {client.contact_number || 'No contact number'}</p>
            </div>
            <div className="rounded-md border border-slate-200 p-3">
              <p className="text-[11px] font-bold uppercase tracking-widest text-slate-500">Vulnerability</p>
              <p className="mt-1 text-[13px] font-semibold text-slate-800">{vuln}</p>
              <p className="mt-1 text-[12px] text-slate-500">Case {caseFile.case_number || '—'} · Tracking {caseFile.tracker_number || '—'}</p>
            </div>
          </div>

          {needsClassification ? (
            <div className="rounded-md border border-amber-200 bg-amber-50 p-3">
              <p className="text-[11px] font-bold uppercase tracking-widest text-amber-800">Classify before approving</p>
              <p className="mt-0.5 text-[12px] text-amber-700">This request came from online intake, so it has no category yet. A category and an issue are required to approve.</p>
              <div className="mt-3">
                <p className="mb-1.5 text-[11px] font-bold uppercase tracking-widest text-slate-600">Categories *</p>
                {categories.length === 0 ? (
                  <p className="text-[12px] text-slate-500">No categories available. Ask an admin to add some first.</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {categories.map((cat) => {
                      const checked = categoryIds.includes(String(cat.id));
                      return (
                        <label key={cat.id} className={`inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-[12px] font-semibold transition-colors ${checked ? 'border-blue-900 bg-blue-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'}`}>
                          <input type="checkbox" className="sr-only" checked={checked} onChange={() => toggleCategory(cat.id)} />
                          {cat.color && <span className="h-2 w-2 rounded-full" style={{ backgroundColor: cat.color }} />}
                          {cat.name}
                        </label>
                      );
                    })}
                  </div>
                )}
              </div>
              <div className="mt-3">
                <label htmlFor="review-case-issue" className="mb-1.5 block text-[11px] font-bold uppercase tracking-widest text-slate-600">Issue / concern *</label>
                <div className="flex gap-2">
                  <select
                    id="review-case-issue"
                    value={caseIssueId}
                    onChange={(e) => { setCaseIssueId(e.target.value); setFormError(''); }}
                    className="h-10 min-w-0 flex-1 rounded-md border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                  >
                    <option value="">Select an issue…</option>
                    {localIssues.map((issue) => (
                      <option key={issue.id} value={issue.id}>{issue.name}</option>
                    ))}
                  </select>
                  <button
                    type="button"
                    onClick={() => { setNewIssueName(''); setFormError(''); setShowAddIssue(!showAddIssue); }}
                    className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-dashed border-blue-900/50 text-blue-900 transition-colors hover:bg-blue-50"
                    title="Add new issue"
                    aria-label="Add new issue"
                    aria-expanded={showAddIssue}
                  >
                    <span className="material-symbols-outlined text-[20px]">add</span>
                  </button>
                </div>
                {showAddIssue && (
                  <div className="mt-2 rounded-md border border-slate-300 bg-slate-50 p-3">
                    <label htmlFor="review-new-issue" className="mb-1.5 block text-[11px] font-bold uppercase tracking-widest text-slate-600">New issue name</label>
                    <input
                      id="review-new-issue"
                      type="text"
                      value={newIssueName}
                      onChange={(e) => setNewIssueName(e.target.value)}
                      onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleQuickAddIssue(); } }}
                      placeholder="Enter new issue name..."
                      className="h-10 w-full rounded-md border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                      autoFocus
                    />
                    <div className="mt-2 flex items-center gap-2">
                      <button
                        type="button"
                        onClick={handleQuickAddIssue}
                        disabled={addingIssue || !newIssueName.trim()}
                        className="inline-flex items-center gap-1.5 rounded-md bg-blue-900 px-3 py-1.5 text-[12px] font-bold text-white transition-colors hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                      >
                        {addingIssue ? 'Adding…' : 'Add'}
                      </button>
                      <button
                        type="button"
                        onClick={() => { setShowAddIssue(false); setNewIssueName(''); }}
                        className="text-[12px] font-medium text-slate-500 hover:text-slate-700"
                      >
                        Cancel
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          ) : (
            <div className="flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2.5">
              <span className="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
              <p className="text-[12px] text-emerald-800">This case already has a category, so it can be approved as is.</p>
            </div>
          )}

          {formError && <p className="text-[12px] font-semibold text-red-600">{formError}</p>}

          <div className="rounded-md border border-slate-200 p-3">
            <p className="text-[11px] font-bold uppercase tracking-widest text-slate-500">Attach a supporting document</p>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <input
                type="file"
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                onChange={(e) => setDocFile(e.target.files?.[0] || null)}
                className="max-w-full text-[12px] text-slate-600"
              />
              <button
                type="button"
                disabled={!docFile || uploading}
                onClick={handleDocUpload}
                className="inline-flex min-h-[32px] items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-[12px] font-bold text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <span className="material-symbols-outlined text-[15px]">upload</span>
                {uploading ? 'Uploading…' : 'Attach file'}
              </button>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-5 py-3">
          <button
            type="button"
            onClick={() => setRejectOpen(true)}
            className="inline-flex min-h-[36px] items-center gap-1.5 rounded-md border border-red-200 bg-white px-4 text-[12px] font-bold text-red-700 transition-colors hover:bg-red-50"
          >
            <span className="material-symbols-outlined text-[15px]">cancel</span>
            Reject
          </button>
          <button
            type="button"
            onClick={handleApprove}
            disabled={processing}
            className="inline-flex min-h-[36px] items-center gap-1.5 rounded-md bg-blue-900 px-4 text-[12px] font-bold text-white transition-colors hover:bg-blue-800 disabled:opacity-60"
          >
            <span className="material-symbols-outlined text-[15px]">check_circle</span>
            {processing ? 'Working…' : 'Approve case'}
          </button>
        </div>
      </div>

      <ConfirmDialog
        open={rejectOpen}
        title="Reject case request"
        confirmLabel="Reject case"
        cancelLabel="Keep reviewing"
        tone="danger"
        onConfirm={handleRejectConfirm}
        onCancel={() => setRejectOpen(false)}
      >
        <p className="text-[13px] leading-relaxed text-slate-600">
          Reject the case request from {clientName(caseFile)}? Give a short reason so the record is clear.
        </p>
        <label htmlFor="review-reject-reason" className="mb-1.5 mt-3 block text-[11px] font-bold uppercase tracking-widest text-slate-600">
          Reason for rejection
        </label>
        <textarea
          id="review-reject-reason"
          rows={3}
          value={rejectReason}
          onChange={(e) => setRejectReason(e.target.value)}
          placeholder="Tell us briefly why this request cannot be approved…"
          className="w-full rounded-md border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
        />
      </ConfirmDialog>
    </div>
  );
}

export default function ReviewPending({ cases, filters, sort = 'created_at', direction = 'asc', categories = [], caseIssues = [] }) {
  usePage(); // keep Inertia page context subscribed for flash toasts
  const [selected, setSelected] = useState(null);
  const rows = useMemo(() => cases?.data ?? [], [cases]);

  function goToPage(page) {
    router.get(safeRoute('cases.review-pending', undefined, '/cases/review-pending'), { ...(filters || {}), page }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  }

  function toggleDirection() {
    router.get(safeRoute('cases.review-pending', undefined, '/cases/review-pending'), { ...(filters || {}), direction: direction === 'asc' ? 'desc' : 'asc' }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  }

  return (
    <AppLayout title="Review Pending Cases">
      <Head title="Review Pending Cases" />

      <PageHeader
        eyebrow="Case review"
        title="Cases waiting for review"
        subtitle="Check requests sent through online intake, add a category, then approve or reject."
      />

      <SectionCard
        title={`Pending requests (${cases?.total ?? 0})`}
        action={(
          <div className="flex items-center gap-2">
            <Link
              href={safeRoute('cases.index', undefined, '/cases')}
              className="inline-flex min-h-[32px] items-center gap-1 rounded-md border border-slate-200 bg-white px-3 text-[12px] font-bold text-slate-600 transition-colors hover:bg-slate-50"
            >
              <MaterialSymbol name="folder" className="text-[15px]" />
              All cases
            </Link>
            <button
              type="button"
              onClick={toggleDirection}
              className="inline-flex min-h-[32px] items-center gap-1 rounded-md border border-slate-200 bg-white px-3 text-[12px] font-bold text-slate-600 transition-colors hover:bg-slate-50"
              title={`Currently oldest ${direction === 'asc' ? 'first' : 'last'}`}
            >
              <MaterialSymbol name={direction === 'asc' ? 'arrow_upward' : 'arrow_downward'} className="text-[15px]" />
              {direction === 'asc' ? 'Oldest first' : 'Newest first'}
            </button>
          </div>
        )}
      >
        {rows.length === 0 ? (
          <div className="flex flex-col items-center gap-2 px-4 py-12 text-center">
            <span className="material-symbols-outlined text-[40px] text-slate-300">inbox</span>
            <p className="text-[14px] font-bold text-slate-700">Nothing waiting for review</p>
            <p className="max-w-sm text-[12px] text-slate-500">New requests sent through online intake will show up here for approval.</p>
            <Link href={safeRoute('cases.index', undefined, '/cases')} className="mt-2 text-[12px] font-bold text-blue-900 hover:underline">
              Back to all cases
            </Link>
          </div>
        ) : (
          <ul className="divide-y divide-slate-100">
            {rows.map((row) => {
              const ready = caseHasCategories(row);
              const vuln = row.vulnerability_indicator || 'None';
              return (
                <li key={row.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="truncate text-[14px] font-bold text-slate-900">{clientName(row)}</p>
                      <StatusBadge status={row.status || 'PENDING_REVIEW'} size="sm" />
                      {vuln !== 'None' ? (
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold leading-none ${vulnStyles[vuln] || 'bg-slate-100 text-slate-700'}`}>
                          {vuln}
                        </span>
                      ) : null}
                      <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold leading-none ${ready ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>
                        <span className="material-symbols-outlined text-[12px]">{ready ? 'category' : 'warning'}</span>
                        {ready ? 'Has category' : 'Needs category'}
                      </span>
                    </div>
                    <p className="mt-1 line-clamp-2 text-[12px] leading-5 text-slate-500">{excerpt(row.summary)}</p>
                    <p className="mt-1 text-[11px] text-slate-400">
                      Case {row.case_number || '—'} · Submitted {formatDisplayDate(row.created_at)} {formatDisplayTime(row.created_at)}
                    </p>
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setSelected(row)}
                      className="inline-flex min-h-[34px] items-center gap-1.5 rounded-md bg-blue-900 px-4 text-[12px] font-bold text-white transition-colors hover:bg-blue-800"
                    >
                      <span className="material-symbols-outlined text-[15px]">rate_review</span>
                      Review
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>
        )}

        {cases && cases.last_page > 1 && (
          <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4">
            <p className="text-[12px] text-slate-500">
              Showing {cases.from}–{cases.to} of {cases.total}
            </p>
            <div className="flex items-center gap-1.5">
              <button
                type="button"
                disabled={cases.current_page <= 1}
                onClick={() => goToPage(cases.current_page - 1)}
                className="min-h-[32px] rounded-md border border-slate-200 bg-white px-3 text-[12px] font-bold text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Previous
              </button>
              <span className="px-1 text-[12px] font-semibold text-slate-500">
                Page {cases.current_page} of {cases.last_page}
              </span>
              <button
                type="button"
                disabled={cases.current_page >= cases.last_page}
                onClick={() => goToPage(cases.current_page + 1)}
                className="min-h-[32px] rounded-md border border-slate-200 bg-white px-3 text-[12px] font-bold text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </SectionCard>

      {selected && (
        <ReviewDetail
          caseFile={selected}
          categories={categories}
          caseIssues={caseIssues}
          onClose={() => setSelected(null)}
        />
      )}
    </AppLayout>
  );
}
