import AppLayout from '@/Layouts/AppLayout';
import Select from '@/Components/Select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';

import { useToast } from '@/Hooks/useToast';
import { Eye, Trash2 } from 'lucide-react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';
import FileUpload from '@/Components/FileUpload';
import { InfoCell, CardHeader, InfoField } from '@/Components/ui/CardSection';
import StatusBadge from '@/Components/ui/StatusBadge';
import { getAvatarColor } from '@/Components/ui/UserAvatar';
import { formatDisplayDateTime, formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import { RETURN_REASON_LABELS } from '@/Components/CaseIntakeForm';
import { formatResolvedAddress } from '@/lib/addressResolver';
import AuditLogModal from '@/Components/AuditLogModal';
import UnifiedTimeline from '@/Components/Timeline';
import PresenceRoster from '@/Components/PresenceRoster';
import { canShowCasePresence } from '@/Components/presencePolicy';

const vulnConfig = {
  'PWD': { icon: 'accessibility', className: 'bg-purple-100 text-purple-800 border-purple-200' },
  'Senior Citizen': { icon: 'elderly', className: 'bg-orange-100 text-orange-800 border-orange-200' },
  'Solo Parent': { icon: 'family_home', className: 'bg-pink-100 text-pink-800 border-pink-200' },
  'Indigenous Person': { icon: 'groups', className: 'bg-teal-100 text-teal-800 border-teal-200' },
};

function formatAddress(addr) {
  return formatResolvedAddress(addr, '');
}

export default function CaseShow({ case: caseFile, case_categories = [], recommended_agencies = [], documentRequirements = [], overdueDays = 7, milestoneTimeline = [], approver = null, lifecycle = [] }) {
  const EVENT_CONFIG = {
    case_opened:             { dot: 'bg-blue-50 border-blue-200 text-blue-600',       icon: 'folder' },
    referral_sent:           { dot: 'bg-purple-50 border-purple-200 text-purple-600',   icon: 'forward_to_inbox' },
    referral_status_changed: { dot: 'bg-amber-50 border-amber-200 text-amber-600',       icon: 'sync_alt' },
    milestone_added:         { dot: 'bg-emerald-50 border-emerald-200 text-emerald-600', icon: 'flag' },
    case_closed:             { dot: 'bg-slate-100 border-slate-200 text-slate-600',     icon: 'lock' },
    case_reopened:           { dot: 'bg-blue-50 border-blue-200 text-blue-600',       icon: 'lock_open' },
  };

  const EVENT_TYPE_OPTIONS = [
    { value: 'ALL',          label: 'All events' },
    { value: 'case_opened',  label: 'Case Opened' },
    { value: 'referral',     label: 'Referrals' },
    { value: 'referral_status_changed', label: 'Status Updates' },
    { value: 'lifecycle', label: 'Lifecycle' },
    { value: 'milestone_added',    label: 'Milestones' },
    { value: 'case_closed',  label: 'Case Closed' },
  ];

  const [timelineAgencyFilter, setTimelineAgencyFilter] = useState('ALL');
  const [timelineTypeFilter, setTimelineTypeFilter] = useState('ALL');

  const timelineAgencyNames = useMemo(() => {
    const names = milestoneTimeline
      ? milestoneTimeline.map(i => i.agency).filter((a, i, arr) => a && arr.indexOf(a) === i)
      : [];
    return names.sort((a, b) => a.localeCompare(b));
  }, [milestoneTimeline]);

  const filteredTimeline = useMemo(() => {
    if (!milestoneTimeline) return [];
    let items = [...milestoneTimeline, ...(lifecycle || []).map((event) => ({
      type: 'case_lifecycle',
      title: event.label,
      description: event.actor ? `${event.actor}${event.role ? ` (${event.role})` : ''}` : event.label,
      date: event.at,
    }))];
    if (timelineAgencyFilter !== 'ALL') {
      items = items.filter(i => i.agency === timelineAgencyFilter);
    }
    if (timelineTypeFilter === 'referral') {
      items = items.filter(i => i.type === 'referral_sent' || i.type === 'referral_status_changed');
    } else if (timelineTypeFilter === 'lifecycle') {
      items = items.filter(i => i.type === 'case_lifecycle');
    } else if (timelineTypeFilter !== 'ALL') {
      items = items.filter(i => i.type === timelineTypeFilter);
    }
    // Newest-first ordering is enforced inside UnifiedTimeline — never reverse here.
    return items;
  }, [milestoneTimeline, lifecycle, timelineAgencyFilter, timelineTypeFilter]);

  const timelineEventCount = (milestoneTimeline?.length || 0) + (lifecycle?.length || 0);
  const hasActiveFilters = timelineAgencyFilter !== 'ALL' || timelineTypeFilter !== 'ALL';

  const clearFilters = () => {
    setTimelineAgencyFilter('ALL');
    setTimelineTypeFilter('ALL');
  };

  const page = usePage();
  const { auth, roles } = page.props;
  const toast = useToast();
  const [showReferralPrompt, setShowReferralPrompt] = useState(!!page.props.just_published);
  const [showOverdueInfo, setShowOverdueInfo] = useState(false);
  const [showReferralBlockingInfo, setShowReferralBlockingInfo] = useState(false);
  const [showAuditLog, setShowAuditLog] = useState(false);
  const [confirmDeleteDoc, setConfirmDeleteDoc] = useState(null);
  const [confirmToggleStatus, setConfirmToggleStatus] = useState(false);
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [confirmUnarchive, setConfirmUnarchive] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deletionReason, setDeletionReason] = useState('');
  const [deletionReasonError, setDeletionReasonError] = useState('');
  const [deletingArchived, setDeletingArchived] = useState(false);
  const [uploadingDoc, setUploadingDoc] = useState(false);
  const [exportingPdf, setExportingPdf] = useState(false);

  async function handleExportPdf() {
    if (exportingPdf) return;
    setExportingPdf(true);
    try {
      const response = await fetch(route('cases.export-pdf', caseFile.id), { credentials: 'same-origin' });
      const contentType = response.headers.get('content-type') || '';
      const bytes = await response.arrayBuffer();
      const signature = new Uint8Array(bytes).subarray(0, 5);
      const isPdf = signature.length === 5 && signature.every((byte, index) => byte === [0x25, 0x50, 0x44, 0x46, 0x2d][index]);

      if (!response.ok || !contentType.toLowerCase().includes('application/pdf') || !isPdf) {
        toast.error('Could not export PDF. Your session may have expired; sign in again and retry.');
        return;
      }

      const url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
      const link = document.createElement('a');
      link.href = url;
      link.download = `case-${caseFile.case_number}.pdf`;
      link.click();
      URL.revokeObjectURL(url);
    } catch {
      toast.error('Could not export PDF. Check your connection and try again.');
    } finally {
      setExportingPdf(false);
    }
  }

  function handleDocumentDelete(docId) {
    setConfirmDeleteDoc(docId);
  }

  function handleDocumentUpload(file, category = null) {
    if (!file || uploadingDoc) return;
    setUploadingDoc(true);

    // Multipart body via the Inertia FormData protocol. A bare `{ file }`
    // object has no `forceFormData` flag, so failures arrive as a silent 422
    // (and a 302 redirect on success replays as GET against a JSON endpoint).
    const formData = new FormData();
    formData.append('file', file);
    if (category) formData.append('category', category);

    router.post(
      route('cases.documents.store', caseFile.id),
      formData,
      {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
          router.reload({ only: ['case', 'documentRequirements'] });
          toast.success('Document uploaded successfully.');
        },
        onError: (errors) => {
          const message = errors?.file
            || Object.values(errors || {})[0]
            || 'Could not upload the document. Please try again.';
          toast.error(message);
        },
        onFinish: () => setUploadingDoc(false),
      },
    );
  }

  const primaryAddress = { region: caseFile.address_region, province: caseFile.address_province, city_municipality: caseFile.address_city_municipality, barangay: caseFile.address_barangay, street: caseFile.address_street };
  const hasAddress = Object.values(primaryAddress).some(Boolean);
  const primaryEmployment = { employer_name: caseFile.employment_employer_name, position: caseFile.employment_position, country: caseFile.employment_country, last_position: caseFile.employment_last_position, date_of_arrival: caseFile.employment_date_of_arrival };
  const hasEmployment = Object.values(primaryEmployment).some(Boolean) || Boolean(caseFile.employment_employer_address);
  const categoryOptions = page.props.categories ?? [];
  const [selectedCategoryIds, setSelectedCategoryIds] = useState(case_categories.map((category) => String(category.id)));

  function saveCategories() {
    if (selectedCategoryIds.length === 0) {
      toast.error('Select at least one case category.');
      return;
    }
    router.put(route('cases.categories.update', caseFile.id), { category_ids: selectedCategoryIds }, {
      preserveScroll: true,
      onSuccess: () => toast.success('Case categories updated.'),
      onError: () => toast.error('Could not update case categories.'),
    });
  }
  const canManageCaseDocuments = auth.user?.role === roles.CASE_MANAGER;
  const canManageReferrals = auth.user?.role === roles.CASE_MANAGER || auth.user?.role === roles.ADMIN;
  const clientTypeLabel = caseFile.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin';
  const intakeName = [caseFile.first_name, caseFile.middle_name, caseFile.last_name, caseFile.suffix].filter(Boolean).join(' ');
  const clientFullName = intakeName;
  const clientInitials = clientFullName ? clientFullName.split(' ').filter(Boolean).map((part) => part[0]).join('').toUpperCase().slice(0, 2) : '';
  const [confirmDeleteReferral, setConfirmDeleteReferral] = useState(null);

  const referralRows = useMemo(() => {
    return (caseFile.referrals || []).map((ref) => {
      const milestones = ref.milestones || [];
      const latest = milestones.length > 0
        ? milestones.reduce((a, b) => new Date(a.created_at) > new Date(b.created_at) ? a : b)
        : null;
      const lastActivity = latest
        ? new Date(latest.created_at)
        : ref.status === 'PENDING'
          ? new Date(ref.created_at)
          : new Date(ref.updated_at);
      const daysSinceActivity = Math.floor((Date.now() - lastActivity.getTime()) / (1000 * 60 * 60 * 24));
      const isOverdue = !['COMPLETED', 'REJECTED'].includes(ref.status) && daysSinceActivity > overdueDays;
      return {
        id: ref.id,
        agency: ref.agency?.name || 'N/A',
        referralStatus: ref.status,
        isOverdue,
        service: ref.required_services,
        latestMilestone: latest?.title || 'Referral Sent',
        dateReferred: formatDisplayDate(ref.created_at),
        timeReferred: formatDisplayTime(ref.created_at),
      };
    });
  }, [caseFile.referrals, overdueDays]);

  const hasOverdueReferrals = useMemo(() => referralRows.some((r) => r.isOverdue), [referralRows]);

  const hasActiveReferrals = useMemo(() => {
    return (caseFile.referrals || []).some(
      (ref) => !['COMPLETED', 'REJECTED'].includes(ref.status),
    );
  }, [caseFile.referrals]);

  const [contextMenu, setContextMenu] = useState(null);

  function handleRowContextMenu(e, row) {
    e.preventDefault();
    setContextMenu({ x: e.clientX, y: e.clientY, row });
  }



  const referralColumns = [
    {
      key: 'agency',
      title: 'AGENCY',
      className: 'w-[34%] whitespace-normal leading-5 align-top',
      render: (row) => (
        <span className="flex items-center gap-1.5 text-[12px] font-semibold text-slate-700">
          {row.isOverdue && (
            <span className="material-symbols-outlined text-[13px] text-amber-600 shrink-0" title="Overdue">warning</span>
          )}
          {row.agency}
        </span>
      ),
    },
    {
      key: 'referralStatus',
      title: 'REFERRAL STATUS',
      className: 'w-[14%] whitespace-nowrap align-top',
      render: (row) => <StatusBadge status={row.referralStatus} />,
    },
    {
      key: 'latestMilestone',
      title: 'LATEST MILESTONE',
      className: 'w-[29%] whitespace-normal leading-5 align-top',
      render: (row) => <span className="text-[12px] text-slate-600">{row.latestMilestone}</span>,
    },
    {
      key: 'dateReferred',
      title: 'DATE REFERRED',
      className: 'w-[16%] whitespace-nowrap align-top',
      render: (row) => (
        <span className="block leading-5">
          <span className="block text-[12px] text-slate-600">{row.dateReferred}</span>
          <span className="block text-[11px] text-slate-400">{row.timeReferred}</span>
        </span>
      ),
    },
    {
      key: 'action',
      title: 'ACTIONS',
      className: 'w-[8%] whitespace-nowrap text-right align-top',
      render: (row) => (
        <div className="flex items-center justify-end gap-1.5">
          <Link
            href={route('referrals.show', row.id)}
            title="View referral"
            className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-50 hover:text-blue-900"
          >
            <span className="material-symbols-outlined text-[15px]">edit</span>
          </Link>
          {canManageReferrals && row.referralStatus !== 'COMPLETED' && (
            <button
              type="button"
              onClick={() => setConfirmDeleteReferral(row)}
              title="Delete referral"
              className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-red-200 bg-white text-red-500 transition-colors hover:bg-red-50"
            >
              <span className="material-symbols-outlined text-[15px]">delete</span>
            </button>
          )}
        </div>
      ),
    },
  ];

  /**
   * Legacy case categories/issues are gone: classification belongs to each
   * referral and confirmed assessment belongs to review. Nothing case-wide to
   * edit from this page.
   */

  function handleToggleStatus() {
    if (caseFile.status === 'OPEN' && hasActiveReferrals) {
      toast.error('Cannot close case: One or more referrals are still pending or in progress.');
      return;
    }

    router.post(route('cases.toggle-status', caseFile.id), {}, {
      preserveScroll: true,
      onError: (errors) => {
        const msg = errors?.message || 'An error occurred while updating case status.';
        toast.error(msg);
      },
    });
  }

  function handleArchive() {
    if (hasActiveReferrals) {
      toast.error('Cannot archive case: One or more referrals are still pending or in progress.');
      return;
    }

    router.post(route('cases.archive', caseFile.id), {}, {
      preserveScroll: true,
    });
  }

  function handleUnarchive() {
    router.post(route('cases.unarchive', caseFile.id), {}, {
      preserveScroll: true,
    });
  }

  function handleDeleteArchived() {
    if (deletingArchived) return;
    if (deletionReason.trim().length < 10) {
      setDeletionReasonError('Deletion reason must be at least 10 characters.');
      return;
    }
    setDeletionReasonError('');
    setDeletingArchived(true);
    router.delete(route('cases.delete-archived', caseFile.id), {
      data: { deletion_reason: deletionReason.trim() },
      onSuccess: () => {
        setShowDeleteModal(false);
        setDeletionReason('');
      },
      onError: (errors) => {
        const msg = errors?.deletion_reason || Object.values(errors)[0] || 'Delete failed.';
        setDeletionReasonError(msg);
      },
      onFinish: () => setDeletingArchived(false),
    });
  }

  return (
    <AppLayout title="Case Details">
      <Head title="Case Details" />

      <div className="mb-5">
        <Link href={route('cases.index')} className="inline-flex items-center gap-1.5 text-[12px] font-semibold text-slate-500 transition hover:text-blue-900">
          <span className="material-symbols-outlined text-[16px]">arrow_back</span>
          Back to Cases
        </Link>
      </div>

      {showReferralPrompt && (
        <div className="mb-5 flex items-start gap-2.5 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2.5">
          <span className="material-symbols-outlined text-[16px] text-indigo-600 shrink-0 mt-px">check_circle</span>
          <div className="flex-1 min-w-0">
            <p className="text-[11px] font-bold text-indigo-900">Case Published Successfully</p>
            <p className="text-[11px] leading-5 text-indigo-700">Would you like to refer this case to an agency?</p>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <button
              type="button"
              onClick={() => router.visit(route('referrals.create', { case_id: caseFile.id }))}
              className="px-3 min-h-[30px] inline-flex items-center bg-blue-900 text-white hover:bg-blue-800 text-[11px] font-bold rounded-md transition-colors border border-blue-900"
            >
              Refer to Agency
            </button>
            <button
              type="button"
              onClick={() => setShowReferralPrompt(false)}
              className="px-3 min-h-[30px] inline-flex items-center bg-white text-slate-600 hover:bg-slate-50 text-[11px] font-bold rounded-md transition-colors border border-slate-300"
            >
              Skip
            </button>
          </div>
        </div>
      )}

      <div className="mx-auto mb-5 max-w-[1440px]">
        <div className="flex items-end justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-[28px] font-extrabold leading-tight tracking-[-0.02em] text-[#172333]">Case Details</h1>
            <p className="mt-2 text-[11px] leading-5 text-[#607080]">Case information, referrals and supporting records</p>
          </div>
          <div className="flex flex-wrap items-center justify-end gap-3">
            {canShowCasePresence(auth.user?.role, roles) && (
              <PresenceRoster url={`/cases/${encodeURIComponent(caseFile.id)}/presence`} currentUserId={auth.user?.id} />
            )}
          <div data-tour="case-actions" className="flex items-center gap-2 shrink-0">
            {canManageReferrals && <button
            type="button"
            onClick={handleExportPdf}
            disabled={exportingPdf}
            aria-busy={exportingPdf}
            className="px-3.5 min-h-[34px] bg-white text-[12px] font-bold text-[#233f82] border border-slate-200 rounded-md hover:bg-slate-50 transition-colors inline-flex items-center gap-1.5 disabled:cursor-wait disabled:opacity-60"
          >
            <span aria-hidden="true" className="material-symbols-outlined text-[15px]">picture_as_pdf</span>
            {exportingPdf ? 'Preparing PDF…' : 'Export PDF'}
          </button>}
          {canManageReferrals && caseFile.status === 'PENDING_REVIEW' && (
            <Link
              href={route('cases.review-pending')}
              className="px-3.5 min-h-[34px] bg-emerald-600 text-[12px] font-bold text-white border border-emerald-600 rounded-md hover:bg-emerald-700 transition-colors inline-flex items-center gap-1.5"
            >
              <span className="material-symbols-outlined text-[15px]">check_circle</span>
              Review intake
            </Link>
          )}
          <button
            type="button"
            onClick={() => setShowAuditLog(true)}
            className="px-3 min-h-[34px] bg-slate-100 text-[12px] font-bold text-slate-700 border border-slate-300 rounded-md hover:bg-slate-200 transition-colors inline-flex items-center gap-1.5 hidden"
          >
            <span className="material-symbols-outlined text-[16px]">history</span>
            Audit Log
          </button>
          {(caseFile.status === 'OPEN' || caseFile.status === 'CLOSED' || caseFile.status === 'ARCHIVED') && (
            <button
              type="button"
              onClick={() => setConfirmToggleStatus(true)}
              disabled={caseFile.status === 'OPEN' && hasActiveReferrals}
              title={caseFile.status === 'OPEN' && hasActiveReferrals ? 'Resolve all referrals before closing this case.' : ''}
              className={`px-3 min-h-[34px] text-[12px] font-bold rounded-md transition-colors border hidden ${
                caseFile.status === 'OPEN' && hasActiveReferrals
                  ? 'bg-gray-200 text-gray-500 border-gray-300 cursor-not-allowed'
                  : 'bg-blue-900 text-white hover:bg-blue-800 border-blue-900'
              }`}
            >
              {caseFile.status === 'OPEN' ? 'Close Case' : 'Reopen Case'}
            </button>
          )}
          {caseFile.status === 'ARCHIVED' ? (
            <>
              <button
                type="button"
                onClick={() => setConfirmUnarchive(true)}
                className="px-3 min-h-[34px] bg-slate-100 text-[12px] font-bold text-slate-700 border border-slate-300 rounded-md hover:bg-slate-200 transition-colors"
              >
                Restore from Archive
              </button>
              {(auth.user.role === roles.CASE_MANAGER || auth.user.role === roles.ADMIN) && (
                <button
                  type="button"
                  onClick={() => setShowDeleteModal(true)}
                  className="px-3 min-h-[34px] bg-red-50 text-[12px] font-bold text-red-700 border border-red-200 rounded-md hover:bg-red-100 transition-colors inline-flex items-center gap-1.5"
                >
                  <span className="material-symbols-outlined text-[15px]">delete_forever</span>
                  Delete Case
                </button>
              )}
            </>
          ) : caseFile.status === 'CLOSED' && !hasActiveReferrals ? (
            <button
              type="button"
              onClick={handleArchive}
              className="px-3 min-h-[34px] bg-slate-100 text-[12px] font-bold text-slate-700 border border-slate-300 rounded-md hover:bg-slate-200 transition-colors"
            >
              Archive Case
            </button>
          ) : null}
        </div>
          </div>
      </div>
      </div>

      <div className="mx-auto grid max-w-[1440px] grid-cols-1 gap-4 xl:grid-cols-12">
        <main className="space-y-4 xl:col-span-8">
          <section className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
            <CardHeader
              title="Case Summary"
              actions={(
                <button type="button" onClick={() => setConfirmToggleStatus(true)} disabled={caseFile.status === 'OPEN' && hasActiveReferrals} className="inline-flex min-h-[34px] items-center gap-1.5 rounded-md bg-slate-100 px-3.5 text-[12px] font-bold text-slate-500 transition hover:bg-slate-200 disabled:cursor-not-allowed disabled:hover:bg-slate-100">
                  <span className="material-symbols-outlined text-[15px]">lock</span>
                  {caseFile.status === 'OPEN' ? 'Close Case' : 'Reopen Case'}
                </button>
              )}
            />
            {/* [&>div]:border-0 strips InfoCell's border-b/border-r dividers for this grid only */}
            <div className="grid grid-cols-1 md:grid-cols-3 [&>div]:border-0">
              <InfoCell label="Case No." value={caseFile.case_number} />
              <InfoCell label="Tracking ID" value={caseFile.tracker_number} />
              <InfoCell label="Date created" value={<>{formatDisplayDate(caseFile.created_at)}<span className="block text-[10px] font-normal text-slate-500">{formatDisplayTime(caseFile.created_at)}</span></>} />
              <InfoCell label="Client type" value={clientTypeLabel} />
              <InfoCell label="Age at intake" value={caseFile.age_at_intake ?? '\u2014'} />
              <InfoCell label="Status" value={<StatusBadge status={caseFile.status} size="sm" />} />
              <InfoCell label="Intake date" value={caseFile.intake_date ? formatDisplayDate(caseFile.intake_date) : '-'} />
              <InfoCell label="Civil status" value={caseFile.civil_status === 'OTHER' ? caseFile.civil_status_other : caseFile.civil_status} />
              <InfoCell label="Deployment mode" value={caseFile.deployment_mode} />
              <InfoCell label="Employment status" value={caseFile.employment_status} />
              <InfoCell label="Return reason" value={caseFile.return_reason ? (RETURN_REASON_LABELS[caseFile.return_reason] ?? caseFile.return_reason) : '-'} />
              <InfoCell label="Proposed assessments" value={(caseFile.proposed_assessments ?? []).map((item) => item.replace(/_/g, ' ').toLowerCase()).join(', ') || '-'} />
              <InfoCell label="Case categories" value={case_categories.map((category) => category.name).join(', ') || '-'} />
              <InfoCell label="Case narrative" value={caseFile.summary || '-'} />
              <InfoCell label="Opened by" value={caseFile.user?.name || '-'} />
              <InfoCell
                label="Final approver"
                value={approver ? (
                  <>
                    {approver.name}
                    {approver.role && <span className="block text-[10px] font-normal text-slate-500">{approver.role}</span>}
                  </>
                ) : '-'}
              />
            </div>
            <p className="border-t border-[#dce3eb] px-5 py-3 text-center text-[11px] text-slate-400">Close Case is unavailable while referrals are active. All referrals must be completed or rejected.</p>
          </section>

          {/* Referrals table */}
          {canManageCaseDocuments && categoryOptions.length > 0 && (
            <section className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
              <CardHeader title="Case categories" />
              <div className="space-y-3 px-5 pb-5">
                <div className="grid gap-2 sm:grid-cols-2">
                  {categoryOptions.map((category) => (
                    <label key={category.id} className="flex items-center gap-2 text-[12px] text-slate-700">
                      <input type="checkbox" checked={selectedCategoryIds.includes(String(category.id))} onChange={() => setSelectedCategoryIds((ids) => ids.includes(String(category.id)) ? ids.filter((id) => id !== String(category.id)) : [...ids, String(category.id)])} className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/40" />
                      {category.name}
                    </label>
                  ))}
                </div>
                <button type="button" onClick={saveCategories} className="min-h-[34px] rounded-md bg-blue-900 px-3 text-[12px] font-bold text-white hover:bg-blue-800">Save categories</button>
              </div>
            </section>
          )}
          <section data-tour="case-referrals" className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
            <CardHeader
              title="Referrals"
              actions={(
                <>
                  {caseFile.status === 'OPEN' && hasActiveReferrals && (
                    <div className="relative">
                      <button
                        type="button"
                        aria-label="Show referral closure requirement"
                        aria-expanded={showReferralBlockingInfo}
                        aria-controls="referral-closure-requirement"
                        onClick={() => setShowReferralBlockingInfo((prev) => !prev)}
                        className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-amber-700 transition-colors hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500"
                      >
                        <span aria-hidden="true" className="material-symbols-outlined text-[18px]">info</span>
                      </button>
                      {showReferralBlockingInfo && (
                        <div id="referral-closure-requirement" className="absolute right-0 top-full z-20 mt-1 w-64 max-w-[calc(100vw-3rem)] rounded-md border border-amber-200 bg-amber-50 px-3 py-2 shadow-md">
                          <p className="text-[11px] leading-5 text-amber-800">
                            This case cannot be closed until all referrals are completed or rejected. Resolve the active referrals below first.
                          </p>
                        </div>
                      )}
                    </div>
                  )}
                  {hasOverdueReferrals && (
                    <>
                      <span
                        className="material-symbols-outlined text-[16px] text-amber-600 shrink-0"
                        title={`${referralRows.filter((r) => r.isOverdue).length} overdue referral(s)`}
                      >warning</span>
                      <div className="relative">
                        <button
                          type="button"
                          onClick={() => setShowOverdueInfo((prev) => !prev)}
                          className="flex h-[20px] w-[20px] items-center justify-center rounded-full text-amber-600 hover:bg-amber-100 transition-colors"
                        >
                          <span className="material-symbols-outlined text-[15px]">info</span>
                        </button>
                        {showOverdueInfo && (
                          <div className="absolute right-0 top-full mt-1 z-20 w-72 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 shadow-md">
                            <p className="text-[11px] leading-5 text-amber-800">
                              A referral is considered overdue when there has been no update or activity for more than {overdueDays} day{overdueDays > 1 ? 's' : ''}.
                            </p>
                          </div>
                        )}
                      </div>
                    </>
                  )}
                  <Link
                    href={route('referrals.create', { case_id: caseFile.id })}
                    className="inline-flex min-h-[34px] items-center gap-1 rounded-md bg-blue-900 px-3.5 text-[12px] font-bold text-white transition-colors hover:bg-blue-800"
                  >
                    <span className="material-symbols-outlined text-[15px]">add</span>
                    Refer to Agency
                  </Link>
                </>
              )}
            />
            <div className="space-y-3 px-5 pb-5">
              <UnifiedTable
                variant="embedded"
                data={referralRows}
                columns={referralColumns}
                keyExtractor={(row) => row.id}
                hideControlBar
                hidePagination
              />
            </div>
          </section>

          <div data-tour="case-timeline">
            <section className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
              <CardHeader
                title="Activity"
                meta="Chronological events"
                actions={(
                  <div className="flex flex-wrap items-center gap-2">
                    {/* Agency filter */}
                    {timelineAgencyNames.length > 0 && (
                      <div className="relative inline-flex">
                        <Select
                          value={timelineAgencyFilter}
                          onChange={e => setTimelineAgencyFilter(e.target.value)}
                          aria-label="Filter events by agency"
                          className="min-h-[34px] cursor-pointer appearance-none pl-3 pr-8 text-[12px] font-semibold"
                        >
                          <option value="ALL">All agencies</option>
                          {timelineAgencyNames.map(a => <option key={a} value={a}>{a}</option>)}
                        </Select>
                      </div>
                    )}
                    {/* Type filter */}
                    <div className="relative inline-flex">
                      <Select
                        value={timelineTypeFilter}
                        onChange={e => setTimelineTypeFilter(e.target.value)}
                        aria-label="Filter events by type"
                        className="min-h-[34px] cursor-pointer appearance-none pl-3 pr-8 text-[12px] font-semibold"
                      >
                        {EVENT_TYPE_OPTIONS.map(opt => (
                          <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                      </Select>
                    </div>
                    {/* Clear filters */}
                    {hasActiveFilters && (
                      <button
                        type="button"
                        onClick={clearFilters}
                        className="min-h-[34px] rounded-md px-2.5 text-[12px] font-bold text-blue-600 transition-colors hover:text-blue-800"
                      >
                        Clear
                      </button>
                    )}
                  </div>
                )}
              />
              <div className="px-5 pb-5">
                {/* Filter results count */}
                {timelineEventCount > 0 && (
                  <p className="mb-2 text-[11px] font-medium text-slate-400">
                    Showing {filteredTimeline.length} of {timelineEventCount} events
                  </p>
                )}

                <UnifiedTimeline
                  items={filteredTimeline}
                  eventConfig={EVENT_CONFIG}
                  emptyTitle="No activity matches your filters."
                  emptyAction={
                    hasActiveFilters ? (
                      <button type="button" onClick={clearFilters} className="mt-2 text-xs font-bold text-blue-600 hover:text-blue-800 underline">
                        Clear filters
                      </button>
                    ) : null
                  }
                />
              </div>
            </section>
          </div>

        </main>

        <aside className="space-y-4 xl:col-span-4">
          {/* Client Information */}
          <section data-tour="case-ofw-info" className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
            <CardHeader title="OFW information" />
            <div className="space-y-4 px-5 pb-5">
              {/* Case facts are independent of any reusable account profile. */}
              {(() => {
                const avatarUrl = caseFile.avatar_url;
                if (!avatarUrl) {
                  return clientFullName ? (
                    <span
                      className={`inline-flex h-[88px] w-[88px] shrink-0 items-center justify-center rounded-2xl text-[26px] font-bold text-white ${getAvatarColor(clientFullName)}`}
                      aria-hidden="true"
                    >
                      {clientInitials || '?'}
                    </span>
                  ) : null;
                }
                return (
                  <img
                    src={avatarUrl}
                    alt=""
                    className="h-[88px] w-[88px] shrink-0 rounded-2xl border border-slate-200 object-cover"
                    onError={(e) => { e.target.style.display = 'none'; }}
                  />
                );
              })()}

              <InfoField label="Intake date" value={caseFile.intake_date ? formatDisplayDate(caseFile.intake_date) : null} />
              <InfoField label="Civil status" value={caseFile.civil_status === 'OTHER' ? caseFile.civil_status_other : caseFile.civil_status} />
              <InfoField label="Deployment mode" value={caseFile.deployment_mode} />
              <InfoField label="Employment status" value={caseFile.employment_status} />
              <InfoField label="Full name" value={clientFullName} />

              <InfoField label="Date of birth" value={caseFile.date_of_birth ? formatDisplayDate(caseFile.date_of_birth) : null} />

              <div className="grid grid-cols-2 gap-3">
                <InfoField label="Age at intake" value={caseFile.age_at_intake} />
                <InfoField label="Sex" value={caseFile.sex} />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <InfoField label="Email" value={caseFile.email} />
                <InfoField label="Contact no." value={caseFile.contact_number} />
              </div>

              {caseFile.nok_name && (
                <div className="space-y-3 border-t border-slate-200 pt-4">
                  <InfoField label="Next of kin" value={caseFile.nok_name} />
                  <InfoField label="Relationship" value={caseFile.nok_relationship} />
                  <div className="grid grid-cols-2 gap-3">
                    <InfoField label="NOK email" value={caseFile.nok_email} />
                    <InfoField label="NOK contact no." value={caseFile.nok_contact_number} />
                  </div>
                </div>
              )}

              {/* Vulnerability */}
              {(() => {
                const ofwVulns = (caseFile.vulnerability_indicator || '').split(',').map(s => s.trim()).filter(v => v && v !== 'None');
                const nokVulns = (caseFile.nok_vulnerability_indicator || '').split(',').map(s => s.trim()).filter(v => v && v !== 'None');
                const hasVulns = ofwVulns.length > 0 || nokVulns.length > 0;
                if (!hasVulns) return null;
                return (
                  <div>
                    <p className="text-[12px] font-medium text-slate-500">Vulnerability</p>
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                      {ofwVulns.map((v) => (
                        <span key={`ofw-${v}`} className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-bold ${vulnConfig[v]?.className || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                          <span className="material-symbols-outlined text-[13px]">{vulnConfig[v]?.icon || 'warning'}</span>
                          OFW: {v}
                        </span>
                      ))}
                      {nokVulns.map((v) => (
                        <span key={`nok-${v}`} className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-bold ${vulnConfig[v]?.className || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                          <span className="material-symbols-outlined text-[13px]">{vulnConfig[v]?.icon || 'warning'}</span>
                          NOK: {v}
                        </span>
                      ))}
                    </div>
                  </div>
                );
              })()}

              {hasAddress && <InfoField label="Address" value={formatAddress(primaryAddress)} fallback="No address recorded" />}

              {hasEmployment && (
                <>
                  <hr className="border-slate-200" />
                  <InfoField label="Employer" value={primaryEmployment.employer_name} />
                  <InfoField label="Employer address" value={caseFile.employment_employer_address} />
                  <div className="grid grid-cols-2 gap-3">
                    <InfoField
                      label="Work history"
                      value={`${primaryEmployment.country || 'N/A'} · ${primaryEmployment.last_position || primaryEmployment.position || 'N/A'}`}
                    />
                    <InfoField label="Arrival date" value={primaryEmployment.date_of_arrival ? formatDisplayDate(primaryEmployment.date_of_arrival) : null} />
                  </div>
                </>
              )}
            </div>
          </section>

          {canManageCaseDocuments && recommended_agencies.length > 0 && (
            <section className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
              <h2 className="text-[12px] font-bold text-emerald-900">Recommended agencies</h2>
              <ul className="mt-2 space-y-1 text-[12px] text-emerald-900">
                {[...new Map(recommended_agencies.map((agency) => [agency.id, agency])).values()].map((agency) => <li key={agency.id}>{agency.name}{agency.short ? ` (${agency.short})` : ''}</li>)}
              </ul>
            </section>
          )}
          <section data-tour="case-documents" className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
            <CardHeader
              title="Documents"
              meta={`${(caseFile.documents || []).length} documents`}
              actions={canManageCaseDocuments && (
                <div className="flex items-center gap-2">
                  {(caseFile.documents || []).length > 0 && (
                    <a
                      href={route('cases.intake-documents.archive', caseFile.id)}
                      aria-label="Download all case documents as ZIP"
                      className="inline-flex min-h-[34px] items-center rounded-md border border-slate-200 bg-white px-3 text-[12px] font-bold text-[#233f82] transition-colors hover:bg-slate-50"
                    >
                      Download all
                    </a>
                  )}
                  <FileUpload
                    variant="button"
                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                    maxSize={10 * 1024 * 1024}
                    label={uploadingDoc ? 'Uploading...' : 'Upload New File'}
                    disabled={uploadingDoc}
                    onFilesSelected={handleDocumentUpload}
                  />
                </div>
              )}
            />
            <div className="space-y-4 px-5 pb-5">

              {documentRequirements.length > 0 && (
                <div className="space-y-2" data-testid="document-requirements">
                  <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Requirements</p>
                  {documentRequirements.map((requirement) => {
                    const tone = {
                      received: 'bg-emerald-50 border-emerald-200 text-emerald-700',
                      complied_onsite: 'bg-blue-50 border-blue-200 text-blue-700',
                      for_compliance: 'bg-amber-50 border-amber-200 text-amber-800',
                    }[requirement.state] ?? 'bg-slate-50 border-slate-200 text-slate-600';
                    const label = { received: 'Received', complied_onsite: 'Complied onsite', for_compliance: 'For compliance' }[requirement.state] ?? requirement.state;
                    return (
                      <div key={requirement.slot} className="rounded-md border border-slate-200 px-3 py-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <p className="text-[12px] font-semibold text-slate-700">{requirement.label}</p>
                          <span className={`rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${tone}`}>{label}</span>
                        </div>
                        {requirement.note && <p className="mt-1 text-[11px] text-slate-500">{requirement.note}</p>}
                        {canManageCaseDocuments && requirement.state === 'for_compliance' && (
                          <label className="mt-2 inline-flex cursor-pointer items-center gap-1 text-[11px] font-semibold text-blue-800">
                            <span className="material-symbols-outlined text-[16px]" aria-hidden="true">upload</span>
                            Upload
                            <input
                              type="file"
                              accept=".pdf,.jpg,.jpeg,.png,.webp"
                              className="sr-only"
                              disabled={uploadingDoc}
                              onChange={(event) => {
                                handleDocumentUpload(event.target.files?.[0] ?? null, requirement.slot);
                                event.target.value = '';
                              }}
                            />
                          </label>
                        )}
                      </div>
                    );
                  })}
                </div>
              )}

              {caseFile.documents?.length > 0 ? (
                <div className="space-y-2">
                  {caseFile.documents.map((doc) => {
                    const canDelete = canManageCaseDocuments;
                    return (
                      <div key={doc.id} className="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                        <div className="min-w-0">
                          <p className="text-[12px] font-semibold text-slate-700 truncate">{doc.file_name}</p>
                          <p className="text-[10px] text-slate-500">
                            {doc.user || 'Unknown'} &middot; {doc.created_at ? formatDisplayDateTime(doc.created_at) : ''}
                            {doc.size ? ` \u00b7 ${(doc.size / 1024).toFixed(1)} KB` : ''}
                          </p>
                        </div>
                        <div className="flex items-center gap-2 shrink-0 ml-2">
                          <a
                            href={route('cases.documents.download', { case: caseFile.id, document: doc.id })}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label={`View document ${doc.file_name}`}
                            className="text-slate-500 hover:text-blue-900"
                          >
                            <Eye aria-hidden="true" className="h-4 w-4" />
                          </a>
                          {canDelete && (
                            <button
                              type="button"
                              onClick={() => handleDocumentDelete(doc.id)}
                              aria-label={`Delete document ${doc.file_name}`}
                              className="text-slate-400 hover:text-red-500 transition-colors"
                            >
                              <Trash2 aria-hidden="true" className="h-4 w-4" />
                            </button>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <div className="flex items-center justify-between gap-3">
                  <p className="text-[12px] text-slate-500">No case documents uploaded.</p>
                  {canManageCaseDocuments && <p className="text-[12px] text-slate-400">Drag &amp; drop or click to browse</p>}
                </div>
              )}

              {canManageCaseDocuments && (
                <FileUpload
                  accept=".pdf,.jpg,.jpeg,.png,.webp"
                  maxSize={10 * 1024 * 1024}
                  label="Drop files here"
                  disabled={uploadingDoc}
                  onFilesSelected={handleDocumentUpload}
                />
              )}
            </div>
          </section>
        </aside>
      </div>

      <AuditLogModal
        show={showAuditLog}
        onClose={() => setShowAuditLog(false)}
        entityType="case"
        entityId={caseFile.id}
        title={`Audit Log — ${caseFile.case_number}`}
      />
      <ConfirmDialog
        open={!!confirmDeleteReferral}
        title="Delete Referral"
        message={`Are you sure you want to delete the referral to ${confirmDeleteReferral?.agency || 'this agency'}? Its milestones, comments and history will be removed from this case.`}
        confirmLabel="Delete"
        tone="danger"
        onConfirm={() => {
          router.delete(route('referrals.destroy', confirmDeleteReferral.id), { preserveScroll: true });
          router.reload({ only: ['case'] });
          setConfirmDeleteReferral(null);
        }}
        onCancel={() => setConfirmDeleteReferral(null)}
      />
      <ConfirmDialog
        open={!!confirmDeleteDoc}
        title="Delete Document"
        message="Are you sure you want to delete this document?"
        confirmLabel="Delete"
        tone="danger"
        onConfirm={() => {
          router.delete(route('cases.documents.destroy', [caseFile.id, confirmDeleteDoc]), { preserveScroll: true });
          setConfirmDeleteDoc(null);
        }}
        onCancel={() => setConfirmDeleteDoc(null)}
      />
      <ConfirmDialog
        open={confirmToggleStatus}
        title={caseFile.status === 'OPEN' ? 'Close Case' : 'Reopen Case'}
        message={
          caseFile.status === 'OPEN'
            ? `Are you sure you want to close case ${caseFile.case_number}? It will be marked as resolved and no longer accept updates.`
            : `Are you sure you want to reopen case ${caseFile.case_number}?`
        }
        confirmLabel={caseFile.status === 'OPEN' ? 'Close Case' : 'Reopen Case'}
        tone={caseFile.status === 'OPEN' ? 'danger' : 'info'}
        onConfirm={() => {
          handleToggleStatus();
          setConfirmToggleStatus(false);
        }}
        onCancel={() => setConfirmToggleStatus(false)}
      />
      <ConfirmDialog
        open={!!confirmUnarchive}
        title="Restore from Archive"
        message={`Are you sure you want to restore case ${caseFile.case_number} from archive? It will return to closed status.`}
        confirmLabel="Restore"
        tone="info"
        onConfirm={() => {
          handleUnarchive();
          setConfirmUnarchive(false);
        }}
        onCancel={() => setConfirmUnarchive(false)}
      />

      {/* Deletion reason modal for archived cases */}
      {showDeleteModal && (
        <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50" onClick={() => setShowDeleteModal(false)}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4" onClick={(e) => e.stopPropagation()}>
            <div className="p-5 border-b border-slate-200">
              <div className="flex items-center gap-2">
                <span className="material-symbols-outlined text-red-500 text-[22px]">warning</span>
                <h3 className="text-[16px] font-bold text-slate-900">Delete Archived Case</h3>
              </div>
              <p className="mt-2 text-[13px] text-slate-600">
                This will move case <span className="font-bold">{caseFile.case_number}</span> to trash.
                It can be restored from the trash view within the retention period.
              </p>
            </div>
            <div className="p-5 space-y-3">
              <div>
                <label className="block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600 mb-1.5">
                  Deletion Reason <span className="text-red-500">*</span>
                </label>
                <textarea
                  rows={4}
                  value={deletionReason}
                  onChange={(e) => {
                    setDeletionReason(e.target.value);
                    if (e.target.value.trim().length >= 10) setDeletionReasonError('');
                  }}
                  placeholder="Explain why this case is being deleted (min 10 characters)..."
                  className="w-full rounded-md border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-red-400 resize-none"
                />
                <div className="flex items-center justify-between mt-1">
                  {deletionReasonError ? (
                    <span className="text-[11px] text-red-600">{deletionReasonError}</span>
                  ) : (
                    <span className="text-[11px] text-slate-400">Required for audit compliance (COA)</span>
                  )}
                  <span className={`text-[11px] ${deletionReason.trim().length < 10 ? 'text-red-500' : 'text-slate-400'}`}>
                    {deletionReason.trim().length}/10 min
                  </span>
                </div>
              </div>
            </div>
            <div className="flex justify-end gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50 rounded-b-lg">
              <button
                type="button"
                onClick={() => {
                  setShowDeleteModal(false);
                  setDeletionReason('');
                  setDeletionReasonError('');
                }}
                className="h-9 rounded-md border border-slate-300 px-4 text-[12px] font-bold text-slate-700 hover:bg-slate-100"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleDeleteArchived}
                disabled={deletionReason.trim().length < 10 || deletingArchived}
                className="h-9 rounded-md bg-red-600 px-4 text-[12px] font-bold text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {deletingArchived ? 'Deleting...' : 'Delete Case'}
              </button>
            </div>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
