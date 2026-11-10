import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { PageHeader, SectionCard, MaterialSymbol } from '@/Components/Dashboard/primitives';
import StatusBadge from '@/Components/ui/StatusBadge';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import InputError from '@/Components/InputError';
import { ASSESSMENT_LABELS, DOCUMENT_SLOT_LABELS, RETURN_REASON_LABELS, requiredReasonSlot } from '@/Components/CaseIntakeForm';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import safeRoute from '@/utils/safeRoute';

const vulnStyles = {
  PWD: 'bg-purple-100 text-purple-800',
  'Senior Citizen': 'bg-orange-100 text-orange-800',
  'Solo Parent': 'bg-pink-100 text-pink-800',
  'Indigenous Person': 'bg-teal-100 text-teal-800',
};

function caseName(row) {
  return [row.first_name, row.middle_name, row.last_name, row.suffix].filter(Boolean).join(' ') || 'Unnamed OFW';
}

function addressOf(row) {
  return [row.address_street, row.address_barangay, row.address_city_municipality, row.address_province, row.address_region].filter(Boolean).join(', ');
}

function employmentOf(row) {
  return [
    [row.employment_position, row.employment_employer_name].filter(Boolean).join(' at '),
    row.employment_country,
  ].filter(Boolean).join(' · ');
}

function excerpt(text, len = 140) {
  if (!text) return 'No statement provided.';
  return text.length > len ? `${text.slice(0, len).trim()}…` : text;
}

function InfoCard({ title, children }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 className="mb-3 text-sm font-bold text-slate-900">{title}</h3>
      {children}
    </section>
  );
}

function InfoGrid({ fields }) {
  return (
    <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {fields.map(([label, value]) => (
        <div key={label} className="min-w-0">
          <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
          <dd className="mt-1 break-words text-[13px] text-slate-800">{value || 'Not provided'}</dd>
        </div>
      ))}
    </dl>
  );
}

