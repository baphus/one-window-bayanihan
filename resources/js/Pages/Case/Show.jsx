import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
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
import { formatResolvedAddress } from '@/lib/addressResolver';
import AuditLogModal from '@/Components/AuditLogModal';
import UnifiedTimeline from '@/Components/Timeline';

const vulnConfig = {
  'PWD': { icon: 'accessibility', className: 'bg-purple-100 text-purple-800 border-purple-200' },
  'Senior Citizen': { icon: 'elderly', className: 'bg-orange-100 text-orange-800 border-orange-200' },
  'Solo Parent': { icon: 'family_home', className: 'bg-pink-100 text-pink-800 border-pink-200' },
  'Indigenous Person': { icon: 'groups', className: 'bg-teal-100 text-teal-800 border-teal-200' },
};

function getCaseCategories(caseFile) {
  if (Array.isArray(caseFile?.categories) && caseFile.categories.length) return caseFile.categories;
  if (Array.isArray(caseFile?.category_ids) && caseFile.category_ids.length) {
    return caseFile.category_ids.map((id) => (id && typeof id === 'object' ? id : { id, name: id }));
  }
  return caseFile?.category ? [caseFile.category] : [];
}

function CategoryBadges({ caseFile }) {
  const categories = getCaseCategories(caseFile);
  if (!categories.length) return <span className="text-slate-400">&mdash;</span>;
  return (
    <span className="flex flex-wrap items-center gap-1">
      {categories.map((category) => (
        <span key={category.id || category.name} className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold" style={{ backgroundColor: category.color ? `${category.color}20` : '#f1f5f9', color: category.color || '#64748b' }}>
          {category.color && <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: category.color }} />}
          {category.name || category.title}
        </span>
      ))}
    </span>
  );
}

function getClientAge(dob) {
  if (!dob) return '\u2014';
  const birth = new Date(dob);
  if (Number.isNaN(birth.getTime())) return '\u2014';
  const today = new Date();
  let age = today.getFullYear() - birth.getFullYear();
  const monthDiff = today.getMonth() - birth.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age--;
  }
  return age;
}

function formatAddress(addr) {
  return formatResolvedAddress(addr, '');
}

function formatNokAddress(nok) {
  return formatResolvedAddress(nok, nok?.full_address || 'N/A');
}

