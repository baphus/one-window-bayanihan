import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import { useToast } from '@/Hooks/useToast';
import { formatDisplayDate } from '@/lib/utils';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import AddressDropdowns from '@/Components/AddressDropdowns';

const VULNERABILITY_OPTIONS = [
  'PWD',
  'Senior Citizen',
  'Solo Parent',
  'Indigenous Person',
];

const VULN_STYLES = {
  PWD: 'bg-purple-100 text-purple-800',
  'Senior Citizen': 'bg-orange-100 text-orange-800',
  'Solo Parent': 'bg-pink-100 text-pink-800',
  'Indigenous Person': 'bg-teal-100 text-amber-800',
};

const SUFFIX_OPTIONS = ['', 'Jr', 'Sr', 'II', 'III', 'IV', 'V'];
const SEX_OPTIONS = ['Male', 'Female'];

/* ── Helpers ──────────────────────────────────────────────────── */

function computeAge(dob) {
  if (!dob) return '—';
  const birth = new Date(dob);
  if (Number.isNaN(birth.getTime())) return '—';
  let age = new Date().getFullYear() - birth.getFullYear();
  const m = new Date().getMonth() - birth.getMonth();
  if (m < 0 || (m === 0 && new Date().getDate() < birth.getDate())) age--;
  return age;
}

function normalizeDateInput(value) {
  if (!value) return '';
  const match = String(value).match(/^\d{4}-\d{2}-\d{2}/);
  return match ? match[0] : '';
}

function formatAddress(resolved, raw) {
  const parts = [];
  if (resolved?.barangay) parts.push(resolved.barangay);
  if (resolved?.city_municipality) parts.push(resolved.city_municipality);
  if (resolved?.province) parts.push(resolved.province);
  if (resolved?.region) parts.push(resolved.region);
  if (parts.length > 0) return parts.join(', ');
  // Fallback: show raw PSGC codes
  const rawParts = [];
  if (raw?.barangay) rawParts.push(raw.barangay);
  if (raw?.city_municipality) rawParts.push(raw.city_municipality);
  if (raw?.province) rawParts.push(raw.province);
  if (raw?.region) rawParts.push(raw.region);
  return rawParts.length > 0 ? rawParts.join(', ') : '—';
}

/* ── CategoryCheckboxDropdown ─────────────────────────────────── */