function ReviewDetail({ caseFile, assessmentOptions = [], onClose }) {
  const [confirmed, setConfirmed] = useState(caseFile.confirmed_assessments ?? []);
  const [confirmedOther, setConfirmedOther] = useState(caseFile.confirmed_assessment_other ?? '');
  const [staffSummary, setStaffSummary] = useState(caseFile.staff_summary ?? '');
  const [rejectReason, setRejectReason] = useState('');
  const [rejectOpen, setRejectOpen] = useState(false);
  const [processing, setProcessing] = useState(false);

  const documents = useMemo(() => (caseFile.documents ?? []).filter((doc) => !doc.referral_id), [caseFile.documents]);
  const [reviewedIds, setReviewedIds] = useState(caseFile.reviewed_document_ids ?? []);
  const requiredSlots = ['passport_front_page', 'arrival_stamp', requiredReasonSlot(caseFile.return_reason)];
  const missingSlots = requiredSlots.filter((slot) => !documents.some((doc) => doc.category === slot));
  const hasEmploymentEvidence = documents.some((doc) => ['employment_contract', 'oec', 'work_visa'].includes(doc.category));
  const needsReplacement = (caseFile.requested_document_slots ?? []).length > 0;

  const [requestSlots, setRequestSlots] = useState([]);
  const [requestReason, setRequestReason] = useState('');

  function toggleAssessment(value) {
    setConfirmed((prev) => (prev.includes(value) ? prev.filter((item) => item !== value) : [...prev, value]));
  }

  function toggleReviewed(id) {
    setReviewedIds((prev) => (prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]));
  }

  function handleApprove() {
    if (confirmed.length === 0) return;
    if (needsReplacement) return;
    setProcessing(true);
    router.post(
      safeRoute('cases.review-approve', caseFile.id, `/cases/${caseFile.id}/review-approve`),
      {
        confirmed_assessments: confirmed,
        confirmed_assessment_other: confirmed.includes('OTHER') ? confirmedOther.trim() : '',
        staff_summary: staffSummary.trim(),
        reviewed_document_ids: reviewedIds,
      },
      {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onFinish: () => setProcessing(false),
      },
    );
  }

  function handleRequestReplacement() {
    if (requestSlots.length === 0 || !requestReason.trim()) return;
    setProcessing(true);
    router.post(
      safeRoute('cases.document-requests.store', caseFile.id, `/cases/${caseFile.id}/document-requests`),
      { slots: requestSlots, reason: requestReason.trim() },
      {
        preserveScroll: true,
        onSuccess: () => { setRequestSlots([]); setRequestReason(''); onClose(); },
        onFinish: () => setProcessing(false),
      },
    );
  }

  function handleRejectConfirm() {
    if (!rejectReason.trim()) return;
    setProcessing(true);
    router.post(
      safeRoute('cases.review-reject', caseFile.id, `/cases/${caseFile.id}/review-reject`),
      { reason: rejectReason.trim() },
      {
        preserveScroll: true,
        onSuccess: () => { setRejectOpen(false); onClose(); },
        onFinish: () => setProcessing(false),
      },
    );
  }

  const canApprove = confirmed.length > 0 && !needsReplacement && reviewedIds.length > 0 && (!confirmed.includes('OTHER') || confirmedOther.trim());

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" onClick={onClose}>
      <div
        className="max-h-[94vh] w-full max-w-6xl overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 shadow-2xl owb-modal-animate"
        onClick={(event) => event.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={`Review case ${caseFile.case_number || ''}`}
      >
        <div className="flex items-start justify-between border-b border-slate-200 bg-white px-6 py-5">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-blue-900">Case review</p>
            <h2 className="mt-1 text-xl font-extrabold text-slate-950">Review submitted intake</h2>
            <p className="mt-1 text-[13px] text-slate-500">{caseName(caseFile)} · Submitted {formatDisplayDate(caseFile.created_at)}</p>
          </div>
          <button type="button" onClick={onClose} aria-label="Close review" className="rounded-md p-1.5 text-slate-400 transition-colors hover:text-slate-600">
            <span className="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <div className="grid grid-cols-1 gap-4 px-6 py-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
          <div className="space-y-4">
            <InfoCard title="OFW details">
              <InfoGrid fields={[
                ['Case number', caseFile.case_number],
                ['Client type', caseFile.client_type],
                ['Date of birth', caseFile.date_of_birth],
                ['Age at intake', caseFile.age_at_intake],
                ['Sex', caseFile.sex],
                ['Email', caseFile.email],
                ['Contact number', caseFile.contact_number],
                ['Civil status', caseFile.civil_status === 'OTHER' ? caseFile.civil_status_other : caseFile.civil_status],
                ['Vulnerability', caseFile.vulnerability_indicator],
              ]} />
            </InfoCard>
            <InfoCard title="Address">
              <InfoGrid fields={[['Home address', addressOf(caseFile)]]} />
            </InfoCard>
            <InfoCard title="Employment">
              <InfoGrid fields={[
                ['Employer', caseFile.employment_employer_name],
                ['Employer address', caseFile.employment_employer_address],
                ['Position', caseFile.employment_position],
                ['Jobsite country', caseFile.employment_country],
                ['Last occupation / country', [caseFile.employment_last_position, caseFile.employment_last_country].filter(Boolean).join(' · ')],
                ['Employment dates', [caseFile.employment_start_date, caseFile.employment_end_date].filter(Boolean).join(' → ')],
                ['Arrival in PH', caseFile.employment_date_of_arrival],
                ['Deployment / status', [caseFile.deployment_mode, caseFile.employment_status].filter(Boolean).join(' · ')],
              ]} />
            </InfoCard>
            {caseFile.client_type === 'NEXT_OF_KIN' && (
              <InfoCard title="Next of kin">
                <InfoGrid fields={[
                  ['Name', caseFile.nok_name],
                  ['Relationship', caseFile.nok_relationship],
                  ['Contact number', caseFile.nok_contact_number],
                  ['Email', caseFile.nok_email],
                ]} />
              </InfoCard>
            )}
            <InfoCard title="Statements">
              <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">OFW statement</p>
              <p className="mt-1 whitespace-pre-wrap text-[13px] leading-relaxed text-slate-800">{caseFile.summary || 'Not provided'}</p>
              {caseFile.staff_summary && (
                <>
                  <p className="mt-4 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Staff summary</p>
                  <p className="mt-1 whitespace-pre-wrap text-[13px] leading-relaxed text-slate-800">{caseFile.staff_summary}</p>
                </>
              )}
            </InfoCard>
            <InfoCard title="Proposed assistance (OFW)">
              {caseFile.proposed_assessments?.length ? (
                <ul className="list-disc space-y-1 pl-5 text-[13px] text-slate-800">
                  {caseFile.proposed_assessments.map((value) => <li key={value}>{ASSESSMENT_LABELS[value] ?? value}</li>)}
                </ul>
              ) : <p className="text-[13px] text-slate-500">No assistance selected.</p>}
              {caseFile.proposed_assessment_other && <p className="mt-2 text-[13px] text-slate-700">Other: {caseFile.proposed_assessment_other}</p>}
            </InfoCard>
            <InfoCard title="Consent and return reason">
              <InfoGrid fields={[
                ['Return reason', RETURN_REASON_LABELS[caseFile.return_reason] ?? caseFile.return_reason],
                ['Consent version', caseFile.consent_text_version],
                ['Consent method', caseFile.consent_method],
                ['Consent evidence', caseFile.consent_evidence],
              ]} />
              {caseFile.missing_document_reasons && Object.keys(caseFile.missing_document_reasons).length > 0 && (
                <div className="mt-3 rounded-md bg-amber-50 px-3 py-2 text-[12px] text-amber-800">
                  Missing-document reasons recorded. Missing documents still block approval.
                </div>
              )}
            </InfoCard>
          </div>

          <div className="space-y-4 lg:sticky lg:top-0 lg:self-start">
            <InfoCard title="Confirm assessment">
              <div className="space-y-2">
                {assessmentOptions.map((value) => (
                  <label key={value} className="flex items-center gap-2 text-[13px] text-slate-700">
                    <input type="checkbox" checked={confirmed.includes(value)} onChange={() => toggleAssessment(value)} className="h-4 w-4 rounded border-slate-300 text-blue-900 focus:ring-blue-900" />
                    {ASSESSMENT_LABELS[value] ?? value}
                  </label>
                ))}
              </div>
              {confirmed.includes('OTHER') && (
                <div className="mt-3">
                  <label htmlFor="review-confirmed-other" className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-600">Other explanation *</label>
                  <input id="review-confirmed-other" value={confirmedOther} onChange={(event) => setConfirmedOther(event.target.value)} maxLength={2000} className="h-10 w-full rounded-md border border-slate-300 px-3 text-[13px]" />
                  <InputError message={!confirmedOther.trim() ? 'Explain the Other assessment.' : ''} className="mt-1" />
                </div>
              )}
              {confirmed.length === 0 && <p className="mt-2 text-[12px] text-amber-700">Select at least one confirmed assessment before approving.</p>}
              <div className="mt-3">
                <label htmlFor="review-staff-summary" className="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-600">Staff summary</label>
                <textarea id="review-staff-summary" rows={3} maxLength={5000} value={staffSummary} onChange={(event) => setStaffSummary(event.target.value)} className="w-full rounded-md border border-slate-300 px-3 py-2 text-[13px]" />
              </div>
            </InfoCard>

            <InfoCard title="Document review">
              {documents.length === 0 ? (
                <p className="text-[12px] text-amber-700">No intake documents uploaded.</p>
              ) : (
                <ul className="space-y-2">
                  {documents.map((doc) => (
                    <li key={doc.id} className="flex items-start gap-2 text-[12px]">
                      <input id={`review-doc-${doc.id}`} type="checkbox" checked={reviewedIds.includes(doc.id)} onChange={() => toggleReviewed(doc.id)} className="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-900 focus:ring-blue-900" />
                      <label htmlFor={`review-doc-${doc.id}`} className="min-w-0 flex-1">
                        <span className="block font-semibold text-slate-800">{DOCUMENT_SLOT_LABELS[doc.category] ?? doc.category}</span>
                        <a href={safeRoute('cases.documents.download', { case: caseFile.id, document: doc.id }, `/cases/${caseFile.id}/documents/${doc.id}/download`)} className="block truncate text-blue-900 hover:underline">
                          {doc.file_name ?? doc.original_name ?? 'View document'}
                        </a>
                      </label>
                    </li>
                  ))}
                </ul>
              )}
              {(!hasEmploymentEvidence || missingSlots.length > 0) && (
                <p className="mt-3 rounded-md bg-amber-50 px-3 py-2 text-[12px] text-amber-800">
                  Required documents are still missing: {[...missingSlots, ...(hasEmploymentEvidence ? [] : ['employment_evidence'])].map((slot) => DOCUMENT_SLOT_LABELS[slot] ?? slot).join(', ')}.
                </p>
              )}
              {needsReplacement && <p className="mt-3 rounded-md bg-red-50 px-3 py-2 text-[12px] text-red-700">A replacement request is open. Approval is blocked until the requested documents are supplied and reviewed.</p>}
              <InputError message={confirmed.length > 0 && reviewedIds.length === 0 ? 'Tick each reviewed document before approving.' : ''} className="mt-2" />

              <details className="mt-4 rounded-md border border-slate-200 p-3">
                <summary className="cursor-pointer text-[12px] font-bold text-slate-700">Request document replacement</summary>
                <div className="mt-3 space-y-2">
                  {Object.entries(DOCUMENT_SLOT_LABELS).map(([slot, label]) => (
                    <label key={slot} className="flex items-center gap-2 text-[12px] text-slate-700">
                      <input type="checkbox" checked={requestSlots.includes(slot)} onChange={() => setRequestSlots((prev) => (prev.includes(slot) ? prev.filter((item) => item !== slot) : [...prev, slot]))} className="h-4 w-4 rounded border-slate-300 text-blue-900 focus:ring-blue-900" />
                      {label}
                    </label>
                  ))}
                  <textarea rows={2} maxLength={1000} value={requestReason} onChange={(event) => setRequestReason(event.target.value)} placeholder="Reason for the replacement request" className="w-full rounded-md border border-slate-300 px-3 py-2 text-[12px]" />
                  <button type="button" onClick={handleRequestReplacement} disabled={processing || requestSlots.length === 0 || !requestReason.trim()} className="inline-flex min-h-[34px] items-center rounded-md bg-amber-600 px-3 text-[12px] font-bold text-white disabled:opacity-50">
                    Send replacement request
                  </button>
                </div>
              </details>
            </InfoCard>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4">
          <button type="button" onClick={() => setRejectOpen(true)} className="inline-flex min-h-[36px] items-center gap-1.5 rounded-md border border-red-200 bg-white px-4 text-[12px] font-bold text-red-700 transition-colors hover:bg-red-50">
            <span className="material-symbols-outlined text-[15px]">cancel</span>
            Reject
          </button>
          <button type="button" onClick={handleApprove} disabled={processing || !canApprove} className="inline-flex min-h-[36px] items-center gap-1.5 rounded-md bg-blue-900 px-4 text-[12px] font-bold text-white transition-colors hover:bg-blue-800 disabled:opacity-50">
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
        <p className="text-[13px] leading-relaxed text-slate-600">Reject the case request from {caseName(caseFile)}? Give a short reason so the record is clear.</p>
        <label htmlFor="review-reject-reason" className="mb-1.5 mt-3 block text-[11px] font-bold uppercase tracking-widest text-slate-600">Reason for rejection</label>
        <textarea id="review-reject-reason" rows={3} value={rejectReason} onChange={(event) => setRejectReason(event.target.value)} className="w-full rounded-md border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900" />
        {!rejectReason.trim() && <p className="mt-1 text-[11px] text-slate-500">Reason is required.</p>}
      </ConfirmDialog>
    </div>
  );
}