export default function CaseShow({ case: caseFile, overdueDays = 7, milestoneTimeline = [], categories = [], caseIssues = [] }) {
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
    let items = [...milestoneTimeline];
    if (timelineAgencyFilter !== 'ALL') {
      items = items.filter(i => i.agency === timelineAgencyFilter);
    }
    if (timelineTypeFilter === 'referral') {
      items = items.filter(i => i.type === 'referral_sent' || i.type === 'referral_status_changed');
    } else if (timelineTypeFilter !== 'ALL') {
      items = items.filter(i => i.type === timelineTypeFilter);
    }
    // Newest-first ordering is enforced inside UnifiedTimeline — never reverse here.
    return items;
  }, [milestoneTimeline, timelineAgencyFilter, timelineTypeFilter]);

  const hasActiveFilters = timelineAgencyFilter !== 'ALL' || timelineTypeFilter !== 'ALL';

  const clearFilters = () => {
    setTimelineAgencyFilter('ALL');
    setTimelineTypeFilter('ALL');
  };

  const page = usePage();
  const { auth, roles } = page.props;
  const client = caseFile.client;
  const toast = useToast();
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [showReferralPrompt, setShowReferralPrompt] = useState(!!page.props.just_published);
  const [showOverdueInfo, setShowOverdueInfo] = useState(false);
  const [showAuditLog, setShowAuditLog] = useState(false);
  const [confirmDeleteDoc, setConfirmDeleteDoc] = useState(null);
  const [confirmToggleStatus, setConfirmToggleStatus] = useState(false);
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [confirmUnarchive, setConfirmUnarchive] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deletionReason, setDeletionReason] = useState('');
  const [deletionReasonError, setDeletionReasonError] = useState('');
  const [deletingArchived, setDeletingArchived] = useState(false);
  const [formStatus, setFormStatus] = useState(caseFile.status);
  const [formIssueId, setFormIssueId] = useState(caseFile.case_issue_id || '');
  const [formCategoryIds, setFormCategoryIds] = useState(getCaseCategories(caseFile).map(c => c.id));
  const [formVulnerability, setFormVulnerability] = useState(caseFile.vulnerability_indicator || '');
  const [nokVulnerability, setNokVulnerability] = useState(caseFile.nok_vulnerability_indicator || '');
  const [formSummary, setFormSummary] = useState(caseFile.summary || '');
  const [saving, setSaving] = useState(false);
  const [uploadingDoc, setUploadingDoc] = useState(false);

  function handleDocumentDelete(docId) {
    setConfirmDeleteDoc(docId);
  }

  function handleDocumentUpload(file) {
    if (!file) return;
    setUploadingDoc(true);
    router.post(
      route('cases.documents.store', caseFile.id),
      { file },
      {
        preserveScroll: true,
        onSuccess: () => setUploadingDoc(false),
        onError: () => setUploadingDoc(false),
      },
    );
  }

  const initialEditRef = useRef({ status: caseFile.status, issueId: caseFile.case_issue_id || '', categoryIds: getCaseCategories(caseFile).map(c => c.id), vulnerability: caseFile.vulnerability_indicator || '', nokVulnerability: caseFile.nok_vulnerability_indicator || '', summary: caseFile.summary || '' });
  const editIntentHandledRef = useRef(false);
  const hasEditDirty = useMemo(() => (
    formStatus !== initialEditRef.current.status
    || formIssueId !== initialEditRef.current.issueId
    || JSON.stringify(formCategoryIds) !== JSON.stringify(initialEditRef.current.categoryIds)
    || formVulnerability !== initialEditRef.current.vulnerability
    || nokVulnerability !== initialEditRef.current.nokVulnerability
    || formSummary !== initialEditRef.current.summary
  ), [formStatus, formIssueId, formCategoryIds, formVulnerability, nokVulnerability, formSummary]);
  const { UnsavedModal, bypassNext } = useUnsavedChanges(hasEditDirty && isEditOpen);

  function openEditDetails() {
    const initial = {
      status: caseFile.status,
      issueId: caseFile.case_issue_id || '',
      categoryIds: getCaseCategories(caseFile).map(c => c.id),
      vulnerability: caseFile.vulnerability_indicator || '',
      nokVulnerability: caseFile.nok_vulnerability_indicator || '',
      summary: caseFile.summary || '',
    };

    initialEditRef.current = initial;
    setFormStatus(initial.status);
    setFormIssueId(initial.issueId);
    setFormCategoryIds(initial.categoryIds);
    setFormVulnerability(initial.vulnerability);
    setNokVulnerability(initial.nokVulnerability);
    setFormSummary(initial.summary);
    setIsEditOpen(true);
  }

  useEffect(() => {
    const query = page.url?.split('?')[1] || '';
    const shouldOpenEdit = new URLSearchParams(query).get('edit') === '1';

    if (shouldOpenEdit && !editIntentHandledRef.current) {
      editIntentHandledRef.current = true;
      openEditDetails();
    }
  }, [page.url]);

  const primaryAddress = client?.addresses?.[0] || null;
  const primaryEmployment = client?.employments?.[0] || null;
  const primaryNok = client?.nextOfKin?.find(n => n.is_primary) || client?.nextOfKin?.[0] || null;

  const canManageCaseDocuments = auth.user?.role === roles.CASE_MANAGER;
  const canManageReferrals = auth.user?.role === roles.CASE_MANAGER || auth.user?.role === roles.ADMIN;
  const clientTypeLabel = caseFile.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin';
  const clientFullName = client ? [client.first_name, client.middle_name, client.last_name, client.suffix].filter(Boolean).join(' ') : '';
  const clientInitials = client ? [client.first_name, client.last_name].filter(Boolean).map((part) => part[0]).join('').toUpperCase().slice(0, 2) : '';
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

  const activeReferralCount = useMemo(() => {
    return (caseFile.referrals || []).filter(
      (ref) => !['COMPLETED', 'REJECTED'].includes(ref.status),
    ).length;
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

  function handleSaveDetails() {
    if (saving) return;
    setSaving(true);
    bypassNext();
    router.patch(route('cases.update', caseFile.id), {
      status: formStatus,
      case_issue_id: formIssueId || null,
      vulnerability_indicator: formVulnerability,
      nok_vulnerability_indicator: nokVulnerability,
      summary: formSummary,
      category_ids: formCategoryIds,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setIsEditOpen(false);
        setSaving(false);
      },
      onError: () => setSaving(false),
    });
  }

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
          <div data-tour="case-actions" className="flex items-center gap-2 shrink-0">
            <a
            href={route('cases.export-pdf', caseFile.id)}
            target="_blank"
            className="px-3.5 min-h-[34px] bg-white text-[12px] font-bold text-[#233f82] border border-slate-200 rounded-md hover:bg-slate-50 transition-colors inline-flex items-center gap-1.5"
          >
            <span className="material-symbols-outlined text-[15px]">picture_as_pdf</span>
            Export PDF
          </a>
          <button
            type="button"
            onClick={() => setShowAuditLog(true)}
            className="px-3 min-h-[34px] bg-slate-100 text-[12px] font-bold text-slate-700 border border-slate-300 rounded-md hover:bg-slate-200 transition-colors inline-flex items-center gap-1.5 hidden"
          >
            <span className="material-symbols-outlined text-[16px]">history</span>
            Audit Log
          </button>
          {(caseFile.status === 'OPEN' || caseFile.status === 'CLOSED') && (
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

      <div className="mx-auto grid max-w-[1440px] grid-cols-1 gap-4 xl:grid-cols-12">
        <main className="space-y-4 xl:col-span-8">
          <section className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
            <div className="flex items-center justify-between gap-3 border-b border-[#dce3eb] px-5 py-4">
              <h3 className="text-[15px] font-bold text-[#172333]">Case Summary</h3>
              <div className="flex items-center gap-2">
                <button type="button" onClick={openEditDetails} className="inline-flex min-h-[34px] items-center gap-1.5 rounded-md bg-[#233f82] px-3.5 text-[12px] font-bold text-white transition hover:bg-[#172c63]">
                  <span className="material-symbols-outlined text-[15px]">edit</span>
                  Edit Details
                </button>
                <button type="button" onClick={() => setConfirmToggleStatus(true)} disabled={caseFile.status === 'OPEN' && hasActiveReferrals} className="inline-flex min-h-[34px] items-center gap-1.5 rounded-md bg-slate-100 px-3.5 text-[12px] font-bold text-slate-500 transition hover:bg-slate-200 disabled:cursor-not-allowed disabled:hover:bg-slate-100">
                  <span className="material-symbols-outlined text-[15px]">lock</span>
                  Close Case
                </button>
              </div>
            </div>
            {/* [&>div]:border-0 strips InfoCell's border-b/border-r dividers for this grid only */}
            <div className="grid grid-cols-1 md:grid-cols-3 [&>div]:border-0">
              <InfoCell label="Case No." value={caseFile.case_number} />
              <InfoCell label="Tracking ID" value={caseFile.tracker_number} />
              <InfoCell label="Date created" value={<>{formatDisplayDate(caseFile.created_at)}<span className="block text-[10px] font-normal text-slate-500">{formatDisplayTime(caseFile.created_at)}</span></>} />
              <InfoCell label="Client type" value={clientTypeLabel} />
              <InfoCell label="Category" value={<CategoryBadges caseFile={caseFile} />} />
              <InfoCell label="Status" value={<StatusBadge status={caseFile.status} size="sm" />} />
              <InfoCell label="Issue / concern" value={caseFile.case_issue?.name || '-'} />
              <InfoCell label="Case narrative" value={caseFile.summary || '-'} />
              <InfoCell label="Opened by" value={caseFile.user?.name || '-'} />
            </div>
            <p className="border-t border-[#dce3eb] px-5 py-3 text-center text-[11px] text-slate-400">Close Case is unavailable while referrals are active. All referrals must be completed or rejected.</p>
          </section>

          {/* Referrals table */}
          <section data-tour="case-referrals" className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
            <CardHeader
              title="Referrals"
              meta={`${(caseFile.referrals || []).length} referrals · ${activeReferralCount} active`}
              actions={(
                <>
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
              {caseFile.status === 'OPEN' && hasActiveReferrals && (
                <div className="flex items-start gap-2.5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5">
                  <span className="material-symbols-outlined text-[16px] text-amber-600 shrink-0 mt-px">warning</span>
                  <p className="text-[11px] leading-5 text-amber-800">
                    This case cannot be closed until all referrals are completed or rejected. Resolve the active referrals below first.
                  </p>
                </div>
              )}
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
                        <select
                          value={timelineAgencyFilter}
                          onChange={e => setTimelineAgencyFilter(e.target.value)}
                          aria-label="Filter events by agency"
                          className="min-h-[34px] cursor-pointer appearance-none rounded-md border border-slate-200 bg-white pl-3 pr-8 text-[12px] font-semibold text-slate-600 outline-none transition-colors hover:border-slate-300 focus:ring-1 focus:ring-blue-900"
                        >
                          <option value="ALL">All agencies</option>
                          {timelineAgencyNames.map(a => <option key={a} value={a}>{a}</option>)}
                        </select>
                        <span className="material-symbols-outlined pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 text-[16px] text-slate-400">expand_more</span>
                      </div>
                    )}
                    {/* Type filter */}
                    <div className="relative inline-flex">
                      <select
                        value={timelineTypeFilter}
                        onChange={e => setTimelineTypeFilter(e.target.value)}
                        aria-label="Filter events by type"
                        className="min-h-[34px] cursor-pointer appearance-none rounded-md border border-slate-200 bg-white pl-3 pr-8 text-[12px] font-semibold text-slate-600 outline-none transition-colors hover:border-slate-300 focus:ring-1 focus:ring-blue-900"
                      >
                        {EVENT_TYPE_OPTIONS.map(opt => (
                          <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                      </select>
                      <span className="material-symbols-outlined pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 text-[16px] text-slate-400">expand_more</span>
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
                {milestoneTimeline && milestoneTimeline.length > 0 && (
                  <p className="mb-2 text-[11px] font-medium text-slate-400">
                    Showing {filteredTimeline.length} of {milestoneTimeline.length} events
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
          <section data-tour="case-client-info" className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
            <CardHeader title="Client Information" />
            <div className="space-y-4 px-5 pb-5">
              {/* Avatar */}
              {client ? (
                client.avatar_url ? (
                  <img
                    src={client.avatar_url}
                    alt=""
                    className="h-[88px] w-[88px] shrink-0 rounded-2xl border border-slate-200 object-cover"
                    onError={(e) => { e.target.style.display = 'none'; }}
                  />
                ) : (
                  <span
                    className={`inline-flex h-[88px] w-[88px] shrink-0 items-center justify-center rounded-2xl text-[26px] font-bold text-white ${getAvatarColor(clientFullName)}`}
                    aria-hidden="true"
                  >
                    {clientInitials || '?'}
                  </span>
                )
              ) : null}

              <InfoField label="Full name" value={clientFullName} />

              <InfoField label="Date of birth" value={client?.date_of_birth ? formatDisplayDate(client.date_of_birth) : null} />

              <div className="grid grid-cols-2 gap-3">
                <InfoField label="Age" value={client?.date_of_birth ? getClientAge(client.date_of_birth) : null} />
                <InfoField label="Sex" value={client?.sex} />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <InfoField label="Email" value={client?.email} />
                <InfoField label="Contact no." value={client?.contact_number} />
              </div>

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

              <InfoField label="Address" value={primaryAddress ? formatAddress(primaryAddress) : null} fallback="No address recorded" />

              {primaryEmployment && (
                <>
                  <hr className="border-slate-200" />
                  <div className="grid grid-cols-2 gap-3">
                    <InfoField
                      label="Work history"
                      value={`${primaryEmployment.last_country || primaryEmployment.country || 'N/A'} · ${primaryEmployment.last_position || primaryEmployment.position || 'N/A'}`}
                    />
                    <InfoField label="Arrival date" value={primaryEmployment.date_of_arrival ? formatDisplayDate(primaryEmployment.date_of_arrival) : null} />
                  </div>
                </>
              )}
            </div>
          </section>

          <section data-tour="case-documents" className="rounded-xl border border-[#dce3eb] bg-white shadow-[0_1px_3px_rgba(23,35,51,0.04)]">
            <CardHeader
              title="Documents"
              meta={`${(caseFile.documents || []).length} documents`}
              actions={canManageCaseDocuments && (
                <FileUpload
                  variant="button"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  maxSize={10 * 1024 * 1024}
                  label={uploadingDoc ? 'Uploading...' : 'Upload New File'}
                  disabled={uploadingDoc}
                  onFilesSelected={handleDocumentUpload}
                />
              )}
            />
            <div className="space-y-4 px-5 pb-5">
              <div className="flex items-start gap-2 rounded-md border border-blue-100 bg-blue-50 px-3 py-2">
                <span className="material-symbols-outlined text-[16px] text-blue-600 mt-0.5">info</span>
                <p className="text-[11px] leading-5 text-blue-800">
                  Everything uploaded to this section will be viewable to all referred agencies.
                </p>
              </div>

              {caseFile.documents?.length > 0 ? (
                <div className="space-y-2">
                  {caseFile.documents.map((doc) => {
                    const canDelete = canManageCaseDocuments;
                    return (
                      <div key={doc.id} className="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                        <div className="min-w-0">
                          <p className="text-[12px] font-semibold text-slate-700 truncate">{doc.file_name}</p>
                          <p className="text-[10px] text-slate-500">
                            {doc.user?.name || 'Unknown'} &middot; {doc.created_at ? formatDisplayDateTime(doc.created_at) : ''}
                            {doc.size ? ` \u00b7 ${(doc.size / 1024).toFixed(1)} KB` : ''}
                          </p>
                        </div>
                        <div className="flex items-center gap-2 shrink-0 ml-2">
                          <a
                            href={route('cases.documents.download', { case: caseFile.id, document: doc.id })}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-slate-500 hover:text-blue-900"
                          >
                            <Eye className="h-4 w-4" />
                          </a>
                          {canDelete && (
                            <button
                              type="button"
                              onClick={() => handleDocumentDelete(doc.id)}
                              className="text-slate-400 hover:text-red-500 transition-colors"
                            >
                              <Trash2 className="h-4 w-4" />
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
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
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

      {isEditOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" onClick={() => setIsEditOpen(false)}>
          <div className="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-lg owb-modal-animate" onClick={(e) => e.stopPropagation()}>
            <div className="border-b border-slate-200 px-5 py-4">
              <h2 className="text-[16px] font-extrabold text-slate-900">Edit Case Details</h2>
              <p className="mt-1 text-[12px] text-slate-500">Update visible case details for this record.</p>
            </div>

            <div className="grid grid-cols-1 gap-4 px-5 py-4 md:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Case Status</label>
                <select
                  value={formStatus}
                  onChange={(e) => setFormStatus(e.target.value)}
                  className="h-10 w-full rounded-md border border-slate-200 px-3 py-2 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                  <option value="OPEN">Open</option>
                  <option value="CLOSED">Closed</option>
                  <option value="ARCHIVED">Archived</option>
                </select>
              </div>

              <div>
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Issue/Concern</label>
                <select
                  value={formIssueId}
                  onChange={(e) => setFormIssueId(e.target.value)}
                  className="h-10 w-full rounded-md border border-slate-200 px-3 py-2 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                  <option value="">None</option>
                  {caseFile.case_issue && !caseIssues.some(i => i.id === caseFile.case_issue_id) && (
                    <option value={caseFile.case_issue_id}>{caseFile.case_issue.name}</option>
                  )}
                  {caseIssues.map((issue) => (
                    <option key={issue.id} value={issue.id}>{issue.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Vulnerability</label>
                <div className="flex flex-wrap gap-3">
                  {['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'].map((opt) => {
                    const val = formVulnerability || '';
                    const checked = val !== '' && val !== 'None' && val.split(',').map(s => s.trim()).includes(opt);
                    return (
                      <label key={opt} className="inline-flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => {
                            const current = val && val !== 'None' ? val.split(',').map(s => s.trim()).filter(Boolean) : [];
                            const next = checked ? current.filter(v => v !== opt) : [...current, opt];
                            setFormVulnerability(next.length > 0 ? next.join(', ') : '');
                          }}
                          className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span className="text-[13px] text-slate-700">{opt}</span>
                      </label>
                    );
                  })}
                </div>
              </div>

              <div className="md:col-span-2">
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">NOK Vulnerability</label>
                <div className="flex flex-wrap gap-3">
                  {['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'].map((opt) => {
                    const val = nokVulnerability || '';
                    const checked = val !== '' && val !== 'None' && val.split(',').map(s => s.trim()).includes(opt);
                    return (
                      <label key={opt} className="inline-flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => {
                            const current = val && val !== 'None' ? val.split(',').map(s => s.trim()).filter(Boolean) : [];
                            const next = checked ? current.filter(v => v !== opt) : [...current, opt];
                            setNokVulnerability(next.length > 0 ? next.join(', ') : '');
                          }}
                          className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span className="text-[13px] text-slate-700">{opt}</span>
                      </label>
                    );
                  })}
                </div>
              </div>

              <div className="md:col-span-2">
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Category</label>
                <div className="flex flex-wrap gap-3">
                  {categories.map((cat) => {
                    const checked = formCategoryIds.includes(cat.id);
                    return (
                      <label key={cat.id} className="inline-flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => {
                            setFormCategoryIds(prev => checked ? prev.filter(id => id !== cat.id) : [...prev, cat.id]);
                          }}
                          className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span className="inline-flex items-center gap-1.5 text-[13px] text-slate-700">
                          {cat.color && <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: cat.color }} />}
                          {cat.name}
                        </span>
                      </label>
                    );
                  })}
                  {categories.length === 0 && (
                    <span className="text-[12px] text-slate-400">No categories available.</span>
                  )}
                </div>
              </div>

              <div className="md:col-span-2">
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Case Narrative</label>
                <textarea
                  rows={5}
                  value={formSummary}
                  onChange={(e) => setFormSummary(e.target.value)}
                  className="w-full rounded-md border border-slate-200 px-3 py-2 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                />
              </div>
            </div>

            <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
              <button
                type="button"
                onClick={() => setIsEditOpen(false)}
                className="h-9 rounded-md border border-slate-200 px-3 text-[12px] font-bold text-slate-700"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleSaveDetails}
                disabled={saving}
                className="h-9 rounded-md bg-blue-900 px-3 text-[12px] font-bold text-white disabled:opacity-60"
              >
                {saving ? 'Saving...' : 'Save Changes'}
              </button>
            </div>
          </div>
        </div>
      )}

      {UnsavedModal}
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
