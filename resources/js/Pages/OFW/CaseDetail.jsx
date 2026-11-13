import Select from '@/Components/Select';
import { useMemo, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Copy } from 'lucide-react';
import OfwPortalLayout from '@/Layouts/OfwPortalLayout';
import OfwBreadcrumbs from '@/Components/OfwBreadcrumbs';
import CitizenTimeline from '@/Components/CitizenTimeline';
import { AgencyCard, DocumentRow, StatusBar } from '@/Components/CitizenStatus';
import InputError from '@/Components/InputError';
import { DOCUMENT_SLOT_LABELS } from '@/Components/CaseIntakeForm';
import { humanizeStatus } from '@/lib/statusLabels';
import { buildCaseStatusLine } from '@/lib/caseStatusLine';
import safeRoute from '@/utils/safeRoute';

/**
 * My Requests — request record detail.
 *
 * Reads like a case logbook: a record header answers "where is my case right
 * now", the timeline shows what was recorded and by whom, each partner office
 * has its own card with a referral status, and the requests and documents
 * the citizen can see sit at the bottom.
 */

const REQUEST_STATUS = {
    OPEN: 'Open',
    IN_PROGRESS: 'In progress',
    CLIENT_RESPONDED: 'Response received',
};

function formatRequestStatus(status) {
    return REQUEST_STATUS[status] ?? (humanizeStatus(status) || 'Unknown');
}

/** Owner-side upload for exactly the slots staff requested on this pending case. */
function IntakeReplacementPanel({ caseId, slots = [], reason = '' }) {
    const form = useForm({ documents: {} });
    const [files, setFiles] = useState({});

    function submit(event) {
        event.preventDefault();
        const documents = Object.fromEntries(Object.entries(files).filter(([, file]) => file));
        if (Object.keys(documents).length === 0) return;
        form.transform(() => ({ documents }));
        form.post(safeRoute('ofw.intake.documents.replace', caseId, `/my-cases/${caseId}/intake-documents`), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { setFiles({}); form.reset(); },
        });
    }

    if (!slots.length) return null;

    return (
        <form onSubmit={submit} className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p className="text-[12px] font-bold text-amber-900">Documents needed from you</p>
            {reason && <p className="mt-1 text-[12px] leading-relaxed text-amber-800">{reason}</p>}
            <div className="mt-3 space-y-2">
                {slots.map((slot) => (
                    <div key={slot}>
                        <label htmlFor={`replacement-${slot}`} className="block text-[12px] font-semibold text-amber-900">
                            {DOCUMENT_SLOT_LABELS[slot] ?? slot}
                        </label>
                        <input
                            id={`replacement-${slot}`}
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            onChange={(event) => setFiles((prev) => ({ ...prev, [slot]: event.target.files?.[0] ?? null }))}
                            className="mt-1 block w-full text-[12px] text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-[12px] file:font-bold"
                        />
                        <InputError message={form.errors[`documents.${slot}`]} className="mt-1" />
                    </div>
                ))}
            </div>
            <button type="submit" disabled={form.processing || Object.values(files).every((file) => !file)} className="mt-3 inline-flex min-h-[38px] items-center rounded-md bg-primary px-4 text-[12px] font-bold text-white disabled:opacity-50">
                {form.processing ? 'Uploading…' : 'Submit documents'}
            </button>
        </form>
    );
}

const REFERRAL_STATUS = {
    PENDING: {
        label: 'Pending',
        badge: 'border-slate-200 bg-slate-100 text-slate-600',
        note: 'Your referral has been sent and is awaiting agency action.',
    },
    PROCESSING: {
        label: 'Processing',
        badge: 'border-primary/25 bg-primary/10 text-primary',
        note: 'Your referral is being reviewed.',
    },
    FOR_COMPLIANCE: {
        label: 'Action Required',
        badge: 'border-amber-200 bg-amber-50 text-amber-700',
        note: 'This office has requested a supporting document from you.',
    },
    COMPLETED: {
        label: 'Completed',
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        note: 'This office recorded that assistance for its referral was completed.',
    },
    REJECTED: {
        label: 'Declined',
        badge: 'border-rose-200 bg-rose-50 text-rose-700',
        note: 'This office was unable to process the referral. Your case manager will advise you on the next steps.',
    },
};

function formatLongDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

function formatShortDate(dateStr) {
    if (!dateStr) return '—';
    const match = String(dateStr).match(/^(\\d{4})-(\\d{2})-(\\d{2})$/);
    const date = match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : new Date(dateStr);
    return Number.isNaN(date.getTime()) ? String(dateStr) : date.toLocaleDateString('en-US', { day: 'numeric', month: 'long', year: 'numeric' });
}

function displayAnswer(value) {
    if (value === null || value === undefined || value === '') return 'Not provided';
    if (typeof value === 'object') return Array.isArray(value) ? value.map(displayAnswer).join(', ') : Object.entries(value).map(([key, item]) => `${key.replace(/[_-]/g, ' ')}: ${displayAnswer(item)}`).join('; ');
    return String(value);
}

/** Lightweight toast for the copy-tracking-number affordance. */
function useCopyToast() {
    const [toast, setToast] = useState('');
    function copy(text, label) {
        if (!text) return;
        try {
            navigator.clipboard?.writeText(text);
        } catch {
            // Clipboard may be unavailable (permissions, non-secure context);
            // the toast still confirms intent so the affordance reads as working.
        }
        setToast(label ?? 'Tracking number copied');
        setTimeout(() => setToast(''), 1800);
    }
    return { toast, copy };
}

/**
 * Derive the case's presentation state from the real record.
 *
 * `action`   — a partner office asked for a document (FOR_COMPLIANCE referral)
 * `closed`   — the case is finished (CLOSED / ARCHIVED)
 * `progress` — everything else
 */
function useCaseState(trackedCase, trackingAgencies) {
    return useMemo(() => {
        const actionAgencies = trackingAgencies.filter((a) => a.status === 'FOR_COMPLIANCE');
        const normalized = String(trackedCase?.status ?? '').toUpperCase();
        const isDeclined = normalized === 'REJECTED';
        const isClosed = normalized === 'CLOSED' || normalized === 'ARCHIVED' || normalized === 'RESOLVED' || isDeclined;

        if (actionAgencies.length > 0) return { kind: 'action', actionAgencies, isClosed };
        if (isDeclined) return { kind: 'declined', actionAgencies, isClosed, isDeclined };
        if (isClosed) return { kind: 'closed', actionAgencies, isClosed, isDeclined };
        return { kind: 'progress', actionAgencies, isClosed, isDeclined };
    }, [trackedCase, trackingAgencies]);
}

/** The four-column meta strip under the record header. */
function CaseMetaGrid({ trackedCase, caseOverview }) {
    const categories = trackedCase?.categories ?? [];
    const categoryLabel = categories.length > 0
        ? categories.map((c) => c?.name ?? c).join(', ')
        : caseOverview?.category ?? '—';

    const cells = [
        { label: 'Submitted', value: formatShortDate(trackedCase?.createdAt) },
        { label: 'Category', value: categoryLabel },
        { label: 'Assigned Office', value: caseOverview?.assignedOffice ?? 'DMW Region VII' },
        { label: 'Request Team', value: caseOverview?.caseTeam ?? 'DMW Case Management Team' },
    ];

    return (
        <dl className="grid grid-cols-2 gap-x-6 gap-y-4 border-t border-slate-200 px-5 py-4 sm:grid-cols-4">
            {cells.map((cell) => (
                <div key={cell.label}>
                    <dt className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">{cell.label}</dt>
                    <dd className="mt-1 text-[13px] font-medium text-slate-700">{cell.value}</dd>
                </div>
            ))}
        </dl>
    );
}

