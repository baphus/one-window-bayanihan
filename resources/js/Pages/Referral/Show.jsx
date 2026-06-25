import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, useRef, useMemo } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { CardSection, MetaTile, InfoCell } from '@/Components/ui/CardSection';
import StatusBadge from '@/Components/ui/StatusBadge';
import UserAvatar from '@/Components/ui/UserAvatar';
import PeerProfileModal from '@/Components/PeerProfileModal';
import { formatDisplayDateTime, formatDisplayDate } from '@/lib/utils';

function parseReferredServices(serviceValue) {
    if (!serviceValue) return [];
    const normalized = serviceValue
        .split(/[,;]+/)
        .map((s) => s.trim())
        .filter(Boolean);
    return normalized.length ? normalized : [serviceValue.trim()].filter(Boolean);
}

function normalizeDocumentName(value) {
    return value.toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}

function getRequirementMatchScore(requirement, documentName) {
    const normalizedRequirement = normalizeDocumentName(requirement);
    const normalizedDocumentName = normalizeDocumentName(documentName);
    if (!normalizedRequirement || !normalizedDocumentName) return 0;
    if (normalizedDocumentName.includes(normalizedRequirement)) return 100;
    const requirementKeywords = normalizedRequirement.split(' ').filter((t) => t.length >= 4);
    if (!requirementKeywords.length) return normalizedDocumentName.includes(normalizedRequirement) ? 1 : 0;
    return requirementKeywords.reduce((score, keyword) => {
        return normalizedDocumentName.includes(keyword) ? score + 1 : score;
    }, 0);
}

function matchRequirementsToDocuments(requirements, documents) {
    const remaining = [...documents];
    const matches = requirements.map((requirement) => {
        let bestIndex = -1;
        let bestScore = 0;
        remaining.forEach((doc, index) => {
            const score = getRequirementMatchScore(requirement, doc.file_name);
            if (score > bestScore) {
                bestScore = score;
                bestIndex = index;
            }
        });
        const matchedDocument = bestIndex >= 0 ? remaining.splice(bestIndex, 1)[0] : null;
        return { requirement, document: matchedDocument };
    });
    return { matches, unassignedDocuments: remaining };
}