function CategoryCheckboxDropdown({ categories, selectedIds, onChange, error }) {
  const [open, setOpen] = useState(false);
  const triggerRef = useRef(null);
  const panelRef = useRef(null);
  const listboxId = 'review-category-listbox';

  useEffect(() => {
    if (!open) return;
    function handlePointerDown(e) {
      if (
        panelRef.current && !panelRef.current.contains(e.target) &&
        triggerRef.current && !triggerRef.current.contains(e.target)
      ) {
        setOpen(false);
      }
    }
    function handleKeyDown(e) {
      if (e.key === 'Escape') {
        setOpen(false);
        triggerRef.current?.focus();
      }
    }
    document.addEventListener('mousedown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('mousedown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [open]);

  function toggle(id) {
    const sid = String(id);
    const next = selectedIds.includes(sid)
      ? selectedIds.filter((x) => x !== sid)
      : [...selectedIds, sid];
    onChange(next);
  }

  const count = selectedIds.length;
  let summary;
  if (count === 0) summary = 'Select categories…';
  else if (count <= 2) {
    summary = selectedIds
      .map((id) => categories.find((c) => String(c.id) === String(id))?.name || id)
      .join(', ');
  } else {
    summary = `${count} categories selected`;
  }

  return (
    <div className="relative">
      <button
        ref={triggerRef}
        type="button"
        role="combobox"
        aria-label="Case categories"
        aria-expanded={open}
        aria-controls={listboxId}
        aria-haspopup="listbox"
        onClick={() => setOpen((v) => !v)}
        className={`flex h-10 w-full items-center justify-between gap-2 rounded-[3px] border px-3 text-left text-[13px] outline-none transition-colors bg-white ${
          error
            ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
            : 'border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500'
        } ${count === 0 ? 'text-slate-400' : 'text-slate-700'}`}
      >
        <span className="truncate">{summary}</span>
        <svg
          className={`h-4 w-4 shrink-0 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`}
          viewBox="0 0 20 20"
          fill="currentColor"
          aria-hidden="true"
        >
          <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clipRule="evenodd" />
        </svg>
      </button>
      {open && (
        <div
          ref={panelRef}
          id={listboxId}
          role="listbox"
          aria-multiselectable="true"
          aria-label="Categories"
          className="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg focus:outline-none owb-scroll-wide"
        >
          {categories.map((cat) => {
            const checked = selectedIds.includes(String(cat.id));
            return (
              <div
                key={cat.id}
                role="option"
                aria-selected={checked}
                onClick={() => toggle(cat.id)}
                onKeyDown={(e) => {
                  if (e.key === ' ' || e.key === 'Enter') {
                    e.preventDefault();
                    toggle(cat.id);
                  }
                }}
                tabIndex={-1}
                className={`flex cursor-pointer items-center gap-2 px-3 py-2 text-[13px] transition-colors ${
                  checked ? 'bg-indigo-50 text-slate-900' : 'text-slate-700 hover:bg-slate-50'
                }`}
              >
                <div className={`flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors ${
                  checked ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300 bg-white'
                }`}>
                  {checked && (
                    <svg className="h-3 w-3 text-white" viewBox="0 0 12 12" fill="none">
                      <path d="M2.5 6L5 8.5L9.5 3.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                  )}
                </div>
                {cat.color && (
                  <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: cat.color }} />
                )}
                <span className="truncate">{cat.name}</span>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

/* ── FieldLabel ───────────────────────────────────────────────── */

function FieldLabel({ children }) {
  return (
    <span className="block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600 mb-1">
      {children}
    </span>
  );
}

/* ── SubSection ───────────────────────────────────────────────── */

function SubSection({ title, children }) {
  return (
    <div className="space-y-2.5">
      <h4 className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-700">{title}</h4>
      {children}
    </div>
  );
}

/* ── InfoRow ──────────────────────────────────────────────────── */

function InfoRow({ label, value }) {
  return (
    <div>
      <FieldLabel>{label}</FieldLabel>
      <p className="text-[13px] text-slate-700">{value || '—'}</p>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════
   MAIN PAGE COMPONENT
   ════════════════════════════════════════════════════════════════ */

export default function ReviewIntake({ case: caseFile, categories = [], caseIssues = [], draftResolvedAddress = {} }) {
  const toast = useToast();
  const draft = caseFile.draft_client_data || {};
  const address = draft.address || {};
  const employment = draft.employment || {};
  const nokRaw = draft.next_of_kin;

  // Normalise next_of_kin: could be array or single object
  const nextOfKin = useMemo(() => {
    if (!nokRaw) return [];
    if (Array.isArray(nokRaw)) return nokRaw;
    return [nokRaw];
  }, [nokRaw]);

  /* ── Edit state ────────────────────────────────────────────── */
  const [editingSection, setEditingSection] = useState(null);

  // Local copies of editable data (only populated when a section is active)
  const [editPersonal, setEditPersonal] = useState(null);
  const [editAddress, setEditAddress] = useState(null);
  const [editEmployment, setEditEmployment] = useState(null);
  const [editNokIndex, setEditNokIndex] = useState(null);
  const [editNokData, setEditNokData] = useState(null);
  const [editSummary, setEditSummary] = useState(null);

  // Classification state
  const [categoryIds, setCategoryIds] = useState(
    (caseFile.category_ids || []).map(String)
  );
  const [caseIssueId, setCaseIssueId] = useState(caseFile.case_issue_id || '');
  const [vulnerability, setVulnerability] = useState(
    Array.isArray(caseFile.vulnerability) ? caseFile.vulnerability : (caseFile.vulnerability ? [caseFile.vulnerability] : [])
  );

  // Reject state
  const [rejectOpen, setRejectOpen] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [rejecting, setRejecting] = useState(false);

  // Loading states
  const [savingSection, setSavingSection] = useState(false);
  const [publishing, setPublishing] = useState(false);

  /* ── Edit actions ──────────────────────────────────────────── */

  function openEditPersonal() {
    setEditPersonal({
      first_name: draft.first_name || '',
      last_name: draft.last_name || '',
      middle_initial: draft.middle_initial || '',
      suffix: draft.suffix || '',
      date_of_birth: normalizeDateInput(draft.date_of_birth),
      sex: draft.sex || 'Male',
      email: draft.email || '',
      contact_number: draft.contact_number || '',
    });
    setEditingSection('personal');
  }

  function openEditAddress() {
    setEditAddress({
      region: address.region || '',
      province: address.province || '',
      city_municipality: address.city_municipality || '',
      barangay: address.barangay || '',
    });
    setEditingSection('address');
  }

  function openEditEmployment() {
    setEditEmployment({
      employer_name: employment.employer_name || '',
      position: employment.position || '',
      country: employment.country || '',
      start_date: normalizeDateInput(employment.start_date),
      end_date: normalizeDateInput(employment.end_date),
      is_present: !!employment.is_present,
      last_country: employment.last_country || '',
      last_position: employment.last_position || '',
      date_of_arrival: normalizeDateInput(employment.date_of_arrival),
    });
    setEditingSection('employment');
  }

  function openEditNok(idx) {
    const nok = nextOfKin[idx] || {};
    setEditNokIndex(idx);
    setEditNokData({
      first_name: nok.first_name || '',
      last_name: nok.last_name || '',
      relationship: nok.relationship || '',
      contact_number: nok.contact_number || nok.phone_number || '',
      email: nok.email || '',
      address: {
        region: nok.address?.region || '',
        province: nok.address?.province || '',
        city_municipality: nok.address?.city_municipality || '',
        barangay: nok.address?.barangay || '',
      },
    });
    setEditingSection('nok');
  }

  function openEditSummary() {
    setEditSummary(draft.summary || caseFile.summary || '');
    setEditingSection('summary');
  }

  function cancelEdit() {
    setEditingSection(null);
    setEditPersonal(null);
    setEditAddress(null);
    setEditEmployment(null);
    setEditNokIndex(null);
    setEditNokData(null);
    setEditSummary(null);
  }

  /* ── Save a section ────────────────────────────────────────── */

  async function saveSection() {
    setSavingSection(true);
    try {
      let payload = {};

      if (editingSection === 'personal' && editPersonal) {
        payload = {
          first_name: editPersonal.first_name,
          last_name: editPersonal.last_name,
          middle_initial: editPersonal.middle_initial,
          suffix: editPersonal.suffix,
          date_of_birth: editPersonal.date_of_birth,
          sex: editPersonal.sex,
          email: editPersonal.email,
          contact_number: editPersonal.contact_number,
        };
        await window.axios.put(route('cases.save-draft', caseFile.id), payload);
        // Update local draft data
        Object.assign(draft, payload);
      } else if (editingSection === 'address' && editAddress) {
        payload = { address: editAddress };
        await window.axios.put(route('cases.save-draft', caseFile.id), payload);
        Object.assign(address, editAddress);
      } else if (editingSection === 'employment' && editEmployment) {
        payload = { employment: editEmployment };
        await window.axios.put(route('cases.save-draft', caseFile.id), payload);
        Object.assign(employment, editEmployment);
      } else if (editingSection === 'nok' && editNokData && editNokIndex !== null) {
        payload = { next_of_kin: nextOfKin.map((nok, i) => {
          if (i !== editNokIndex) return nok;
          return { ...nok, ...editNokData, contact_number: editNokData.contact_number, phone_number: editNokData.contact_number };
        }) };
        await window.axios.put(route('cases.save-draft', caseFile.id), payload);
        // Update the local nextOfKin mirror
        nextOfKin[editNokIndex] = { ...nextOfKin[editNokIndex], ...editNokData, contact_number: editNokData.contact_number, phone_number: editNokData.contact_number };
      } else if (editingSection === 'summary' && editSummary !== null) {
        payload = { summary: editSummary };
        await window.axios.put(route('cases.save-draft', caseFile.id), payload);
        draft.summary = editSummary;
      }

      cancelEdit();
      toast.success('Changes saved.');
    } catch (err) {
      const msg = Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Failed to save changes.';
      toast.error(msg);
    } finally {
      setSavingSection(false);
    }
  }

  /* ── Publish ───────────────────────────────────────────────── */

  async function handlePublish() {
    setPublishing(true);
    try {
      // 1. Save classification data first
      await window.axios.put(route('cases.save-draft', caseFile.id), {
        category_ids: categoryIds,
        case_issue_id: caseIssueId || null,
        vulnerability,
      });

      // 2. Publish
      await window.axios.post(route('cases.publish', caseFile.id));

      toast.success('Case published successfully');
      router.visit(route('cases.show', caseFile.id));
    } catch (err) {
      const msg = Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Failed to publish case.';
      toast.error(msg);
    } finally {
      setPublishing(false);
    }
  }

  /* ── Reject ────────────────────────────────────────────────── */

  function handleReject() {
    if (!rejectReason || rejectReason.length < 10) return;
    setRejecting(true);
    router.post(route('cases.reject-intake', caseFile.id), {
      deletion_reason: rejectReason,
    }, {
      onSuccess: () => {
        toast.success('Intake submission rejected');
        router.visit(route('cases.intake-queue'));
      },
      onError: (errors) => {
        const msg = errors?.deletion_reason || Object.values(errors)[0] || 'Failed to reject intake.';
        toast.error(msg);
      },
      onFinish: () => setRejecting(false),
    });
  }

  /* ── Computed values ───────────────────────────────────────── */

  const age = computeAge(draft.date_of_birth);
  const employmentPeriod = useMemo(() => {
    const start = employment.start_date ? formatDisplayDate(employment.start_date) : '';
    if (employment.is_present) return start ? `${start} – Present` : 'Present';
    const end = employment.end_date ? formatDisplayDate(employment.end_date) : '';
    if (start && end) return `${start} – ${end}`;
    if (start) return start;
    if (end) return end;
    return '—';
  }, [employment.start_date, employment.end_date, employment.is_present]);

  const resolvedAddress = useMemo(() => {
    return formatAddress(draftResolvedAddress, address);
  }, [draftResolvedAddress, address]);

  const canPublish = categoryIds.length > 0;

  /* ── Render ────────────────────────────────────────────────── */

  return (
    <AppLayout title="Review Intake">
      <Head title="Review Intake Submission" />

      <div className="pb-8 max-w-4xl mx-auto">
        {/* ── Page header ──────────────────────────────────── */}
        <header className="mb-6">
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">
            Review Intake Submission
          </h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">
            Self-filed OFW submission — review details and assign categories before publishing.
          </p>
          <div className="mt-3 flex items-center gap-3">
            <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-600">
              <span className="material-symbols-outlined text-[14px]">tag</span>
              {caseFile.case_number}
            </span>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-[11px] font-bold text-blue-800">
              <span className="material-symbols-outlined text-[14px]">person</span>
              {caseFile.client_type === 'OFW' ? 'OFW' : 'Next of Kin'}
            </span>
            <span className="text-[11px] text-slate-400">
              Submitted {caseFile.created_at ? formatDisplayDate(caseFile.created_at) : '—'}
            </span>
          </div>
        </header>

        {/* ═══ OFW SUBMISSION SECTION ══════════════════════════ */}
        <div className="space-y-4">
          {/* ── Personal Info ──────────────────────────────── */}
          <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
            <div className="px-5 py-4 bg-slate-50 border-b border-slate-300 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                  Personal Info
                </span>
              </div>
              {editingSection !== 'personal' && (
                <button onClick={openEditPersonal} className="text-[11px] font-bold text-blue-900 hover:text-blue-700 cursor-pointer inline-flex items-center gap-1">
                  <span className="material-symbols-outlined text-[14px]">edit</span>
                  Edit
                </button>
              )}
            </div>
            <div className="p-5">
              {editingSection === 'personal' && editPersonal ? (
                <div className="space-y-4">
                  <SubSection title="Name">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                      <div>
                        <FieldLabel>First Name *</FieldLabel>
                        <input type="text" value={editPersonal.first_name} onChange={(e) => setEditPersonal({ ...editPersonal, first_name: e.target.value })}
                          className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                      </div>
                      <div>
                        <FieldLabel>Middle Initial</FieldLabel>
                        <input type="text" value={editPersonal.middle_initial} onChange={(e) => setEditPersonal({ ...editPersonal, middle_initial: e.target.value })}
                          className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                      </div>
                      <div>
                        <FieldLabel>Last Name *</FieldLabel>
                        <input type="text" value={editPersonal.last_name} onChange={(e) => setEditPersonal({ ...editPersonal, last_name: e.target.value })}
                          className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                      </div>
                      <div>
                        <FieldLabel>Suffix</FieldLabel>
                        <select value={editPersonal.suffix} onChange={(e) => setEditPersonal({ ...editPersonal, suffix: e.target.value })}
                          className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                          {SUFFIX_OPTIONS.map((s) => <option key={s} value={s}>{s || '—'}</option>)}
                        </select>
                      </div>
                    </div>
                  </SubSection>
                  <SubSection title="Details">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div>
                        <FieldLabel>Date of Birth *</FieldLabel>
                        <input type="date" value={editPersonal.date_of_birth} onChange={(e) => setEditPersonal({ ...editPersonal, date_of_birth: e.target.value })}
                          className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                      </div>
                      <div>
                        <FieldLabel>Sex *</FieldLabel>
                        <select value={editPersonal.sex} onChange={(e) => setEditPersonal({ ...editPersonal, sex: e.target.value })}
                          className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                          {SEX_OPTIONS.map((s) => <option key={s} value={s}>{s}</option>)}
                        </select>
                      </div>
                      <div>
                        <FieldLabel>Email</FieldLabel>
                        <input type="email" value={editPersonal.email} onChange={(e) => setEditPersonal({ ...editPersonal, email: e.target.value })}
                          className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                      </div>
                      <div>
                        <FieldLabel>Contact Number</FieldLabel>
                        <input type="text" value={editPersonal.contact_number} onChange={(e) => setEditPersonal({ ...editPersonal, contact_number: e.target.value })}
                          className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                      </div>
                    </div>
                  </SubSection>
                  <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button onClick={cancelEdit} className="h-9 px-4 rounded-[3px] border border-slate-300 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                      Cancel
                    </button>
                    <button onClick={saveSection} disabled={savingSection || !editPersonal.first_name || !editPersonal.last_name}
                      className="h-9 px-4 rounded-[3px] bg-blue-900 text-[12px] font-bold text-white hover:bg-blue-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                      {savingSection ? 'Saving…' : 'Save'}
                    </button>
                  </div>
                </div>
              ) : (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-5">
                  <InfoRow label="Full Name" value={[draft.first_name, draft.middle_initial, draft.last_name, draft.suffix].filter(Boolean).join(' ')} />
                  <InfoRow label="Date of Birth" value={draft.date_of_birth ? `${formatDisplayDate(draft.date_of_birth)} (${age} yrs)` : '—'} />
                  <InfoRow label="Sex" value={draft.sex} />
                  <InfoRow label="Email" value={draft.email} />
                  <InfoRow label="Contact Number" value={draft.contact_number} />
                </div>
              )}
            </div>
          </div>

          {/* ── Address ────────────────────────────────────── */}
          <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
            <div className="px-5 py-4 bg-slate-50 border-b border-slate-300 flex items-center justify-between">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Address</span>
              {editingSection !== 'address' && (
                <button onClick={openEditAddress} className="text-[11px] font-bold text-blue-900 hover:text-blue-700 cursor-pointer inline-flex items-center gap-1">
                  <span className="material-symbols-outlined text-[14px]">edit</span>
                  Edit
                </button>
              )}
            </div>
            <div className="p-5">
              {editingSection === 'address' && editAddress ? (
                <div className="space-y-4">
                  <AddressDropdowns
                    values={editAddress}
                    onChange={(fieldOrObj, value) => {
                      if (typeof fieldOrObj === 'object') {
                        setEditAddress({ ...editAddress, ...fieldOrObj });
                      } else {
                        setEditAddress({ ...editAddress, [fieldOrObj]: value });
                      }
                    }}
                  />
                  <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button onClick={cancelEdit} className="h-9 px-4 rounded-[3px] border border-slate-300 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                      Cancel
                    </button>
                    <button onClick={saveSection} disabled={savingSection}
                      className="h-9 px-4 rounded-[3px] bg-blue-900 text-[12px] font-bold text-white hover:bg-blue-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                      {savingSection ? 'Saving…' : 'Save'}
                    </button>
                  </div>
                </div>
              ) : (
                <InfoRow label="Resolved Address" value={resolvedAddress} />
              )}
            </div>
          </div>

          {/* ── Employment ─────────────────────────────────── */}
          <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
            <div className="px-5 py-4 bg-slate-50 border-b border-slate-300 flex items-center justify-between">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Employment</span>
              {editingSection !== 'employment' && (
                <button onClick={openEditEmployment} className="text-[11px] font-bold text-blue-900 hover:text-blue-700 cursor-pointer inline-flex items-center gap-1">
                  <span className="material-symbols-outlined text-[14px]">edit</span>
                  Edit
                </button>
              )}
            </div>
            <div className="p-5">
              {editingSection === 'employment' && editEmployment ? (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <FieldLabel>Employer Name</FieldLabel>
                      <input type="text" value={editEmployment.employer_name} onChange={(e) => setEditEmployment({ ...editEmployment, employer_name: e.target.value })}
                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                      <FieldLabel>Position</FieldLabel>
                      <input type="text" value={editEmployment.position} onChange={(e) => setEditEmployment({ ...editEmployment, position: e.target.value })}
                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                      <FieldLabel>Country</FieldLabel>
                      <input type="text" value={editEmployment.country} onChange={(e) => setEditEmployment({ ...editEmployment, country: e.target.value })}
                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                      <FieldLabel>Start Date</FieldLabel>
                      <input type="date" value={editEmployment.start_date} onChange={(e) => setEditEmployment({ ...editEmployment, start_date: e.target.value })}
                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                      <FieldLabel>End Date</FieldLabel>
                      <input type="date" value={editEmployment.end_date} onChange={(e) => setEditEmployment({ ...editEmployment, end_date: e.target.value, is_present: !e.target.value })}
                        disabled={editEmployment.is_present}
                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400" />
                    </div>
                    <div>
                      <FieldLabel>Currently Employed?</FieldLabel>
                      <label className="flex items-center gap-2 h-10 cursor-pointer">
                        <input type="checkbox" checked={editEmployment.is_present}
                          onChange={(e) => setEditEmployment({ ...editEmployment, is_present: e.target.checked, end_date: e.target.checked ? '' : editEmployment.end_date })}
                          className="rounded border-slate-300 text-blue-900 focus:ring-blue-900 focus:ring-offset-0" />
                        <span className="text-[13px] text-slate-700">Present (end date unknown)</span>
                      </label>
                    </div>
                    <div>
                      <FieldLabel>Date of Arrival</FieldLabel>
                      <input type="date" value={editEmployment.date_of_arrival} onChange={(e) => setEditEmployment({ ...editEmployment, date_of_arrival: e.target.value })}
                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                      <FieldLabel>Last Country</FieldLabel>
                      <input type="text" value={editEmployment.last_country} onChange={(e) => setEditEmployment({ ...editEmployment, last_country: e.target.value })}
                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                    </div>
                    <div>
                      <FieldLabel>Last Position</FieldLabel>
                      <input type="text" value={editEmployment.last_position} onChange={(e) => setEditEmployment({ ...editEmployment, last_position: e.target.value })}
                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                    </div>
                  </div>
                  <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button onClick={cancelEdit} className="h-9 px-4 rounded-[3px] border border-slate-300 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                      Cancel
                    </button>
                    <button onClick={saveSection} disabled={savingSection}
                      className="h-9 px-4 rounded-[3px] bg-blue-900 text-[12px] font-bold text-white hover:bg-blue-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                      {savingSection ? 'Saving…' : 'Save'}
                    </button>
                  </div>
                </div>
              ) : (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-5">
                  <InfoRow label="Employer" value={employment.employer_name} />
                  <InfoRow label="Position" value={employment.position} />
                  <InfoRow label="Country" value={employment.country} />
                  <InfoRow label="Employment Period" value={employmentPeriod} />
                  <InfoRow label="Date of Arrival" value={employment.date_of_arrival ? formatDisplayDate(employment.date_of_arrival) : '—'} />
                  <InfoRow label="Last Country" value={employment.last_country} />
                  <InfoRow label="Last Position" value={employment.last_position} />
                </div>
              )}
            </div>
          </div>

          {/* ── Next of Kin ────────────────────────────────── */}
          <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
            <div className="px-5 py-4 bg-slate-50 border-b border-slate-300 flex items-center justify-between">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Next of Kin</span>
            </div>
            <div className="p-5">
              {nextOfKin.length === 0 ? (
                <p className="text-[13px] text-slate-400 italic">No next of kin provided.</p>
              ) : (
                <div className="space-y-5">
                  {nextOfKin.map((nok, idx) => {
                    const isEditing = editingSection === 'nok' && editNokIndex === idx;
                    return (
                      <div key={idx} className={`${idx > 0 ? 'pt-5 border-t border-slate-100' : ''}`}>
                        <div className="flex items-center justify-between mb-3">
                          <div className="flex items-center gap-2">
                            <span className="text-[11px] font-extrabold uppercase tracking-[0.1em] text-slate-500">
                              {nok.is_primary ? 'Primary NOK' : `NOK #${idx + 1}`}
                            </span>
                          </div>
                          {!isEditing && (
                            <button onClick={() => openEditNok(idx)} className="text-[11px] font-bold text-blue-900 hover:text-blue-700 cursor-pointer inline-flex items-center gap-1">
                              <span className="material-symbols-outlined text-[14px]">edit</span>
                              Edit
                            </button>
                          )}
                        </div>
                        {isEditing && editNokData ? (
                          <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                              <div>
                                <FieldLabel>First Name</FieldLabel>
                                <input type="text" value={editNokData.first_name} onChange={(e) => setEditNokData({ ...editNokData, first_name: e.target.value })}
                                  className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                              </div>
                              <div>
                                <FieldLabel>Last Name</FieldLabel>
                                <input type="text" value={editNokData.last_name} onChange={(e) => setEditNokData({ ...editNokData, last_name: e.target.value })}
                                  className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                              </div>
                              <div>
                                <FieldLabel>Relationship</FieldLabel>
                                <input type="text" value={editNokData.relationship} onChange={(e) => setEditNokData({ ...editNokData, relationship: e.target.value })}
                                  className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                              </div>
                              <div>
                                <FieldLabel>Contact Number</FieldLabel>
                                <input type="text" value={editNokData.contact_number} onChange={(e) => setEditNokData({ ...editNokData, contact_number: e.target.value })}
                                  className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                              </div>
                              <div>
                                <FieldLabel>Email</FieldLabel>
                                <input type="email" value={editNokData.email} onChange={(e) => setEditNokData({ ...editNokData, email: e.target.value })}
                                  className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
                              </div>
                            </div>
                            <AddressDropdowns
                              values={editNokData.address}
                              onChange={(fieldOrObj, value) => {
                                if (typeof fieldOrObj === 'object') {
                                  setEditNokData({ ...editNokData, address: { ...editNokData.address, ...fieldOrObj } });
                                } else {
                                  setEditNokData({ ...editNokData, address: { ...editNokData.address, [fieldOrObj]: value } });
                                }
                              }}
                            />
                            <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                              <button onClick={cancelEdit} className="h-9 px-4 rounded-[3px] border border-slate-300 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                                Cancel
                              </button>
                              <button onClick={saveSection} disabled={savingSection}
                                className="h-9 px-4 rounded-[3px] bg-blue-900 text-[12px] font-bold text-white hover:bg-blue-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                {savingSection ? 'Saving…' : 'Save'}
                              </button>
                            </div>
                          </div>
                        ) : (
                          <div className="grid grid-cols-2 md:grid-cols-4 gap-5">
                            <InfoRow label="Name" value={[nok.first_name, nok.last_name].filter(Boolean).join(' ')} />
                            <InfoRow label="Relationship" value={nok.relationship} />
                            <InfoRow label="Contact" value={nok.contact_number || nok.phone_number} />
                            <InfoRow label="Email" value={nok.email} />
                            {nok.address && (
                              <div className="col-span-2 md:col-span-4">
                                <InfoRow label="Address" value={formatAddress(null, nok.address)} />
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </div>

          {/* ── Case Summary ───────────────────────────────── */}
          <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
            <div className="px-5 py-4 bg-slate-50 border-b border-slate-300 flex items-center justify-between">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Case Summary</span>
              {editingSection !== 'summary' && (
                <button onClick={openEditSummary} className="text-[11px] font-bold text-blue-900 hover:text-blue-700 cursor-pointer inline-flex items-center gap-1">
                  <span className="material-symbols-outlined text-[14px]">edit</span>
                  Edit
                </button>
              )}
            </div>
            <div className="p-5">
              {editingSection === 'summary' && editSummary !== null ? (
                <div className="space-y-4">
                  <textarea
                    value={editSummary}
                    onChange={(e) => setEditSummary(e.target.value)}
                    rows={6}
                    placeholder="Describe the case summary…"
                    className="w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 resize-y"
                  />
                  <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button onClick={cancelEdit} className="h-9 px-4 rounded-[3px] border border-slate-300 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                      Cancel
                    </button>
                    <button onClick={saveSection} disabled={savingSection}
                      className="h-9 px-4 rounded-[3px] bg-blue-900 text-[12px] font-bold text-white hover:bg-blue-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                      {savingSection ? 'Saving…' : 'Save'}
                    </button>
                  </div>
                </div>
              ) : (
                <p className="text-[13px] text-slate-700 leading-relaxed whitespace-pre-wrap">
                  {draft.summary || caseFile.summary || '—'}
                </p>
              )}
            </div>
          </div>

          {/* ── Vulnerability Indicators ───────────────────── */}
          <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
            <div className="px-5 py-4 bg-slate-50 border-b border-slate-300">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Vulnerability Indicators</span>
            </div>
            <div className="p-5">
              {draft.vulnerability && draft.vulnerability.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {draft.vulnerability.map((v) => (
                    <span key={v} className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold leading-none ${VULN_STYLES[v] || 'bg-slate-100 text-slate-700'}`}>
                      {v}
                    </span>
                  ))}
                </div>
              ) : (
                <p className="text-[13px] text-slate-400 italic">No vulnerability indicators selected.</p>
              )}
            </div>
          </div>
        </div>

        {/* ═══ CM CLASSIFICATION SECTION ══════════════════════ */}
        <div className="mt-6">
          <div className="bg-white border border-slate-300 shadow-sm rounded-md overflow-hidden">
            <div className="px-5 py-4 bg-slate-50 border-b border-slate-300">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                Case Manager Classification
              </span>
            </div>
            <div className="p-5 space-y-6">
              {/* Categories */}
              <div>
                <FieldLabel>Categories <span className="text-red-500">*</span></FieldLabel>
                <CategoryCheckboxDropdown
                  categories={categories}
                  selectedIds={categoryIds}
                  onChange={setCategoryIds}
                />
                {categoryIds.length === 0 && (
                  <p className="mt-1.5 text-[11px] text-red-500">At least one category is required to publish.</p>
                )}
              </div>

              {/* Issue/Concern */}
              <div>
                <FieldLabel>Issue / Concern</FieldLabel>
                <select
                  value={caseIssueId}
                  onChange={(e) => setCaseIssueId(e.target.value)}
                  className="h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                  <option value="">Select an issue…</option>
                  {caseIssues.map((issue) => (
                    <option key={issue.id} value={issue.id}>{issue.name}</option>
                  ))}
                </select>
              </div>

              {/* Vulnerability (CM can toggle) */}
              <div>
                <FieldLabel>Vulnerability</FieldLabel>
                <div className="flex flex-wrap gap-3 mt-1">
                  {VULNERABILITY_OPTIONS.map((v) => {
                    const checked = vulnerability.includes(v);
                    return (
                      <label key={v} className="flex items-center gap-2 cursor-pointer select-none">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => {
                            setVulnerability((prev) =>
                              checked ? prev.filter((x) => x !== v) : [...prev, v]
                            );
                          }}
                          className="rounded border-slate-300 text-blue-900 focus:ring-blue-900 focus:ring-offset-0"
                        />
                        <span className="text-[13px] text-slate-700">{v}</span>
                      </label>
                    );
                  })}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* ═══ ACTION BAR ═══════════════════════════════════════ */}
        <div className="mt-6 flex items-center justify-between">
          <button
            onClick={() => setRejectOpen(true)}
            className="px-4 py-2 bg-white text-red-600 border border-red-200 hover:bg-red-50 text-[13px] font-bold rounded-[3px] transition-colors inline-flex items-center gap-2"
          >
            <span className="material-symbols-outlined text-[16px]">close</span>
            Reject
          </button>
          <button
            onClick={handlePublish}
            disabled={!canPublish || publishing}
            className="px-4 py-2 bg-blue-900 text-white hover:bg-blue-800 text-[13px] font-bold rounded-[3px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2"
          >
            <span className="material-symbols-outlined text-[16px]">check</span>
            {publishing ? 'Publishing…' : 'Publish Case →'}
          </button>
        </div>
      </div>

      {/* ── Reject Confirmation Dialog ──────────────────────── */}
      <ConfirmDialog
        open={rejectOpen}
        onClose={() => { setRejectOpen(false); setRejectReason(''); }}
        onConfirm={handleReject}
        title="Reject Intake Submission"
        confirmLabel={rejecting ? 'Rejecting…' : 'Reject'}
        confirmVariant="danger"
        disabled={rejecting || rejectReason.length < 10}
      >
        <p className="text-sm text-slate-600 mb-3">
          This will reject the OFW's submission. Please provide a reason (minimum 10 characters):
        </p>
        <textarea
          value={rejectReason}
          onChange={(e) => setRejectReason(e.target.value)}
          placeholder="Reason for rejection…"
          rows={3}
          className="w-full border border-slate-300 rounded-[3px] px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:ring-1 focus:ring-blue-900 outline-none"
        />
        {rejectReason.length > 0 && rejectReason.length < 10 && (
          <p className="text-xs text-red-500 mt-1">Reason must be at least 10 characters.</p>
        )}
      </ConfirmDialog>
    </AppLayout>
  );
}