export default function ReviewPending({ cases, filters, sort = 'created_at', direction = 'asc', assessmentOptions = [] }) {
  usePage();
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
        subtitle="Check the submitted intake and documents, confirm the assessment, then approve or reject."
      />

      <SectionCard
        title={`Pending requests (${cases?.total ?? 0})`}
        action={(
          <div className="flex items-center gap-2">
            <Link href={safeRoute('cases.index', undefined, '/cases')} className="inline-flex min-h-[32px] items-center gap-1 rounded-md border border-slate-200 bg-white px-3 text-[12px] font-bold text-slate-600 transition-colors hover:bg-slate-50">
              <MaterialSymbol name="folder" className="text-[15px]" />
              All cases
            </Link>
            <button type="button" onClick={toggleDirection} className="inline-flex min-h-[32px] items-center gap-1 rounded-md border border-slate-200 bg-white px-3 text-[12px] font-bold text-slate-600 transition-colors hover:bg-slate-50" title={`Currently oldest ${direction === 'asc' ? 'first' : 'last'}`}>
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
            <Link href={safeRoute('cases.index', undefined, '/cases')} className="mt-2 text-[12px] font-bold text-blue-900 hover:underline">Back to all cases</Link>
          </div>
        ) : (
          <ul className="divide-y divide-slate-100">
            {rows.map((row) => {
              const vuln = row.vulnerability_indicator || 'None';
              return (
                <li key={row.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="truncate text-[14px] font-bold text-slate-900">{caseName(row)}</p>
                      <StatusBadge status={row.status || 'PENDING_REVIEW'} size="sm" />
                      {vuln !== 'None' && (
                        <span className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold leading-none ${vulnStyles[vuln] || 'bg-slate-100 text-slate-700'}`}>{vuln}</span>
                      )}
                    </div>
                    <p className="mt-1 line-clamp-2 text-[12px] leading-5 text-slate-500">{excerpt(row.summary)}</p>
                    <p className="mt-1 text-[11px] text-slate-400">
                      Case {row.case_number || '—'} · {RETURN_REASON_LABELS[row.return_reason] ?? 'No return reason'} · Submitted {formatDisplayDate(row.created_at)} {formatDisplayTime(row.created_at)}
                    </p>
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    <button type="button" onClick={() => setSelected(row)} className="inline-flex min-h-[34px] items-center gap-1.5 rounded-md bg-blue-900 px-4 text-[12px] font-bold text-white transition-colors hover:bg-blue-800">
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
            <p className="text-[12px] text-slate-500">Showing {cases.from}–{cases.to} of {cases.total}</p>
            <div className="flex items-center gap-1.5">
              <button type="button" disabled={cases.current_page <= 1} onClick={() => goToPage(cases.current_page - 1)} className="min-h-[32px] rounded-md border border-slate-200 bg-white px-3 text-[12px] font-bold text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Previous</button>
              <span className="px-1 text-[12px] font-semibold text-slate-500">Page {cases.current_page} of {cases.last_page}</span>
              <button type="button" disabled={cases.current_page >= cases.last_page} onClick={() => goToPage(cases.current_page + 1)} className="min-h-[32px] rounded-md border border-slate-200 bg-white px-3 text-[12px] font-bold text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Next</button>
            </div>
          </div>
        )}
      </SectionCard>

      {selected && <ReviewDetail caseFile={selected} assessmentOptions={assessmentOptions} onClose={() => setSelected(null)} />}
    </AppLayout>
  );
}