/** Recent updates aside: the latest recorded update per office. */
function RecentUpdates({ trackingAgencies, milestoneTimeline }) {
    const recent = useMemo(() => {
        const byAgency = new Map();
        for (const item of milestoneTimeline) {
            if (!item.agency) continue;
            if (!byAgency.has(item.agency)) byAgency.set(item.agency, item);
        }

        if (byAgency.size > 0) {
            return Array.from(byAgency.entries())
                .slice(0, 4)
                .map(([agency, item]) => ({ agency, item }));
        }

        return trackingAgencies.slice(0, 4).map((agency) => ({
            agency: agency.name,
            item: { title: agency.latestMilestoneLabel ?? 'Referral sent', date: null },
        }));
    }, [trackingAgencies, milestoneTimeline]);

    if (recent.length === 0) return null;

    return (
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="border-b border-slate-100 px-5 py-3.5">
                <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">
                    Recent Updates
                </h2>
            </header>
            <ul className="divide-y divide-slate-100">
                {recent.map(({ agency, item }, index) => (
                    <li key={index} className="px-5 py-3.5">
                        <p className="text-[11px] font-medium text-slate-400">
                            {item.date ? formatShortDate(item.date) : 'Recorded update'}
                        </p>
                        <p className="mt-0.5 text-[13px] font-bold text-primary">{agency}</p>
                        <p className="mt-0.5 text-[13px] text-slate-600">{item.title ?? ''}</p>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** "Need help?" aside card. */
function HelpCard() {
    return (
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="px-5 py-5">
                <span
                    aria-hidden="true"
                    className="material-symbols-outlined text-[20px] leading-none text-slate-400"
                >
                    help
                </span>
                <h2 className="mt-3 font-headline text-[17px] font-extrabold tracking-tight text-slate-900">
                    Need help with your request?
                </h2>
                <p className="mt-2 text-[13px] leading-relaxed text-slate-600">
                    Contact DMW for help understanding your request status or responding to an agency request.
                </p>

                <Link
                    href={safeRoute('home', undefined, '/')}
                    className="mt-4 flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-[13px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    Help Center
                </Link>

                <Link
                    href={safeRoute('contact', undefined, '/contact')}
                    className="mt-3 block text-center text-[13px] font-bold text-primary transition-colors hover:text-primary/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    Contact DMW
                </Link>

                <p className="mt-4 flex items-start gap-2 border-t border-slate-100 pt-4 text-[12px] leading-relaxed text-slate-500">
                    <span
                        aria-hidden="true"
                        className="material-symbols-outlined mt-px shrink-0 text-[16px] leading-none text-slate-400"
                    >
                        shield
                    </span>
                    <span>Keep your tracking number private. Share it only through official DMW channels.</span>
                </p>
            </div>
        </section>
    );
}

export default function CaseDetail({
    trackingId,
    trackedCase,
    caseOverview,
    milestoneTimeline = [],
    completionPercentage = 0,
    trackingAgencies = [],
    rejectedCount = 0,
    documents = [],
    requestDocuments = [],
    case: caseFile,
    intakeAnswers = {},
}) {
    const requestedSlots = caseFile?.requested_document_slots
        ?? trackedCase?.requestedDocumentSlots
        ?? [];
    const requestedReason = caseFile?.document_request_reason ?? trackedCase?.documentRequestReason ?? '';
    // Outstanding requirements (case-level) are owed by the OFW; a closed case keeps them readable but not uploadable.
    const requirementSlots = (caseFile?.document_requirements ?? [])
        .filter((requirement) => requirement.state === 'for_compliance')
        .map((requirement) => requirement.slot);
    const totalAgencies = trackingAgencies.length;
    const completedAgencies = trackingAgencies.filter((a) => a.status === 'COMPLETED').length;

    const { toast, copy } = useCopyToast();
    const { kind, actionAgencies, isClosed, isDeclined } = useCaseState(trackedCase, trackingAgencies);
    const requestAgencies = trackingAgencies.filter((agency) => agency.openRequest);
    const submittedDocuments = caseFile?.documents ?? documents;
    const intakeFields = caseFile ?? trackedCase ?? {};
    const actionableRequests = requestAgencies.filter((agency) => agency.openRequest.status === 'OPEN');
    const requestsNeedAttention = actionableRequests.length > 0;
    const [documentsTab, setDocumentsTab] = useState(() => requirementSlots.length || requestedSlots.length ? 'documents' : 'requests');

    const statusLine = useMemo(
        () => buildCaseStatusLine({
            status: trackedCase?.status,
            totalAgencies,
            completedAgencies,
            rejectedCount,
        }),
        [trackedCase, totalAgencies, completedAgencies, rejectedCount],
    );

    /**
     * The recorded events, oldest first — the timeline reads forward.
     * The newest event is the current one; anything after it is pending.
     */
    const timelineEvents = useMemo(() => {
        const sorted = [...milestoneTimeline].sort((a, b) => {
            const at = new Date(a.date ?? a.timestamp ?? a.created_at ?? a.createdAt ?? 0).getTime();
            const bt = new Date(b.date ?? b.timestamp ?? b.created_at ?? b.createdAt ?? 0).getTime();
            if (Number.isNaN(at) || Number.isNaN(bt)) return 0;
            return at - bt;
        });

        return sorted.map((item, index) => ({
            ...item,
            state: index === sorted.length - 1 ? 'current' : 'complete',
        }));
    }, [milestoneTimeline]);

    const statusBar = useMemo(() => {
        if (isDeclined) {
            return <StatusBar tone="negative" title="This request was declined." detail="Submit a fresh intake to request assistance. This will create a new case." />;
        }

        if (requestsNeedAttention) {
            return (
                <StatusBar
                    tone="action"
                    title="A response is needed for the agency request."
                    detail="Open the Requests tab below to view the agency request."
                />
            );
        }

        if (kind === 'action') {
            return (
                <StatusBar
                    tone="action"
                    title="An agency needs additional information."
                    detail="No linked response request is available. Review agency progress below or contact your case manager."
                />
            );
        }

        if (kind === 'closed') {
            return (
                <StatusBar
                    tone="positive"
                    title="No outstanding requests."
                    detail="Only authorized DMW staff can reopen a request."
                />
            );
        }

        if (requirementSlots.length || requestedSlots.length) {
            return (
                <StatusBar
                    tone="action"
                    title="Documents are needed from you."
                    detail="Open My Submitted Documents below to upload them."
                />
            );
        }

        return (
            <StatusBar
                tone="positive"
                title="No action is needed from you right now."
                detail="We will let you know here when a partner office needs something from you."
            />
        );
    }, [kind, isDeclined, requestsNeedAttention, requirementSlots.length, requestedSlots.length]);

    const recordBadge = kind === 'closed'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-primary/25 bg-primary/10 text-primary';

    const recordBadgeLabel = kind === 'declined' ? 'Declined' : kind === 'closed' ? 'Request Closed' : 'Open — In Progress';

    return (
        <OfwPortalLayout title={trackingId ? `Request — ${trackingId}` : 'Request Details'}>
            <OfwBreadcrumbs items={[
                { label: 'My Requests', href: safeRoute('ofw.dashboard', undefined, '/my-cases') },
                { label: trackingId ? `Request ${trackingId}` : 'Request' },
            ]} />

            <h1 className="mt-2 font-headline text-[28px] font-extrabold tracking-tight text-slate-900 sm:text-[32px]">
                My Request Tracking
            </h1>
            <p className="mt-1.5 text-[14px] text-slate-600">
                Track your request, follow agency updates, and see if any action is needed from you.
            </p>

            {/* Request selector */}
            {trackingId ? (
                <div className="mt-5">
                    <label htmlFor="request-selector" className="sr-only">Select a request</label>
                    <Select
                        id="request-selector"
                        value={trackingId}
                        onChange={() => {}}
                        className="inline-flex items-center gap-2 py-2 pl-3 pr-8 text-[13px] font-semibold"
                    >
                        <option value={trackingId}>{trackingId}</option>
                    </Select>
                </div>
            ) : null}

            {/* Status banner */}
            {requestsNeedAttention ? (
                <div className="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-5 py-4">
                    <div className="flex items-start gap-3">
                        <span
                            aria-hidden="true"
                            className="material-symbols-outlined mt-px shrink-0 text-[18px] leading-none text-amber-600"
                        >
                            error
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-[13px] font-bold text-amber-800">
                                Action Required: Respond to an Agency Request
                            </p>
                            <p className="mt-0.5 text-[12px] text-amber-700">
                                {actionableRequests[0]?.name ?? 'A partner office'} requested action on your assistance request.
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Link
                                    href={actionableRequests[0].openRequest.url}
                                    className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3.5 py-2 text-[12px] font-bold text-white transition-colors hover:bg-primary-container focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                >
                                    View Request and Respond
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => setDocumentsTab('requests')}
                                    className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3.5 py-2 text-[12px] font-bold text-primary transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                >
                                    View All Requests
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            ) : kind === 'action' ? (
                <div className="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-5 py-4">
                    <p className="text-[13px] font-bold text-amber-800">Action Required: Agency compliance update</p>
                    <p className="mt-0.5 text-[12px] text-amber-700">
                        {actionAgencies[0]?.name ?? 'A partner office'} marked this referral for compliance. No linked response request is available here yet. View agency progress below or contact your case manager.
                    </p>
                </div>
            ) : null}

            {kind === 'declined' ? (
                <div className="mt-5 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-5 py-4">
                    <span aria-hidden="true" className="material-symbols-outlined mt-px shrink-0 text-[18px] leading-none text-rose-600">cancel</span>
                    <div>
                        <p className="text-[13px] font-bold text-rose-800">Request Declined</p>
                        <p className="mt-0.5 text-[12px] text-rose-700">Submit a fresh intake to request assistance. You will receive a new case number; information from this case will not be copied.</p>
                        <Link href={safeRoute('ofw.intake', undefined, '/my-cases/intake')} className="mt-3 inline-flex min-h-[36px] items-center rounded-md bg-primary px-3.5 text-[12px] font-bold text-white hover:bg-primary-container">Submit a fresh intake</Link>
                    </div>
                </div>
            ) : kind === 'closed' ? (
                <div className="mt-5 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-4">
                    <span
                        aria-hidden="true"
                        className="material-symbols-outlined mt-px shrink-0 text-[18px] leading-none text-emerald-600"
                    >
                        check_circle
                    </span>
                    <div>
                        <p className="text-[13px] font-bold text-emerald-800">Request Closed</p>
                        <p className="mt-0.5 text-[12px] text-emerald-700">
                            There are no outstanding requests. You can still view your documents and full request history.
                        </p>
                    </div>
                </div>
            ) : null}

            {/* Record header */}
            <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="px-5 py-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">
                            Request for Assistance
                        </p>
                        <span className={`inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11px] font-semibold ${recordBadge}`}>
                            <span className="h-1.5 w-1.5 rounded-full bg-current" />
                            {recordBadgeLabel}
                        </span>
                    </div>

                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <h2 className="font-headline font-mono text-[26px] font-extrabold tracking-tight text-slate-900">
                            {trackingId ?? 'Request details'}
                        </h2>
                        {trackingId ? (
                            <button
                                type="button"
                                onClick={() => copy(trackingId, 'Tracking number copied')}
                                aria-label="Copy tracking number"
                                title="Copy tracking number"
                                className="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-primary transition-colors hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            >
                                <Copy className="h-4 w-4" aria-hidden="true" />
                            </button>
                        ) : null}
                    </div>

                    <p className="mt-2 text-[13px] leading-relaxed text-slate-600">{statusLine}</p>
                    {trackedCase?.status === 'REJECTED' && trackedCase?.rejectionReason ? (
                        <div className="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-[13px] text-rose-800">
                            <p className="font-bold">Why this request was not approved</p>
                            <p className="mt-1 leading-relaxed">{trackedCase.rejectionReason}</p>
                        </div>
                    ) : null}
                </div>

                <CaseMetaGrid trackedCase={trackedCase} caseOverview={caseOverview} />

                {totalAgencies > 0 ? (
                    <div className="border-t border-slate-100 px-5 py-4">
                        <div className="flex h-1.5 gap-1" role="img" aria-label={`${completionPercentage}% of processing complete`}>
                            {trackingAgencies.map((a) => (
                                <span
                                    key={a.referralId ?? a.name}
                                    title={`${a.name} — ${(REFERRAL_STATUS[a.status] ?? REFERRAL_STATUS.PENDING).label}`}
                                    className={`flex-1 rounded-sm ${
                                        a.status === 'COMPLETED' ? 'bg-emerald-300' :
                                        a.status === 'REJECTED' ? 'bg-rose-300/60' :
                                        a.status === 'FOR_COMPLIANCE' ? 'bg-amber-300/70' :
                                        a.status === 'PROCESSING' ? 'bg-blue-300/80' :
                                        'bg-slate-200'
                                    }`}
                                >
                                    <span className="sr-only">{`${a.name} — ${(REFERRAL_STATUS[a.status] ?? REFERRAL_STATUS.PENDING).label}`}</span>
                                </span>
                            ))}
                        </div>
                        <p className="mt-2 text-[11px] text-slate-400">
                            Last updated {formatLongDate(trackedCase?.updatedAt)} · {completionPercentage}% of processing complete
                        </p>
                    </div>
                ) : null}
            </section>

            <section className="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header className="border-b border-slate-100 px-5 py-4">
                    <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">Your submitted intake</h2>
                    <p className="mt-1 text-[12px] text-slate-500">Latest submitted version · Answers not provided at submission are shown below.</p>
                </header>
                <div className="space-y-5 px-5 py-5">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {[
                            ['OFW details', [['First name', intakeFields.first_name], ['Middle name', intakeFields.middle_name], ['Last name', intakeFields.last_name], ['Suffix', intakeFields.suffix], ['Client type', intakeFields.client_type], ['Date of birth', intakeFields.date_of_birth], ['Age at intake', intakeFields.age_at_intake], ['Sex', intakeFields.sex], ['Email', intakeFields.email], ['Contact number', intakeFields.contact_number], ['Civil status', intakeFields.civil_status === 'OTHER' ? intakeFields.civil_status_other : intakeFields.civil_status], ['Vulnerability', intakeFields.vulnerability_indicator]]],
                            ['Home address', [['Street', intakeFields.address_street], ['Barangay', intakeFields.address_barangay], ['City / municipality', intakeFields.address_city_municipality], ['Province', intakeFields.address_province], ['Region', intakeFields.address_region]]],
                            ['Employment and return', [['Employer', intakeFields.employment_employer_name], ['Employer address', intakeFields.employment_employer_address], ['Position', intakeFields.employment_position], ['Jobsite country or territory', intakeFields.employment_country], ['Last occupation', intakeFields.employment_last_position], ['Arrival in Philippines', intakeFields.employment_date_of_arrival], ['Deployment mode', intakeFields.deployment_mode], ['Employment status', intakeFields.employment_status], ['Return reason', intakeFields.return_reason]]],
                            ...(intakeFields.client_type === 'NEXT_OF_KIN' ? [['Next of kin', [['Name', intakeFields.nok_name], ['Relationship', intakeFields.nok_relationship], ['Contact number', intakeFields.nok_contact_number], ['Email', intakeFields.nok_email]]]] : []),
                            ['Your statement and requested assistance', [['Statement', intakeFields.summary ?? caseOverview?.narrative], ['Assistance selected', (intakeFields.proposed_assessments ?? []).map((value) => value.replace(/_/g, ' '))], ['Other assistance', intakeFields.proposed_assessment_other]]],
                        ].map(([section, fields]) => <section key={section} className="rounded-lg border border-slate-100 p-3">
                            <h3 className="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">{section}</h3>
                            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">{fields.map(([label, value]) => <div key={label} className="min-w-0"><dt className="text-[11px] font-semibold text-slate-500">{label}</dt><dd className="mt-1 break-words whitespace-pre-wrap text-[13px] text-slate-800">{label.toLowerCase().includes('date') || label.startsWith('Arrival') ? (value ? formatShortDate(value) : 'Not provided') : displayAnswer(value)}</dd></div>)}</dl>
                        </section>)}
                    </div>
                    {submittedDocuments.length > 0 && <section className="border-t border-slate-100 pt-4">
                        <h3 className="mb-3 text-[11px] font-bold uppercase tracking-wide text-slate-500">Submitted documents</h3>
                        <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {submittedDocuments.map((doc) => {
                                const url = doc.download_url ?? doc.url;
                                const label = doc.requirement_label ?? doc.display_name ?? DOCUMENT_SLOT_LABELS[doc.category ?? doc.slot] ?? 'Submitted document';
                                return <li key={doc.id ?? doc.category ?? doc.slot ?? label} className="min-w-0 rounded-lg border border-slate-200 p-3">
                                    <p className="text-[12px] font-semibold text-slate-800">{label}</p>
                                    {url && <a href={url} aria-label={`Download ${label}`} className="mt-2 inline-flex items-center gap-1 text-[12px] font-bold text-primary underline"><span className="material-symbols-outlined text-[15px]" aria-hidden="true">download</span>Download document</a>}
                                </li>;
                            })}
                        </ul>
                    </section>}
                </div>
            </section>

            {/* Timeline + aside */}
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div className="min-w-0 space-y-6">
                    {/* Request timeline */}
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <header className="border-b border-slate-100 px-5 py-4">
                            <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">
                                Request Timeline
                            </h2>
                            <p className="mt-0.5 text-[12px] text-slate-500">
                                {kind === 'closed'
                                    ? 'Complete recorded history · Read only'
                                    : 'Follow the recorded milestones for your request.'}
                            </p>
                        </header>
                        <CitizenTimeline
                            events={timelineEvents}
                            statusBar={statusBar}
                            emptyMessage="No milestones are recorded for this request yet."
                        />
                    </section>

                    {/* Agencies */}
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <header className="border-b border-slate-100 px-5 py-4">
                            <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">
                                Agencies Handling Your Request
                            </h2>
                            <p className="mt-0.5 text-[12px] text-slate-500">
                                Each agency has a separate referral status.
                            </p>
                        </header>
                        {totalAgencies > 0 ? (
                            <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                                {trackingAgencies.map((agency) => {
                                    const status = REFERRAL_STATUS[agency.status] ?? REFERRAL_STATUS.PENDING;

                                    return (
                                        <AgencyCard
                                            key={agency.referralId ?? agency.name}
                                            name={agency.name}
                                            logoUrl={agency.logoUrl}
                                            description={agency.agencyDescription ?? agency.description}
                                            statusLabel={status.label}
                                            statusClass={status.badge}
                                            note={agency.status === 'REJECTED' ? status.note : (agency.latestMilestoneLabel ?? status.note)}
                                            updatedAt={agency.updatedAt ? formatShortDate(agency.updatedAt) : null}
                                            href={agency.milestonesUrl}
                                        />
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="px-5 py-8 text-center">
                                <span className="material-symbols-outlined text-3xl text-slate-300" aria-hidden="true">
                                    hourglass_empty
                                </span>
                                <p className="mt-2 text-[13px] font-semibold text-slate-700">
                                    No partner offices assigned yet
                                </p>
                                <p className="mx-auto mt-1 max-w-xs text-[12px] text-slate-500">
                                    Your case manager is reviewing your request. Referrals will appear here once they are sent.
                                </p>
                            </div>
                        )}
                    </section>

                    {/* Documents and requests */}
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <header className="border-b border-slate-100 px-5 py-4">
                            <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">
                                Documents and Requests
                            </h2>
                        </header>

                        <div role="tablist" aria-label="Documents" className="grid grid-cols-2 border-b border-slate-100">
                            <button
                                type="button"
                                role="tab"
                                aria-selected={documentsTab === 'requests'}
                                onClick={() => setDocumentsTab('requests')}
                                className={`px-4 py-3 text-center text-[12px] font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${
                                    documentsTab === 'requests'
                                        ? 'border-b-2 border-primary text-primary'
                                        : 'text-slate-500 hover:text-slate-700'
                                }`}
                            >
                                Requests from Agencies
                            </button>
                            <button
                                type="button"
                                role="tab"
                                aria-selected={documentsTab === 'documents'}
                                onClick={() => setDocumentsTab('documents')}
                                className={`px-4 py-3 text-center text-[12px] font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${
                                    documentsTab === 'documents'
                                        ? 'border-b-2 border-primary text-primary'
                                        : 'text-slate-500 hover:text-slate-700'
                                }`}
                            >
                                My Submitted Documents
                            </button>
                        </div>

                        {documentsTab === 'requests' ? (
                            requestAgencies.length > 0 ? (
                                <div className="space-y-3 p-5">
                                    {requestAgencies.map((agency) => {
                                        const request = agency.openRequest;
                                        const needsAction = request.status === 'OPEN';
                                        return (
                                            <div key={request.id} className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <p className="text-[12px] font-bold text-amber-800">
                                                        {agency.name}{request.requestedAt ? ` · ${formatShortDate(request.requestedAt)}` : ''}
                                                    </p>
                                                    <span className="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-800">
                                                        {formatRequestStatus(request.status)}
                                                    </span>
                                                </div>
                                                <p className="mt-2 font-headline text-[14px] font-extrabold tracking-tight text-slate-900">{request.title}</p>
                                                {request.dueAt ? <p className="mt-1 text-[12px] text-slate-600">Due {formatShortDate(request.dueAt)}</p> : null}
                                                <Link href={request.url} className="mt-3 inline-flex items-center gap-1.5 rounded-md bg-primary px-3.5 py-2 text-[12px] font-bold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                                    {needsAction ? 'Respond to Request' : 'View Request'}
                                                </Link>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : kind === 'closed' ? (
                                <div className="px-5 py-8 text-center">
                                    <span className="material-symbols-outlined text-3xl text-slate-300" aria-hidden="true">
                                        drafts
                                    </span>
                                    <p className="mt-2 text-[13px] font-semibold text-slate-700">No outstanding requests</p>
                                    <p className="mt-1 text-[12px] text-slate-500">
                                        This request is closed. Your documents and history remain available.
                                    </p>
                                </div>
                            ) : (
                                <div className="px-5 py-8 text-center">
                                    <span className="material-symbols-outlined text-3xl text-slate-300" aria-hidden="true">
                                        drafts
                                    </span>
                                    <p className="mt-2 text-[13px] font-semibold text-slate-700">No requests from agencies</p>
                                    <p className="mt-1 text-[12px] text-slate-500">
                                        If an agency needs information, its request will appear here.
                                    </p>
                                </div>
                            )
                        ) : (
                            <div className="px-5 py-5">
                                {!isDeclined && <IntakeReplacementPanel caseId={trackedCase?.id} slots={isClosed ? [] : [...new Set([...requestedSlots, ...requirementSlots])]} reason={requestedReason} />}
                                <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">
                                    My submitted documents
                                    {submittedDocuments.length > 0 ? ` · ${submittedDocuments.length}` : ''}
                                </p>
                                {submittedDocuments.length > 0 ? (
                                    <div className="mt-2 divide-y divide-slate-100">
                                        {submittedDocuments.map((doc) => {
                                            const row = <DocumentRow
                                                name={doc.requirement_label ?? doc.display_name ?? DOCUMENT_SLOT_LABELS[doc.category ?? doc.slot] ?? 'Submitted document'}
                                                meta={doc.meta}
                                                statusLabel={doc.statusLabel ?? 'Received'}
                                            />;
                                            return doc.download_url
                                                ? <a key={doc.id ?? doc.name} href={doc.download_url} className="block">{row}</a>
                                                : <div key={doc.id ?? doc.name}>{row}</div>;
                                        })}
                                    </div>
                                ) : (
                                    <div className="mt-3 flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3.5">
                                        <span
                                            aria-hidden="true"
                                            className="material-symbols-outlined mt-px shrink-0 text-[18px] leading-none text-slate-400"
                                        >
                                            description
                                        </span>
                                        <p className="text-[12px] leading-relaxed text-slate-500">
                                            Only you and authorized staff can access these files.
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                    </section>
                </div>

                {/* Aside */}
                <aside className="space-y-6 lg:sticky lg:top-24 lg:self-start">
                    <RecentUpdates trackingAgencies={trackingAgencies} milestoneTimeline={milestoneTimeline} />
                    <HelpCard />
                </aside>
            </div>

            <p className="mt-6 flex items-center gap-1.5 text-[11px] text-slate-400">
                <span
                    aria-hidden="true"
                    className="material-symbols-outlined text-[14px] leading-none"
                >
                    info
                </span>
                All request information on this page comes from your own records.
            </p>

            {/* Copy-to-clipboard toast */}
            {toast ? (
                <div
                    role="status"
                    aria-live="polite"
                    className="fixed bottom-5 left-1/2 z-[80] -translate-x-1/2 rounded-lg bg-slate-900 px-4 py-2.5 text-[13px] font-medium text-white shadow-lg motion-safe:animate-pop-in"
                >
                    <span className="flex items-center gap-2">
                        <CheckCircle2 className="h-4 w-4 text-emerald-400" aria-hidden="true" />
                        {toast}
                    </span>
                </div>
            ) : null}
        </OfwPortalLayout>
    );
}
