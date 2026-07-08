import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import { useToast } from '@/Hooks/useToast';
import { Eye, Trash2 } from 'lucide-react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';
import FileUpload from '@/Components/FileUpload';
import { CardSection, MetaTile } from '@/Components/ui/CardSection';
import StatusBadge from '@/Components/ui/StatusBadge';
import ProfilePictureUpload from '@/Components/ProfilePictureUpload';
import { getInitials, getAvatarColor } from '@/Components/ui/UserAvatar';
import { formatDisplayDateTime, formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import { formatResolvedAddress } from '@/lib/addressResolver';

const vulnConfig = {
  'PWD': { icon: 'accessibility', className: 'bg-purple-100 text-purple-800 border-purple-200' },
  'Senior Citizen': { icon: 'elderly', className: 'bg-orange-100 text-orange-800 border-orange-200' },
  'Solo Parent': { icon: 'family_home', className: 'bg-pink-100 text-pink-800 border-pink-200' },
  'Indigenous Person': { icon: 'groups', className: 'bg-teal-100 text-teal-800 border-teal-200' },
};

function getCaseAgeDays(createdAt, status, updatedAt) {
  const created = new Date(createdAt).getTime();
  const end = status === 'CLOSED' ? new Date(updatedAt).getTime() : Date.now();
  const days = Math.max(1, Math.round((end - created) / (1000 * 60 * 60 * 24)));
  return `${days} day${days > 1 ? 's' : ''}`;
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

export default function CaseShow({ case: caseFile, overdueDays = 7, milestoneTimeline = [] }) {
  const EVENT_CONFIG = {
    case_opened:     { dot: 'bg-blue-50 border-blue-200 text-blue-600',       icon: 'folder' },
    referral_sent:   { dot: 'bg-purple-50 border-purple-200 text-purple-600',   icon: 'forward_to_inbox' },
    referral_status: { dot: 'bg-amber-50 border-amber-200 text-amber-600',       icon: 'sync_alt' },
    milestone:       { dot: 'bg-emerald-50 border-emerald-200 text-emerald-600', icon: 'flag' },
    case_closed:     { dot: 'bg-slate-100 border-slate-200 text-slate-600',     icon: 'lock' },
  };

  const EVENT_TYPE_OPTIONS = [
    { value: 'ALL',          label: 'All Events' },
    { value: 'case_opened',  label: 'Case Opened' },
    { value: 'referral',     label: 'Referrals' },
    { value: 'referral_status', label: 'Status Updates' },
    { value: 'milestone',    label: 'Milestones' },
    { value: 'case_closed',  label: 'Case Closed' },
  ];

  function formatEventDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) {
      return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function formatHumanDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }) + ' at ' + date.toLocaleTimeString('en-PH', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    });
  }

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
      items = items.filter(i => i.type === 'referral_sent' || i.type === 'referral_status');
    } else if (timelineTypeFilter !== 'ALL') {
      items = items.filter(i => i.type === timelineTypeFilter);
    }
    return items.reverse();
  }, [milestoneTimeline, timelineAgencyFilter, timelineTypeFilter]);

  const hasActiveFilters = timelineAgencyFilter !== 'ALL' || timelineTypeFilter !== 'ALL';

  const clearFilters = () => {
    setTimelineAgencyFilter('ALL');
    setTimelineTypeFilter('ALL');
  };

  const page = usePage();
  const { auth } = page.props;
  const client = caseFile.client;
  const toast = useToast();
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [showReferralPrompt, setShowReferralPrompt] = useState(!!page.props.just_published);
  const [showOverdueInfo, setShowOverdueInfo] = useState(false);
  const [formStatus, setFormStatus] = useState(caseFile.status);
  const [formClientType, setFormClientType] = useState(caseFile.client_type);
  const [formVulnerability, setFormVulnerability] = useState(caseFile.vulnerability_indicator || '');
  const [nokVulnerability, setNokVulnerability] = useState(caseFile.nok_vulnerability_indicator || '');
  const [formSummary, setFormSummary] = useState(caseFile.summary || '');
  const [saving, setSaving] = useState(false);
  const [uploadingDoc, setUploadingDoc] = useState(false);

  function handleDocumentDelete(docId) {
    if (confirm('Are you sure you want to delete this document?')) {
      router.delete(route('cases.documents.destroy', [caseFile.id, docId]), {
        preserveScroll: true,
      });
    }
  }

  const initialEditRef = useRef({ status: caseFile.status, clientType: caseFile.client_type, vulnerability: caseFile.vulnerability_indicator || '', nokVulnerability: caseFile.nok_vulnerability_indicator || '', summary: caseFile.summary || '' });
  const editIntentHandledRef = useRef(false);
  const hasEditDirty = useMemo(() => (
    formStatus !== initialEditRef.current.status
    || formClientType !== initialEditRef.current.clientType
    || formVulnerability !== initialEditRef.current.vulnerability
    || nokVulnerability !== initialEditRef.current.nokVulnerability
    || formSummary !== initialEditRef.current.summary
  ), [formStatus, formClientType, formVulnerability, nokVulnerability, formSummary]);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasEditDirty && isEditOpen);

  function openEditDetails() {
    const initial = {
      status: caseFile.status,
      clientType: caseFile.client_type,
      vulnerability: caseFile.vulnerability_indicator || '',
      nokVulnerability: caseFile.nok_vulnerability_indicator || '',
      summary: caseFile.summary || '',
    };

    initialEditRef.current = initial;
    setFormStatus(initial.status);
    setFormClientType(initial.clientType);
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

  const canUploadAvatar = auth.user?.role === 'ADMIN' || auth.user?.role === 'CASE_MANAGER';
  const canManageCaseDocuments = auth.user?.role === 'CASE_MANAGER';
  const clientTypeLabel = caseFile.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin';

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
        dateReferred: formatDisplayDateTime(ref.created_at),
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
      render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.agency}</span>,
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
      render: (row) => <span className="text-[12px] text-slate-500">{row.dateReferred}</span>,
    },
    {
      key: 'action',
      title: 'ACTION',
      className: 'w-[7%] whitespace-nowrap text-right align-top',
      render: (row) => (
        <Link
          href={route('referrals.show', row.id)}
          className="inline-flex px-2 min-h-[28px] items-center bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300"
        >
          View
        </Link>
      ),
    },
  ];

  function handleSaveDetails() {
    setSaving(true);
    bypassNext();
    router.patch(route('cases.update', caseFile.id), {
      status: formStatus,
      client_type: formClientType,
      vulnerability_indicator: formVulnerability,
      nok_vulnerability_indicator: nokVulnerability,
      summary: formSummary,
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
    router.post(route('cases.archive', caseFile.id), {}, {
      preserveScroll: true,
    });
  }

  function handleUnarchive() {
    router.post(route('cases.unarchive', caseFile.id), {}, {
      preserveScroll: true,
    });
  }

  return (
    <AppLayout title="Case Details">
      <Head title="Case Details" />

      <div className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500 mb-5">
        <Link href={route('cases.index')} className="transition hover:text-blue-900">Cases</Link>
        <span className="mx-2">&gt;</span>
        <span>{caseFile.case_number}</span>
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

      <div data-tour="case-header" className="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
          <h1 className="text-3xl md:text-[34px] font-black leading-tight tracking-tight text-slate-900">Case Details</h1>
          <p className="mt-1 text-[14px] leading-6 text-slate-600">Overview of client profile, referral progress, and timeline updates.</p>
        </div>
        <div data-tour="case-actions" className="flex items-center gap-2 shrink-0">
          <StatusBadge status={caseFile.status} size="md" />
          {caseFile.user && (
            <div className="flex items-center gap-1.5 px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-md">
              <span className="w-5 h-5 rounded-full bg-slate-700 text-white text-[8px] font-bold flex items-center justify-center shrink-0">
                {caseFile.user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)}
              </span>
              <span className="text-[11px] font-medium text-slate-600">
                Created by <span className="font-bold text-slate-800">{caseFile.user.name}</span>
              </span>
            </div>
          )}
          <button
            type="button"
            onClick={openEditDetails}
            className="px-3 min-h-[32px] bg-slate-100 text-slate-700 hover:bg-slate-200 text-[12px] font-bold rounded-md transition-colors border border-slate-300"
          >
            Edit Details
          </button>
          <button
            type="button"
            onClick={handleToggleStatus}
            disabled={caseFile.status === 'OPEN' && hasActiveReferrals}
            title={caseFile.status === 'OPEN' && hasActiveReferrals ? 'Resolve all referrals before closing this case.' : ''}
            className={`px-3 min-h-[32px] text-[12px] font-bold rounded-md transition-colors border ${
              caseFile.status === 'OPEN' && hasActiveReferrals
                ? 'bg-gray-200 text-gray-500 border-gray-300 cursor-not-allowed'
                : 'bg-blue-900 text-white hover:bg-blue-800 border-blue-900'
            }`}
          >
            {caseFile.status === 'OPEN' ? 'Close Case' : 'Reopen Case'}
          </button>
          {caseFile.status === 'ARCHIVED' ? (
            <button
              type="button"
              onClick={handleUnarchive}
              className="px-3 min-h-[32px] bg-gray-200 text-gray-700 hover:bg-gray-300 text-[12px] font-bold rounded-md transition-colors border border-gray-300"
            >
              Restore from Archive
            </button>
          ) : caseFile.status === 'CLOSED' ? (
            <button
              type="button"
              onClick={handleArchive}
              className="px-3 min-h-[32px] bg-gray-100 text-gray-600 hover:bg-gray-200 text-[12px] font-bold rounded-md transition-colors border border-gray-300"
            >
              Archive Case
            </button>
          ) : null}
          <Link
            href={route('cases.index')}
            className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors shrink-0"
          >
            &larr; Back to Cases
          </Link>
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <main className="xl:col-span-8 space-y-4">
          {/* Key Stats Ribbon */}
          <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1.5 rounded-md border border-slate-200 bg-white px-4 py-2.5 shadow-sm">
            <StatusBadge status={caseFile.status} size="sm" />
            <span className="text-[11px] text-slate-300 select-none">|</span>
            <span className="text-[11px] text-slate-600">
              <span className="font-semibold text-slate-800">{getCaseAgeDays(caseFile.created_at, caseFile.status, caseFile.updated_at)}</span>
              <span className="text-slate-400 ml-1">since created</span>
            </span>
            <span className="text-[11px] text-slate-300 select-none">|</span>
            <span className="text-[11px] text-slate-600">
              <span className="font-semibold text-slate-800">{(caseFile.referrals || []).length}</span>
              <span className="text-slate-400 ml-1">referral{(caseFile.referrals || []).length !== 1 ? 's' : ''}</span>
            </span>
            {hasOverdueReferrals && (
              <>
                <span className="text-[11px] text-slate-300 select-none">|</span>
                <span className="inline-flex items-center gap-1 text-[11px] text-red-600 font-semibold">
                  <span className="material-symbols-outlined text-[14px]">warning</span>
                  {referralRows.filter(r => r.isOverdue).length} overdue
                </span>
              </>
            )}
            <span className="text-[11px] text-slate-300 select-none">|</span>
            <span className="text-[11px] text-slate-600">
              <span className="font-semibold text-slate-800">{clientTypeLabel}</span>
            </span>
            {caseFile.category && (
              <>
                <span className="text-[11px] text-slate-300 select-none">|</span>
                <span className="text-[11px] text-slate-600">
                  {caseFile.category.color && (
                    <span className="w-2 h-2 rounded-full inline-block mr-1" style={{ backgroundColor: caseFile.category.color }} />
                  )}
                  <span className="font-semibold text-slate-800">{caseFile.category.name}</span>
                </span>
              </>
            )}
          </div>

          <CardSection title="Case Information" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
              <MetaTile label="Case No." value={caseFile.case_number} />
              <MetaTile label="Tracking ID" value={caseFile.tracker_number} />
              <MetaTile label="Client Type" value={clientTypeLabel} />
              <MetaTile label="Date Created" value={formatDisplayDate(caseFile.created_at)} subtext={formatDisplayTime(caseFile.created_at)} />
              {caseFile.category && (
                <MetaTile label="Category" value={
                  <span className="inline-flex items-center gap-1.5">
                    {caseFile.category.color && (
                      <span className="w-2.5 h-2.5 rounded-full inline-block shrink-0" style={{ backgroundColor: caseFile.category.color }} />
                    )}
                    {caseFile.category.name}
                  </span>
                } />
              )}
              {caseFile.case_issue && (
                <MetaTile label="Issue/Concern" value={caseFile.case_issue.name} />
              )}
            </div>
          </CardSection>

          {/* Case Narrative — moved to top of main column */}
          <CardSection title="Case Narrative" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
            {caseFile.summary ? (
              <p className="text-[13px] leading-6 text-slate-700 whitespace-pre-wrap">{caseFile.summary}</p>
            ) : (
              <p className="text-[12px] text-slate-500 italic">No narrative recorded for this case.</p>
            )}
          </CardSection>

          {/* Referrals table — unchanged */}
          <CardSection title={`Referrals (${(caseFile.referrals || []).length})`} className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <h4 className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Agency Referrals</h4>
                {hasOverdueReferrals && (
                  <div className="relative">
                    <button
                      type="button"
                      onClick={() => setShowOverdueInfo((prev) => !prev)}
                      className="flex h-[18px] w-[18px] items-center justify-center rounded-full text-red-500 hover:bg-red-100 transition-colors"
                    >
                      <span className="material-symbols-outlined text-[14px]">info</span>
                    </button>
                    {showOverdueInfo && (
                      <div className="absolute left-0 top-full mt-1 z-20 w-72 rounded-md border border-red-200 bg-red-50 px-3 py-2 shadow-md">
                        <p className="text-[10px] leading-5 text-red-800">
                          A referral is considered overdue when there has been no update or activity for more than {overdueDays} day{overdueDays > 1 ? 's' : ''}.
                        </p>
                      </div>
                    )}
                  </div>
                )}
              </div>
              <Link
                href={route('referrals.create', { case_id: caseFile.id })}
                className="px-3 min-h-[30px] inline-flex items-center bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300"
              >
                + Refer to Agency
              </Link>
            </div>
            {caseFile.status === 'OPEN' && hasActiveReferrals && (
              <div className="mb-3 flex items-start gap-2.5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5">
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
          </CardSection>

          <div data-tour="case-timeline">
          <CardSection title="Activity Timeline" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div className="flex items-center gap-2">
                <span className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Chronological Events</span>
                {milestoneTimeline && milestoneTimeline.length > 0 && (
                  <span className="text-[10px] text-slate-400">({milestoneTimeline.length} event{milestoneTimeline.length !== 1 ? 's' : ''})</span>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-2">
                {/* Agency filter */}
                {timelineAgencyNames.length > 0 && (
                  <select
                    value={timelineAgencyFilter}
                    onChange={e => setTimelineAgencyFilter(e.target.value)}
                    className="text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-600 border border-slate-200 rounded-md px-2.5 py-1.5 bg-white hover:border-slate-300 transition-colors cursor-pointer outline-none focus:ring-1 focus:ring-blue-900"
                  >
                    <option value="ALL">All Agencies</option>
                    {timelineAgencyNames.map(a => <option key={a} value={a}>{a}</option>)}
                  </select>
                )}
                {/* Type filter */}
                <select
                  value={timelineTypeFilter}
                  onChange={e => setTimelineTypeFilter(e.target.value)}
                  className="text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-600 border border-slate-200 rounded-md px-2.5 py-1.5 bg-white hover:border-slate-300 transition-colors cursor-pointer outline-none focus:ring-1 focus:ring-blue-900"
                >
                  {EVENT_TYPE_OPTIONS.map(opt => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                  ))}
                </select>
                {/* Clear filters */}
                {hasActiveFilters && (
                  <button
                    onClick={clearFilters}
                    className="text-[10px] font-extrabold uppercase tracking-[0.08em] text-blue-600 hover:text-blue-800 px-2 py-1.5 transition-colors"
                  >
                    Clear
                  </button>
                )}
              </div>
            </div>

            {/* Filter results count */}
            {milestoneTimeline && milestoneTimeline.length > 0 && (
              <p className="mt-2 text-[10px] text-slate-400 font-medium px-0.5">
                Showing {filteredTimeline.length} of {milestoneTimeline.length} events
              </p>
            )}

            {filteredTimeline.length === 0 ? (
              <div className="mt-4 bg-white rounded-xl border border-slate-200 p-10 text-center">
                <span className="material-symbols-outlined text-4xl text-slate-300 block mb-2">history</span>
                <p className="text-sm text-slate-500">No activity matches your filters.</p>
                {hasActiveFilters && (
                  <button onClick={clearFilters} className="mt-2 text-xs font-bold text-blue-600 hover:text-blue-800 underline">
                    Clear filters
                  </button>
                )}
              </div>
            ) : (
              <div className="relative mt-4">
                <div className="absolute left-[13px] top-2 bottom-2 w-px bg-slate-200" />
                <div className="space-y-6">
                  {filteredTimeline.map((item, index) => {
                    const cfg = EVENT_CONFIG[item.type] ?? EVENT_CONFIG.milestone;
                    return (
                      <div key={`${item.date}-${index}`} className="relative flex gap-4 items-start group">
                        <div className={`z-10 flex h-7 w-7 items-center justify-center rounded-full border bg-white ${cfg.dot} shadow-sm shrink-0`}>
                          <span className="material-symbols-outlined text-[14px]">{cfg.icon}</span>
                        </div>
                        <div className="min-w-0 pt-0.5 flex-1">
                          <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-tight">
                              {formatEventDate(item.date)}
                            </span>
                            <span className="text-[10px] text-slate-400 font-medium hidden sm:inline">
                              {formatHumanDate(item.date)}
                            </span>
                            {item.agency && (
                              <span className="text-[11px] font-semibold text-slate-400 border-l border-slate-200 pl-2">
                                {item.agency}
                              </span>
                            )}
                          </div>
                          <h4 className="text-sm font-bold text-slate-900 mt-1 leading-snug">{item.title}</h4>
                          {item.description && (
                            <p className="text-xs text-slate-500 mt-1 leading-relaxed max-w-prose">{item.description}</p>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
          </CardSection>
          </div>

        </main>

        <aside className="xl:col-span-4 space-y-4">
          {/* Client Profile — compact all-in-one card */}
          <CardSection title="Client Profile" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
            <div className="space-y-4">
              {/* Avatar + Name */}
              {client ? (
                <div className="flex items-start gap-3 pb-3 border-b border-slate-200">
                  {canUploadAvatar ? (
                    <ProfilePictureUpload
                      currentUrl={client.avatar_url}
                      name={[client.first_name, client.last_name].filter(Boolean).join(' ')}
                      size="md"
                      clientId={client.id}
                    />
                  ) : client.avatar_url ? (
                    <img src={client.avatar_url} alt="" className="h-12 w-12 rounded-full object-cover border border-slate-200 flex-shrink-0" />
                  ) : (
                    <span className={`h-12 w-12 inline-flex items-center justify-center rounded-full text-white text-[15px] font-bold flex-shrink-0 ${getAvatarColor([client.first_name, client.last_name].filter(Boolean).join(' '))}`}>
                      {getInitials([client.first_name, client.last_name].filter(Boolean).join(' '))}
                    </span>
                  )}
                  <div className="min-w-0 flex-1 self-center">
                    <p className="text-[14px] font-bold text-slate-800 break-words">
                      {[client.first_name, client.middle_initial, client.last_name, client.suffix].filter(Boolean).join(' ')}
                    </p>
                    <p className="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500">{clientTypeLabel}</p>
                  </div>
                </div>
              ) : (
                <p className="text-[12px] font-semibold text-slate-700">N/A</p>
              )}

              {/* DOB · Age · Sex */}
              <div className="grid grid-cols-3 gap-2">
                <div className="min-w-0">
                  <p className="text-[8px] font-extrabold uppercase tracking-[0.06em] text-slate-500 break-words">Date of Birth</p>
                  <p className="mt-0.5 text-[12px] font-semibold text-slate-700 break-words">{client?.date_of_birth ? formatDisplayDate(client.date_of_birth) : 'N/A'}</p>
                </div>
                <div>
                  <p className="text-[8px] font-extrabold uppercase tracking-[0.06em] text-slate-500">Age</p>
                  <p className="mt-0.5 text-[12px] font-semibold text-slate-700">{client?.date_of_birth ? getClientAge(client.date_of_birth) : 'N/A'}</p>
                </div>
                <div>
                  <p className="text-[8px] font-extrabold uppercase tracking-[0.06em] text-slate-500">Sex</p>
                  <p className="mt-0.5 text-[12px] font-semibold text-slate-700">{client?.sex || 'N/A'}</p>
                </div>
              </div>

              {/* Email · Contact */}
              <div className="grid grid-cols-2 gap-2">
                <div className="min-w-0">
                  <p className="text-[8px] font-extrabold uppercase tracking-[0.06em] text-slate-500">Email</p>
                  <p className="mt-0.5 text-[12px] font-semibold text-slate-700 break-words">{client?.email || 'N/A'}</p>
                </div>
                <div className="min-w-0">
                  <p className="text-[8px] font-extrabold uppercase tracking-[0.06em] text-slate-500">Contact No.</p>
                  <p className="mt-0.5 text-[12px] font-semibold text-slate-700 break-words">{client?.contact_number || 'N/A'}</p>
                </div>
              </div>

              {/* Address - full width */}
              <div>
                <p className="text-[9px] font-extrabold uppercase tracking-[0.08em] text-slate-500">Address</p>
                <p className="mt-0.5 text-[12px] font-semibold text-slate-700">{primaryAddress ? formatAddress(primaryAddress) : 'No address recorded'}</p>
              </div>

              {/* Work History — compact inline */}
              {primaryEmployment && (
                <>
                  <hr className="border-slate-200" />
                  <div>
                    <p className="text-[9px] font-extrabold uppercase tracking-[0.08em] text-slate-500">Work History</p>
                    <div className="mt-2 space-y-2">
                      <div className="flex items-center gap-2 text-[12px]">
                        <span className="font-semibold text-slate-700 shrink-0">{primaryEmployment.last_country || primaryEmployment.country || 'N/A'}</span>
                        <span className="text-slate-300">·</span>
                        <span className="text-slate-600 truncate">{primaryEmployment.last_position || primaryEmployment.position || 'N/A'}</span>
                      </div>
                      {primaryEmployment.date_of_arrival && (
                        <p className="text-[11px] text-slate-500">Arrived {formatDisplayDate(primaryEmployment.date_of_arrival)}</p>
                      )}
                    </div>
                  </div>
                </>
              )}

              {/* Next of Kin — compact */}
              {client?.nextOfKin?.length > 0 && (
                <>
                  <hr className="border-slate-200" />
                  <div>
                    <div className="flex items-center justify-between">
                      <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Next of Kin</p>
                      <p className="text-[9px] text-slate-400">{client.nextOfKin.length} record{client.nextOfKin.length !== 1 ? 's' : ''}</p>
                    </div>
                    <div className="mt-2 space-y-2">
                      {client.nextOfKin.map((nok, idx) => (
                        <div key={nok.id} className={`text-[12px] text-slate-700 ${idx > 0 ? 'pt-2 border-t border-slate-200' : ''}`}>
                          <div className="flex items-center gap-2">
                            <span className="font-semibold">{[nok.first_name, nok.last_name].filter(Boolean).join(' ')}</span>
                            {nok.is_primary && (
                              <span className="inline-flex items-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-[0.1em] text-white">Primary</span>
                            )}
                          </div>
                          <div className="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-slate-500">
                            {nok.relationship && <span>{nok.relationship}</span>}
                            {nok.phone_number && <span>{nok.phone_number}</span>}
                            {nok.email && <span className="break-all">{nok.email}</span>}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </>
              )}

              {/* Vulnerability — compact badges */}
              {((caseFile.vulnerability_indicator && caseFile.vulnerability_indicator !== 'None') || (caseFile.nok_vulnerability_indicator && caseFile.nok_vulnerability_indicator !== 'None')) && (
                <>
                  <hr className="border-slate-200" />
                  <div>
                    <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Vulnerability</p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      {caseFile.vulnerability_indicator && caseFile.vulnerability_indicator !== 'None' && (
                        <span className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-bold ${vulnConfig[caseFile.vulnerability_indicator]?.className || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                          <span className="material-symbols-outlined text-[13px]">{vulnConfig[caseFile.vulnerability_indicator]?.icon || 'warning'}</span>
                          OFW: {caseFile.vulnerability_indicator}
                        </span>
                      )}
                      {caseFile.nok_vulnerability_indicator && caseFile.nok_vulnerability_indicator !== 'None' && (
                        <span className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-bold ${vulnConfig[caseFile.nok_vulnerability_indicator]?.className || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                          <span className="material-symbols-outlined text-[13px]">{vulnConfig[caseFile.nok_vulnerability_indicator]?.icon || 'warning'}</span>
                          NOK: {caseFile.nok_vulnerability_indicator}
                        </span>
                      )}
                    </div>
                  </div>
                </>
              )}
            </div>
          </CardSection>

          <CardSection title="Case Documents" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
            <div className="mb-3 flex items-start gap-2 rounded-md border border-blue-100 bg-blue-50 px-3 py-2">
              <span className="material-symbols-outlined text-[16px] text-blue-600 mt-0.5">info</span>
              <p className="text-[11px] leading-5 text-blue-800">
                Everything uploaded to this section will be viewable to all referred agencies.
              </p>
            </div>
            <div className="space-y-4">
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
                <p className="text-[12px] text-slate-500">No case documents uploaded.</p>
              )}

              {canManageCaseDocuments && (
                <FileUpload
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  maxSize={10 * 1024 * 1024}
                  label={uploadingDoc ? 'Uploading...' : 'Upload New File'}
                  disabled={uploadingDoc}
                  onFilesSelected={(file) => {
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
                  }}
                />
              )}
            </div>
          </CardSection>
        </aside>
      </div>

      {isEditOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" onClick={() => setIsEditOpen(false)}>
          <div className="w-full max-w-2xl rounded-md border border-slate-200 bg-white shadow-lg" onClick={(e) => e.stopPropagation()}>
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
                  className="h-10 w-full rounded-md border border-slate-200 px-3 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                  <option value="OPEN">Open</option>
                  <option value="CLOSED">Closed</option>
                  <option value="ARCHIVED">Archived</option>
                </select>
              </div>

              <div>
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Client Type</label>
                <select
                  value={formClientType}
                  onChange={(e) => setFormClientType(e.target.value)}
                  className="h-10 w-full rounded-md border border-slate-200 px-3 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                  <option value="OFW">Overseas Filipino Worker</option>
                  <option value="NEXT_OF_KIN">Next of Kin</option>
                </select>
              </div>

              <div>
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Vulnerability</label>
                <select
                  value={formVulnerability}
                  onChange={(e) => setFormVulnerability(e.target.value)}
                  className="h-10 w-full rounded-md border border-slate-200 px-3 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                  <option value="">None</option>
                  <option value="PWD">PWD</option>
                  <option value="Senior Citizen">Senior Citizen</option>
                  <option value="Solo Parent">Solo Parent</option>
                  <option value="Indigenous Person">Indigenous Person</option>
                </select>
              </div>

              <div className="md:col-span-2">
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">NOK Vulnerability</label>
                <select
                  value={nokVulnerability}
                  onChange={(e) => setNokVulnerability(e.target.value)}
                  className="h-10 w-full rounded-md border border-slate-200 px-3 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900"
                >
                  <option value="">None</option>
                  <option value="PWD">PWD</option>
                  <option value="Senior Citizen">Senior Citizen</option>
                  <option value="Solo Parent">Solo Parent</option>
                  <option value="Indigenous Person">Indigenous Person</option>
                </select>
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

      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </AppLayout>
  );
}