export default function ReferralShow({ referral, serviceRequirements, overdueDays = 7 }) {
    const { auth } = usePage().props;
    const isAgency = auth.user.role === 'AGENCY';
    const isCaseManager = auth.user.role === 'CASE_MANAGER';
    const isIntervention = referral.type === 'intervention';
    const canAddMilestone = isAgency || (isCaseManager && isIntervention);
    const canUpdateStatus = isAgency || (isCaseManager && isIntervention);

    const documents = referral.attachments?.filter((a) => !a.is_archived) ?? [];
    const allDocuments = referral.attachments ?? [];
    const referredServices = parseReferredServices(referral.required_services);
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

    const serviceRequirementGroups = referredServices.map((serviceTitle) => {
        const matched = (serviceRequirements ?? []).find(
            (s) => s.title.toLowerCase() === serviceTitle.toLowerCase()
        );
        return {
            serviceTitle,
            requiredDocuments: matched?.requiredDocuments ?? [],
        };
    });

    const groupedRequirements = useMemo(() => {
        const initial = { groups: [], unassignedDocuments: [...documents] };
        return serviceRequirementGroups.reduce((acc, group) => {
            const { matches, unassignedDocuments } = matchRequirementsToDocuments(
                group.requiredDocuments, acc.unassignedDocuments
            );
            return { groups: [...acc.groups, { ...group, matches }], unassignedDocuments };
        }, initial);
    }, [documents, serviceRequirementGroups]);

    const comments = referral.comments ?? [];
    const topLevelComments = comments.filter((c) => !c.parent_id);
    const caseFile = referral.case_file;
    const caseComments = caseFile?.comments ?? [];
    const topLevelCaseComments = caseComments.filter((c) => !c.parent_id);

    const [peerProfileUser, setPeerProfileUser] = useState(null);
    const [showOverdueInfo, setShowOverdueInfo] = useState(false);

    const [pendingDecision, setPendingDecision] = useState(null);
    const [decisionRemark, setDecisionRemark] = useState('');
    const [showUpdateStatus, setShowUpdateStatus] = useState(false);
    const [updateStatusValue, setUpdateStatusValue] = useState('PROCESSING');
    const [updateStatusRemark, setUpdateStatusRemark] = useState('');

    const [commentDraft, setCommentDraft] = useState('');
    const [replyToCommentId, setReplyToCommentId] = useState(null);
    const [postingComment, setPostingComment] = useState(false);
    const [caseCommentDraft, setCaseCommentDraft] = useState('');
    const [caseReplyToCommentId, setCaseReplyToCommentId] = useState(null);
    const [casePostingComment, setCasePostingComment] = useState(false);
    const [activeVersionGroupId, setActiveVersionGroupId] = useState(null);

    const [showMilestoneModal, setShowMilestoneModal] = useState(false);
    const milestoneForm = useForm({ title: '', description: '' });

    const fileInputRefs = useRef({});
    const attachInputRef = useRef(null);
    const commentsEndRef = useRef(null);

    const replyToComment = replyToCommentId
        ? comments.find((c) => c.id === replyToCommentId) ?? null
        : null;

    const timelineItems = (referral.milestones || []).map((ms) => ({
        id: ms.id,
        title: ms.title,
        description: ms.description,
        timestamp: ms.created_at,
        actor: ms.user?.name ?? 'System',
    }));

    const orderedTimeline = [...timelineItems].sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));

    const documentVersionRows = activeVersionGroupId
        ? allDocuments
            .filter((doc) => (doc.version_group_id ?? doc.id) === activeVersionGroupId)
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        : [];

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

    function handleDocumentReplace(event) {
        const files = Array.from(event.target.files ?? []);
        const targetId = event.target.dataset.docId;
        if (!targetId || !files[0]) return;
        event.target.value = '';
        const formData = new FormData();
        formData.append('file', files[0]);
        router.post(route('referrals.attachments.replace', [referral.id, targetId]), formData, {
            preserveScroll: true,
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    }

    function handleAttachDocuments(event) {
        const files = Array.from(event.target.files ?? []);
        if (!files.length) return;
        event.target.value = '';
        files.forEach((file) => {
            const formData = new FormData();
            formData.append('file', file);
            router.post(route('referrals.attachments.store', referral.id), formData, {
                preserveScroll: true,
                headers: { 'Content-Type': 'multipart/form-data' },
            });
        });
    }

    function cancelReply() {
        setReplyToCommentId(null);
    }

    function handlePostCaseComment() {
        const trimmed = caseCommentDraft.trim();
        if (!trimmed || casePostingComment) return;
        const caseId = referral.case_id;
        if (!caseId) return;
        setCasePostingComment(true);
        const replyingTo = caseReplyToCommentId
            ? caseComments.find((c) => c.id === caseReplyToCommentId) ?? null
            : null;
        const routeName = replyingTo
            ? 'cases.comments.reply'
            : 'cases.comments.store';
        const routeParams = replyingTo
            ? [caseId, caseReplyToCommentId]
            : [caseId];
        router.post(route(routeName, routeParams), {
            content: trimmed,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setCaseCommentDraft('');
                setCaseReplyToCommentId(null);
                setCasePostingComment(false);
            },
            onError: () => setCasePostingComment(false),
        });
    }

    function cancelCaseReply() {
        setCaseReplyToCommentId(null);
    }

    return (
        <AppLayout title="Referral Detail">
            <Head title="Referral Detail" />

            <div className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500 mb-5">
                <Link href={route('referrals.index')} className="transition hover:text-[#0b5384]">Referrals</Link>
                <span className="mx-2">&gt;</span>
                <span>{referral.case_file?.case_number ?? referral.id}</span>
            </div>

            <div className="flex items-start justify-between gap-4 flex-wrap mb-6">
                <h1 className="text-3xl md:text-[34px] font-black leading-tight tracking-tight text-slate-900">Referral Details</h1>
                <div className="flex items-center gap-2">
                    {canUpdateStatus && referral.status === 'PENDING' && (
                        <>
                            <button
                                onClick={() => setPendingDecision({ id: referral.id, mode: 'ACCEPT', status: 'PROCESSING' })}
                                className="h-[34px] px-3 bg-emerald-600 text-white text-[11px] font-bold rounded-[3px] border border-emerald-600 hover:bg-emerald-700"
                            >
                                Accept
                            </button>
                            <button
                                onClick={() => setPendingDecision({ id: referral.id, mode: 'REJECT', status: 'REJECTED' })}
                                className="h-[34px] px-3 bg-red-50 text-red-700 text-[11px] font-bold rounded-[3px] border border-red-200 hover:bg-red-100"
                            >
                                Reject
                            </button>
                        </>
                    )}
                    {canUpdateStatus && !['PENDING', 'COMPLETED', 'REJECTED'].includes(referral.status) && (
                        <button
                            onClick={() => { setShowUpdateStatus(true); setUpdateStatusValue(referral.status); setUpdateStatusRemark(''); }}
                            className="h-[34px] px-3 border border-[#cbd5e1] bg-white text-slate-700 text-[11px] font-bold rounded-[3px] inline-flex items-center hover:bg-slate-50"
                        >
                            Update Status
                        </button>
                    )}
                    <Link
                        href={route('referrals.index')}
                        className="h-[34px] px-3 border border-[#cbd5e1] bg-white text-slate-700 text-[11px] font-bold rounded-[3px] inline-flex items-center hover:bg-slate-50"
                    >
                        Back
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-12 gap-4">
                <main className="xl:col-span-8 space-y-4">
                    <CardSection title="Referral Information" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                        {isOverdue && (
                            <div className="mb-3 rounded-[3px] border border-red-200 bg-red-50">
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
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border border-[#d8dee8]">
                            <InfoCell label="Receiving Agency" value={referral.agency?.name ?? 'N/A'} />
                            <InfoCell label="Status" value={
                                <span className="inline-flex items-center gap-1.5">
                                    <StatusBadge status={referral.status} />
                                    {isIntervention && (
                                        <span className="inline-flex items-center rounded-full bg-[#7c3aed] px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.1em] text-white">
                                            DMW Intervention
                                        </span>
                                    )}
                                </span>
                            } />
                            <InfoCell label="Associated Case No." value={
                                <Link href={route('cases.show', referral.case_id)} className="text-[#0b5384] hover:underline">
                                    {referral.case_file?.case_number ?? 'N/A'}
                                </Link>
                            } />
                            <InfoCell label="Tracking ID" value={referral.case_file?.tracker_number ?? 'N/A'} />
                            <InfoCell label="Date Referred" value={formatDisplayDateTime(referral.created_at)} />
                            <InfoCell label="Last Updated" value={formatDisplayDateTime(referral.updated_at)} />
                        </div>
                        {referral.required_services && (
                            <div className="px-3 py-2 border-b border-[#d8dee8]">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Required Services</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700">{referral.required_services}</p>
                            </div>
                        )}
                        {referral.notes && (
                            <div className="px-3 py-2 border-b border-[#d8dee8]">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Notes</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700 whitespace-pre-wrap">{referral.notes}</p>
                            </div>
                        )}
                        {referral.decision && (
                            <div className="px-3 py-2 border-b border-[#d8dee8]">
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

                    <CardSection title="Attached Documents" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                        {!isAgency && (
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-[3px] border border-[#d8dee8] bg-[#f8fafc] px-3 py-2">
                                <p className="text-[10px] text-slate-600">Attach additional files for this referral.</p>
                                <button
                                    type="button"
                                    onClick={() => attachInputRef.current?.click()}
                                    className="h-[28px] px-3 bg-[#0b5384] text-white text-[10px] font-bold rounded-[3px] border border-[#0b5384] hover:bg-[#09416a]"
                                >
                                    Attach Document
                                </button>
                                <input
                                    ref={attachInputRef}
                                    type="file"
                                    multiple
                                    onChange={handleAttachDocuments}
                                    className="hidden"
                                />
                            </div>
                        )}

                        {documents.length > 0 ? (
                            <div className="space-y-3">
                                {groupedRequirements.groups.map((group) => {
                                    const attachedCount = group.matches.filter((m) => Boolean(m.document)).length;
                                    return (
                                        <div key={group.serviceTitle} className="rounded-[3px] border border-[#d8dee8] bg-[#f8fafc] p-3 space-y-2">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <h4 className="text-[11px] font-extrabold uppercase tracking-[0.08em] text-[#334155]">{group.serviceTitle}</h4>
                                                <span className="text-[10px] font-bold text-slate-500">
                                                    Requirements Attached: {attachedCount}/{group.requiredDocuments.length}
                                                </span>
                                            </div>
                                            {group.requiredDocuments.length > 0 ? (
                                                <div className="space-y-2">
                                                    {group.matches.map(({ requirement, document }) => {
                                                        const isAttached = Boolean(document);
                                                        return (
                                                            <div key={`${group.serviceTitle}-${requirement}`} className="rounded-[2px] border border-[#e2e8f0] bg-white px-2.5 py-2">
                                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                                    <p className="text-[11px] text-slate-700">{requirement}</p>
                                                                    <span className={`inline-flex items-center rounded-[2px] px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-[0.08em] ${
                                                                        isAttached
                                                                            ? 'bg-[#ecfdf5] text-[#166534] border border-[#86efac]'
                                                                            : 'bg-amber-50 text-amber-700 border border-amber-200'
                                                                    }`}>
                                                                        {isAttached ? 'Attached' : 'Missing'}
                                                                    </span>
                                                                </div>
                                                                {document && (
                                                                    <div className="mt-1.5 flex items-center justify-between gap-3 rounded-[2px] border border-[#dbeafe] bg-[#eff6ff] px-2 py-1.5">
                                                                        <div className="min-w-0">
                                                                            <p className="text-[10px] font-bold text-[#0b5384] truncate">{document.file_name}</p>
                                                                            <p className="text-[9px] text-slate-500 truncate">
                                                                                {document.user?.name ?? 'Unknown'} &middot; {formatDisplayDateTime(document.created_at)}
                                                                            </p>
                                                                        </div>
                                                                        <div className="flex items-center gap-2 shrink-0">
                                                                            <a href={document.file_url} target="_blank" rel="noopener noreferrer" className="text-[10px] text-[#0b5384] font-bold hover:underline">View</a>
                                                                            {document.version_group_id && (
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => setActiveVersionGroupId(document.version_group_id)}
                                                                                    className="text-[10px] text-slate-600 font-bold hover:underline"
                                                                                >
                                                                                    Versions
                                                                                </button>
                                                                            )}
                                                                            {!isAgency && (
                                                                            <>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => fileInputRefs.current[document.id]?.click()}
                                                                                    className="text-[10px] text-[#0b5384] font-bold hover:underline"
                                                                                >
                                                                                    Replace
                                                                                </button>
                                                                                <input
                                                                                    ref={(el) => { fileInputRefs.current[document.id] = el; }}
                                                                                    data-doc-id={document.id}
                                                                                    type="file"
                                                                                    onChange={handleDocumentReplace}
                                                                                    className="hidden"
                                                                                />
                                                                            </>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            ) : (
                                                <p className="text-[11px] text-slate-500">No required documents configured for this referred service.</p>
                                            )}
                                        </div>
                                    );
                                })}

                                {groupedRequirements.unassignedDocuments.length > 0 && (
                                    <div className="rounded-[3px] border border-[#d8dee8] bg-[#f8fafc] p-3 space-y-2">
                                        <h4 className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Other Attached Files</h4>
                                        {groupedRequirements.unassignedDocuments.map((doc) => (
                                            <div key={doc.id} className="bg-white border border-[#e2e8f0] p-2.5 flex items-center justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="text-[11px] font-bold text-slate-700 truncate">{doc.file_name}</p>
                                                    <p className="text-[9px] text-slate-400 truncate">
                                                        {doc.user?.name ?? 'Unknown'} &middot; {formatDisplayDateTime(doc.created_at)}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <a href={doc.file_url} target="_blank" rel="noopener noreferrer" className="text-[10px] text-[#0b5384] font-bold hover:underline">View</a>
                                                    {doc.version_group_id && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setActiveVersionGroupId(doc.version_group_id)}
                                                            className="text-[10px] text-slate-600 font-bold hover:underline"
                                                        >
                                                            Versions
                                                        </button>
                                                    )}
                                                    {!isAgency && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() => fileInputRefs.current[doc.id]?.click()}
                                                            className="text-[10px] text-[#0b5384] font-bold hover:underline"
                                                        >
                                                            Replace
                                                        </button>
                                                        <input
                                                            ref={(el) => { fileInputRefs.current[doc.id] = el; }}
                                                            data-doc-id={doc.id}
                                                            type="file"
                                                            onChange={handleDocumentReplace}
                                                            className="hidden"
                                                        />
                                                    </>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="border border-dashed border-[#cbd5e1] rounded-[3px] p-4 text-center">
                                <p className="text-[11px] text-slate-500">No documents attached to this referral.</p>
                            </div>
                        )}
                    </CardSection>

                    {referral.compliance_requirements?.length > 0 && (
                        <CardSection title="For Compliance" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                            <div className="space-y-3">
                                {referral.compliance_requirements.map((cr) => (
                                    <div key={cr.id} className="rounded-[3px] border border-slate-200 bg-white px-4 py-3">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <p className="text-[11px] text-slate-500">{cr.service_name}</p>
                                                <p className="mt-0.5 text-[12px] font-semibold text-slate-700">{cr.requirement_name}</p>
                                            </div>
                                            <span className={`shrink-0 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-[2px] ${
                                                cr.status === 'COMPLIED'
                                                    ? 'border border-green-200 bg-green-50 text-green-700'
                                                    : 'border border-orange-200 bg-orange-50 text-orange-700'
                                            }`}>
                                                {cr.status}
                                            </span>
                                        </div>

                                        {cr.status === 'PENDING' ? (
                                            <div className="mt-3">
                                                <label className="inline-flex cursor-pointer items-center gap-2 rounded-[3px] bg-indigo-50 px-3 py-1.5 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors">
                                                    <span>Upload to Fulfill</span>
                                                    <input
                                                        type="file"
                                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp"
                                                        className="hidden"
                                                        onChange={(e) => {
                                                            const file = e.target.files?.[0];
                                                            if (!file) return;
                                                            e.target.value = '';
                                                            router.post(
                                                                route('referrals.compliance.fulfill', [referral.id, cr.id]),
                                                                { file },
                                                                { preserveScroll: true }
                                                            );
                                                        }}
                                                    />
                                                </label>
                                            </div>
                                        ) : (
                                            <div className="mt-2 text-[11px] text-slate-500">
                                                Fulfilled {cr.completed_at ? new Date(cr.completed_at).toLocaleDateString() : ''}
                                                {cr.fulfilled_by_name ? ` by ${cr.fulfilled_by_name}` : ''}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardSection>
                    )}

                </main>

                <aside className="xl:col-span-4 space-y-4">
                    <CardSection title="Referral Timeline" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                        {orderedTimeline.length > 0 ? (
                            <div className="mt-1 relative pl-4">
                                <div className="absolute left-[4px] top-1 bottom-1 w-px bg-[#cbd5e1]" />
                                <div className="flex flex-col-reverse gap-4">
                                    {orderedTimeline.map((item) => (
                                        <div key={item.id} className="relative flex items-start gap-3">
                                            <div className="mt-0.5 -ml-[18px] h-5 w-5 overflow-hidden rounded-full border border-white bg-[#0b5384] shadow-sm z-10 flex items-center justify-center">
                                                <span className="material-symbols-outlined text-[12px] text-white">flag</span>
                                            </div>
                                            <div>
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
                            <p className="text-[11px] text-slate-500 py-2">No timeline events recorded.</p>
                        )}
                        {canAddMilestone && (
                            <button
                                type="button"
                                onClick={() => setShowMilestoneModal(true)}
                                className="mt-3 h-[28px] w-full px-3 bg-[#0b5384] text-white text-[10px] font-bold rounded-[3px] border border-[#0b5384] hover:bg-[#09416a] transition-colors"
                            >
                                + Add Milestone
                            </button>
                        )}
                    </CardSection>

                    <CardSection title="Case Narrative" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                        {referral.case_file?.summary ? (
                            <p className="text-[12px] leading-5 text-slate-600 whitespace-pre-wrap">{referral.case_file.summary}</p>
                        ) : (
                            <p className="text-[12px] text-slate-500 italic">No case narrative recorded.</p>
                        )}
                    </CardSection>

                    <CardSection title="Referral Comments" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                        <div className="max-h-[340px] overflow-y-auto space-y-3">
                            {topLevelComments.length > 0 ? (
                                topLevelComments.map((comment) => {
                                    const replies = comment.replies ?? [];
                                    return (
                                        <div key={comment.id} className="rounded-[3px] border border-[#e2e8f0] bg-white shadow-sm">
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
                                                        className="mt-1 text-[9px] font-bold text-[#0b5384] hover:text-[#09416a] transition-colors"
                                                    >
                                                        Reply
                                                    </button>
                                                </div>
                                            </div>
                                            {replies.length > 0 && (
                                                <div className="ml-8 mr-3 pb-2.5 space-y-2">
                                                    {replies.map((reply) => (
                                                        <div key={reply.id} className="flex items-start gap-2 rounded-[2px] bg-[#f8fafc] px-2.5 py-2">
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
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[#f1f5f9] border border-[#e2e8f0]">
                                        <span className="material-symbols-outlined text-[18px] text-slate-400">chat_bubble_outline</span>
                                    </div>
                                    <p className="mt-2 text-[11px] font-semibold text-slate-500">No comments yet</p>
                                    <p className="text-[10px] text-slate-400">Start the conversation below.</p>
                                </div>
                            )}
                        </div>

                        <div className="mt-3 pt-3 border-t border-[#e2e8f0]">
                            {replyToComment && (
                                <div className="mb-2 flex items-center justify-between rounded-[3px] bg-[#f0f7ff] border border-[#bfdbfe] px-2.5 py-1.5">
                                    <p className="text-[10px] text-[#0b5384] font-semibold truncate">
                                        Replying to <span className="font-bold">{replyToComment.user?.name ?? 'comment'}</span>
                                    </p>
                                    <button
                                        type="button"
                                        onClick={cancelReply}
                                        className="text-[10px] font-bold text-[#0b5384] hover:underline shrink-0 ml-2"
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
                                        className="w-full rounded-[3px] border border-[#cbd5e1] px-3 py-1.5 text-[12px] text-slate-700 outline-none focus:border-[#0b5384] focus:ring-1 focus:ring-[#0b5384]/20 resize-none transition-colors"
                                        placeholder={replyToComment ? 'Write a reply...' : 'Write a comment...'}
                                    />
                                    <div className="mt-1.5 flex items-center justify-between">
                                        <span className="text-[9px] text-slate-400">{replyToComment ? 'Your reply will be posted immediately' : 'Your comment will be posted immediately'}</span>
                                        <button
                                            type="button"
                                            onClick={handlePostComment}
                                            disabled={postingComment || !commentDraft.trim()}
                                            className="h-[26px] px-3 bg-[#0b5384] text-white text-[10px] font-bold rounded-[3px] border border-[#0b5384] hover:bg-[#09416a] disabled:opacity-60 transition-colors"
                                        >
                                            {postingComment ? 'Posting...' : 'Post'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardSection>

                    <CardSection title="Case Comments" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                        <div className="max-h-[340px] overflow-y-auto space-y-3">
                            {topLevelCaseComments.length > 0 ? (
                                topLevelCaseComments.map((comment) => {
                                    const replies = comment.replies ?? [];
                                    return (
                                        <div key={comment.id} className="rounded-[3px] border border-[#e2e8f0] bg-white shadow-sm">
                                            <div className="flex items-start gap-2.5 px-3 pt-2.5 pb-2">
                                                <UserAvatar user={comment.user} size="sm" onClick={() => setPeerProfileUser(comment.user)} />
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
                                                        className="mt-1 text-[9px] font-bold text-[#0b5384] hover:text-[#09416a] transition-colors"
                                                    >
                                                        Reply
                                                    </button>
                                                </div>
                                            </div>
                                            {replies.length > 0 && (
                                                <div className="ml-8 mr-3 pb-2.5 space-y-2">
                                                    {replies.map((reply) => (
                                                        <div key={reply.id} className="flex items-start gap-2 rounded-[2px] bg-[#f8fafc] px-2.5 py-2">
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
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[#f1f5f9] border border-[#e2e8f0]">
                                        <span className="material-symbols-outlined text-[18px] text-slate-400">chat_bubble_outline</span>
                                    </div>
                                    <p className="mt-2 text-[11px] font-semibold text-slate-500">No case comments yet</p>
                                    <p className="text-[10px] text-slate-400">Write a comment about this case.</p>
                                </div>
                            )}
                        </div>

                        <div className="mt-3 pt-3 border-t border-[#e2e8f0]">
                            {caseReplyToCommentId && (
                                <div className="mb-2 flex items-center justify-between rounded-[3px] bg-[#f0f7ff] border border-[#bfdbfe] px-2.5 py-1.5">
                                    <p className="text-[10px] text-[#0b5384] font-semibold truncate">
                                        Replying to <span className="font-bold">{caseComments.find(c => c.id === caseReplyToCommentId)?.user?.name ?? 'comment'}</span>
                                    </p>
                                    <button
                                        type="button"
                                        onClick={cancelCaseReply}
                                        className="text-[10px] font-bold text-[#0b5384] hover:underline shrink-0 ml-2"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            )}
                            <div className="flex items-start gap-2">
                                <UserAvatar user={auth.user} size="sm" />
                                <div className="flex-1 min-w-0">
                                    <textarea
                                        value={caseCommentDraft}
                                        onChange={(e) => setCaseCommentDraft(e.target.value)}
                                        rows={2}
                                        className="w-full rounded-[3px] border border-[#cbd5e1] px-3 py-1.5 text-[12px] text-slate-700 outline-none focus:border-[#0b5384] focus:ring-1 focus:ring-[#0b5384]/20 resize-none transition-colors"
                                        placeholder={caseReplyToCommentId ? 'Write a reply...' : 'Write a comment about this case...'}
                                    />
                                    <div className="mt-1.5 flex items-center justify-between">
                                        <span className="text-[9px] text-slate-400">{caseReplyToCommentId ? 'Your reply will be posted immediately' : 'Your comment will be posted immediately'}</span>
                                        <button
                                            type="button"
                                            onClick={handlePostCaseComment}
                                            disabled={casePostingComment || !caseCommentDraft.trim()}
                                            className="h-[26px] px-3 bg-[#0b5384] text-white text-[10px] font-bold rounded-[3px] border border-[#0b5384] hover:bg-[#09416a] disabled:opacity-60 transition-colors"
                                        >
                                            {casePostingComment ? 'Posting...' : 'Post'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardSection>
                </aside>
            </div>

            {activeVersionGroupId && (
                <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/40 p-4" onClick={() => setActiveVersionGroupId(null)}>
                    <div className="w-full max-w-xl rounded-[3px] border border-[#d8dee8] bg-white p-4 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between gap-2">
                            <h3 className="text-[14px] font-extrabold text-slate-800">Document Versions</h3>
                            <button
                                type="button"
                                onClick={() => setActiveVersionGroupId(null)}
                                className="h-[28px] px-3 border border-[#cbd5e1] bg-white text-slate-700 text-[10px] font-bold rounded-[3px] hover:bg-slate-50"
                            >
                                Close
                            </button>
                        </div>
                        <div className="mt-3 max-h-[300px] space-y-2 overflow-y-auto border border-[#e2e8f0] bg-[#f8fafc] p-3">
                            {documentVersionRows.length > 0 ? (
                                documentVersionRows.map((doc) => (
                                    <div key={doc.id} className="flex items-center justify-between gap-2 border border-[#d8dee8] bg-white p-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-[11px] font-bold text-slate-700">{doc.file_name}</p>
                                            <p className="text-[10px] text-slate-500">
                                                {formatDisplayDateTime(doc.created_at)} &middot; {doc.user?.name ?? 'Unknown'} &middot; {doc.is_archived ? 'Archived' : 'Current'}
                                            </p>
                                        </div>
                                        <a href={doc.file_url} target="_blank" rel="noopener noreferrer" className="text-[10px] text-[#0b5384] font-bold hover:underline shrink-0">View</a>
                                    </div>
                                ))
                            ) : (
                                <p className="text-[11px] text-slate-500 text-center">No versions found.</p>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {pendingDecision && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-xl">
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
                                        className="h-10 w-full rounded border border-slate-300 px-3 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
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
                    <div className="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-xl">
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
                                    className="h-10 w-full rounded border border-slate-300 px-3 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
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
                    <div className="w-full max-w-lg rounded-[3px] border border-[#d8dee8] bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="border-b border-[#e2e8f0] px-5 py-4">
                            <h3 className="text-[16px] font-extrabold text-slate-900">Add Milestone</h3>
                            <p className="mt-1 text-[12px] text-slate-500">Record a new milestone for this referral.</p>
                        </div>
                        <form onSubmit={handleMilestoneModalSubmit}>
                            <div className="px-5 py-4 space-y-4">
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
                                        className="mt-1 block w-full rounded-[3px] border border-[#cbd5e1] px-3 py-2 text-[13px] text-slate-700 outline-none focus:ring-1 focus:ring-[#0b5384] resize-none"
                                        rows={3}
                                        value={milestoneForm.data.description}
                                        onChange={(e) => milestoneForm.setData('description', e.target.value)}
                                    />
                                    <InputError message={milestoneForm.errors.description} className="mt-2" />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 border-t border-[#e2e8f0] px-5 py-3">
                                <button
                                    type="button"
                                    onClick={() => { setShowMilestoneModal(false); milestoneForm.reset(); }}
                                    className="h-[34px] px-3 border border-[#cbd5e1] bg-white text-slate-700 text-[11px] font-bold rounded-[3px] hover:bg-slate-50"
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
            <PeerProfileModal user={peerProfileUser} show={!!peerProfileUser} onClose={() => setPeerProfileUser(null)} />
        </AppLayout>
    );
}
