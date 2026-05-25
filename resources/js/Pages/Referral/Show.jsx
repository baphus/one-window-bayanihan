import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, useRef, useMemo, useEffect } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { CardSection, MetaTile, InfoCell } from '@/Components/ui/CardSection';
import { formatDisplayDateTime, formatDisplayDate } from '@/lib/utils';

const statusStyles = {
    PENDING: 'border-[#fde68a] bg-[#fef3c7] text-[#b45309]',
    PROCESSING: 'border-[#bae6fd] bg-[#e0f2fe] text-[#0369a1]',
    'FOR COMPLIANCE': 'border-[#fed7aa] bg-[#ffedd5] text-[#c2410c]',
    COMPLETED: 'border-[#bbf7d0] bg-[#dcfce7] text-[#15803d]',
    REJECTED: 'border-[#fecaca] bg-[#fee2e2] text-[#b91c1c]',
};

const statusTransitions = {
    PENDING: ['PROCESSING', 'FOR COMPLIANCE', 'REJECTED'],
    PROCESSING: ['FOR COMPLIANCE', 'COMPLETED', 'REJECTED'],
    'FOR COMPLIANCE': ['PROCESSING', 'COMPLETED', 'REJECTED'],
    COMPLETED: [],
    REJECTED: [],
};

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

