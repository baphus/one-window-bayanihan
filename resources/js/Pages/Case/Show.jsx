import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import { useToast } from '@/Hooks/useToast';
import { Eye, Trash2 } from 'lucide-react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { CardSection, MetaTile, InfoCell } from '@/Components/ui/CardSection';
import StatusBadge from '@/Components/ui/StatusBadge';
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

function formatAddress(addr, names) {
  if (!addr) return '';
  const parts = [];
  if (addr.street) parts.push(addr.street);
  if (addr.barangay) parts.push(names[addr.barangay] || addr.barangay);
  if (addr.city_municipality) parts.push(names[addr.city_municipality] || addr.city_municipality);
  if (addr.province) parts.push(names[addr.province] || addr.province);
  if (addr.region) parts.push(names[addr.region] || addr.region);
  return parts.join(', ');
}

function Subsection({ title, children }) {
  return (
    <div className="space-y-2.5">
      <h4 className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[#334155]">{title}</h4>
      {children}
    </div>
  );
}

export default function CaseShow({ case: caseFile, overdueDays = 7 }) {
  const { auth } = usePage().props;
  const client = caseFile.client;
  const toast = useToast();
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [showOverdueInfo, setShowOverdueInfo] = useState(false);
  const [formClientType, setFormClientType] = useState(caseFile.client_type);
  const [formVulnerability, setFormVulnerability] = useState(caseFile.vulnerability_indicator || '');
  const [formSummary, setFormSummary] = useState(caseFile.summary || '');
  const [saving, setSaving] = useState(false);
  const [uploadingDoc, setUploadingDoc] = useState(false);
  const [addressNames, setAddressNames] = useState({});
  const docInputRef = useRef(null);
  
  function handleDocumentUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 10 * 1024 * 1024) {
      alert('File size exceeds 10MB limit.');
      if (docInputRef.current) docInputRef.current.value = '';
      return;
    }

    setUploadingDoc(true);
    router.post(
      route('cases.documents.store', caseFile.id),
      { file },
      {
        preserveScroll: true,
        onSuccess: () => {
          setUploadingDoc(false);
          if (docInputRef.current) docInputRef.current.value = '';
        },
        onError: () => {
          setUploadingDoc(false);
          if (docInputRef.current) docInputRef.current.value = '';
        },
      }
    );
  }

  function handleDocumentDelete(docId) {
    if (confirm('Are you sure you want to delete this document?')) {
      router.delete(route('cases.documents.destroy', [caseFile.id, docId]), {
        preserveScroll: true,
      });
    }
  }

  const initialEditRef = useRef({ clientType: caseFile.client_type, vulnerability: caseFile.vulnerability_indicator || '', summary: caseFile.summary || '' });
  const hasEditDirty = useMemo(() => (
    formClientType !== initialEditRef.current.clientType
    || formVulnerability !== initialEditRef.current.vulnerability
    || formSummary !== initialEditRef.current.summary
  ), [formClientType, formVulnerability, formSummary]);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasEditDirty && isEditOpen);

  const primaryAddress = client?.addresses?.[0] || null;
  const primaryEmployment = client?.employments?.[0] || null;
  const primaryNok = client?.nextOfKin?.find(n => n.is_primary) || client?.nextOfKin?.[0] || null;

  useEffect(() => {
    if (!primaryAddress) return;
    const codes = [];
    if (primaryAddress.barangay) codes.push(primaryAddress.barangay);
    if (primaryAddress.city_municipality) codes.push(primaryAddress.city_municipality);
    if (primaryAddress.province) codes.push(primaryAddress.province);
    if (primaryAddress.region) codes.push(primaryAddress.region);
    if (codes.length === 0) return;
    const params = new URLSearchParams();
    codes.forEach(c => params.append('codes[]', c));
    fetch(`/api/address/resolve?${params.toString()}`)
      .then(r => r.json())
      .then(data => setAddressNames(data))
      .catch(() => {});
  }, [primaryAddress]);

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
    const unique = new Set(timelineItems.map((item) => item.agency));
    return Array.from(unique).sort((a, b) => a.localeCompare(b));
  }, [timelineItems]);

  const [timelineFilter, setTimelineFilter] = useState('ALL');

  const filteredTimeline = useMemo(() => {
    if (timelineFilter === 'ALL') return timelineItems;
    return timelineItems.filter((item) => item.agency === timelineFilter);
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
          className="inline-flex px-2 min-h-[28px] items-center bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300"
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
        <Link href={route('cases.index')} className="transition hover:text-[#0b5384]">Cases</Link>
        <span className="mx-2">&gt;</span>
        <span>{caseFile.case_number}</span>
      </div>

      <div className="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
          <h1 className="text-3xl md:text-[34px] font-black leading-tight tracking-tight text-slate-900">Case Details</h1>
          <p className="mt-1 text-[14px] leading-6 text-slate-600">Overview of client profile, referral progress, and timeline updates.</p>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          <StatusBadge status={caseFile.status} size="md" />
          {caseFile.user && (
            <div className="flex items-center gap-1.5 px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-[3px]">
              <span className="w-5 h-5 rounded-full bg-[#0b5384] text-white text-[8px] font-bold flex items-center justify-center shrink-0">
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
              setFormSummary(caseFile.summary || '');
              setIsEditOpen(true);
            }}
            className="px-3 min-h-[32px] bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[12px] font-bold rounded-[3px] transition-colors border border-slate-300"
          >
            Edit Details
          </button>
          <button
            type="button"
            onClick={handleToggleStatus}
            disabled={caseFile.status === 'OPEN' && hasActiveReferrals}
            title={caseFile.status === 'OPEN' && hasActiveReferrals ? 'Resolve all referrals before closing this case.' : ''}
            className={`px-3 min-h-[32px] text-[12px] font-bold rounded-[3px] transition-colors border ${
              caseFile.status === 'OPEN' && hasActiveReferrals
                ? 'bg-gray-200 text-gray-500 border-gray-300 cursor-not-allowed'
                : 'bg-[#0b5384] text-white hover:bg-[#09416a] border-[#0b5384]'
            }`}
          >
            {caseFile.status === 'OPEN' ? 'Close Case' : 'Reopen Case'}
          </button>
          {caseFile.status === 'ARCHIVED' ? (
            <button
              type="button"
              onClick={handleUnarchive}
              className="px-3 min-h-[32px] bg-gray-200 text-gray-700 hover:bg-gray-300 text-[12px] font-bold rounded-[3px] transition-colors border border-gray-300"
            >
              Restore from Archive
            </button>
          ) : caseFile.status === 'CLOSED' ? (
            <button
              type="button"
              onClick={handleArchive}
              className="px-3 min-h-[32px] bg-gray-100 text-gray-600 hover:bg-gray-200 text-[12px] font-bold rounded-[3px] transition-colors border border-gray-300"
            >
              Archive Case
            </button>
          ) : null}
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <main className="xl:col-span-8 space-y-4">
          <CardSection title="Case Information" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
              <MetaTile label="Case No." value={caseFile.case_number} />
              <MetaTile label="Tracking ID" value={caseFile.tracker_number} />
              <MetaTile label="Client Type" value={clientTypeLabel} />
              {caseFile.category && (
                <MetaTile label="Category" value={
                  <span className="inline-flex items-center gap-1.5">
                    {caseFile.category.color && (
                      <span className="w-2.5 h-2.5 rounded-full inline-block" style={{ backgroundColor: caseFile.category.color }} />
                    )}
                    {caseFile.category.name}
                  </span>
                } />
              )}
              <MetaTile label="Date Created" value={formatDisplayDate(caseFile.created_at)} subtext={formatDisplayTime(caseFile.created_at)} />
              <MetaTile label="Case Age" value={getCaseAgeDays(caseFile.created_at, caseFile.status, caseFile.updated_at)} />
            </div>
          </CardSection>

          <CardSection title="Client Information" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
            <div className="space-y-5">
              <Subsection title="Client Profile">
                <div className="grid grid-cols-1 md:grid-cols-3 border border-[#d8dee8]">
                  <InfoCell label="Full Name" value={client ? [client.first_name, client.middle_name, client.last_name, client.suffix].filter(Boolean).join(' ') : 'N/A'} />
                  <InfoCell label="Date of Birth" value={client?.date_of_birth ? formatDisplayDate(client.date_of_birth) : 'N/A'} />
                  <InfoCell label="Gender" value={client?.sex || 'N/A'} />
                  <InfoCell label="Email Address" value={client?.email || 'N/A'} />
                  <InfoCell label="Contact Number" value={client?.contact_number || 'N/A'} />
                  <InfoCell label=" " value=" " />
                  {primaryAddress ? (
                    <InfoCell label="Home Address" value={formatAddress(primaryAddress, addressNames)} fullRow />
                  ) : (
                    <InfoCell label="Home Address" value="No address recorded" fullRow />
                  )}
                </div>

                {caseFile.vulnerability_indicator && caseFile.vulnerability_indicator !== 'None' && (
                  <div className="rounded-[3px] border border-[#d8dee8] bg-[#f8fafc] p-3">
                    <p className="text-[9px] font-extrabold uppercase tracking-[0.14em] text-[#7c889b]">Vulnerability</p>
                    <div className="mt-2">
                      <span className={`inline-flex items-center gap-1.5 rounded-[3px] border px-2.5 py-1 text-[11px] font-bold ${vulnConfig[caseFile.vulnerability_indicator]?.className || 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                        <span className="material-symbols-outlined text-[16px]">{vulnConfig[caseFile.vulnerability_indicator]?.icon || 'warning'}</span>
                        {caseFile.vulnerability_indicator}
                      </span>
                    </div>
                  </div>
                )}
              </Subsection>

              <Subsection title="Work History">
                {primaryEmployment ? (
                  <div className="grid grid-cols-1 md:grid-cols-3 border border-[#d8dee8]">
                    <InfoCell label="Last Country" value={primaryEmployment.last_country || primaryEmployment.country || 'N/A'} />
                    <InfoCell label="Last Position" value={primaryEmployment.last_position || primaryEmployment.position || 'N/A'} />
                    <InfoCell label="Arrival Date" value={primaryEmployment.date_of_arrival ? formatDisplayDate(primaryEmployment.date_of_arrival) : 'N/A'} />
                  </div>
                ) : (
                  <div className="rounded-[3px] border border-[#d8dee8] bg-[#f8fafc] px-3 py-2 text-[12px] text-slate-600">
                    No work history recorded for this case.
                  </div>
                )}
              </Subsection>

              <Subsection title="Next of Kin Information">
                {primaryNok ? (
                  <div className="grid grid-cols-1 md:grid-cols-3 border border-[#d8dee8]">
                    <InfoCell label="Full Name" value={[primaryNok.first_name, primaryNok.last_name].filter(Boolean).join(' ')} />
                    <InfoCell label="Relationship" value={primaryNok.relationship || 'N/A'} />
                    <InfoCell label="Contact Number" value={primaryNok.phone_number || 'N/A'} />
                    <InfoCell label="Email Address" value={primaryNok.email || 'N/A'} />
                    <InfoCell label="Home Address" value={primaryNok.full_address || 'N/A'} fullRow />
                  </div>
                ) : (
                  <div className="rounded-[3px] border border-[#d8dee8] bg-[#f8fafc] px-3 py-2 text-[12px] text-slate-600">
                    No next of kin indicated for this case.
                  </div>
                )}
              </Subsection>
            </div>
          </CardSection>

          <CardSection title={`Referrals (${(caseFile.referrals || []).length})`} className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <h4 className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-[#64748b]">Agency Referrals</h4>
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
                      <div className="absolute left-0 top-full mt-1 z-20 w-72 rounded-[3px] border border-red-200 bg-red-50 px-3 py-2 shadow-md">
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
                className="px-3 min-h-[30px] inline-flex items-center bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300"
              >
                + Refer to Agency
              </Link>
            </div>
            {caseFile.status === 'OPEN' && hasActiveReferrals && (
              <div className="mb-3 flex items-start gap-2.5 rounded-[3px] border border-amber-200 bg-amber-50 px-3 py-2.5">
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

          <section className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <CardSection title="Case Documents" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
              <div className="space-y-4">
                {caseFile.documents?.length > 0 ? (
                  <div className="space-y-2">
                    {caseFile.documents.map((doc) => {
                      const canDelete = auth.user?.id === caseFile.user_id || auth.user?.role === 'ADMIN';
                      return (
                        <div key={doc.id} className="flex items-center justify-between rounded-[3px] border border-[#e2e8f0] bg-[#f8fafc] px-3 py-2">
                          <div className="min-w-0">
                            <p className="text-[12px] font-semibold text-slate-700 truncate">{doc.file_name}</p>
                            <p className="text-[10px] text-slate-500">
                              {doc.user?.name || 'Unknown'} &middot; {doc.created_at ? formatDisplayDateTime(doc.created_at) : ''}
                              {doc.size ? ` \u00b7 ${(doc.size / 1024).toFixed(1)} KB` : ''}
                            </p>
                          </div>
                          <div className="flex items-center gap-2 shrink-0 ml-2">
                            <a
                              href={doc.file_path}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-slate-500 hover:text-[#0b5384]"
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
                
                <div 
                  className="bg-white border border-dashed border-[#cbd5e1] rounded-[3px] p-4 flex items-center justify-center cursor-pointer hover:bg-slate-50 transition-colors"
                  onClick={() => docInputRef.current?.click()}
                >
                  <div className="text-center">
                    <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-[#eff6ff] text-[#0b5384] border border-[#bfdbfe]">
                      {uploadingDoc ? (
                        <span className="material-symbols-outlined text-[20px] animate-spin">sync</span>
                      ) : (
                        <span className="material-symbols-outlined text-[20px]">upload</span>
                      )}
                    </div>
                    <p className="mt-2 text-[11px] font-bold uppercase tracking-[0.08em] text-[#0b5384]">
                      {uploadingDoc ? 'Uploading...' : 'Upload New File'}
                    </p>
                    <p className="mt-1 text-[10px] text-slate-500">PDF or image up to 10MB</p>
                  </div>
                  <input
                    type="file"
                    ref={docInputRef}
                    className="hidden"
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif"
                    onChange={handleDocumentUpload}
                    disabled={uploadingDoc}
                  />
                </div>
              </div>
            </CardSection>

            <CardSection title="Referral Attachments" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
              {allAttachments.length > 0 ? (
                <div className="space-y-2">
                  {allAttachments.map((att) => (
                    <div key={att.id} className="flex items-center justify-between rounded-[3px] border border-[#e2e8f0] bg-[#f8fafc] px-3 py-2">
                      <div className="min-w-0">
                        <p className="text-[12px] font-semibold text-slate-700 truncate">{att.file_name}</p>
                        <p className="text-[10px] text-slate-500">
                          {att.agencyName || att.user?.name || 'Unknown'} &middot; {att.created_at ? formatDisplayDateTime(att.created_at) : ''}
                          {att.size ? ` \u00b7 ${(att.size / 1024).toFixed(1)} KB` : ''}
                        </p>
                      </div>
                      <a
                        href={att.file_path}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-slate-500 hover:text-[#0b5384] shrink-0 ml-2"
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
          <CardSection title="Case Narrative" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
            {caseFile.summary ? (
              <p className="text-[12px] leading-5 text-slate-600 whitespace-pre-wrap">{caseFile.summary}</p>
            ) : (
              <p className="text-[12px] text-slate-500 italic">No narrative recorded for this case.</p>
            )}
          </CardSection>

          <CardSection title="Case Timeline" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
            <div className="flex flex-wrap items-center justify-end gap-2">
              <select
                value={timelineFilter}
                onChange={(e) => setTimelineFilter(e.target.value)}
                className="h-[30px] w-[170px] max-w-full shrink-0 rounded-[3px] border border-[#cbd5e1] bg-white px-2 text-[10px] font-extrabold uppercase tracking-[0.08em] text-slate-600"
              >
                <option value="ALL">All agencies</option>
                {timelineAgencies.map((agency) => (
                  <option key={agency} value={agency}>{agency}</option>
                ))}
              </select>
            </div>
            {filteredTimeline.length > 0 ? (
              <div className="relative mt-4">
                <div className="absolute left-[10px] top-1 bottom-1 w-px bg-[#cbd5e1]" />
                <div className="flex flex-col-reverse gap-4">
                  {filteredTimeline.map((item) => (
                    <div key={item.id} className="relative grid grid-cols-[22px_1fr] items-start gap-3">
                      <div className={`z-10 mt-0.5 flex h-[22px] w-[22px] items-center justify-center overflow-hidden rounded-full border border-white bg-white shadow-sm ${item.type === 'system' ? 'text-[#0b5384]' : 'text-slate-500'}`}>
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
        </aside>
      </div>

      {isEditOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" onClick={() => setIsEditOpen(false)}>
          <div className="w-full max-w-2xl rounded-[3px] border border-[#cbd5e1] bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="border-b border-[#e2e8f0] px-5 py-4">
              <h2 className="text-[16px] font-extrabold text-slate-900">Edit Case Details</h2>
              <p className="mt-1 text-[12px] text-slate-500">Update visible case details for this record.</p>
            </div>

            <div className="grid grid-cols-1 gap-4 px-5 py-4 md:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Client Type</label>
                <select
                  value={formClientType}
                  onChange={(e) => setFormClientType(e.target.value)}
                  className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384]"
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
                  className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384]"
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
                  className="w-full rounded-[3px] border border-[#cbd5e1] px-3 py-2 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384]"
                />
              </div>
            </div>

            <div className="flex justify-end gap-2 border-t border-[#e2e8f0] px-5 py-3">
              <button
                type="button"
                onClick={() => setIsEditOpen(false)}
                className="h-9 rounded-[3px] border border-[#cbd5e1] px-3 text-[12px] font-bold text-slate-700"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleSaveDetails}
                disabled={saving}
                className="h-9 rounded-[3px] bg-[#0b5384] px-3 text-[12px] font-bold text-white disabled:opacity-60"
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
