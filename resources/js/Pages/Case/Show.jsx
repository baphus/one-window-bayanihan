import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import { useToast } from '@/Hooks/useToast';
import { Eye, Trash2 } from 'lucide-react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import FileUpload from '@/Components/FileUpload';
import { CardSection, MetaTile } from '@/Components/ui/CardSection';
import StatusBadge from '@/Components/ui/StatusBadge';
import ProfilePictureUpload from '@/Components/ProfilePictureUpload';
import { getInitials, getAvatarColor } from '@/Components/ui/UserAvatar';
import { formatDisplayDateTime, formatDisplayDate, formatDisplayTime } from '@/lib/utils';

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
  if (!addr) return '';
  const parts = [];
  if (addr.street) parts.push(addr.street);
  if (addr.barangay) parts.push(addr.barangay);
  if (addr.city_municipality) parts.push(addr.city_municipality);
  if (addr.province) parts.push(addr.province);
  if (addr.region) parts.push(addr.region);
  return parts.join(', ');
}

function formatNokAddress(nok) {
  if (!nok) return 'N/A';
  const parts = [nok.street, nok.barangay, nok.city_municipality, nok.province, nok.region].filter(Boolean);
  return parts.length > 0 ? parts.join(', ') : (nok.full_address || 'N/A');
}