export default function ReferralShow({ referral, serviceRequirements }) {
    const { auth } = usePage().props;
    const isAgency = auth.user.role === 'AGENCY';
    const isCaseManager = auth.user.role === 'CASE_MANAGER';
    const currentUser = auth.user;

    const statusForm = useForm({ status: '', decision: '', decision_comment: '' });
    const milestoneForm = useForm({ title: '', description: '' });

    const statusInitialRef = useRef({ status: '', decision: '', decision_comment: '' });
    const milestoneInitialRef = useRef({ title: '', description: '' });
    const hasDirty = useMemo(() => (
        statusForm.data.status !== statusInitialRef.current.status
        || statusForm.data.decision !== statusInitialRef.current.decision
        || statusForm.data.decision_comment !== statusInitialRef.current.decision_comment
        || milestoneForm.data.title !== milestoneInitialRef.current.title
        || milestoneForm.data.description !== milestoneInitialRef.current.description
    ), [statusForm.data, milestoneForm.data]);
    const { showModal, confirmNavigation, cancelNavigation } = useUnsavedChanges(hasDirty);

    const documents = referral.attachments?.filter((a) => !a.is_archived) ?? [];
    const allDocuments = referral.attachments ?? [];
    const referredServices = parseReferredServices(referral.required_services);
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

    const [isAddCommentOpen, setIsAddCommentOpen] = useState(false);
    const [commentDraft, setCommentDraft] = useState('');
    const [pendingComment, setPendingComment] = useState(null);
    const [pendingReplyToCommentId, setPendingReplyToCommentId] = useState(null);
    const [pendingReplacements, setPendingReplacements] = useState({});
    const [pendingAttachments, setPendingAttachments] = useState([]);
    const [isConfirmModalOpen, setIsConfirmModalOpen] = useState(false);
    const [activeVersionGroupId, setActiveVersionGroupId] = useState(null);
    const [saveMessage, setSaveMessage] = useState('');
    const [isSaving, setIsSaving] = useState(false);

    const fileInputRefs = useRef({});
    const attachInputRef = useRef(null);

    const replyToComment = pendingReplyToCommentId
        ? comments.find((c) => c.id === pendingReplyToCommentId) ?? null
        : null;

    const hasPendingChanges = Boolean(pendingComment?.trim()) || Object.keys(pendingReplacements).length > 0 || pendingAttachments.length > 0;

    const pendingChangeSummary = [
        pendingComment?.trim()
            ? `${replyToComment ? `Reply to ${replyToComment.user?.name ?? 'comment'}` : 'Add comment'}: "${pendingComment.trim().slice(0, 90)}${pendingComment.trim().length > 90 ? '...' : ''}"`
            : null,
        ...Object.entries(pendingReplacements).map(([docId, file]) => {
            const targetDoc = documents.find((d) => d.id === docId);
            return `Replace document: ${targetDoc?.file_name ?? 'Unknown'} -> ${file.name}`;
        }),
        ...pendingAttachments.map((file) => `Attach document: ${file.name}`),
    ].filter(Boolean);

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

    function handleStatusUpdate(e) {
        e.preventDefault();
        statusForm.patch(route('referrals.update-status', referral.id));
    }

    function handleMilestoneSubmit(e) {
        e.preventDefault();
        milestoneForm.post(route('referrals.milestones.store', referral.id), {
            preserveScroll: true,
            onSuccess: () => milestoneForm.reset(),
        });
    }

    function queueComment() {
        const trimmed = commentDraft.trim();
        if (!trimmed) return;
        setPendingComment(trimmed);
        setIsAddCommentOpen(false);
        setCommentDraft('');
    }

    function discardPendingChanges() {
        setPendingComment(null);
        setPendingReplyToCommentId(null);
        setPendingReplacements({});
        setPendingAttachments([]);
        setIsConfirmModalOpen(false);
    }

    async function saveConfirmedChanges() {
        if (!hasPendingChanges || isSaving) return;
        setIsSaving(true);

        try {
            if (pendingComment?.trim()) {
                if (replyToComment) {
                    await new Promise((resolve, reject) => {
                        router.post(route('referrals.comments.reply', [referral.id, replyToComment.id]), {
                            content: pendingComment.trim(),
                        }, {
                            preserveScroll: true,
                            onSuccess: () => resolve(),
                            onError: (err) => reject(new Error(Object.values(err).join(', '))),
                        });
                    });
                } else {
                    await new Promise((resolve, reject) => {
                        router.post(route('referrals.comments.store', referral.id), {
                            content: pendingComment.trim(),
                        }, {
                            preserveScroll: true,
                            onSuccess: () => resolve(),
                            onError: (err) => reject(new Error(Object.values(err).join(', '))),
                        });
                    });
                }
            }

            for (const [docId, file] of Object.entries(pendingReplacements)) {
                const formData = new FormData();
                formData.append('file', file);
                await new Promise((resolve, reject) => {
                    router.post(route('referrals.attachments.replace', [referral.id, docId]), formData, {
                        preserveScroll: true,
                        headers: { 'Content-Type': 'multipart/form-data' },
                        onSuccess: () => resolve(),
                        onError: (err) => reject(new Error(Object.values(err).join(', '))),
                    });
                });
            }

            for (const file of pendingAttachments) {
                const formData = new FormData();
                formData.append('file', file);
                await new Promise((resolve, reject) => {
                    router.post(route('referrals.attachments.store', referral.id), formData, {
                        preserveScroll: true,
                        headers: { 'Content-Type': 'multipart/form-data' },
                        onSuccess: () => resolve(),
                        onError: (err) => reject(new Error(Object.values(err).join(', '))),
                    });
                });
            }

            setPendingComment(null);
            setPendingReplyToCommentId(null);
            setPendingReplacements({});
            setPendingAttachments([]);
            setIsConfirmModalOpen(false);
            setSaveMessage(`Saved ${formatDisplayDateTime(new Date().toISOString())}.`);

            router.reload({ preserveScroll: true });
        } catch (err) {
            alert('Failed to save changes: ' + err.message);
        } finally {
            setIsSaving(false);
        }
    }

    function handleDocumentSelect(event) {
        const files = Array.from(event.target.files ?? []);
        const targetId = event.target.dataset.docId;
        if (!targetId || !files[0]) return;
        setPendingReplacements((prev) => ({ ...prev, [targetId]: files[0] }));
        event.target.value = '';
    }

    function handleAttachDocumentsSelect(event) {
        const files = Array.from(event.target.files ?? []);
        if (!files.length) return;
        setPendingAttachments((prev) => [...prev, ...files]);
        event.target.value = '';
    }

    function removePendingAttachment(index) {
        setPendingAttachments((prev) => prev.filter((_, i) => i !== index));
    }

    const allowedTransitions = statusTransitions[referral.status] || [];
    const canUpdateStatus = allowedTransitions.length > 0 && (isAgency || isCaseManager);

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
                <Link
                    href={route('referrals.index')}
                    className="h-[34px] px-3 border border-[#cbd5e1] bg-white text-slate-700 text-[11px] font-bold rounded-[3px] inline-flex items-center hover:bg-slate-50"
                >
                    Back
                </Link>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-12 gap-4">
                <main className="xl:col-span-8 space-y-4">
                    <CardSection title="Referral Information" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border border-[#d8dee8]">
                            <InfoCell label="Receiving Agency" value={referral.agency?.name ?? 'N/A'} />
                            <InfoCell label="Status" value={
                                <span className={`inline-flex rounded-[2px] border px-2 py-0.5 text-[10px] font-extrabold uppercase ${statusStyles[referral.status] || ''}`}>
                                    {referral.status}
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
                                onChange={handleAttachDocumentsSelect}
                                className="hidden"
                            />
                        </div>

                        {pendingAttachments.length > 0 && (
                            <div className="mb-3 rounded-[3px] border border-amber-200 bg-amber-50 px-3 py-2 space-y-2">
                                <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-700">Pending Attachments</p>
                                <div className="space-y-1.5">
                                    {pendingAttachments.map((file, index) => (
                                        <div key={`${file.name}-${file.size}-${index}`} className="flex items-center justify-between gap-2 rounded-[2px] border border-amber-200 bg-white px-2 py-1.5">
                                            <p className="min-w-0 truncate text-[11px] text-slate-700">{file.name}</p>
                                            <button
                                                type="button"
                                                onClick={() => removePendingAttachment(index)}
                                                className="text-[10px] font-semibold text-amber-700 hover:underline"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    ))}
                                </div>
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
                                                                            {pendingReplacements[document.id] && (
                                                                                <p className="mt-1 text-[10px] font-semibold text-amber-700">
                                                                                    Pending replacement: {pendingReplacements[document.id].name}
                                                                                </p>
                                                                            )}
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
                                                                                onChange={handleDocumentSelect}
                                                                                className="hidden"
                                                                            />
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
                                                    {pendingReplacements[doc.id] && (
                                                        <p className="mt-1 text-[10px] font-semibold text-amber-700">
                                                            Pending replacement: {pendingReplacements[doc.id].name}
                                                        </p>
                                                    )}
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
                                                        onChange={handleDocumentSelect}
                                                        className="hidden"
                                                    />
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

                    {canUpdateStatus && (
                        <CardSection title="Update Status" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                            <form onSubmit={handleStatusUpdate} className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="status" value="New Status" />
                                    <select
                                        id="status"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={statusForm.data.status}
                                        onChange={(e) => statusForm.setData('status', e.target.value)}
                                    >
                                        <option value="">Select status...</option>
                                        {allowedTransitions.map((s) => (
                                            <option key={s} value={s}>{s}</option>
                                        ))}
                                    </select>
                                    <InputError message={statusForm.errors.status} className="mt-2" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="decision" value="Decision" />
                                    <select
                                        id="decision"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={statusForm.data.decision}
                                        onChange={(e) => statusForm.setData('decision', e.target.value)}
                                    >
                                        <option value="">Select decision...</option>
                                        <option value="ACCEPT">Accept</option>
                                        <option value="REJECT">Reject</option>
                                    </select>
                                    <InputError message={statusForm.errors.decision} className="mt-2" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="decision_comment" value="Decision Comment" />
                                    <textarea
                                        id="decision_comment"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        rows={3}
                                        value={statusForm.data.decision_comment}
                                        onChange={(e) => statusForm.setData('decision_comment', e.target.value)}
                                    />
                                    <InputError message={statusForm.errors.decision_comment} className="mt-2" />
                                </div>
                                <PrimaryButton disabled={statusForm.processing || !statusForm.data.status}>
                                    Update Status
                                </PrimaryButton>
                            </form>
                        </CardSection>
                    )}

                    {isAgency && (
                        <CardSection title="Add Milestone" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                            <form onSubmit={handleMilestoneSubmit} className="space-y-4">
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
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        rows={2}
                                        value={milestoneForm.data.description}
                                        onChange={(e) => milestoneForm.setData('description', e.target.value)}
                                    />
                                    <InputError message={milestoneForm.errors.description} className="mt-2" />
                                </div>
                                <PrimaryButton disabled={milestoneForm.processing || !milestoneForm.data.title}>
                                    Add Milestone
                                </PrimaryButton>
                            </form>
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
                    </CardSection>

                    <CardSection title="Case Comments" className="[&>h3]:text-[#1f2937] [&>h3]:tracking-[0.14em]">
                        <div className="mb-3 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    setIsAddCommentOpen((prev) => !prev);
                                    if (!isAddCommentOpen) setPendingReplyToCommentId(null);
                                }}
                                className="h-[28px] px-3 bg-[#0b5384] text-white text-[10px] font-bold rounded-[3px] border border-[#0b5384] hover:bg-[#09416a]"
                            >
                                {pendingReplyToCommentId ? 'Reply' : 'Add Comment'}
                            </button>
                        </div>

                        <div className="max-h-[340px] border border-[#d8dee8] bg-[#f8fafc] p-3 overflow-y-auto">
                            {isAddCommentOpen && (
                                <div className="mb-3 space-y-2 border border-[#d8dee8] bg-white p-3">
                                    {replyToComment && (
                                        <div className="flex items-center justify-between gap-2 rounded-[2px] border border-[#d8dee8] bg-[#f8fafc] px-2.5 py-1.5">
                                            <p className="text-[10px] text-slate-600">Replying to {replyToComment.user?.name ?? 'comment'}</p>
                                            <button
                                                type="button"
                                                onClick={() => setPendingReplyToCommentId(null)}
                                                className="text-[10px] font-semibold text-slate-600 hover:underline"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    )}
                                    <textarea
                                        value={commentDraft}
                                        onChange={(e) => setCommentDraft(e.target.value)}
                                        rows={3}
                                        className="w-full rounded-[3px] border border-[#cbd5e1] px-3 py-2 text-[12px] text-slate-700 outline-none focus:border-[#0b5384]"
                                        placeholder={replyToComment ? 'Write a reply...' : 'Write a comment...'}
                                    />
                                    <div className="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setIsAddCommentOpen(false);
                                                setCommentDraft('');
                                                setPendingReplyToCommentId(null);
                                            }}
                                            className="h-[28px] px-3 border border-[#cbd5e1] bg-white text-slate-700 text-[10px] font-bold rounded-[3px] hover:bg-slate-50"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            onClick={queueComment}
                                            className="h-[28px] px-3 bg-[#0b5384] text-white text-[10px] font-bold rounded-[3px] border border-[#0b5384] hover:bg-[#09416a]"
                                        >
                                            {replyToComment ? 'Queue Reply' : 'Queue Comment'}
                                        </button>
                                    </div>
                                </div>
                            )}

                            {pendingComment?.trim() && (
                                <div className="mb-3 rounded-[3px] border border-amber-200 bg-amber-50 px-3 py-2">
                                    <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-700">
                                        {replyToComment ? 'Pending Reply' : 'Pending Comment'}
                                    </p>
                                    {replyToComment && (
                                        <p className="mt-1 text-[10px] text-amber-700">Replying to {replyToComment.user?.name ?? 'comment'}</p>
                                    )}
                                    <p className="mt-1 text-[11px] text-amber-900">{pendingComment}</p>
                                </div>
                            )}

                            {topLevelComments.length > 0 ? (
                                <div className="space-y-3">
                                    {topLevelComments.map((comment) => {
                                        const replies = comment.replies ?? [];
                                        return (
                                            <div key={comment.id} className="border border-[#e2e8f0] bg-white rounded-[2px] px-2.5 py-2">
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <p className="text-[11px] text-slate-700 whitespace-pre-wrap">{comment.content}</p>
                                                        <p className="mt-1 text-[9px] text-slate-400">
                                                            {comment.user?.name ?? 'Unknown'} &middot; {formatDisplayDateTime(comment.created_at)}
                                                            {comment.is_edited && ' (edited)'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="mt-1.5 flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setPendingReplyToCommentId(comment.id);
                                                            setIsAddCommentOpen(true);
                                                            setCommentDraft('');
                                                        }}
                                                        className="text-[9px] font-bold text-[#0b5384] hover:underline"
                                                    >
                                                        Reply
                                                    </button>
                                                </div>
                                                {replies.length > 0 && (
                                                    <div className="mt-2 ml-3 pl-3 border-l-2 border-[#d8dee8] space-y-2">
                                                        {replies.map((reply) => (
                                                            <div key={reply.id}>
                                                                <p className="text-[11px] text-slate-700 whitespace-pre-wrap">{reply.content}</p>
                                                                <p className="mt-0.5 text-[9px] text-slate-400">
                                                                    {reply.user?.name ?? 'Unknown'} &middot; {formatDisplayDateTime(reply.created_at)}
                                                                    {reply.is_edited && ' (edited)'}
                                                                </p>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="text-[11px] text-slate-500 text-center py-4">No comments yet.</p>
                            )}
                        </div>
                    </CardSection>
                </aside>
            </div>

            {saveMessage && (
                <p className="mt-4 text-[11px] font-semibold text-[#0b5384]">{saveMessage}</p>
            )}

            {hasPendingChanges && (
                <button
                    type="button"
                    onClick={() => setIsConfirmModalOpen(true)}
                    className="fixed bottom-6 right-6 z-30 h-[38px] px-4 bg-[#0b5384] text-white text-[11px] font-bold rounded-[3px] border border-[#0b5384] shadow-lg hover:bg-[#09416a]"
                >
                    Review Pending Changes
                </button>
            )}

            {isConfirmModalOpen && (
                <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/40 p-4">
                    <div className="w-full max-w-lg rounded-[3px] border border-[#d8dee8] bg-white p-4 shadow-xl">
                        <h3 className="text-[14px] font-extrabold text-slate-800">Confirm Referral Changes</h3>
                        <p className="mt-1 text-[11px] text-slate-500">Review what will be saved to comments and documents.</p>
                        <div className="mt-3 max-h-[260px] space-y-2 overflow-y-auto border border-[#e2e8f0] bg-[#f8fafc] p-3">
                            {pendingChangeSummary.length > 0 ? (
                                pendingChangeSummary.map((change) => (
                                    <p key={change} className="text-[11px] text-slate-700">&bull; {change}</p>
                                ))
                            ) : (
                                <p className="text-[11px] text-slate-500">No pending changes.</p>
                            )}
                        </div>
                        <div className="mt-4 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setIsConfirmModalOpen(false)}
                                className="h-[32px] px-3 border border-[#cbd5e1] bg-white text-slate-700 text-[11px] font-bold rounded-[3px] hover:bg-slate-50"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                onClick={discardPendingChanges}
                                className="h-[32px] px-3 border border-red-200 bg-red-50 text-red-700 text-[11px] font-bold rounded-[3px] hover:bg-red-100"
                            >
                                Discard
                            </button>
                            <button
                                type="button"
                                onClick={saveConfirmedChanges}
                                disabled={isSaving}
                                className="h-[32px] px-3 bg-[#0b5384] text-white text-[11px] font-bold rounded-[3px] border border-[#0b5384] hover:bg-[#09416a] disabled:opacity-60"
                            >
                                {isSaving ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

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

            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
        </AppLayout>
    );
}
