import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { CardSection, InfoCell } from '@/Components/ui/CardSection';
import StatusBadge from '@/Components/ui/StatusBadge';
import UserAvatar, { getAvatarColor } from '@/Components/ui/UserAvatar';
import PeerProfileModal from '@/Components/PeerProfileModal';
import AuditLogModal from '@/Components/AuditLogModal';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import { formatDisplayDateTime, formatDisplayDate } from '@/lib/utils';
import { formatRelativeTime } from '@/lib/relativeTime';
import { formatResolvedAddress } from '@/lib/addressResolver';

const TIMELINE_EVENT_CONFIG = {
  referral_sent:   { dot: 'bg-purple-50 border-purple-200 text-purple-600', icon: 'forward_to_inbox' },
  referral_status: { dot: 'bg-amber-50 border-amber-200 text-amber-600',   icon: 'sync_alt' },
  milestone:       { dot: 'bg-emerald-50 border-emerald-200 text-emerald-600', icon: 'flag' },
};

function formatFullName(person) {
    if (!person) return 'N/A';
    return [person.first_name, person.middle_initial, person.last_name, person.suffix].filter(Boolean).join(' ') || person.name || 'N/A';
}

function formatAddress(address) {
    return formatResolvedAddress(address, 'N/A');
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

export default function ReferralShow({ referral, serviceRequirements = [], overdueDays = 7, timeline = [] }) {
    const { auth } = usePage().props;
    const isAgency = auth.user.role === 'AGENCY';
    const isCaseManager = auth.user.role === 'CASE_MANAGER';
    const isAdmin = auth.user.role === 'ADMIN';
    const canAddMilestone = isAgency && referral.status !== 'COMPLETED';
    const canUpdateStatus = isAgency;

    const caseFile = referral.case_file;
    const client = caseFile?.client;
    const clientAddress = client?.addresses?.[0] ?? client?.address ?? null;
    const receivingAgencyName = referral.agency?.name ?? 'the receiving agency';
    const isReceivingAgency = isAgency && auth.user.agcy_id && auth.user.agcy_id === referral.agcy_id;
    const showMilestoneAgencyWarning = canAddMilestone && !isReceivingAgency;
    const refMilestones = referral.milestones || [];
    const latestMilestone = refMilestones.length > 0
      ? refMilestones.reduce((a, b) => new Date(a.created_at) > new Date(b.created_at) ? a : b)
      : null;
    const lastActivity = latestMilestone
      ? new Date(latestMilestone.created_at)
      : referral.status === 'PENDING'
        ? new Date(referral.created_at)
        : new Date(referral.updated_at);
    const referralAge = Math.floor((Date.now() - lastActivity.getTime()) / (1000 * 60 * 60 * 24));
    const isOverdue = !['COMPLETED', 'REJECTED'].includes(referral.status) && referralAge > overdueDays;

    const comments = referral.comments ?? [];
    const topLevelComments = comments.filter((c) => !c.parent_id);
    const [peerProfileUser, setPeerProfileUser] = useState(null);
    const [showOverdueInfo, setShowOverdueInfo] = useState(false);
    const [showAuditLog, setShowAuditLog] = useState(false);

    const [pendingDecision, setPendingDecision] = useState(null);
    const [decisionRemark, setDecisionRemark] = useState('');
    const [showUpdateStatus, setShowUpdateStatus] = useState(false);
    const [updateStatusValue, setUpdateStatusValue] = useState('PROCESSING');
    const [updateStatusRemark, setUpdateStatusRemark] = useState('');

    const [commentDraft, setCommentDraft] = useState('');
    const [replyToCommentId, setReplyToCommentId] = useState(null);
    const [postingComment, setPostingComment] = useState(false);
    const [showMilestoneModal, setShowMilestoneModal] = useState(false);
    const [removeAttachment, setRemoveAttachment] = useState(null);
    const milestoneForm = useForm({ title: '', description: '' });

    const replyToComment = replyToCommentId
        ? comments.find((c) => c.id === replyToCommentId) ?? null
        : null;



    function handleMilestoneModalSubmit(e) {
        e.preventDefault();
        milestoneForm.post(route('referrals.milestones.store', referral.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowMilestoneModal(false);
                milestoneForm.reset();
            },
        });
    }

    function handlePostComment() {
        const trimmed = commentDraft.trim();
        if (!trimmed || postingComment) return;
        setPostingComment(true);
        const routeName = replyToComment
            ? 'referrals.comments.reply'
            : 'referrals.comments.store';
        const routeParams = replyToComment
            ? [referral.id, replyToComment.id]
            : [referral.id];
        router.post(route(routeName, routeParams), {
            content: trimmed,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setCommentDraft('');
                setReplyToCommentId(null);
                setPostingComment(false);
            },
            onError: () => setPostingComment(false),
        });
    }

    function cancelReply() {
        setReplyToCommentId(null);
    }

    return (
        <AppLayout title="Referral Detail">
            <Head title="Referral Detail" />

            <div className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500 mb-5">
                <Link href={route('referrals.index')} className="transition hover:text-blue-900">Referrals</Link>
                <span className="mx-2">&gt;</span>
                <span>{referral.case_file?.case_number ?? referral.id}</span>
            </div>

            <div className="flex items-start justify-between gap-4 flex-wrap mb-6">
                <h1 className="text-3xl md:text-[34px] font-black leading-tight tracking-tight text-slate-900">Referral Details</h1>
                <div data-tour="referral-actions" className="flex items-center gap-2">
                    {canUpdateStatus && referral.status === 'PENDING' && (
                        <>
                            <button
                                onClick={() => setPendingDecision({ id: referral.id, mode: 'ACCEPT', status: 'PROCESSING' })}
                                className="h-[34px] px-3 bg-emerald-600 text-white text-[11px] font-bold rounded-md border border-emerald-600 hover:bg-emerald-700"
                            >
                                Accept
                            </button>
                            <button
                                onClick={() => setPendingDecision({ id: referral.id, mode: 'REJECT', status: 'REJECTED' })}
                                className="h-[34px] px-3 bg-red-50 text-red-700 text-[11px] font-bold rounded-md border border-red-200 hover:bg-red-100"
                            >
                                Reject
                            </button>
                        </>
                    )}
                    {canUpdateStatus && !['PENDING', 'COMPLETED', 'REJECTED'].includes(referral.status) && (
                        <button
                            onClick={() => { setShowUpdateStatus(true); setUpdateStatusValue(referral.status); setUpdateStatusRemark(''); }}
                            className="h-[34px] px-3 border border-slate-200 bg-white text-slate-700 text-[11px] font-bold rounded-md inline-flex items-center hover:bg-slate-50"
                        >
                            Update Status
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => setShowAuditLog(true)}
                        className="h-[34px] px-3 border border-slate-200 bg-white text-slate-700 text-[11px] font-bold rounded-md inline-flex items-center gap-1.5 hover:bg-slate-50"
                    >
                        <span className="material-symbols-outlined text-[16px]">history</span>
                        Audit Log
                    </button>
                    <Link
                        href={route('referrals.index')}
                        className="h-[34px] px-3 border border-slate-200 bg-white text-slate-700 text-[11px] font-bold rounded-md inline-flex items-center hover:bg-slate-50"
                    >
                        Back
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-12 gap-4">
                <main className="xl:col-span-8 space-y-4">
                    <div data-tour="referral-info">
                    <CardSection title="Referral Information" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
                        {isOverdue && (
                            <div className="mb-3 rounded-md border border-red-200 bg-red-50">
                                <div className="flex items-center gap-2 px-3 py-2">
                                    <span className="material-symbols-outlined text-[18px] text-red-600">warning</span>
                                    <p className="flex-1 text-[12px] font-bold text-red-700">This referral is overdue by {referralAge} day{referralAge > 1 ? 's' : ''}</p>
                                    <button
                                        type="button"
                                        onClick={() => setShowOverdueInfo((prev) => !prev)}
                                        className="flex h-[20px] w-[20px] items-center justify-center rounded-full text-red-500 hover:bg-red-200 transition-colors"
                                    >
                                        <span className="material-symbols-outlined text-[16px]">{showOverdueInfo ? 'close' : 'info'}</span>
                                    </button>
                                </div>
                                {showOverdueInfo && (
                                    <div className="border-t border-red-200 px-3 py-2">
                                        <p className="text-[11px] leading-5 text-red-800">
                                            A referral is considered overdue when there has been no update or activity for more than {overdueDays} day{overdueDays > 1 ? 's' : ''}.
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border border-slate-200">
                            <InfoCell label="Receiving Agency" value={referral.agency?.name ?? 'N/A'} />
                            <InfoCell label="Status" value={<StatusBadge status={referral.status} />} />
                            <InfoCell label="Associated Case No." value={
                                <Link href={route('cases.show', referral.case_id)} className="text-blue-900 hover:underline">
                                    {referral.case_file?.case_number ?? 'N/A'}
                                </Link>
                            } />
                            <InfoCell label="Tracking ID" value={referral.case_file?.tracker_number ?? 'N/A'} />
                            <InfoCell label="Date Referred" value={formatDisplayDateTime(referral.created_at)} />
                            <InfoCell label="Last Updated" value={formatDisplayDateTime(referral.updated_at)} />
                        </div>
                        {referral.required_services && (
                            <div className="px-3 py-2 border-b border-slate-200">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Required Services</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700">{referral.required_services}</p>
                            </div>
                        )}
                        {referral.notes && (
                            <div className="px-3 py-2 border-b border-slate-200">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Notes</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700 whitespace-pre-wrap">{referral.notes}</p>
                            </div>
                        )}
                        {referral.decision && (
                            <div className="px-3 py-2 border-b border-slate-200">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Decision</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700">{referral.decision}</p>
                            </div>
                        )}
                        {referral.decision_comment && (
                            <div className="px-3 py-2">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Decision Comment</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700 whitespace-pre-wrap">{referral.decision_comment}</p>
                            </div>
                        )}
                    </CardSection>
                    </div>

                    <CardSection title="Case Information" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border border-slate-200">
                            <InfoCell label="Case Status" value={caseFile?.status ? <StatusBadge status={caseFile.status} /> : 'N/A'} />
                            <InfoCell label="Tracker Number" value={caseFile?.tracker_number ?? 'N/A'} />
                            <InfoCell label="Category" value={caseFile?.category?.name ?? caseFile?.category?.title ?? 'N/A'} />
                            <InfoCell label="Issue / Concern" value={caseFile?.case_issue?.name ?? caseFile?.case_issue?.title ?? 'N/A'} />
                        </div>
                    </CardSection>

                    <CardSection title="Client Details" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
                        <div className="space-y-4 p-4">
                            {/* Avatar + Name row */}
                            <div className="flex items-start gap-4 pb-4 border-b border-slate-200">
                                {client?.avatar_url ? (
                                    <img src={client.avatar_url} alt="" className="h-14 w-14 rounded-circle object-cover border border-slate-200 shrink-0" onError={(e) => { e.target.style.display = 'none'; }} />
                                ) : (
                                    <span className={`h-14 w-14 inline-flex items-center justify-center rounded-circle shrink-0 ${getAvatarColor(formatFullName(client))}`}>
                                        <span className="material-symbols-outlined text-[24px] text-white/60">person</span>
                                    </span>
                                )}
                                <div className="min-w-0 flex-1 self-center">
                                    <p className="text-[16px] font-bold text-slate-900 break-words">
                                        {formatFullName(client)}
                                    </p>
                                    <div className="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span className="inline-flex items-center rounded-md bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-bold text-blue-700 uppercase tracking-wider">
                                            {caseFile?.client_type?.replace(/_/g, ' ') ?? 'N/A'}
                                        </span>
                                        {client?.sex && (
                                            <span className="inline-flex items-center rounded-md bg-slate-50 border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                                {client.sex}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Personal Info grid */}
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <p className="text-[8px] font-extrabold uppercase tracking-[0.08em] text-slate-500">Date of Birth</p>
                                    <p className="mt-0.5 text-[12px] font-semibold text-slate-700">{client?.date_of_birth ? formatDisplayDate(client.date_of_birth) : 'N/A'}</p>
                                </div>
                                <div>
                                    <p className="text-[8px] font-extrabold uppercase tracking-[0.08em] text-slate-500">Age</p>
                                    <p className="mt-0.5 text-[12px] font-semibold text-slate-700">{client?.date_of_birth ? getClientAge(client.date_of_birth) : 'N/A'}</p>
                                </div>
                                <div className="col-span-2">
                                    <p className="text-[8px] font-extrabold uppercase tracking-[0.08em] text-slate-500">Vulnerability</p>
                                    <p className="mt-0.5 text-[12px] font-semibold text-slate-700">
                                        {(() => {
                                            const vuln = caseFile?.client_type === 'NEXT_OF_KIN'
                                                ? caseFile?.nok_vulnerability_indicator
                                                : caseFile?.vulnerability_indicator;
                                            return vuln || 'None';
                                        })()}
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <p className="text-[8px] font-extrabold uppercase tracking-[0.08em] text-slate-500">Email</p>
                                    <p className="mt-0.5 text-[12px] font-semibold text-slate-700 break-words">{client?.email || 'N/A'}</p>
                                </div>
                                <div>
                                    <p className="text-[8px] font-extrabold uppercase tracking-[0.08em] text-slate-500">Contact Number</p>
                                    <p className="mt-0.5 text-[12px] font-semibold text-slate-700">{client?.contact_number || 'N/A'}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <p className="text-[8px] font-extrabold uppercase tracking-[0.08em] text-slate-500">Address</p>
                                    <p className="mt-0.5 text-[12px] font-semibold text-slate-700">{formatAddress(clientAddress)}</p>
                                </div>
                            </div>

                            {/* Employment History */}
                            {client?.employments?.length > 0 && (
                                <>
                                    <hr className="border-slate-200" />
                                    <div>
                                        <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500 mb-2">
                                            Employment History
                                            <span className="ml-1.5 text-slate-400 font-normal normal-case">({client.employments.length} record{client.employments.length !== 1 ? 's' : ''})</span>
                                        </p>
                                        <div className="space-y-2">
                                            {client.employments.map((emp, idx) => (
                                                <div key={emp.id} className="rounded-md border border-slate-100 bg-slate-50 px-3 py-2">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0">
                                                            {(emp.last_position || emp.position) && (
                                                                <p className="text-[12px] font-semibold text-slate-800">
                                                                    {emp.last_position || emp.position}
                                                                </p>
                                                            )}
                                                            {(emp.employer_name || emp.last_country || emp.country) && (
                                                                <p className="text-[11px] text-slate-600 mt-0.5">
                                                                    {[emp.employer_name, emp.last_country || emp.country].filter(Boolean).join(' · ')}
                                                                </p>
                                                            )}
                                                        </div>
                                                        {emp.date_of_arrival && (
                                                            <span className="shrink-0 text-[10px] text-slate-400 whitespace-nowrap">
                                                                Arrived in PH {formatDisplayDate(emp.date_of_arrival)}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {(emp.start_date && emp.end_date) && (
                                                        <p className="mt-1 text-[10px] text-slate-400">
                                                            {formatDisplayDate(emp.start_date)} &ndash; {formatDisplayDate(emp.end_date)}
                                                        </p>
                                                    )}
                                                    {(emp.start_date && !emp.end_date) && (
                                                        <p className="mt-1 text-[10px] text-slate-400">
                                                            Since {formatDisplayDate(emp.start_date)}
                                                        </p>
                                                    )}
                                                    {(!emp.start_date && emp.end_date) && (
                                                        <p className="mt-1 text-[10px] text-slate-400">
                                                            Until {formatDisplayDate(emp.end_date)}
                                                        </p>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </>
                            )}

                            {/* Next of Kin */}
                            {client?.nextOfKin?.length > 0 && (
                                <>
                                    <hr className="border-slate-200" />
                                    <div>
                                        <div className="flex items-center justify-between mb-2">
                                            <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">
                                                Next of Kin
                                                <span className="ml-1.5 text-slate-400 font-normal normal-case">({client.nextOfKin.length} record{client.nextOfKin.length !== 1 ? 's' : ''})</span>
                                            </p>
                                        </div>
                                        <div className="space-y-2">
                                            {client.nextOfKin.map((nok, idx) => (
                                                <div key={nok.id} className={`rounded-md border border-slate-100 px-3 py-2 ${idx > 0 ? 'bg-white' : 'bg-blue-50/50 border-blue-100'}`}>
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-[13px] font-bold text-slate-800">
                                                            {[nok.first_name, nok.last_name].filter(Boolean).join(' ')}
                                                        </span>
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
                        </div>
                    </CardSection>
                    {/* Requirements Section */}
                    <div data-tour="referral-documents">
                        <CardSection title="Requirements" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
                            {referral.requirements && referral.requirements.length > 0 ? (
                                <ul className="space-y-1.5">
                                    {referral.requirements.map((req, idx) => (
                                        <li key={idx} className="flex items-start gap-2 text-[12px] text-slate-700">
                                            <span className="material-symbols-outlined text-[14px] text-slate-400 mt-0.5 shrink-0">chevron_right</span>
                                            <span>{req}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-[12px] text-slate-500 italic">No requirements listed.</p>
                            )}
                        </CardSection>
                    </div>

                    {/* Uploaded Documents / Attachments */}
                    {(referral.attachments ?? []).filter((att) => !att.is_archived).length > 0 && (
                        <CardSection title="Uploaded Documents" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
                            <div className="divide-y divide-slate-100">
                                {(referral.attachments ?? [])
                                    .filter((att) => !att.is_archived)
                                    .map((att) => (
                                        <div key={att.id} className="flex items-center gap-3 px-1 py-2.5">
                                            <span className="material-symbols-outlined text-[16px] text-slate-400">description</span>
                                            <div className="min-w-0 flex-1">
                                                <a
                                                    href={route('referrals.attachments.download', [referral.id, att.id])}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-[12px] text-blue-700 hover:text-blue-900 hover:underline font-medium truncate block"
                                                >
                                                    {att.file_name}
                                                </a>
                                                <div className="flex items-center gap-2 mt-0.5">
                                                    {att.user && (
                                                        <span className="text-[9px] text-slate-400">
                                                            Uploaded by {att.user.name}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            {att.user_id === auth.user.id && (
                                                <button
                                                    type="button"
                                                    onClick={() => setRemoveAttachment(att)}
                                                    className="text-[9px] text-red-600 hover:text-red-800 font-bold inline-flex items-center gap-0.5 shrink-0"
                                                >
                                                    <span className="material-symbols-outlined text-[12px]">delete</span>
                                                    Remove
                                                </button>
                                            )}
                                        </div>
                                    ))}
                            </div>
                        </CardSection>
                    )}

                </main>

                <aside className="xl:col-span-4 space-y-4">
                    <div data-tour="referral-timeline">
                    <CardSection title="Referral Timeline" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
                        {timeline.length > 0 ? (
                            <div className="mt-1 relative pl-4">
                                <div className="absolute left-[4px] top-1 bottom-1 w-px bg-slate-200" />
                                <div className="flex flex-col-reverse gap-4">
                                    {[...timeline].reverse().map((item) => {
                                        const cfg = TIMELINE_EVENT_CONFIG[item.type] ?? TIMELINE_EVENT_CONFIG.milestone;
                                        return (
                                            <div key={item.id} className="relative flex items-start gap-3">
                                                <div className={`mt-0.5 -ml-[18px] h-5 w-5 overflow-hidden rounded-full border border-white shadow-sm z-10 flex items-center justify-center ${cfg.dot}`}>
                                                    <span className="material-symbols-outlined text-[12px]">{cfg.icon}</span>
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                                        <span className="text-[10px] font-bold text-slate-500 uppercase tracking-tight">
                                                            {formatRelativeTime(item.timestamp)}
                                                        </span>
                                                        <span className="text-[9px] text-slate-400">
                                                            {formatDisplayDateTime(item.timestamp)}
                                                        </span>
                                                    </div>
                                                    <p className="text-[11px] leading-5 font-semibold text-slate-700 mt-0.5">{item.title}</p>
                                                    {item.description && (
                                                        <p className="text-[11px] leading-5 text-slate-600">{item.description}</p>
                                                    )}
                                                    <p className="mt-0.5 text-[10px] text-slate-400">{item.actor}</p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : (
                            <p className="text-[11px] text-slate-500 py-2">No timeline events recorded.</p>
                        )}
                        {canAddMilestone && (
                            <button
                                type="button"
                                onClick={() => setShowMilestoneModal(true)}
                                className="mt-3 h-[28px] w-full px-3 bg-blue-900 text-white text-[10px] font-bold rounded-md border border-blue-900 hover:bg-blue-800 transition-colors"
                            >
                                + Add Milestone
                            </button>
                        )}
                    </CardSection>
                    </div>

                    <CardSection title="Case Narrative" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
                        {referral.case_file?.summary ? (
                            <p className="text-[12px] leading-5 text-slate-600 whitespace-pre-wrap">{referral.case_file.summary}</p>
                        ) : (
                            <p className="text-[12px] text-slate-500 italic">No case narrative recorded.</p>
                        )}
                    </CardSection>

                    <div data-tour="referral-comments">
                    <CardSection title="Referral Comments" className="[&>h3]:text-gray-800 [&>h3]:tracking-[0.14em]">
                        <div className="max-h-[340px] overflow-y-auto space-y-3">
                            {topLevelComments.length > 0 ? (
                                topLevelComments.map((comment) => {
                                    const replies = comment.replies ?? [];
                                    return (
                                        <div key={comment.id} className="rounded-md border border-slate-200 bg-white shadow-sm">
                                            <div className="flex items-start gap-2.5 px-3 pt-2.5 pb-2">
                                                <UserAvatar user={comment.user} size="sm" />
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-baseline gap-2">
                                                        <span className="text-[11px] font-bold text-slate-800">{comment.user?.name ?? 'Unknown'}</span>
                                                        <span className="text-[9px] text-slate-400">{formatDisplayDateTime(comment.created_at)}</span>
                                                        {comment.is_edited && <span className="text-[9px] text-slate-400 italic">(edited)</span>}
                                                    </div>
                                                    <p className="mt-0.5 text-[11px] leading-5 text-slate-700 whitespace-pre-wrap">{comment.content}</p>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setReplyToCommentId(comment.id);
                                                            setCommentDraft('');
                                                        }}
                                                        className="mt-1 text-[9px] font-bold text-blue-900 hover:text-blue-800 transition-colors"
                                                    >
                                                        Reply
                                                    </button>
                                                </div>
                                            </div>
                                            {replies.length > 0 && (
                                                <div className="ml-8 mr-3 pb-2.5 space-y-2">
                                                    {replies.map((reply) => (
                                                        <div key={reply.id} className="flex items-start gap-2 rounded-md bg-slate-50 px-2.5 py-2">
                                                            <UserAvatar user={reply.user} size="sm" onClick={() => setPeerProfileUser(reply.user)} />
                                                            <div className="min-w-0 flex-1">
                                                                <div className="flex items-baseline gap-2">
                                                                    <span className="text-[10px] font-bold text-slate-700">{reply.user?.name ?? 'Unknown'}</span>
                                                                    <span className="text-[9px] text-slate-400">{formatDisplayDateTime(reply.created_at)}</span>
                                                                    {reply.is_edited && <span className="text-[9px] text-slate-400 italic">(edited)</span>}
                                                                </div>
                                                                <p className="text-[11px] leading-5 text-slate-700 whitespace-pre-wrap">{reply.content}</p>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })
                            ) : (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 border border-slate-200">
                                        <span className="material-symbols-outlined text-[18px] text-slate-400">chat_bubble_outline</span>
                                    </div>
                                    <p className="mt-2 text-[11px] font-semibold text-slate-500">No comments yet</p>
                                    <p className="text-[10px] text-slate-400">Start the conversation below.</p>
                                </div>
                            )}
                        </div>

                        <div className="mt-3 pt-3 border-t border-slate-200">
                            {replyToComment && (
                                <div className="mb-2 flex items-center justify-between rounded-md bg-sky-50 border border-sky-200 px-2.5 py-1.5">
                                    <p className="text-[10px] text-blue-900 font-semibold truncate">
                                        Replying to <span className="font-bold">{replyToComment.user?.name ?? 'comment'}</span>
                                    </p>
                                    <button
                                        type="button"
                                        onClick={cancelReply}
                                        className="text-[10px] font-bold text-blue-900 hover:underline shrink-0 ml-2"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            )}
                            <div className="flex items-start gap-2">
                                <UserAvatar user={auth.user} size="sm" />
                                <div className="flex-1 min-w-0">
                                    <textarea
                                        value={commentDraft}
                                        onChange={(e) => setCommentDraft(e.target.value)}
                                        rows={2}
                                        className="w-full rounded-md border border-slate-200 px-3 py-1.5 text-[12px] text-slate-700 outline-none focus:border-blue-900 focus:ring-1 focus:ring-blue-900/20 resize-none transition-colors"
                                        placeholder={replyToComment ? 'Write a reply...' : 'Write a comment...'}
                                    />
                                    <div className="mt-1.5 flex items-center justify-between">
                                        <span className="text-[9px] text-slate-400">{replyToComment ? 'Your reply will be posted immediately' : 'Your comment will be posted immediately'}</span>
                                        <button
                                            type="button"
                                            onClick={handlePostComment}
                                            disabled={postingComment || !commentDraft.trim()}
                                            className="h-[26px] px-3 bg-blue-900 text-white text-[10px] font-bold rounded-md border border-blue-900 hover:bg-blue-800 disabled:opacity-60 transition-colors"
                                        >
                                            {postingComment ? 'Posting...' : 'Post'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardSection>
                    </div>
                </aside>
            </div>

            {pendingDecision && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-lg">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h2 className="text-base font-bold text-slate-900">
                                {pendingDecision.mode === 'ACCEPT' ? 'Accept' : 'Reject'} Referral
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                {pendingDecision.mode === 'ACCEPT'
                                    ? 'Choose a status and provide a remark.'
                                    : 'A remark is required before rejecting.'}
                            </p>
                        </div>
                        <div className="px-5 py-4 space-y-4">
                            {pendingDecision.mode === 'ACCEPT' && (
                                <div>
                                    <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-600">Status</label>
                                    <select
                                        value={pendingDecision.status}
                                        onChange={(e) => setPendingDecision({ ...pendingDecision, status: e.target.value })}
                                        className="h-10 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                    >
                                        <option value="PROCESSING">Processing</option>
                                        <option value="FOR_COMPLIANCE">For Compliance</option>
                                    </select>
                                </div>
                            )}
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-600">Remark</label>
                                <textarea
                                    value={decisionRemark}
                                    onChange={(e) => setDecisionRemark(e.target.value)}
                                    rows={4}
                                    placeholder="Enter your decision remark..."
                                    className="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                />
                            </div>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                            <button onClick={() => { setPendingDecision(null); setDecisionRemark(''); }}
                                className="h-9 rounded border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button onClick={() => {
                                const trimmed = decisionRemark.trim();
                                if (!trimmed) return;
                                router.patch(route('referrals.update-status', pendingDecision.id), {
                                    status: pendingDecision.status,
                                    decision: pendingDecision.mode,
                                    decision_comment: trimmed,
                                }, {
                                    preserveScroll: true,
                                    onSuccess: () => { setPendingDecision(null); setDecisionRemark(''); },
                                });
                            }} disabled={!decisionRemark.trim()}
                                className="h-9 rounded bg-blue-900 px-4 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                Confirm {pendingDecision.mode === 'ACCEPT' ? 'Accept' : 'Reject'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showUpdateStatus && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-lg">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h2 className="text-base font-bold text-slate-900">Update Status</h2>
                            <p className="mt-1 text-xs text-slate-500">Update the referral status and provide a remark.</p>
                        </div>
                        <div className="px-5 py-4 space-y-4">
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-600">New Status</label>
                                <select
                                    value={updateStatusValue}
                                    onChange={(e) => setUpdateStatusValue(e.target.value)}
                                    className="h-10 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                >
                                    <option value="PROCESSING">Processing</option>
                                    <option value="FOR_COMPLIANCE">For Compliance</option>
                                    <option value="COMPLETED">Completed</option>
                                </select>
                            </div>
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-600">Remark</label>
                                <textarea
                                    value={updateStatusRemark}
                                    onChange={(e) => setUpdateStatusRemark(e.target.value)}
                                    rows={4}
                                    placeholder="Enter a remark for this status update..."
                                    className="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                />
                            </div>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                            <button onClick={() => { setShowUpdateStatus(false); setUpdateStatusRemark(''); }}
                                className="h-9 rounded border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button onClick={() => {
                                const trimmed = updateStatusRemark.trim();
                                if (!trimmed) return;
                                router.patch(route('referrals.update-status', referral.id), {
                                    status: updateStatusValue,
                                    decision_comment: trimmed,
                                }, {
                                    preserveScroll: true,
                                    onSuccess: () => { setShowUpdateStatus(false); setUpdateStatusRemark(''); },
                                });
                            }} disabled={!updateStatusRemark.trim()}
                                className="h-9 rounded bg-blue-900 px-4 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                Update Status
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {showMilestoneModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" onClick={() => { setShowMilestoneModal(false); milestoneForm.reset(); }}>
                    <div className="w-full max-w-lg rounded-md border border-slate-200 bg-white shadow-lg" onClick={(e) => e.stopPropagation()}>
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h3 className="text-[16px] font-extrabold text-slate-900">Add Milestone</h3>
                            <p className="mt-1 text-[12px] text-slate-500">Record a new milestone for this referral.</p>
                        </div>
                        <form onSubmit={handleMilestoneModalSubmit}>
                            <div className="px-5 py-4 space-y-4">
                                {showMilestoneAgencyWarning && (
                                    <div className="flex gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2">
                                        <span className="material-symbols-outlined text-[18px] text-amber-600">warning</span>
                                        <p className="text-[12px] font-semibold leading-5 text-amber-800">
                                            You are adding this milestone on behalf of {receivingAgencyName}. Continue only if this update was coordinated with the agency.
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <InputLabel htmlFor="milestone_title" value="Title *" />
                                    <TextInput
                                        id="milestone_title"
                                        type="text"
                                        className="mt-1 block w-full"
                                        value={milestoneForm.data.title}
                                        onChange={(e) => milestoneForm.setData('title', e.target.value)}
                                    />
                                    <InputError message={milestoneForm.errors.title} className="mt-2" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="milestone_description" value="Description" />
                                    <textarea
                                        id="milestone_description"
                                        className="mt-1 block w-full rounded-md border border-slate-200 px-3 py-2 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-blue-900 resize-none"
                                        rows={3}
                                        value={milestoneForm.data.description}
                                        onChange={(e) => milestoneForm.setData('description', e.target.value)}
                                    />
                                    <InputError message={milestoneForm.errors.description} className="mt-2" />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                                <button
                                    type="button"
                                    onClick={() => { setShowMilestoneModal(false); milestoneForm.reset(); }}
                                    className="h-[34px] px-3 border border-slate-200 bg-white text-slate-700 text-[11px] font-bold rounded-md hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                                <PrimaryButton disabled={milestoneForm.processing || !milestoneForm.data.title}>
                                    Add Milestone
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            <AuditLogModal
                show={showAuditLog}
                onClose={() => setShowAuditLog(false)}
                entityType="referral"
                entityId={referral.id}
                title={`Audit Log — ${referral.case_file?.case_number ?? 'Referral'}`}
            />
            <PeerProfileModal user={peerProfileUser} show={!!peerProfileUser} onClose={() => setPeerProfileUser(null)} />
            <ConfirmDialog
                open={!!removeAttachment}
                tone="danger"
                title="Remove Document"
                message="Are you sure you want to remove this document? This action is audited and cannot be undone."
                confirmLabel="Remove"
                cancelLabel="Cancel"
                onCancel={() => setRemoveAttachment(null)}
                onConfirm={() => {
                    if (!removeAttachment) return;
                    router.post(
                        route('referrals.attachments.delete', [referral.id, removeAttachment.id]),
                        {},
                        {
                            preserveScroll: true,
                            onSuccess: () => setRemoveAttachment(null),
                            onError: () => setRemoveAttachment(null),
                        }
                    );
                }}
            />
        </AppLayout>
    );
}