export default function CaseShow({ case: caseFile, overdueDays = 7 }) {
  const { auth } = usePage().props;
  const client = caseFile.client;
  const toast = useToast();
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [showReferralPrompt, setShowReferralPrompt] = useState(!!usePage().props.just_published);
  const [showOverdueInfo, setShowOverdueInfo] = useState(false);
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

  const initialEditRef = useRef({ clientType: caseFile.client_type, vulnerability: caseFile.vulnerability_indicator || '', nokVulnerability: caseFile.nok_vulnerability_indicator || '', summary: caseFile.summary || '' });
  const hasEditDirty = useMemo(() => (
    formClientType !== initialEditRef.current.clientType
    || formVulnerability !== initialEditRef.current.vulnerability
    || nokVulnerability !== initialEditRef.current.nokVulnerability
    || formSummary !== initialEditRef.current.summary
  ), [formClientType, formVulnerability, nokVulnerability, formSummary]);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasEditDirty && isEditOpen);

  const primaryAddress = client?.addresses?.[0] || null;
  const primaryEmployment = client?.employments?.[0] || null;
  const primaryNok = client?.nextOfKin?.find(n => n.is_primary) || client?.nextOfKin?.[0] || null;

  const canUploadAvatar = auth.user?.role === 'ADMIN' || auth.user?.role === 'CASE_MANAGER';
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

  const timelineItems = useMemo(() => {
    const items = [];
    items.push({
      id: `${caseFile.id}-created`,
      type: 'system',
      actor: caseFile.user?.name || 'Case Manager',
      agency: 'Bayanihan',
      title: 'Case Created',
      description: 'Case record was created in the Bayanihan portal.',
      timestamp: caseFile.created_at,
    });
    (caseFile.referrals || []).forEach((ref) => {
      items.push({
        id: `${ref.id}-referred`,
        type: 'referral',
        actor: caseFile.user?.name || 'Case Manager',
        agency: ref.agency?.name || 'Agency',
        title: `Referral Sent to ${ref.agency?.name || 'Agency'}`,
        description: `Case was referred for ${ref.required_services || 'services'}.`,
        timestamp: ref.created_at,
      });
      const milestones = ref.milestones || [];
      if (milestones.length > 0) {
        const latest = milestones.reduce((a, b) => new Date(a.created_at) > new Date(b.created_at) ? a : b);
        items.push({
          id: latest.id,
          type: 'milestone',
          actor: latest.user?.name || 'System',
          agency: ref.agency?.name || 'Agency',
          title: latest.title,
          description: latest.description || '',
          timestamp: latest.created_at,
        });
      }
    });
    if (caseFile.status === 'CLOSED') {
      items.push({
        id: `${caseFile.id}-closed`,
        type: 'system',
        actor: 'System',
        agency: 'Bayanihan',
        title: 'Case Closed',
        description: 'Case was closed after processing.',
        timestamp: caseFile.updated_at,
      });
    }
    items.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
    return items;
  }, [caseFile]);

  const timelineAgencies = useMemo(() => {
    const agencies = timelineItems
      .filter((item) => item.type !== 'system')
      .map((item) => item.agency)
      .filter(Boolean);
    return Array.from(new Set(agencies)).sort((a, b) => a.localeCompare(b));
  }, [timelineItems]);

  const [timelineFilter, setTimelineFilter] = useState('ALL');

  const filteredTimeline = useMemo(() => {
    if (timelineFilter === 'ALL') return timelineItems;
    return timelineItems.filter(
      (item) => item.type === 'system' || item.agency === timelineFilter
    );
  }, [timelineItems, timelineFilter]);

  const allAttachments = useMemo(() => {
    return (caseFile.referrals || []).flatMap((ref) =>
      (ref.attachments || []).map((att) => ({
        ...att,
        agencyName: ref.agency?.name || '',
      }))
    );
  }, [caseFile.referrals]);

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
            onClick={() => {
              setFormClientType(caseFile.client_type);
              setFormVulnerability(caseFile.vulnerability_indicator || '');
              setNokVulnerability(caseFile.nok_vulnerability_indicator || '');
              setFormSummary(caseFile.summary || '');
              setIsEditOpen(true);
            }}
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
                {caseFile.referrals?.some(ref => ref.type === 'intervention') && (
                  <span className="inline-flex items-center rounded-full bg-violet-600 px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.1em] text-white">
                    DMW Intervention
                  </span>
                )}
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

          {/* Timeline — moved from sidebar, full width */}
          <div data-tour="case-timeline">
          <CardSection title="Case Timeline" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <span className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Chronological Events</span>
                {filteredTimeline.length > 0 && (
                  <span className="text-[10px] text-slate-400">({filteredTimeline.length} event{filteredTimeline.length !== 1 ? 's' : ''})</span>
                )}
              </div>
              <select
                value={timelineFilter}
                onChange={(e) => setTimelineFilter(e.target.value)}
                className="h-[30px] w-[170px] max-w-full shrink-0 rounded-md border border-slate-200 bg-white px-2 text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-600"
              >
                <option value="ALL">All agencies</option>
                {timelineAgencies.map((agency) => (
                  <option key={agency} value={agency}>{agency}</option>
                ))}
              </select>
            </div>
            {filteredTimeline.length > 0 ? (
              <div className="relative mt-4">
                <div className="absolute left-[10px] top-1 bottom-1 w-px bg-slate-300" />
                <div className="flex flex-col-reverse gap-4">
                  {filteredTimeline.map((item) => (
                    <div key={item.id} className="relative grid grid-cols-[22px_1fr] items-start gap-3">
                      <div className={`z-10 mt-0.5 flex h-[22px] w-[22px] items-center justify-center overflow-hidden rounded-full border border-white bg-white shadow-sm ${item.type === 'system' ? 'text-blue-900' : 'text-slate-500'}`}>
                        <span className="material-symbols-outlined text-[13px]">
                          {item.type === 'system' ? 'account_balance' : 'business'}
                        </span>
                      </div>
                      <div className="min-w-0">
                        <p className="text-[11px] leading-5 font-semibold text-slate-700">{item.title}</p>
                        {item.description && (
                          <p className="text-[11px] leading-5 text-slate-600">{item.description}</p>
                        )}
                        <p className="mt-0.5 text-[10px] text-slate-400">
                          {formatDisplayDateTime(item.timestamp)} &middot; {item.actor}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <p className="text-[12px] text-slate-500 py-4 text-center">No timeline events recorded.</p>
            )}
          </CardSection>
          </div>

          {/* Documents + Attachments — side by side, unchanged */}
          <section className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <CardSection title="Case Documents" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
              <div className="space-y-4">
                {caseFile.documents?.length > 0 ? (
                  <div className="space-y-2">
                    {caseFile.documents.map((doc) => {
                      const canDelete = auth.user?.id === caseFile.user_id || auth.user?.role === 'ADMIN';
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
                
                <FileUpload
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif"
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
              </div>
            </CardSection>

            <CardSection title="Referral Attachments" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
              {allAttachments.length > 0 ? (
                <div className="space-y-2">
                  {allAttachments.map((att) => (
                    <div key={att.id} className="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                      <div className="min-w-0">
                        <p className="text-[12px] font-semibold text-slate-700 truncate">{att.file_name}</p>
                        <p className="text-[10px] text-slate-500">
                          {att.agencyName || att.user?.name || 'Unknown'} &middot; {att.created_at ? formatDisplayDateTime(att.created_at) : ''}
                          {att.size ? ` \u00b7 ${(att.size / 1024).toFixed(1)} KB` : ''}
                        </p>
                      </div>
                      <a
                        href={route('referrals.attachments.download', { referral: att.referral_id, attachment: att.id })}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-slate-500 hover:text-blue-900 shrink-0 ml-2"
                      >
                        <Eye className="h-4 w-4" />
                      </a>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-[12px] text-slate-500 py-2">No documents attached from referrals.</p>
              )}
            </CardSection>
          </section>
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
                      {[client.first_name, client.middle_name, client.last_name, client.suffix].filter(Boolean).join(' ')}
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

          {/* Case Information — single column to prevent overflow */}
          <CardSection title="Case Information" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
            <div className="space-y-2">
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

              <div>
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
